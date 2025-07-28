<?php
header('Content-Type: application/json');
// Define the base path
$basePath = dirname(__DIR__);
require_once $basePath . '/config/database.php';

try {
    $pdo = getDBConnection();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Add new transaction
        $date = $_POST['date'] ?? '';
        $transactionName = $_POST['transaction_name'] ?? '';
        $transactionType = $_POST['transaction_type'] ?? '';
        $amount = $_POST['amount'] ?? '';
        $description = $_POST['description'] ?? '';
        
        if (empty($date) || empty($transactionName) || empty($transactionType) || empty($amount)) {
            echo json_encode(['success' => false, 'message' => 'Required fields are missing']);
            exit();
        }
        
        if (!in_array($transactionType, ['credit', 'debit'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid transaction type']);
            exit();
        }
        
        // Insert new transaction
        $stmt = $pdo->prepare("
            INSERT INTO transactions (date, transaction_name, transaction_type, amount, description) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$date, $transactionName, $transactionType, $amount, $description]);
        
        echo json_encode(['success' => true, 'message' => 'Transaction added successfully']);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        // Update transaction
        parse_str(file_get_contents("php://input"), $data);
        
        $id = $data['id'] ?? '';
        $date = $data['date'] ?? '';
        $transactionName = $data['transaction_name'] ?? '';
        $transactionType = $data['transaction_type'] ?? '';
        $amount = $data['amount'] ?? '';
        $description = $data['description'] ?? '';
        
        if (empty($id) || empty($date) || empty($transactionName) || empty($transactionType) || empty($amount)) {
            echo json_encode(['success' => false, 'message' => 'Required fields are missing']);
            exit();
        }
        
        if (!in_array($transactionType, ['credit', 'debit'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid transaction type']);
            exit();
        }
        
        $stmt = $pdo->prepare("
            UPDATE transactions 
            SET date = ?, transaction_name = ?, transaction_type = ?, amount = ?, description = ? 
            WHERE id = ?
        ");
        $stmt->execute([$date, $transactionName, $transactionType, $amount, $description, $id]);
        
        echo json_encode(['success' => true, 'message' => 'Transaction updated successfully']);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        // Delete transaction
        $id = $_GET['id'] ?? '';
        
        if (empty($id)) {
            echo json_encode(['success' => false, 'message' => 'Transaction ID is required']);
            exit();
        }
        
        $stmt = $pdo->prepare("DELETE FROM transactions WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true, 'message' => 'Transaction deleted successfully']);
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 