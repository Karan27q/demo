-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 03, 2025 at 05:31 PM
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
  `id` int(11) NOT NULL,
  `loan_no` varchar(20) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `loan_date` date NOT NULL,
  `principal_amount` decimal(10,2) NOT NULL,
  `interest_rate` decimal(5,2) NOT NULL,
  `total_weight` decimal(8,3) DEFAULT NULL,
  `net_weight` decimal(8,3) DEFAULT NULL,
  `pledge_items` text DEFAULT NULL,
  `status` enum('active','closed') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
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
--
ALTER TABLE `loans`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `loan_no` (`loan_no`),
  ADD KEY `idx_loans_loan_no` (`loan_no`),
  ADD KEY `idx_loans_customer_id` (`customer_id`),
  ADD KEY `idx_loans_status` (`status`),
  ADD KEY `idx_loans_date` (`loan_date`);

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
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `groups`
--
ALTER TABLE `groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `interest`
--
ALTER TABLE `interest`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `loans`
--
ALTER TABLE `loans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `loan_closings`
--
ALTER TABLE `loan_closings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

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
ALTER TABLE `loans`
  ADD CONSTRAINT `loans_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`);

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
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
