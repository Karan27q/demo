<?php
/**
 * Migration Script: Remove UNIQUE constraint on loan_no
 * 
 * This script removes the UNIQUE constraint on the loan_no column in the loans table,
 * allowing customers to have multiple loans without duplicate key errors.
 * 
 * Run this script once to update your existing database.
 */

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'lakshmi_finance');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Starting migration: Remove ALL UNIQUE constraints on loans table\n";
    echo "================================================================\n\n";
    
    // Step 1: Remove unique_customer_loan constraint (composite unique on customer_id, loan_no)
    echo "Step 1: Checking for 'unique_customer_loan' constraint...\n";
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM INFORMATION_SCHEMA.STATISTICS 
        WHERE table_schema = DATABASE() 
        AND table_name = 'loans' 
        AND index_name = 'unique_customer_loan' 
        AND non_unique = 0
    ");
    $result = $stmt->fetch();
    
    if ($result['count'] > 0) {
        echo "  Found UNIQUE constraint 'unique_customer_loan' on (customer_id, loan_no). Removing it...\n";
        try {
            $pdo->exec("ALTER TABLE loans DROP INDEX unique_customer_loan");
            echo "  ✓ UNIQUE constraint 'unique_customer_loan' removed successfully!\n\n";
        } catch (PDOException $e) {
            echo "  ⚠ Error removing unique_customer_loan: " . $e->getMessage() . "\n";
            echo "  Trying alternative method (DROP KEY)...\n";
            try {
                $pdo->exec("ALTER TABLE loans DROP KEY unique_customer_loan");
                echo "  ✓ UNIQUE constraint removed via DROP KEY!\n\n";
            } catch (PDOException $e2) {
                echo "  ⚠ Alternative method also failed: " . $e2->getMessage() . "\n";
                echo "  Please manually remove the constraint using:\n";
                echo "  ALTER TABLE loans DROP INDEX unique_customer_loan;\n\n";
            }
        }
    } else {
        echo "  ✓ UNIQUE constraint 'unique_customer_loan' does not exist (already removed or never existed).\n\n";
    }
    
    // Step 2: Remove UNIQUE constraint on loan_no (single column unique)
    echo "Step 2: Checking for UNIQUE constraint on 'loan_no' column...\n";
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM INFORMATION_SCHEMA.STATISTICS 
        WHERE table_schema = DATABASE() 
        AND table_name = 'loans' 
        AND index_name = 'loan_no' 
        AND non_unique = 0
    ");
    $result = $stmt->fetch();
    
    if ($result['count'] > 0) {
        echo "  Found UNIQUE constraint on loan_no. Removing it...\n";
        try {
            $pdo->exec("ALTER TABLE loans DROP INDEX loan_no");
            echo "  ✓ UNIQUE constraint on loan_no removed successfully!\n\n";
        } catch (PDOException $e) {
            echo "  ⚠ Error: " . $e->getMessage() . "\n";
            echo "  Please manually remove using: ALTER TABLE loans DROP INDEX loan_no;\n\n";
        }
    } else {
        echo "  ✓ UNIQUE constraint on loan_no does not exist (already removed or never existed).\n\n";
    }
    
    // Step 3: Add composite index for performance (non-unique)
    echo "Step 3: Adding composite index for query performance...\n";
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM INFORMATION_SCHEMA.STATISTICS 
        WHERE table_schema = DATABASE() 
        AND table_name = 'loans' 
        AND index_name = 'idx_loans_customer_loan'
    ");
    $result = $stmt->fetch();
    
    if ($result['count'] == 0) {
        echo "  Adding composite index on (customer_id, loan_no) for better query performance...\n";
        try {
            $pdo->exec("ALTER TABLE loans ADD INDEX idx_loans_customer_loan (customer_id, loan_no)");
            echo "  ✓ Composite index added successfully!\n\n";
        } catch (PDOException $e) {
            echo "  ⚠ Error adding index: " . $e->getMessage() . "\n\n";
        }
    } else {
        echo "  ✓ Composite index idx_loans_customer_loan already exists.\n\n";
    }
    
    // Step 4: Verify the changes
    echo "Step 4: Verifying changes...\n";
    echo "Checking all indexes on loans table:\n";
    $stmt = $pdo->query("
        SELECT index_name, non_unique, GROUP_CONCAT(column_name ORDER BY seq_in_index) as columns
        FROM INFORMATION_SCHEMA.STATISTICS 
        WHERE table_schema = DATABASE() 
        AND table_name = 'loans' 
        AND index_name != 'PRIMARY'
        GROUP BY index_name, non_unique
        ORDER BY index_name
    ");
    $indexes = $stmt->fetchAll();
    
    if (empty($indexes)) {
        echo "  ✓ No additional indexes found.\n";
    } else {
        foreach ($indexes as $index) {
            $unique = $index['non_unique'] == 0 ? 'UNIQUE' : 'NON-UNIQUE';
            echo "  - {$index['index_name']} ({$unique}): {$index['columns']}\n";
        }
    }
    
    // Check specifically for unique constraints
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM INFORMATION_SCHEMA.STATISTICS 
        WHERE table_schema = DATABASE() 
        AND table_name = 'loans' 
        AND non_unique = 0
        AND index_name != 'PRIMARY'
    ");
    $uniqueCount = $stmt->fetch()['count'];
    
    if ($uniqueCount == 0) {
        echo "\n✓ SUCCESS: No UNIQUE constraints found (except PRIMARY KEY).\n";
        echo "  Customers can now have multiple loans!\n\n";
    } else {
        echo "\n⚠ WARNING: {$uniqueCount} UNIQUE constraint(s) still exist.\n";
        echo "  Please check and remove them manually.\n\n";
    }
    
    echo "================================================================\n";
    echo "Migration Summary:\n";
    echo "==================\n";
    echo "✓ Removed unique_customer_loan constraint (if existed)\n";
    echo "✓ Removed UNIQUE constraint on loan_no (if existed)\n";
    echo "✓ Added composite index for performance\n";
    echo "\nYou can now add multiple loans for the same customer.\n";
    echo "Each loan will have a unique 'id' (auto-increment primary key).\n";
    echo "The 'loan_no' field and (customer_id, loan_no) combination are no longer required to be unique.\n";
    echo "\nExample: Customer ID 22 can now have multiple loans:\n";
    echo "  - Loan 1: loan_no='1', customer_id=22, id=100\n";
    echo "  - Loan 2: loan_no='2', customer_id=22, id=101\n";
    echo "  - Loan 3: loan_no='1', customer_id=22, id=102 (even same loan_no is allowed)\n";
    echo "\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>

