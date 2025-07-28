<?php
header('Content-Type: application/json');
// Define the base path
$basePath = dirname(__DIR__);
require_once $basePath . '/config/database.php';

try {
    $pdo = getDBConnection();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Add new group
        $name = $_POST['name'] ?? '';
        
        if (empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Group name is required']);
            exit();
        }
        
        // Check if group name already exists
        $stmt = $pdo->prepare("SELECT id FROM groups WHERE name = ?");
        $stmt->execute([$name]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Group name already exists']);
            exit();
        }
        
        // Insert new group
        $stmt = $pdo->prepare("INSERT INTO groups (name) VALUES (?)");
        $stmt->execute([$name]);
        
        echo json_encode(['success' => true, 'message' => 'Group added successfully']);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        // Update group
        parse_str(file_get_contents("php://input"), $data);
        
        $id = $data['id'] ?? '';
        $name = $data['name'] ?? '';
        
        if (empty($id) || empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Required fields are missing']);
            exit();
        }
        
        $stmt = $pdo->prepare("UPDATE groups SET name = ? WHERE id = ?");
        $stmt->execute([$name, $id]);
        
        echo json_encode(['success' => true, 'message' => 'Group updated successfully']);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        // Delete group
        $id = $_GET['id'] ?? '';
        
        if (empty($id)) {
            echo json_encode(['success' => false, 'message' => 'Group ID is required']);
            exit();
        }
        
        // Check if group has products
        $stmt = $pdo->prepare("SELECT COUNT(*) as product_count FROM products WHERE group_id = ?");
        $stmt->execute([$id]);
        $productCount = $stmt->fetch()['product_count'];
        
        if ($productCount > 0) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete group with existing products']);
            exit();
        }
        
        $stmt = $pdo->prepare("DELETE FROM groups WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true, 'message' => 'Group deleted successfully']);
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 