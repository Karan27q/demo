<?php
/**
 * Database Migration Script
 * Run this file to add missing columns to existing tables
 * Usage: Navigate to http://localhost/demo/migrate_database.php
 */

require_once 'config/database.php';

function columnExists($pdo, $table, $column) {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        return $stmt->rowCount() > 0;
    } catch(PDOException $e) {
        return false;
    }
}

try {
    $pdo = getDBConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>Database Migration Script</h2>";
    echo "<p>Adding missing columns to existing tables...</p>";
    echo "<hr>";
    
    // Migrate customers table
    echo "<h3>Customers Table</h3>";
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
        if (!columnExists($pdo, 'customers', $column)) {
            $pdo->exec("ALTER TABLE customers ADD COLUMN $column $type");
            echo "<p style='color: green;'>✓ Added column 'customers.$column'</p>";
        } else {
            echo "<p style='color: orange;'>- Column 'customers.$column' already exists</p>";
        }
    }
    
    // Migrate loans table
    echo "<h3>Loans Table</h3>";
    $loansColumns = [
        'date_of_birth' => 'DATE',
        'group_id' => 'INT',
        'recovery_period' => 'VARCHAR(50)',
        'ornament_file' => 'VARCHAR(255)',
        'proof_file' => 'VARCHAR(255)'
    ];
    
    foreach ($loansColumns as $column => $type) {
        if (!columnExists($pdo, 'loans', $column)) {
            $sql = "ALTER TABLE loans ADD COLUMN $column $type";
            if ($column === 'group_id') {
                $sql .= ", ADD INDEX idx_group_id (group_id)";
            }
            $pdo->exec($sql);
            echo "<p style='color: green;'>✓ Added column 'loans.$column'</p>";
        } else {
            echo "<p style='color: orange;'>- Column 'loans.$column' already exists</p>";
        }
    }
    
    // Add foreign key for group_id if it doesn't exist
    try {
        $pdo->exec("ALTER TABLE loans ADD CONSTRAINT fk_loans_group FOREIGN KEY (group_id) REFERENCES groups(id)");
        echo "<p style='color: green;'>✓ Added foreign key constraint for loans.group_id</p>";
    } catch(PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') === false && 
            strpos($e->getMessage(), 'already exists') === false) {
            echo "<p style='color: orange;'>- Foreign key constraint may already exist</p>";
        }
    }
    
    echo "<hr>";
    echo "<h3 style='color: green;'>Migration completed successfully!</h3>";
    echo "<p>All required columns have been added to the database.</p>";
    echo "<p><a href='dashboard.php'>Go to Dashboard</a></p>";
    
} catch(PDOException $e) {
    echo "<h3 style='color: red;'>Migration Error</h3>";
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Please check your database connection and try again.</p>";
}
?>

