<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lakshmi Finance - Login</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="logo-section">
                <div class="logo">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h1 class="company-name">LAKSHMI</h1>
                <h2 class="company-subtitle">FINANCE</h2>
                <div class="app-title">
                    <span class="demo-text">DEMO</span>
                    <span class="app-name">JEWELL PAWN SHOP</span>
                </div>
            </div>
            
            <?php
            // Display error messages
            if (isset($_GET['error'])) {
                $error = $_GET['error'];
                $message = '';
                
                switch ($error) {
                    case 'empty_fields':
                        $message = 'Please fill in all fields';
                        break;
                    case 'invalid_credentials':
                        $message = 'Invalid username or password';
                        break;
                    case 'database_error':
                        $message = 'Database connection error';
                        break;
                    default:
                        $message = 'Login failed';
                }
                
                echo '<div class="error-message">' . htmlspecialchars($message) . '</div>';
            }
            ?>
            
            <form class="login-form" action="auth/login.php" method="POST">
                <div class="input-group">
                    <input type="text" name="username" placeholder="User Name" required>
                </div>
                <div class="input-group">
                    <input type="password" name="password" placeholder="Password" required>
                    <i class="fas fa-eye-slash password-toggle"></i>
                </div>
                <button type="submit" class="login-btn">Login</button>
            </form>
            
            <div class="login-info">
                <p><strong>Default Credentials:</strong></p>
                <p>Username: admin</p>
                <p>Password: admin123</p>
            </div>
        </div>
    </div>

    <script src="assets/js/login.js"></script>
</body>
</html> 