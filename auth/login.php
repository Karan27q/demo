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
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];
            
            header('Location: ../dashboard.php');
            exit();
        } else {
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