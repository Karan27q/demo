<?php
/**
 * Quick Database Fix Script
 * This script adds missing columns to fix the "Unknown column 'place'" error
 * Run this by navigating to: http://localhost/demo/fix_database.php
 */

require_once 'config/database.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Migration - Fix Missing Columns</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        .success { color: green; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; margin: 10px 0; }
        .error { color: red; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; margin: 10px 0; }
        .info { color: #856404; padding: 10px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; margin: 10px 0; }
        .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; margin-top: 10px; }
        .btn:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Database Migration - Fix Missing Columns</h1>
        
        <?php
        try {
            $pdo = getDBConnection();
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            echo "<h2>Adding Missing Columns...</h2>";
            
            // Function to check if column exists
            function columnExists($pdo, $table, $column) {
                try {
                    $stmt = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
                    return $stmt->rowCount() > 0;
                } catch(PDOException $e) {
                    return false;
                }
            }
            
            $errors = [];
            $success = [];
            
            // Fix customers table
            echo "<h3>Customers Table</h3>";
            $customersColumns = [
                'place' => 'VARCHAR(100) DEFAULT NULL',
                'pincode' => 'VARCHAR(10) DEFAULT NULL',
                'additional_number' => 'VARCHAR(15) DEFAULT NULL',
                'reference' => 'VARCHAR(100) DEFAULT NULL',
                'proof_type' => 'VARCHAR(50) DEFAULT NULL',
                'customer_photo' => 'VARCHAR(255) DEFAULT NULL',
                'proof_file' => 'VARCHAR(255) DEFAULT NULL'
            ];
            
            foreach ($customersColumns as $column => $type) {
                if (!columnExists($pdo, 'customers', $column)) {
                    try {
                        $pdo->exec("ALTER TABLE customers ADD COLUMN $column $type");
                        $success[] = "Added column 'customers.$column'";
                        echo "<div class='success'>✓ Added column 'customers.$column'</div>";
                    } catch(PDOException $e) {
                        $errors[] = "Error adding 'customers.$column': " . $e->getMessage();
                        echo "<div class='error'>✗ Error adding 'customers.$column': " . htmlspecialchars($e->getMessage()) . "</div>";
                    }
                } else {
                    echo "<div class='info'>- Column 'customers.$column' already exists</div>";
                }
            }
            
            // Fix loans table
            echo "<h3>Loans Table</h3>";
            $loansColumns = [
                'date_of_birth' => 'DATE DEFAULT NULL',
                'group_id' => 'INT(11) DEFAULT NULL',
                'recovery_period' => 'VARCHAR(50) DEFAULT NULL',
                'ornament_file' => 'VARCHAR(255) DEFAULT NULL',
                'proof_file' => 'VARCHAR(255) DEFAULT NULL',
                'loan_days' => 'INT DEFAULT NULL',
                'interest_amount' => 'DECIMAL(10,2) DEFAULT NULL'
            ];
            
            foreach ($loansColumns as $column => $type) {
                if (!columnExists($pdo, 'loans', $column)) {
                    try {
                        $pdo->exec("ALTER TABLE loans ADD COLUMN $column $type");
                        $success[] = "Added column 'loans.$column'";
                        echo "<div class='success'>✓ Added column 'loans.$column'</div>";
                    } catch(PDOException $e) {
                        $errors[] = "Error adding 'loans.$column': " . $e->getMessage();
                        echo "<div class='error'>✗ Error adding 'loans.$column': " . htmlspecialchars($e->getMessage()) . "</div>";
                    }
                } else {
                    echo "<div class='info'>- Column 'loans.$column' already exists</div>";
                }
            }
            
            // Add index for group_id if it doesn't exist
            try {
                $stmt = $pdo->query("SHOW INDEX FROM loans WHERE Key_name = 'idx_group_id'");
                if ($stmt->rowCount() == 0) {
                    $pdo->exec("ALTER TABLE loans ADD INDEX idx_group_id (group_id)");
                    echo "<div class='success'>✓ Added index 'idx_group_id' on loans.group_id</div>";
                } else {
                    echo "<div class='info'>- Index 'idx_group_id' already exists</div>";
                }
            } catch(PDOException $e) {
                echo "<div class='info'>- Could not add index (may already exist): " . htmlspecialchars($e->getMessage()) . "</div>";
            }
            
            // Add foreign key for group_id if it doesn't exist
            try {
                $stmt = $pdo->query("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = 'loans' 
                    AND CONSTRAINT_NAME = 'fk_loans_group'");
                if ($stmt->rowCount() == 0) {
                    $pdo->exec("ALTER TABLE loans ADD CONSTRAINT fk_loans_group FOREIGN KEY (group_id) REFERENCES groups(id)");
                    echo "<div class='success'>✓ Added foreign key 'fk_loans_group'</div>";
                } else {
                    echo "<div class='info'>- Foreign key 'fk_loans_group' already exists</div>";
                }
            } catch(PDOException $e) {
                echo "<div class='info'>- Could not add foreign key (may already exist or groups table missing): " . htmlspecialchars($e->getMessage()) . "</div>";
            }
            
            echo "<hr>";
            
            if (count($errors) == 0) {
                echo "<div class='success'><h2>✓ Migration Completed Successfully!</h2>";
                echo "<p>All required columns have been added to the database.</p>";
                echo "<p>The error 'Unknown column place' should now be fixed.</p></div>";
            } else {
                echo "<div class='error'><h2>Migration Completed with Errors</h2>";
                echo "<p>Some columns could not be added. Please check the errors above.</p></div>";
            }
            
            echo "<p><a href='dashboard.php' class='btn'>Go to Dashboard</a></p>";
            
        } catch(PDOException $e) {
            echo "<div class='error'><h2>Database Connection Error</h2>";
            echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<p>Please check your database configuration in config/database.php</p></div>";
        }
        ?>
    </div>
</body>
</html>

