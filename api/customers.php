<?php
header('Content-Type: application/json');
// Define the base path
$basePath = dirname(__DIR__);
require_once $basePath . '/config/database.php';

try {
    $pdo = getDBConnection();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Add new customer
        $customerNo = $_POST['customer_no'] ?? '';
        $name = $_POST['name'] ?? '';
        $mobile = $_POST['mobile'] ?? '';
        $address = $_POST['address'] ?? '';
        
        if (empty($customerNo) || empty($name) || empty($mobile)) {
            echo json_encode(['success' => false, 'message' => 'Required fields are missing']);
            exit();
        }
        
        // Check if customer number already exists
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE customer_no = ?");
        $stmt->execute([$customerNo]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Customer number already exists']);
            exit();
        }
        
        // Insert new customer
        $stmt = $pdo->prepare("
            INSERT INTO customers (customer_no, name, mobile, address) 
            VALUES (?, ?, ?, ?)
        ");
        $result = $stmt->execute([$customerNo, $name, $mobile, $address]);
        
        if ($result) {
            // Get the inserted customer to verify
            $stmt = $pdo->prepare("SELECT * FROM customers WHERE customer_no = ?");
            $stmt->execute([$customerNo]);
            $insertedCustomer = $stmt->fetch();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Customer added successfully',
                'customer' => $insertedCustomer
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to insert customer']);
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        // Update customer
        parse_str(file_get_contents("php://input"), $data);
        
        $id = $data['id'] ?? '';
        $name = $data['name'] ?? '';
        $mobile = $data['mobile'] ?? '';
        $address = $data['address'] ?? '';
        
        if (empty($id) || empty($name) || empty($mobile)) {
            echo json_encode(['success' => false, 'message' => 'Required fields are missing']);
            exit();
        }
        
        $stmt = $pdo->prepare("
            UPDATE customers 
            SET name = ?, mobile = ?, address = ? 
            WHERE id = ?
        ");
        $stmt->execute([$name, $mobile, $address, $id]);
        
        echo json_encode(['success' => true, 'message' => 'Customer updated successfully']);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        // Delete customer
        $id = $_GET['id'] ?? '';
        
        if (empty($id)) {
            echo json_encode(['success' => false, 'message' => 'Customer ID is required']);
            exit();
        }
        
        // Check if customer has loans
        $stmt = $pdo->prepare("SELECT COUNT(*) as loan_count FROM loans WHERE customer_id = ?");
        $stmt->execute([$id]);
        $loanCount = $stmt->fetch()['loan_count'];
        
        if ($loanCount > 0) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete customer with existing loans']);
            exit();
        }
        
        $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true, 'message' => 'Customer deleted successfully']);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_customers') {
        // Get customers for dropdown
        $stmt = $pdo->query("SELECT id, customer_no, name FROM customers ORDER BY name");
        $customers = $stmt->fetchAll();
        echo json_encode(['success' => true, 'customers' => $customers]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 