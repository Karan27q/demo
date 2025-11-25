<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'lakshmi_finance');
define('DB_USER', 'root');
define('DB_PASS', '');

// Create database connection
function getDBConnection() {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch(PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

// Initialize database tables
function initializeDatabase() {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create database if not exists
        $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
        $pdo->exec("USE " . DB_NAME);
        
        // Create users table
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100),
            role ENUM('admin', 'user') DEFAULT 'user',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Create customers table
        $pdo->exec("CREATE TABLE IF NOT EXISTS customers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_no VARCHAR(20) UNIQUE NOT NULL,
            name VARCHAR(100) NOT NULL,
            mobile VARCHAR(15) NOT NULL,
            address TEXT,
            place VARCHAR(100),
            pincode VARCHAR(10),
            additional_number VARCHAR(15),
            reference VARCHAR(100),
            proof_type VARCHAR(50),
            customer_photo VARCHAR(255),
            proof_file VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Add new columns to existing customers table if they don't exist
        $customersColumns = [
            'place' => 'VARCHAR(100)',
            'pincode' => 'VARCHAR(10)',
            'additional_number' => 'VARCHAR(15)',
            'reference' => 'VARCHAR(100)',
            'proof_type' => 'VARCHAR(50)',
            'customer_photo' => 'VARCHAR(255)',
            'proof_file' => 'VARCHAR(255)'
        ];
        
        foreach ($customersColumns as $column => $type) {
            try {
                $stmt = $pdo->query("SHOW COLUMNS FROM customers LIKE '$column'");
                if ($stmt->rowCount() == 0) {
                    $pdo->exec("ALTER TABLE customers ADD COLUMN $column $type");
                }
            } catch(PDOException $e) {
                // Column might already exist or table doesn't exist yet, ignore
            }
        }
        
        // Create groups table
        $pdo->exec("CREATE TABLE IF NOT EXISTS groups (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Create products table
        $pdo->exec("CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            name_tamil VARCHAR(100),
            group_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (group_id) REFERENCES groups(id)
        )");
        
        // Create loans table
        // Note: loan_no is NOT unique - allows multiple loans per customer
        // The primary key 'id' ensures each loan is unique
        $pdo->exec("CREATE TABLE IF NOT EXISTS loans (
            id INT AUTO_INCREMENT PRIMARY KEY,
            loan_no VARCHAR(20) NOT NULL,
            customer_id INT NOT NULL,
            loan_date DATE NOT NULL,
            principal_amount DECIMAL(10,2) NOT NULL,
            interest_rate DECIMAL(5,2) NOT NULL,
            loan_days INT,
            interest_amount DECIMAL(10,2),
            total_weight DECIMAL(8,3),
            net_weight DECIMAL(8,3),
            pledge_items TEXT,
            date_of_birth DATE,
            group_id INT,
            recovery_period VARCHAR(50),
            ornament_file VARCHAR(255),
            proof_file VARCHAR(255),
            status ENUM('active', 'closed') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_loans_customer_id (customer_id),
            INDEX idx_loans_loan_no (loan_no),
            INDEX idx_loans_status (status),
            INDEX idx_loans_date (loan_date),
            INDEX idx_loans_customer_loan (customer_id, loan_no),
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT ON UPDATE CASCADE,
            FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE SET NULL ON UPDATE CASCADE
        )");
        
        // Add new columns to existing loans table if they don't exist
        $loansColumns = [
            'date_of_birth' => 'DATE',
            'group_id' => 'INT',
            'recovery_period' => 'VARCHAR(50)',
            'ornament_file' => 'VARCHAR(255)',
            'proof_file' => 'VARCHAR(255)',
            'loan_days' => 'INT',
            'interest_amount' => 'DECIMAL(10,2)'
        ];
        
        foreach ($loansColumns as $column => $type) {
            try {
                $stmt = $pdo->query("SHOW COLUMNS FROM loans LIKE '$column'");
                if ($stmt->rowCount() == 0) {
                    $sql = "ALTER TABLE loans ADD COLUMN $column $type";
                    if ($column === 'group_id') {
                        $sql .= ", ADD INDEX idx_group_id (group_id)";
                    }
                    $pdo->exec($sql);
                }
            } catch(PDOException $e) {
                // Column might already exist or table doesn't exist yet, ignore
            }
        }
        
        // Add foreign key if it doesn't exist
        try {
            $stmt = $pdo->query("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'loans' 
                AND CONSTRAINT_NAME = 'fk_loans_group'");
            if ($stmt->rowCount() == 0) {
                $pdo->exec("ALTER TABLE loans ADD CONSTRAINT fk_loans_group FOREIGN KEY (group_id) REFERENCES groups(id)");
            }
        } catch(PDOException $e) {
            // Foreign key might already exist or groups table doesn't exist yet, ignore
        }
        
        // Create interest table
        $pdo->exec("CREATE TABLE IF NOT EXISTS interest (
            id INT AUTO_INCREMENT PRIMARY KEY,
            loan_id INT NOT NULL,
            interest_date DATE NOT NULL,
            interest_amount DECIMAL(10,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (loan_id) REFERENCES loans(id)
        )");
        
        // Create loan_closings table
        $pdo->exec("CREATE TABLE IF NOT EXISTS loan_closings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            loan_id INT NOT NULL,
            closing_date DATE NOT NULL,
            total_interest_paid DECIMAL(10,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (loan_id) REFERENCES loans(id)
        )");
        
        // Create transactions table
        $pdo->exec("CREATE TABLE IF NOT EXISTS transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            date DATE NOT NULL,
            transaction_name VARCHAR(100) NOT NULL,
            transaction_type ENUM('credit', 'debit') NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Insert default admin user
        $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, password, name, role) VALUES (?, ?, ?, ?)");
        $stmt->execute(['admin', password_hash('admin123', PASSWORD_DEFAULT), 'Administrator', 'admin']);
        
        // Insert default group
        $stmt = $pdo->prepare("INSERT IGNORE INTO groups (name) VALUES (?)");
        $stmt->execute(['Gold']);
        
        // Insert sample products
        $products = [
            ['chain', 'சங்கிலி'],
            ['RING', 'மோதிரம்'],
            ['BANGLE', 'வளையல்'],
            ['STUD W/DROPS', 'Stud w/சொட்டுகள்'],
            ['STUD', 'ஸ்டட்']
        ];
        
        $stmt = $pdo->prepare("INSERT IGNORE INTO products (name, name_tamil, group_id) VALUES (?, ?, 1)");
        foreach ($products as $product) {
            $stmt->execute($product);
        }
        
        // Insert sample customers
        $customers = [
            ['C0001', 'siva', '9025148309'],
            ['C0002', 'Anantha babu', '8489020465']
        ];
        
        $stmt = $pdo->prepare("INSERT IGNORE INTO customers (customer_no, name, mobile) VALUES (?, ?, ?)");
        foreach ($customers as $customer) {
            $stmt->execute($customer);
        }
        
        // Insert sample loans
        $loans = [
            ['A0001', 1, '2025-07-04', 5000, 1.5, 17.00, 15.00, 'BABY RING - 1, STUD W/JIMMIKI - 1'],
            ['A0002', 2, '2025-07-05', 25000, 1.58, 4.10, 4.00, 'STUD - 2']
        ];
        
        $stmt = $pdo->prepare("INSERT IGNORE INTO loans (loan_no, customer_id, loan_date, principal_amount, interest_rate, total_weight, net_weight, pledge_items) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($loans as $loan) {
            $stmt->execute($loan);
        }
        
        // Insert sample transactions
        $transactions = [
            ['2025-07-04', 'SIVA', 'credit', 200000.00],
            ['2025-07-04', 'MANI SALARY', 'debit', 10000.00]
        ];
        
        $stmt = $pdo->prepare("INSERT IGNORE INTO transactions (date, transaction_name, transaction_type, amount) VALUES (?, ?, ?, ?)");
        foreach ($transactions as $transaction) {
            $stmt->execute($transaction);
        }
        
        echo "Database initialized successfully!";
        
    } catch(PDOException $e) {
        die("Database initialization failed: " . $e->getMessage());
    }
}
?> 