<?php
header('Content-Type: application/json');
// Define the base path
$basePath = dirname(__DIR__);
require_once $basePath . '/config/database.php';

try {
    $pdo = getDBConnection();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Add new interest record
        $loanId = $_POST['loan_id'] ?? '';
        $interestDate = $_POST['interest_date'] ?? '';
        $interestAmount = $_POST['interest_amount'] ?? '';
        
        // Validate input
        if (empty($loanId) || empty($interestDate) || empty($interestAmount)) {
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
            echo json_encode(['success' => false, 'message' => 'Cannot add interest to closed loan']);
            exit;
        }
        
        // Insert interest record
        $stmt = $pdo->prepare("
            INSERT INTO interest (loan_id, interest_date, interest_amount) 
            VALUES (?, ?, ?)
        ");
        
        if ($stmt->execute([$loanId, $interestDate, $interestAmount])) {
            echo json_encode(['success' => true, 'message' => 'Interest record added successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add interest record']);
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        // Update interest record
        parse_str(file_get_contents("php://input"), $putData);
        
        $interestId = $putData['id'] ?? '';
        $interestDate = $putData['interest_date'] ?? '';
        $interestAmount = $putData['interest_amount'] ?? '';
        
        if (empty($interestId) || empty($interestDate) || empty($interestAmount)) {
            echo json_encode(['success' => false, 'message' => 'All fields are required']);
            exit;
        }
        
        $stmt = $pdo->prepare("
            UPDATE interest 
            SET interest_date = ?, interest_amount = ? 
            WHERE id = ?
        ");
        
        if ($stmt->execute([$interestDate, $interestAmount, $interestId])) {
            echo json_encode(['success' => true, 'message' => 'Interest record updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update interest record']);
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        // Delete interest record
        parse_str(file_get_contents("php://input"), $deleteData);
        
        $interestId = $deleteData['id'] ?? '';
        
        if (empty($interestId)) {
            echo json_encode(['success' => false, 'message' => 'Interest ID is required']);
            exit;
        }
        
        $stmt = $pdo->prepare("DELETE FROM interest WHERE id = ?");
        
        if ($stmt->execute([$interestId])) {
            echo json_encode(['success' => true, 'message' => 'Interest record deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete interest record']);
        }
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 