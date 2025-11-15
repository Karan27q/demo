<?php
header('Content-Type: application/json');
$basePath = dirname(__DIR__);
require_once $basePath . '/config/database.php';

try {
    $pdo = getDBConnection();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Add new user
        $username = $_POST['username'] ?? '';
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'user';
        
        if (empty($username) || empty($name) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Required fields are missing']);
            exit();
        }
        
        // Check if username already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Username already exists']);
            exit();
        }
        
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert new user
        $stmt = $pdo->prepare("
            INSERT INTO users (username, name, email, password, role) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$username, $name, $email, $hashedPassword, $role]);
        
        echo json_encode(['success' => true, 'message' => 'User added successfully']);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        // Update user
        parse_str(file_get_contents("php://input"), $data);
        
        $id = $data['id'] ?? '';
        $username = $data['username'] ?? '';
        $name = $data['name'] ?? '';
        $email = $data['email'] ?? '';
        $role = $data['role'] ?? 'user';
        $password = $data['password'] ?? '';
        
        if (empty($id) || empty($username) || empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Required fields are missing']);
            exit();
        }
        
        // Check if username already exists (excluding current user)
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$username, $id]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Username already exists']);
            exit();
        }
        
        // Update user
        if (!empty($password)) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                UPDATE users 
                SET username = ?, name = ?, email = ?, password = ?, role = ? 
                WHERE id = ?
            ");
            $stmt->execute([$username, $name, $email, $hashedPassword, $role, $id]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET username = ?, name = ?, email = ?, role = ? 
                WHERE id = ?
            ");
            $stmt->execute([$username, $name, $email, $role, $id]);
        }
        
        echo json_encode(['success' => true, 'message' => 'User updated successfully']);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        // Delete user
        $id = $_GET['id'] ?? '';
        
        if (empty($id)) {
            echo json_encode(['success' => false, 'message' => 'User ID is required']);
            exit();
        }
        
        // Prevent deleting yourself
        session_start();
        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $id) {
            echo json_encode(['success' => false, 'message' => 'You cannot delete your own account']);
            exit();
        }
        
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get user by ID
        $id = $_GET['id'] ?? '';
        
        if (empty($id)) {
            echo json_encode(['success' => false, 'message' => 'User ID is required']);
            exit();
        }
        
        $stmt = $pdo->prepare("SELECT id, username, name, email, role, created_at FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        
        if ($user) {
            echo json_encode(['success' => true, 'user' => $user]);
        } else {
            echo json_encode(['success' => false, 'message' => 'User not found']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>

