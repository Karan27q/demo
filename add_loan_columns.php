<?php
/**
 * Quick Fix: Add loan_days and interest_amount columns
 * Run this by navigating to: http://localhost/demo/add_loan_columns.php
 */

require_once 'config/database.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Loan Columns</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h2 { color: #333; }
        .success { color: green; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; margin: 10px 0; }
        .error { color: red; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; margin: 10px 0; }
        .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Add Missing Loan Columns</h2>
        
        <?php
        try {
            $pdo = getDBConnection();
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Check and add loan_days column
            $stmt = $pdo->query("SHOW COLUMNS FROM loans LIKE 'loan_days'");
            if ($stmt->rowCount() == 0) {
                $pdo->exec("ALTER TABLE loans ADD COLUMN loan_days INT DEFAULT NULL");
                echo "<div class='success'>✓ Added column 'loan_days'</div>";
            } else {
                echo "<div class='success'>- Column 'loan_days' already exists</div>";
            }
            
            // Check and add interest_amount column
            $stmt = $pdo->query("SHOW COLUMNS FROM loans LIKE 'interest_amount'");
            if ($stmt->rowCount() == 0) {
                $pdo->exec("ALTER TABLE loans ADD COLUMN interest_amount DECIMAL(10,2) DEFAULT NULL");
                echo "<div class='success'>✓ Added column 'interest_amount'</div>";
            } else {
                echo "<div class='success'>- Column 'interest_amount' already exists</div>";
            }
            
            echo "<hr>";
            echo "<h3 style='color: green;'>✓ Columns added successfully!</h3>";
            echo "<p>The error should now be fixed. You can try adding a loan again.</p>";
            echo "<p><a href='dashboard.php?page=loans' class='btn'>Go to Loans Page</a></p>";
            
        } catch(PDOException $e) {
            echo "<div class='error'><h3>Error</h3>";
            echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p></div>";
        }
        ?>
    </div>
</body>
</html>





