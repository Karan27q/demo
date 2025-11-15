<?php
header('Content-Type: application/json');
// Define the base path
$basePath = dirname(__DIR__);
require_once $basePath . '/config/database.php';

try {
    $pdo = getDBConnection();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Add new product
        $name = $_POST['name'] ?? '';
        $nameTamil = $_POST['name_tamil'] ?? '';
        $groupId = $_POST['group_id'] ?? null;
        
        if (empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Product name is required']);
            exit();
        }
        
        // Check if product name already exists
        $stmt = $pdo->prepare("SELECT id FROM products WHERE name = ?");
        $stmt->execute([$name]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Product name already exists']);
            exit();
        }
        
        // Insert new product
        $stmt = $pdo->prepare("
            INSERT INTO products (name, name_tamil, group_id) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$name, $nameTamil, $groupId]);
        
        echo json_encode(['success' => true, 'message' => 'Product added successfully']);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        // Update product
        parse_str(file_get_contents("php://input"), $data);
        
        $id = $data['id'] ?? '';
        $name = $data['name'] ?? '';
        $nameTamil = $data['name_tamil'] ?? '';
        $groupId = $data['group_id'] ?? null;
        
        if (empty($id) || empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Required fields are missing']);
            exit();
        }
        
        $stmt = $pdo->prepare("
            UPDATE products 
            SET name = ?, name_tamil = ?, group_id = ? 
            WHERE id = ?
        ");
        $stmt->execute([$name, $nameTamil, $groupId, $id]);
        
        echo json_encode(['success' => true, 'message' => 'Product updated successfully']);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        // Delete product
        $id = $_GET['id'] ?? '';
        
        if (empty($id)) {
            echo json_encode(['success' => false, 'message' => 'Product ID is required']);
            exit();
        }
        
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true, 'message' => 'Product deleted successfully']);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get single product by ID if id parameter is provided
        $id = $_GET['id'] ?? '';
        
        if (!empty($id)) {
            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
            $stmt->execute([$id]);
            $product = $stmt->fetch();
            
            if ($product) {
                echo json_encode(['success' => true, 'product' => $product]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Product not found']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Product ID is required']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 