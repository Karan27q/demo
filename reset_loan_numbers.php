<?php
/**
 * Reset Loan Numbers Script
 * 
 * This script resets all loan numbers to start from 1, 2, 3, etc.
 * based on their creation order (by id).
 * 
 * Run this once to reset loan numbering.
 */

require_once 'config/database.php';

try {
    $pdo = getDBConnection();
    
    echo "<h2>Resetting Loan Numbers</h2>";
    echo "<p>Starting reset process...</p>";
    
    $pdo->beginTransaction();
    
    // Get all loans ordered by creation date (id)
    $stmt = $pdo->query("SELECT id FROM loans ORDER BY id ASC");
    $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $loanNumber = 1;
    $updated = 0;
    
    foreach ($loans as $loan) {
        $updateStmt = $pdo->prepare("UPDATE loans SET loan_no = ? WHERE id = ?");
        $updateStmt->execute([$loanNumber, $loan['id']]);
        echo "<p>Updated loan ID {$loan['id']} to loan number {$loanNumber}</p>";
        $loanNumber++;
        $updated++;
    }
    
    $pdo->commit();
    
    echo "<h3 style='color: green;'>✓ Success!</h3>";
    echo "<p><strong>Updated {$updated} loans.</strong></p>";
    echo "<p><strong>Next loan number will be: {$loanNumber}</strong></p>";
    echo "<p><a href='pages/loans.php'>Go to Loans Page</a></p>";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "<h3 style='color: red;'>✗ Error!</h3>";
    echo "<p>Error resetting loan numbers: " . $e->getMessage() . "</p>";
}
?>

