<?php
header('Content-Type: application/json');
// Define the base path
$basePath = dirname(__DIR__);
require_once $basePath . '/config/database.php';

try {
    $pdo = getDBConnection();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Add new loan closing
        $loanId = $_POST['loan_id'] ?? '';
        $closingDate = $_POST['closing_date'] ?? '';
        $totalInterestPaid = $_POST['total_interest_paid'] ?? '';
        
        // Validate input
        if (empty($loanId) || empty($closingDate) || empty($totalInterestPaid)) {
            echo json_encode(['success' => false, 'message' => 'All fields are required']);
            exit;
        }
        
        // Check if loan exists and is active
        $stmt = $pdo->prepare("SELECT id, status FROM loans WHERE id = ?");
        $stmt->execute([$loanId]);
        $loan = $stmt->fetch();
        
        if (!$loan) {
            echo json_encode(['success' => false, 'message' => 'Loan not found']);
            exit;
        }
        
        if ($loan['status'] !== 'active') {
            echo json_encode(['success' => false, 'message' => 'Loan is already closed']);
            exit;
        }
        
        // Check if loan closing already exists
        $stmt = $pdo->prepare("SELECT id FROM loan_closings WHERE loan_id = ?");
        $stmt->execute([$loanId]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Loan closing record already exists']);
            exit;
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        try {
            // Insert loan closing record
            $stmt = $pdo->prepare("
                INSERT INTO loan_closings (loan_id, closing_date, total_interest_paid) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$loanId, $closingDate, $totalInterestPaid]);
            
            // Update loan status to closed
            $stmt = $pdo->prepare("UPDATE loans SET status = 'closed' WHERE id = ?");
            $stmt->execute([$loanId]);
            
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Loan closed successfully']);
            
        } catch (Exception $e) {
            $pdo->rollback();
            echo json_encode(['success' => false, 'message' => 'Failed to close loan: ' . $e->getMessage()]);
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        // Update loan closing record
        parse_str(file_get_contents("php://input"), $putData);
        
        $closingId = $putData['id'] ?? '';
        $closingDate = $putData['closing_date'] ?? '';
        $totalInterestPaid = $putData['total_interest_paid'] ?? '';
        
        if (empty($closingId) || empty($closingDate) || empty($totalInterestPaid)) {
            echo json_encode(['success' => false, 'message' => 'All fields are required']);
            exit;
        }
        
        $stmt = $pdo->prepare("
            UPDATE loan_closings 
            SET closing_date = ?, total_interest_paid = ? 
            WHERE id = ?
        ");
        
        if ($stmt->execute([$closingDate, $totalInterestPaid, $closingId])) {
            echo json_encode(['success' => true, 'message' => 'Loan closing record updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update loan closing record']);
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        // Delete loan closing record
        parse_str(file_get_contents("php://input"), $deleteData);
        
        $closingId = $deleteData['id'] ?? '';
        
        if (empty($closingId)) {
            echo json_encode(['success' => false, 'message' => 'Closing ID is required']);
            exit;
        }
        
        // Get loan ID before deleting
        $stmt = $pdo->prepare("SELECT loan_id FROM loan_closings WHERE id = ?");
        $stmt->execute([$closingId]);
        $closing = $stmt->fetch();
        
        if (!$closing) {
            echo json_encode(['success' => false, 'message' => 'Loan closing record not found']);
            exit;
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        try {
            // Delete loan closing record
            $stmt = $pdo->prepare("DELETE FROM loan_closings WHERE id = ?");
            $stmt->execute([$closingId]);
            
            // Update loan status back to active
            $stmt = $pdo->prepare("UPDATE loans SET status = 'active' WHERE id = ?");
            $stmt->execute([$closing['loan_id']]);
            
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Loan closing record deleted successfully']);
            
        } catch (Exception $e) {
            $pdo->rollback();
            echo json_encode(['success' => false, 'message' => 'Failed to delete loan closing record: ' . $e->getMessage()]);
        }
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 