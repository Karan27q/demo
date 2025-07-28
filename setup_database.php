<?php
// Comprehensive Database Setup for Lakshmi Finance
echo "<h2>Lakshmi Finance - Database Setup</h2>";

try {
    // Connect to MySQL without specifying database
    $pdo = new PDO("mysql:host=localhost", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p>✅ Connected to MySQL successfully</p>";
    
    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS lakshmi_finance");
    echo "<p>✅ Database 'lakshmi_finance' created/verified</p>";
    
    // Use the database
    $pdo->exec("USE lakshmi_finance");
    
    // Create users table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100),
            role ENUM('admin', 'user') DEFAULT 'user',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "<p>✅ Users table created</p>";
    
    // Create customers table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS customers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_no VARCHAR(20) UNIQUE NOT NULL,
            name VARCHAR(100) NOT NULL,
            mobile VARCHAR(15) NOT NULL,
            address TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "<p>✅ Customers table created</p>";
    
    // Create groups table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS groups (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "<p>✅ Groups table created</p>";
    
    // Create products table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            name_tamil VARCHAR(100),
            group_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE SET NULL
        )
    ");
    echo "<p>✅ Products table created</p>";
    
    // Create loans table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS loans (
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
        )
    ");
    echo "<p>✅ Loans table created</p>";
    
    // Create interest table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS interest (
            id INT AUTO_INCREMENT PRIMARY KEY,
            loan_id INT NOT NULL,
            interest_date DATE NOT NULL,
            interest_amount DECIMAL(10,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE
        )
    ");
    echo "<p>✅ Interest table created</p>";
    
    // Create loan_closings table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS loan_closings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            loan_id INT NOT NULL,
            closing_date DATE NOT NULL,
            total_interest_paid DECIMAL(10,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE
        )
    ");
    echo "<p>✅ Loan closings table created</p>";
    
    // Create transactions table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            date DATE NOT NULL,
            transaction_name VARCHAR(100) NOT NULL,
            transaction_type ENUM('credit', 'debit') NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "<p>✅ Transactions table created</p>";
    
    // Clear existing admin user and create new one with correct password
    $pdo->exec("DELETE FROM users WHERE username = 'admin'");
    
    $password = 'admin123';
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("INSERT INTO users (username, password, name, role) VALUES (?, ?, ?, ?)");
    $stmt->execute(['admin', $hashedPassword, 'Administrator', 'admin']);
    
    echo "<p>✅ Admin user created with correct password</p>";
    
    // Insert sample data
    $pdo->exec("INSERT IGNORE INTO groups (name) VALUES ('Gold'), ('Silver'), ('Platinum')");
    echo "<p>✅ Sample groups added</p>";
    
    $pdo->exec("
        INSERT IGNORE INTO products (name, name_tamil, group_id) VALUES 
        ('chain', 'சங்கிலி', 1),
        ('RING', 'மோதிரம்', 1),
        ('BANGLE', 'வளையல்', 1),
        ('STUD W/DROPS', 'Stud w/சொட்டுகள்', 1),
        ('STUD', 'ஸ்டட்', 1),
        ('NECKLACE', 'தாலி', 1),
        ('EARRINGS', 'காதணிகள்', 1)
    ");
    echo "<p>✅ Sample products added</p>";
    
    $pdo->exec("
        INSERT IGNORE INTO customers (customer_no, name, mobile, address) VALUES 
        ('C0001', 'siva', '9025148309', 'VNR, Tamil Nadu'),
        ('C0002', 'Anantha babu', '8489020465', 'Sivakasi, Tamil Nadu'),
        ('C0003', 'Mani', '9876543210', 'Chennai, Tamil Nadu'),
        ('C0004', 'Priya', '8765432109', 'Madurai, Tamil Nadu')
    ");
    echo "<p>✅ Sample customers added</p>";
    
    $pdo->exec("
        INSERT IGNORE INTO loans (loan_no, customer_id, loan_date, principal_amount, interest_rate, total_weight, net_weight, pledge_items, status) VALUES 
        ('A0001', 1, '2025-07-04', 5000.00, 1.50, 17.000, 15.000, 'BABY RING - 1, STUD W/JIMMIKI - 1', 'active'),
        ('A0002', 2, '2025-07-05', 25000.00, 1.58, 4.100, 4.000, 'STUD - 2', 'closed'),
        ('A0003', 3, '2025-07-06', 15000.00, 1.75, 8.500, 8.000, 'CHAIN - 1', 'active'),
        ('A0004', 4, '2025-07-07', 30000.00, 1.60, 12.000, 11.500, 'BANGLE - 2, RING - 1', 'active')
    ");
    echo "<p>✅ Sample loans added</p>";
    
    $pdo->exec("
        INSERT IGNORE INTO transactions (date, transaction_name, transaction_type, amount, description) VALUES 
        ('2025-07-04', 'SIVA', 'credit', 200000.00, 'Initial capital'),
        ('2025-07-04', 'MANI SALARY', 'debit', 10000.00, 'Salary payment'),
        ('2025-07-05', 'LOAN A0001', 'credit', 5000.00, 'Jewelry pawn loan'),
        ('2025-07-05', 'LOAN A0002', 'credit', 25000.00, 'Jewelry pawn loan')
    ");
    echo "<p>✅ Sample transactions added</p>";
    
    // Verify admin user
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = 'admin'");
    $stmt->execute();
    $admin = $stmt->fetch();
    
    if ($admin && password_verify('admin123', $admin['password'])) {
        echo "<p>✅ Admin user verified - login should work!</p>";
    } else {
        echo "<p>❌ Admin user verification failed</p>";
    }
    
    echo "<h3>🎉 Database Setup Complete!</h3>";
    echo "<p><strong>Login Credentials:</strong></p>";
    echo "<p>Username: <strong>admin</strong></p>";
    echo "<p>Password: <strong>admin123</strong></p>";
    echo "<p><a href='index.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 20px;'>Go to Login Page</a></p>";
    
} catch (PDOException $e) {
    echo "<p>❌ Database Error: " . $e->getMessage() . "</p>";
    echo "<p>Please check your MySQL connection settings.</p>";
}
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 800px;
    margin: 50px auto;
    padding: 20px;
    background: #f5f5f5;
}

h2, h3 {
    color: #333;
    border-bottom: 2px solid #007bff;
    padding-bottom: 10px;
}

p {
    margin: 10px 0;
    padding: 10px;
    background: white;
    border-radius: 5px;
    border-left: 4px solid #007bff;
}

a {
    text-decoration: none;
}

a:hover {
    opacity: 0.8;
}
</style> 