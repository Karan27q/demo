-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 27, 2025 at 04:12 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `lakshmi_finance`
--

-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS `lakshmi_finance` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `lakshmi_finance`;

-- ============================================================================
-- CRITICAL: Remove unique_customer_loan constraint FIRST (if table exists)
-- This must run BEFORE any table operations to allow multiple loans per customer
-- ============================================================================
SET @dbname = DATABASE();

-- Remove unique_customer_loan constraint using multiple methods to ensure it's removed
SET @sql = '';
SET @sql = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
   WHERE table_schema = @dbname 
   AND table_name = 'loans' 
   AND index_name = 'unique_customer_loan') > 0,
  'ALTER TABLE loans DROP INDEX unique_customer_loan;',
  'SELECT 1;'
));
SET @sql = IFNULL(@sql, 'SELECT 1;');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Also try DROP KEY method
SET @sql = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
   WHERE table_schema = @dbname 
   AND table_name = 'loans' 
   AND constraint_name = 'unique_customer_loan') > 0,
  'ALTER TABLE loans DROP KEY unique_customer_loan;',
  'SELECT 1;'
));
SET @sql = IFNULL(@sql, 'SELECT 1;');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Remove UNIQUE constraint on loan_no
SET @sql = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
   WHERE table_schema = @dbname 
   AND table_name = 'loans' 
   AND index_name = 'loan_no' 
   AND non_unique = 0) > 0,
  'ALTER TABLE loans DROP INDEX loan_no;',
  'SELECT 1;'
));
SET @sql = IFNULL(@sql, 'SELECT 1;');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `CalculateLoanInterest` (IN `p_loan_id` INT, IN `p_interest_date` DATE, OUT `p_interest_amount` DECIMAL(10,2))   BEGIN
    DECLARE v_principal DECIMAL(10,2);
    DECLARE v_rate DECIMAL(5,2);
    DECLARE v_days INT;
    
    -- Get loan details
    SELECT principal_amount, interest_rate 
    INTO v_principal, v_rate 
    FROM loans 
    WHERE id = p_loan_id AND status = 'active';
    
    -- Calculate days since loan date
    SELECT DATEDIFF(p_interest_date, loan_date) 
    INTO v_days 
    FROM loans 
    WHERE id = p_loan_id;
    
    -- Calculate interest (daily rate)
    SET p_interest_amount = (v_principal * v_rate * v_days) / 100;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `CloseLoan` (IN `p_loan_id` INT, IN `p_closing_date` DATE, IN `p_total_interest_paid` DECIMAL(10,2))   BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Update loan status
    UPDATE loans SET status = 'closed' WHERE id = p_loan_id;
    
    -- Insert loan closing record
    INSERT INTO loan_closings (loan_id, closing_date, total_interest_paid)
    VALUES (p_loan_id, p_closing_date, p_total_interest_paid);
    
    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `GetCustomerStats` (IN `p_customer_id` INT)   BEGIN
    SELECT 
        c.customer_no,
        c.name,
        c.mobile,
        COUNT(l.id) as total_loans,
        SUM(CASE WHEN l.status = 'active' THEN 1 ELSE 0 END) as active_loans,
        SUM(CASE WHEN l.status = 'closed' THEN 1 ELSE 0 END) as closed_loans,
        SUM(l.principal_amount) as total_principal,
        SUM(CASE WHEN l.status = 'active' THEN l.principal_amount ELSE 0 END) as active_principal,
        COALESCE(SUM(i.interest_amount), 0) as total_interest_paid
    FROM customers c
    LEFT JOIN loans l ON c.id = l.customer_id
    LEFT JOIN interest i ON l.id = i.loan_id
    WHERE c.id = p_customer_id
    GROUP BY c.id, c.customer_no, c.name, c.mobile;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `customer_no` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `mobile` varchar(15) NOT NULL,
  `address` text DEFAULT NULL,
  `place` varchar(100) DEFAULT NULL,
  `pincode` varchar(10) DEFAULT NULL,
  `additional_number` varchar(15) DEFAULT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `proof_type` varchar(50) DEFAULT NULL,
  `customer_photo` varchar(255) DEFAULT NULL,
  `proof_file` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `customer_loan_summary`
-- (See below for the actual view)
--
CREATE TABLE `customer_loan_summary` (
`id` int(11)
,`customer_no` varchar(20)
,`name` varchar(100)
,`mobile` varchar(15)
,`total_loans` bigint(21)
,`active_loans` decimal(22,0)
,`closed_loans` decimal(22,0)
,`total_principal` decimal(32,2)
,`active_principal` decimal(32,2)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `daily_transaction_summary`
-- (See below for the actual view)
--
CREATE TABLE `daily_transaction_summary` (
`date` date
,`total_credit` decimal(32,2)
,`total_debit` decimal(32,2)
,`net_amount` decimal(32,2)
);

-- --------------------------------------------------------

--
-- Table structure for table `groups`
--

CREATE TABLE `groups` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `interest`
--

CREATE TABLE `interest` (
  `id` int(11) NOT NULL,
  `loan_id` int(11) NOT NULL,
  `interest_date` date NOT NULL,
  `interest_amount` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `loans`
--

CREATE TABLE `loans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `loan_no` varchar(20) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `loan_date` date NOT NULL,
  `principal_amount` decimal(10,2) NOT NULL,
  `interest_rate` decimal(5,2) NOT NULL,
  `loan_days` int(11) DEFAULT NULL,
  `interest_amount` decimal(10,2) DEFAULT NULL,
  `total_weight` decimal(8,3) DEFAULT NULL,
  `net_weight` decimal(8,3) DEFAULT NULL,
  `pledge_items` text DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `group_id` int(11) DEFAULT NULL,
  `recovery_period` varchar(50) DEFAULT NULL,
  `ornament_file` varchar(255) DEFAULT NULL,
  `proof_file` varchar(255) DEFAULT NULL,
  `status` enum('active','closed') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_loans_customer_id` (`customer_id`),
  KEY `idx_loans_loan_no` (`loan_no`),
  KEY `idx_loans_status` (`status`),
  KEY `idx_loans_date` (`loan_date`),
  KEY `idx_loans_customer_loan` (`customer_id`, `loan_no`),
  CONSTRAINT `loans_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_loans_group` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `loans`
--
DELIMITER $$
CREATE TRIGGER `before_loan_insert` BEFORE INSERT ON `loans` FOR EACH ROW BEGIN
    IF NEW.principal_amount <= 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Principal amount must be greater than 0';
    END IF;
    
    IF NEW.interest_rate <= 0 OR NEW.interest_rate > 100 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Interest rate must be between 0 and 100';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `loan_closings`
--

CREATE TABLE `loan_closings` (
  `id` int(11) NOT NULL,
  `loan_id` int(11) NOT NULL,
  `closing_date` date NOT NULL,
  `total_interest_paid` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `loan_closings`
--
DELIMITER $$
CREATE TRIGGER `after_loan_closing_insert` AFTER INSERT ON `loan_closings` FOR EACH ROW BEGIN
    UPDATE loans SET status = 'closed' WHERE id = NEW.loan_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Stand-in structure for view `loan_details`
-- (See below for the actual view)
--
CREATE TABLE `loan_details` (
`id` int(11)
,`loan_no` varchar(20)
,`loan_date` date
,`principal_amount` decimal(10,2)
,`interest_rate` decimal(5,2)
,`total_weight` decimal(8,3)
,`net_weight` decimal(8,3)
,`pledge_items` text
,`status` enum('active','closed')
,`customer_no` varchar(20)
,`customer_name` varchar(100)
,`mobile` varchar(15)
,`total_interest_paid` decimal(32,2)
,`closing_interest_paid` decimal(10,2)
);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `name_tamil` varchar(100) DEFAULT NULL,
  `group_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `product_summary`
-- (See below for the actual view)
--
CREATE TABLE `product_summary` (
`id` int(11)
,`name` varchar(100)
,`name_tamil` varchar(100)
,`group_name` varchar(100)
,`loan_count` bigint(21)
,`total_value` decimal(32,2)
);

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `date` date NOT NULL,
  `transaction_name` varchar(100) NOT NULL,
  `transaction_type` enum('credit','debit') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role` enum('admin','user') DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `name`, `email`, `role`, `created_at`) VALUES
(0, 'Admin', '$2y$10$oIVZYG/mxI9Yzkb8D6oqROeC9agwCutcboU5ho.iVO/uebCs3XE6e', 'Admin', 'Admin@gmail.com', 'admin', '2025-09-19 04:59:41');

-- --------------------------------------------------------

--
-- Structure for view `customer_loan_summary`
--
DROP TABLE IF EXISTS `customer_loan_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `customer_loan_summary`  AS SELECT `c`.`id` AS `id`, `c`.`customer_no` AS `customer_no`, `c`.`name` AS `name`, `c`.`mobile` AS `mobile`, count(`l`.`id`) AS `total_loans`, sum(case when `l`.`status` = 'active' then 1 else 0 end) AS `active_loans`, sum(case when `l`.`status` = 'closed' then 1 else 0 end) AS `closed_loans`, sum(`l`.`principal_amount`) AS `total_principal`, sum(case when `l`.`status` = 'active' then `l`.`principal_amount` else 0 end) AS `active_principal` FROM (`customers` `c` left join `loans` `l` on(`c`.`id` = `l`.`customer_id`)) GROUP BY `c`.`id`, `c`.`customer_no`, `c`.`name`, `c`.`mobile` ;

-- --------------------------------------------------------

--
-- Structure for view `daily_transaction_summary`
--
DROP TABLE IF EXISTS `daily_transaction_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `daily_transaction_summary`  AS SELECT `transactions`.`date` AS `date`, sum(case when `transactions`.`transaction_type` = 'credit' then `transactions`.`amount` else 0 end) AS `total_credit`, sum(case when `transactions`.`transaction_type` = 'debit' then `transactions`.`amount` else 0 end) AS `total_debit`, sum(case when `transactions`.`transaction_type` = 'credit' then `transactions`.`amount` else -`transactions`.`amount` end) AS `net_amount` FROM `transactions` GROUP BY `transactions`.`date` ORDER BY `transactions`.`date` DESC ;

-- --------------------------------------------------------

--
-- Structure for view `loan_details`
--
DROP TABLE IF EXISTS `loan_details`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `loan_details`  AS SELECT `l`.`id` AS `id`, `l`.`loan_no` AS `loan_no`, `l`.`loan_date` AS `loan_date`, `l`.`principal_amount` AS `principal_amount`, `l`.`interest_rate` AS `interest_rate`, `l`.`total_weight` AS `total_weight`, `l`.`net_weight` AS `net_weight`, `l`.`pledge_items` AS `pledge_items`, `l`.`status` AS `status`, `c`.`customer_no` AS `customer_no`, `c`.`name` AS `customer_name`, `c`.`mobile` AS `mobile`, coalesce(sum(`i`.`interest_amount`),0) AS `total_interest_paid`, coalesce(`lc`.`total_interest_paid`,0) AS `closing_interest_paid` FROM (((`loans` `l` join `customers` `c` on(`l`.`customer_id` = `c`.`id`)) left join `interest` `i` on(`l`.`id` = `i`.`loan_id`)) left join `loan_closings` `lc` on(`l`.`id` = `lc`.`loan_id`)) GROUP BY `l`.`id`, `l`.`loan_no`, `l`.`loan_date`, `l`.`principal_amount`, `l`.`interest_rate`, `l`.`total_weight`, `l`.`net_weight`, `l`.`pledge_items`, `l`.`status`, `c`.`customer_no`, `c`.`name`, `c`.`mobile`, `lc`.`total_interest_paid` ;

-- --------------------------------------------------------

--
-- Structure for view `product_summary`
--
DROP TABLE IF EXISTS `product_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `product_summary`  AS SELECT `p`.`id` AS `id`, `p`.`name` AS `name`, `p`.`name_tamil` AS `name_tamil`, `g`.`name` AS `group_name`, count(distinct `l`.`id`) AS `loan_count`, sum(`l`.`principal_amount`) AS `total_value` FROM ((`products` `p` left join `groups` `g` on(`p`.`group_id` = `g`.`id`)) left join `loans` `l` on(`l`.`pledge_items` like concat('%',`p`.`name`,'%'))) GROUP BY `p`.`id`, `p`.`name`, `p`.`name_tamil`, `g`.`name` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `customer_no` (`customer_no`),
  ADD KEY `idx_customers_customer_no` (`customer_no`),
  ADD KEY `idx_customers_mobile` (`mobile`);

--
-- Indexes for table `groups`
--
ALTER TABLE `groups`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `interest`
--
ALTER TABLE `interest`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_interest_loan_id` (`loan_id`),
  ADD KEY `idx_interest_date` (`interest_date`);

--
-- Indexes for table `loans`
-- NOTE: NO UNIQUE constraints - allows multiple loans per customer
--
ALTER TABLE `loans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_loans_loan_no` (`loan_no`),
  ADD KEY `idx_loans_customer_id` (`customer_id`),
  ADD KEY `idx_loans_status` (`status`),
  ADD KEY `idx_loans_date` (`loan_date`),
  ADD KEY `idx_loans_customer_loan` (`customer_id`, `loan_no`);

--
-- Indexes for table `loan_closings`
--
ALTER TABLE `loan_closings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `loan_id` (`loan_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_products_group_id` (`group_id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_transactions_date` (`date`),
  ADD KEY `idx_transactions_type` (`transaction_type`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- Add missing columns to customers table (for existing databases)
-- Run these only if columns don't exist
--
SET @dbname = DATABASE();
SET @tablename = "customers";

-- Add place column
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE table_schema = @dbname AND table_name = @tablename AND column_name = 'place') > 0,
  "SELECT 'Column place already exists' AS result",
  "ALTER TABLE customers ADD COLUMN place varchar(100) DEFAULT NULL AFTER address"
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add pincode column
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE table_schema = @dbname AND table_name = @tablename AND column_name = 'pincode') > 0,
  "SELECT 'Column pincode already exists' AS result",
  "ALTER TABLE customers ADD COLUMN pincode varchar(10) DEFAULT NULL AFTER place"
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add additional_number column
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE table_schema = @dbname AND table_name = @tablename AND column_name = 'additional_number') > 0,
  "SELECT 'Column additional_number already exists' AS result",
  "ALTER TABLE customers ADD COLUMN additional_number varchar(15) DEFAULT NULL AFTER pincode"
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add reference column
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE table_schema = @dbname AND table_name = @tablename AND column_name = 'reference') > 0,
  "SELECT 'Column reference already exists' AS result",
  "ALTER TABLE customers ADD COLUMN reference varchar(100) DEFAULT NULL AFTER additional_number"
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add proof_type column
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE table_schema = @dbname AND table_name = @tablename AND column_name = 'proof_type') > 0,
  "SELECT 'Column proof_type already exists' AS result",
  "ALTER TABLE customers ADD COLUMN proof_type varchar(50) DEFAULT NULL AFTER reference"
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add customer_photo column
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE table_schema = @dbname AND table_name = @tablename AND column_name = 'customer_photo') > 0,
  "SELECT 'Column customer_photo already exists' AS result",
  "ALTER TABLE customers ADD COLUMN customer_photo varchar(255) DEFAULT NULL AFTER proof_type"
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add proof_file column
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE table_schema = @dbname AND table_name = @tablename AND column_name = 'proof_file') > 0,
  "SELECT 'Column proof_file already exists' AS result",
  "ALTER TABLE customers ADD COLUMN proof_file varchar(255) DEFAULT NULL AFTER customer_photo"
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `groups`
--
ALTER TABLE `groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `interest`
--
ALTER TABLE `interest`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- Add missing columns to loans table (for existing databases)
-- Run these only if columns don't exist
--
SET @dbname = DATABASE();
SET @tablename = "loans";

-- Add date_of_birth column
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE table_schema = @dbname AND table_name = @tablename AND column_name = 'date_of_birth') > 0,
  "SELECT 'Column date_of_birth already exists' AS result",
  "ALTER TABLE loans ADD COLUMN date_of_birth date DEFAULT NULL AFTER pledge_items"
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add group_id column
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE table_schema = @dbname AND table_name = @tablename AND column_name = 'group_id') > 0,
  "SELECT 'Column group_id already exists' AS result",
  "ALTER TABLE loans ADD COLUMN group_id int(11) DEFAULT NULL AFTER date_of_birth"
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add recovery_period column
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE table_schema = @dbname AND table_name = @tablename AND column_name = 'recovery_period') > 0,
  "SELECT 'Column recovery_period already exists' AS result",
  "ALTER TABLE loans ADD COLUMN recovery_period varchar(50) DEFAULT NULL AFTER group_id"
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add ornament_file column
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE table_schema = @dbname AND table_name = @tablename AND column_name = 'ornament_file') > 0,
  "SELECT 'Column ornament_file already exists' AS result",
  "ALTER TABLE loans ADD COLUMN ornament_file varchar(255) DEFAULT NULL AFTER recovery_period"
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add proof_file column
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE table_schema = @dbname AND table_name = @tablename AND column_name = 'proof_file') > 0,
  "SELECT 'Column proof_file already exists' AS result",
  "ALTER TABLE loans ADD COLUMN proof_file varchar(255) DEFAULT NULL AFTER ornament_file"
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add index for group_id if it doesn't exist
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE table_schema = @dbname AND table_name = @tablename AND index_name = 'idx_group_id') > 0,
  "SELECT 'Index idx_group_id already exists' AS result",
  "ALTER TABLE loans ADD INDEX idx_group_id (group_id)"
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add foreign key for group_id if it doesn't exist
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE table_schema = @dbname AND table_name = @tablename AND constraint_name = 'fk_loans_group') > 0,
  "SELECT 'Foreign key fk_loans_group already exists' AS result",
  "ALTER TABLE loans ADD CONSTRAINT fk_loans_group FOREIGN KEY (group_id) REFERENCES groups(id)"
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

--
-- AUTO_INCREMENT for table `loans`
--
ALTER TABLE `loans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `loan_closings`
--
ALTER TABLE `loan_closings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `interest`
--
ALTER TABLE `interest`
  ADD CONSTRAINT `interest_ibfk_1` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `loans`
--
-- CRITICAL: Remove ALL UNIQUE constraints to allow multiple loans per customer
-- This must be done before adding foreign keys to avoid constraint violations
SET @dbname = DATABASE();

-- Step 1: Remove unique_customer_loan constraint (composite unique on customer_id, loan_no)
-- Try multiple methods to ensure it's removed
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
   WHERE table_schema = @dbname 
   AND table_name = 'loans' 
   AND index_name = 'unique_customer_loan') > 0,
  "ALTER TABLE loans DROP INDEX unique_customer_loan",
  "SELECT 'UNIQUE constraint unique_customer_loan does not exist' AS result"
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Also try DROP KEY as alternative
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
   WHERE table_schema = @dbname 
   AND table_name = 'loans' 
   AND constraint_name = 'unique_customer_loan') > 0,
  "ALTER TABLE loans DROP KEY unique_customer_loan",
  "SELECT 'Constraint unique_customer_loan does not exist' AS result"
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 2: Remove UNIQUE constraint on loan_no (single column unique)
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
   WHERE table_schema = @dbname 
   AND table_name = 'loans' 
   AND index_name = 'loan_no' 
   AND non_unique = 0) > 0,
  "ALTER TABLE loans DROP INDEX loan_no",
  "SELECT 'UNIQUE constraint on loan_no does not exist' AS result"
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add composite index for customer_id and loan_no (for faster queries, but not unique)
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
   WHERE table_schema = @dbname 
   AND table_name = 'loans' 
   AND index_name = 'idx_loans_customer_loan') > 0,
  "SELECT 'Index idx_loans_customer_loan already exists' AS result",
  "ALTER TABLE loans ADD INDEX idx_loans_customer_loan (customer_id, loan_no)"
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ensure foreign key constraint exists (only if it doesn't already exist)
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
   WHERE table_schema = @dbname 
   AND table_name = 'loans' 
   AND constraint_name = 'loans_ibfk_1') > 0,
  "SELECT 'Foreign key loans_ibfk_1 already exists' AS result",
  "ALTER TABLE loans ADD CONSTRAINT loans_ibfk_1 FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT ON UPDATE CASCADE"
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

--
-- Constraints for table `loan_closings`
--
ALTER TABLE `loan_closings`
  ADD CONSTRAINT `loan_closings_ibfk_1` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE SET NULL;

--
-- ============================================================================
-- EXAMPLE INSERT STATEMENTS: Multiple Loans Per Customer
-- ============================================================================
-- These examples demonstrate that customers can have multiple loans
-- without encountering duplicate key errors.
--
-- IMPORTANT: The UNIQUE constraint on loan_no has been removed.
-- Each loan is uniquely identified by its auto-increment 'id' field.
-- The 'customer_id' foreign key maintains the relationship to customers.
-- ============================================================================
--

-- Example 1: Customer with 3 active loans
-- Step 1: Insert customer
-- INSERT INTO `customers` (`customer_no`, `name`, `mobile`, `address`, `place`) VALUES
-- ('C0001', 'John Doe', '9876543210', '123 Main Street', 'Chennai');

-- Step 2: Insert multiple loans for the same customer
-- INSERT INTO `loans` (`loan_no`, `customer_id`, `loan_date`, `principal_amount`, `interest_rate`, `loan_days`, `status`) VALUES
-- ('A0001', 1, '2025-01-15', 10000.00, 12.00, 30, 'active'),
-- ('A0002', 1, '2025-02-20', 15000.00, 12.00, 60, 'active'),
-- ('A0003', 1, '2025-03-10', 20000.00, 12.00, 90, 'active');

-- Example 2: Another customer with 2 loans
-- Step 1: Insert customer
-- INSERT INTO `customers` (`customer_no`, `name`, `mobile`, `address`, `place`) VALUES
-- ('C0002', 'Jane Smith', '9876543211', '456 Oak Avenue', 'Bangalore');

-- Step 2: Insert multiple loans for this customer
-- INSERT INTO `loans` (`loan_no`, `customer_id`, `loan_date`, `principal_amount`, `interest_rate`, `loan_days`, `status`) VALUES
-- ('A0004', 2, '2025-01-20', 5000.00, 10.00, 30, 'active'),
-- ('A0005', 2, '2025-02-25', 8000.00, 10.00, 45, 'active');

-- Example 3: Customer with mixed active and closed loans
-- INSERT INTO `loans` (`loan_no`, `customer_id`, `loan_date`, `principal_amount`, `interest_rate`, `loan_days`, `status`) VALUES
-- ('A0006', 1, '2024-12-01', 5000.00, 12.00, 30, 'closed'),
-- ('A0007', 1, '2025-04-01', 12000.00, 12.00, 60, 'active');

--
-- Database Schema Notes:
-- ======================
-- 1. PRIMARY KEY: The 'id' field is the primary key and auto-increments,
--    ensuring each loan record is unique.
--
-- 2. FOREIGN KEY: The 'customer_id' field references customers(id),
--    establishing the one-to-many relationship (one customer, many loans).
--
-- 3. LOAN_NO: The 'loan_no' field is NOT unique, allowing:
--    - Multiple customers to have loans with the same loan number
--    - The same customer to have multiple loans (even with same loan_no if needed)
--
-- 4. INDEXES: Composite index on (customer_id, loan_no) improves query
--    performance when filtering loans by customer.
--
-- 5. DATA INTEGRITY: Foreign key constraints ensure referential integrity
--    between customers and loans tables.
--

COMMIT;
-- Quick SQL Fix: Remove unique_customer_loan constraint
-- Run this SQL file in phpMyAdmin or MySQL command line to fix the duplicate entry error

USE `lakshmi_finance`;

-- Method 1: Try DROP INDEX
ALTER TABLE `loans` DROP INDEX IF EXISTS `unique_customer_loan`;

-- Method 2: Try DROP KEY (alternative syntax)
ALTER TABLE `loans` DROP KEY IF EXISTS `unique_customer_loan`;

-- Method 3: Remove UNIQUE constraint on loan_no if it exists
ALTER TABLE `loans` DROP INDEX IF EXISTS `loan_no`;

-- Verify the constraint is removed
SELECT 
    index_name, 
    non_unique,
    GROUP_CONCAT(column_name ORDER BY seq_in_index) as columns
FROM INFORMATION_SCHEMA.STATISTICS 
WHERE table_schema = 'lakshmi_finance' 
AND table_name = 'loans' 
AND non_unique = 0
AND index_name != 'PRIMARY'
GROUP BY index_name, non_unique;

-- If the above query returns no rows, the constraint has been successfully removed!


/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
