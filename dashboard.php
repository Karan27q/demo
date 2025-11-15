<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    // Preserve the page parameter if present for redirect after login
    $redirect = isset($_GET['page']) ? '?redirect=' . urlencode($_GET['page']) : '';
    header('Location: index.php' . $redirect);
    exit();
}

// Get the page parameter from URL
$pageParam = isset($_GET['page']) ? htmlspecialchars($_GET['page']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lakshmi Finance - Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <div class="sidebar-company">LAKSHMI</div>
                <div class="sidebar-app-name">FINANCE</div>
            </div>
            
            <nav class="nav-menu">
                <div class="nav-item active" data-page="dashboard">
                    <i class="fas fa-th-large"></i>
                    <span>Dashboard</span>
                </div>
                
                <div class="nav-item" data-page="user-access">
                    <i class="fas fa-users-cog"></i>
                    <span>User</span>
                </div>
                
                <div class="nav-item has-submenu">
                    <i class="fas fa-cog"></i>
                    <span>Master</span>
                    <i class="fas fa-chevron-right arrow"></i>
                </div>
                <div class="sub-menu">
                    <div class="sub-item" data-page="customers">• Customer</div>
                    <div class="sub-item" data-page="groups">• Group</div>
                    <div class="sub-item" data-page="products">• Products</div>
                    <div class="sub-item" data-page="jewel-recovery">• Jewel Recovery</div>
                </div>
                
                <div class="nav-item has-submenu">
                    <i class="fas fa-gem"></i>
                    <span>Pawn</span>
                    <i class="fas fa-chevron-right arrow"></i>
                </div>
                <div class="sub-menu">
                    <div class="sub-item" data-page="loans">• Jewelry Pawning</div>
                    <div class="sub-item" data-page="bank-pledge">• Bank Pledge</div>
                </div>
                
                <div class="nav-item" data-page="interest">
                    <i class="fas fa-percent"></i>
                    <span>Interest</span>
                </div>
                
                <div class="nav-item" data-page="transactions">
                    <i class="fas fa-exchange-alt"></i>
                    <span>Transaction</span>
                </div>
                
                <div class="nav-item" data-page="expense">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Expense</span>
                </div>
                
                <div class="nav-item has-submenu">
                    <i class="fas fa-chart-bar"></i>
                    <span>Report</span>
                    <i class="fas fa-chevron-right arrow"></i>
                </div>
                <div class="sub-menu">
                    <div class="sub-item" data-page="reports">• Balance Sheet</div>
                </div>
            </nav>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="header">
                <div class="header-left">
                    <button class="menu-toggle" id="menuToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
                <div class="header-right">
                    <div class="user-info">
                        <i class="fas fa-user"></i>
                        <span><?php echo htmlspecialchars($_SESSION['name']); ?></span>
                    </div>
                    <a href="auth/logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
            
            <!-- Content Area -->
            <div class="content-area" id="contentArea">
                <?php include 'pages/dashboard.php'; ?>
            </div>
        </div>
    </div>
    
    <?php if (!empty($pageParam)): ?>
    <script>
        // Pass the page parameter to JavaScript
        window.initialPage = <?php echo json_encode($pageParam); ?>;
    </script>
    <?php endif; ?>
    <script src="assets/js/dashboard.js"></script>
</body>
</html>