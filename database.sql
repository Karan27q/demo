-- Lakshmi Finance Database Schema
-- Gold Finance Management System

-- Create database
CREATE DATABASE IF NOT EXISTS lakshmi_finance;
USE lakshmi_finance;

-- Users table for authentication
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    role ENUM('admin', 'user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Customers table
CREATE TABLE customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_no VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    mobile VARCHAR(15) NOT NULL,
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Groups table for jewelry categories
CREATE TABLE groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Products table with bilingual support
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    name_tamil VARCHAR(100),
    group_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE SET NULL
);

-- Loans table for jewelry pawn records
CREATE TABLE loans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    loan_no VARCHAR(20) UNIQUE NOT NULL,
    customer_id INT NOT NULL,
    loan_date DATE NOT NULL,
    principal_amount DECIMAL(10,2) NOT NULL,
    interest_rate DECIMAL(5,2) NOT NULL,
    total_weight DECIMAL(8,3),
    net_weight DECIMAL(8,3),
    pledge_items TEXT,
    status ENUM('active', 'closed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT
);

-- Interest table for interest payments
CREATE TABLE interest (
    id INT AUTO_INCREMENT PRIMARY KEY,
    loan_id INT NOT NULL,
    interest_date DATE NOT NULL,
    interest_amount DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE
);

-- Loan closings table
CREATE TABLE loan_closings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    loan_id INT NOT NULL,
    closing_date DATE NOT NULL,
    total_interest_paid DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE
);

-- Transactions table for financial entries
CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    transaction_name VARCHAR(100) NOT NULL,
    transaction_type ENUM('credit', 'debit') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default admin user
INSERT INTO users (username, password, name, role) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin');

-- Insert sample groups
INSERT INTO groups (name) VALUES 
('Gold'),
('Silver'),
('Platinum');

-- Insert sample products
INSERT INTO products (name, name_tamil, group_id) VALUES 
('chain', 'சங்கிலி', 1),
('RING', 'மோதிரம்', 1),
('BANGLE', 'வளையல்', 1),
('STUD W/DROPS', 'Stud w/சொட்டுகள்', 1),
('STUD', 'ஸ்டட்', 1),
('NECKLACE', 'தாலி', 1),
('EARRINGS', 'காதணிகள்', 1);

-- Insert sample customers
INSERT INTO customers (customer_no, name, mobile, address) VALUES 
('C0001', 'siva', '9025148309', 'VNR, Tamil Nadu'),
('C0002', 'Anantha babu', '8489020465', 'Sivakasi, Tamil Nadu'),
('C0003', 'Mani', '9876543210', 'Chennai, Tamil Nadu'),
('C0004', 'Priya', '8765432109', 'Madurai, Tamil Nadu');

-- Insert sample loans
INSERT INTO loans (loan_no, customer_id, loan_date, principal_amount, interest_rate, total_weight, net_weight, pledge_items, status) VALUES 
('A0001', 1, '2025-07-04', 5000.00, 1.50, 17.000, 15.000, 'BABY RING - 1, STUD W/JIMMIKI - 1', 'active'),
('A0002', 2, '2025-07-05', 25000.00, 1.58, 4.100, 4.000, 'STUD - 2', 'closed'),
('A0003', 3, '2025-07-06', 15000.00, 1.75, 8.500, 8.000, 'CHAIN - 1', 'active'),
('A0004', 4, '2025-07-07', 30000.00, 1.60, 12.000, 11.500, 'BANGLE - 2, RING - 1', 'active');

-- Insert sample interest records
INSERT INTO interest (loan_id, interest_date, interest_amount) VALUES 
(1, '2025-07-19', 75.00),
(2, '2025-07-20', 395.00),
(3, '2025-07-21', 262.50),
(4, '2025-07-22', 480.00);

-- Insert sample loan closing
INSERT INTO loan_closings (loan_id, closing_date, total_interest_paid) VALUES 
(2, '2025-07-17', 395.00);

-- Insert sample transactions
INSERT INTO transactions (date, transaction_name, transaction_type, amount, description) VALUES 
('2025-07-04', 'SIVA', 'credit', 200000.00, 'Initial capital'),
('2025-07-04', 'MANI SALARY', 'debit', 10000.00, 'Salary payment'),
('2025-07-05', 'LOAN A0001', 'credit', 5000.00, 'Jewelry pawn loan'),
('2025-07-05', 'LOAN A0002', 'credit', 25000.00, 'Jewelry pawn loan'),
('2025-07-06', 'INTEREST A0001', 'credit', 75.00, 'Interest received'),
('2025-07-07', 'UTILITIES', 'debit', 5000.00, 'Monthly utilities'),
('2025-07-08', 'LOAN A0003', 'credit', 15000.00, 'Jewelry pawn loan'),
('2025-07-09', 'LOAN A0004', 'credit', 30000.00, 'Jewelry pawn loan');

-- Create indexes for better performance
CREATE INDEX idx_customers_customer_no ON customers(customer_no);
CREATE INDEX idx_customers_mobile ON customers(mobile);
CREATE INDEX idx_loans_loan_no ON loans(loan_no);
CREATE INDEX idx_loans_customer_id ON loans(customer_id);
CREATE INDEX idx_loans_status ON loans(status);
CREATE INDEX idx_loans_date ON loans(loan_date);
CREATE INDEX idx_interest_loan_id ON interest(loan_id);
CREATE INDEX idx_interest_date ON interest(interest_date);
CREATE INDEX idx_transactions_date ON transactions(date);
CREATE INDEX idx_transactions_type ON transactions(transaction_type);
CREATE INDEX idx_products_group_id ON products(group_id);

-- Create views for common queries

-- Customer loan summary view
CREATE VIEW customer_loan_summary AS
SELECT 
    c.id,
    c.customer_no,
    c.name,
    c.mobile,
    COUNT(l.id) as total_loans,
    SUM(CASE WHEN l.status = 'active' THEN 1 ELSE 0 END) as active_loans,
    SUM(CASE WHEN l.status = 'closed' THEN 1 ELSE 0 END) as closed_loans,
    SUM(l.principal_amount) as total_principal,
    SUM(CASE WHEN l.status = 'active' THEN l.principal_amount ELSE 0 END) as active_principal
FROM customers c
LEFT JOIN loans l ON c.id = l.customer_id
GROUP BY c.id, c.customer_no, c.name, c.mobile;

-- Loan details with customer info view
CREATE VIEW loan_details AS
SELECT 
    l.id,
    l.loan_no,
    l.loan_date,
    l.principal_amount,
    l.interest_rate,
    l.total_weight,
    l.net_weight,
    l.pledge_items,
    l.status,
    c.customer_no,
    c.name as customer_name,
    c.mobile,
    COALESCE(SUM(i.interest_amount), 0) as total_interest_paid,
    COALESCE(lc.total_interest_paid, 0) as closing_interest_paid
FROM loans l
JOIN customers c ON l.customer_id = c.id
LEFT JOIN interest i ON l.id = i.loan_id
LEFT JOIN loan_closings lc ON l.id = lc.loan_id
GROUP BY l.id, l.loan_no, l.loan_date, l.principal_amount, l.interest_rate, 
         l.total_weight, l.net_weight, l.pledge_items, l.status, 
         c.customer_no, c.name, c.mobile, lc.total_interest_paid;

-- Daily transaction summary view
CREATE VIEW daily_transaction_summary AS
SELECT 
    date,
    SUM(CASE WHEN transaction_type = 'credit' THEN amount ELSE 0 END) as total_credit,
    SUM(CASE WHEN transaction_type = 'debit' THEN amount ELSE 0 END) as total_debit,
    SUM(CASE WHEN transaction_type = 'credit' THEN amount ELSE -amount END) as net_amount
FROM transactions
GROUP BY date
ORDER BY date DESC;

-- Product summary view
CREATE VIEW product_summary AS
SELECT 
    p.id,
    p.name,
    p.name_tamil,
    g.name as group_name,
    COUNT(DISTINCT l.id) as loan_count,
    SUM(l.principal_amount) as total_value
FROM products p
LEFT JOIN groups g ON p.group_id = g.id
LEFT JOIN loans l ON l.pledge_items LIKE CONCAT('%', p.name, '%')
GROUP BY p.id, p.name, p.name_tamil, g.name;

-- Insert additional sample data for testing

-- More customers
INSERT INTO customers (customer_no, name, mobile, address) VALUES 
('C0005', 'Rajesh Kumar', '9876543211', 'Coimbatore, Tamil Nadu'),
('C0006', 'Lakshmi Devi', '8765432108', 'Salem, Tamil Nadu'),
('C0007', 'Arun Kumar', '7654321098', 'Erode, Tamil Nadu'),
('C0008', 'Geetha Rani', '6543210987', 'Tirupur, Tamil Nadu');

-- More loans
INSERT INTO loans (loan_no, customer_id, loan_date, principal_amount, interest_rate, total_weight, net_weight, pledge_items, status) VALUES 
('A0005', 5, '2025-07-10', 18000.00, 1.65, 6.200, 6.000, 'NECKLACE - 1', 'active'),
('A0006', 6, '2025-07-11', 12000.00, 1.70, 3.800, 3.600, 'EARRINGS - 2', 'active'),
('A0007', 7, '2025-07-12', 35000.00, 1.55, 14.500, 14.000, 'CHAIN - 1, RING - 1', 'active'),
('A0008', 8, '2025-07-13', 22000.00, 1.68, 7.300, 7.000, 'BANGLE - 1, STUD - 1', 'active');

-- More interest records
INSERT INTO interest (loan_id, interest_date, interest_amount) VALUES 
(5, '2025-07-25', 297.00),
(6, '2025-07-26', 204.00),
(7, '2025-07-27', 542.50),
(8, '2025-07-28', 369.60);

-- More transactions
INSERT INTO transactions (date, transaction_name, transaction_type, amount, description) VALUES 
('2025-07-10', 'LOAN A0005', 'credit', 18000.00, 'Jewelry pawn loan'),
('2025-07-11', 'LOAN A0006', 'credit', 12000.00, 'Jewelry pawn loan'),
('2025-07-12', 'LOAN A0007', 'credit', 35000.00, 'Jewelry pawn loan'),
('2025-07-13', 'LOAN A0008', 'credit', 22000.00, 'Jewelry pawn loan'),
('2025-07-15', 'RENT EXPENSE', 'debit', 15000.00, 'Monthly rent'),
('2025-07-16', 'INTEREST A0005', 'credit', 297.00, 'Interest received'),
('2025-07-17', 'INTEREST A0006', 'credit', 204.00, 'Interest received'),
('2025-07-18', 'INTEREST A0007', 'credit', 542.50, 'Interest received'),
('2025-07-19', 'INTEREST A0008', 'credit', 369.60, 'Interest received');

-- Update loan status for some loans to closed
UPDATE loans SET status = 'closed' WHERE loan_no IN ('A0003', 'A0005');

-- Insert loan closing for closed loans
INSERT INTO loan_closings (loan_id, closing_date, total_interest_paid) VALUES 
(3, '2025-07-30', 262.50),
(5, '2025-07-31', 297.00);

-- Create stored procedures for common operations

DELIMITER //

-- Procedure to calculate interest for a loan
CREATE PROCEDURE CalculateLoanInterest(
    IN p_loan_id INT,
    IN p_interest_date DATE,
    OUT p_interest_amount DECIMAL(10,2)
)
BEGIN
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
END //

-- Procedure to close a loan
CREATE PROCEDURE CloseLoan(
    IN p_loan_id INT,
    IN p_closing_date DATE,
    IN p_total_interest_paid DECIMAL(10,2)
)
BEGIN
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
END //

-- Procedure to get customer statistics
CREATE PROCEDURE GetCustomerStats(
    IN p_customer_id INT
)
BEGIN
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
END //

DELIMITER ;

-- Create triggers for data integrity

-- Trigger to update loan status when closing
DELIMITER //
CREATE TRIGGER after_loan_closing_insert
AFTER INSERT ON loan_closings
FOR EACH ROW
BEGIN
    UPDATE loans SET status = 'closed' WHERE id = NEW.loan_id;
END //
DELIMITER ;

-- Trigger to validate loan amount
DELIMITER //
CREATE TRIGGER before_loan_insert
BEFORE INSERT ON loans
FOR EACH ROW
BEGIN
    IF NEW.principal_amount <= 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Principal amount must be greater than 0';
    END IF;
    
    IF NEW.interest_rate <= 0 OR NEW.interest_rate > 100 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Interest rate must be between 0 and 100';
    END IF;
END //
DELIMITER ;

-- Grant permissions (adjust as needed)
-- GRANT SELECT, INSERT, UPDATE, DELETE ON lakshmi_finance.* TO 'lakshmi_user'@'localhost';

-- Show final status
SELECT 'Database created successfully!' as status;
SELECT COUNT(*) as total_customers FROM customers;
SELECT COUNT(*) as total_loans FROM loans;
SELECT COUNT(*) as total_transactions FROM transactions; 