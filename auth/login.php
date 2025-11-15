<?php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        header('Location: ../index.php?error=empty_fields');
        exit();
    }
    
    try {
        $pdo = getDBConnection();

        // Debug: Check if username exists
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        // Debug: Log user info (remove in production)
        file_put_contents('../debug_login.txt', "Username: $username\nUser found: " . print_r($user, true) . "\n", FILE_APPEND);

        // Check if user exists
        if (!$user) {
            header('Location: ../index.php?error=user_not_found');
            exit();
        }

        // TEMP: Log the hash for 'admin123' for comparison (remove after use)
        file_put_contents('../debug_login.txt', "Hash for admin123: " . password_hash('admin123', PASSWORD_DEFAULT) . "\n", FILE_APPEND);

        // Support both hashed and plain text passwords
        if (
            password_verify($password, $user['password']) ||
            $password === $user['password']
        ) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];
            
            // Get redirect page from POST or GET, or use default dashboard
            $redirectPage = $_POST['redirect'] ?? $_GET['redirect'] ?? '';
            
            // Build redirect URL
            $redirectUrl = '../dashboard.php';
            if (!empty($redirectPage)) {
                $redirectUrl .= '?page=' . urlencode($redirectPage);
            }
            
            header('Location: ' . $redirectUrl);
            exit();
        } else {
            // Debug: Log password mismatch
            file_put_contents('../debug_login.txt', "Password mismatch for username: $username\n", FILE_APPEND);
            header('Location: ../index.php?error=invalid_credentials');
            exit();
        }
    } catch (PDOException $e) {
        header('Location: ../index.php?error=database_error');
        exit();
    }
} else {
    header('Location: ../index.php');
    exit();
}
?>