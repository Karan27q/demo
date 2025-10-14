<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}
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
                
                <div class="nav-item has-submenu">
                    <i class="fas fa-plus"></i>
                    <span>Master</span>
                    <i class="fas fa-chevron-right arrow"></i>
                </div>
                <div class="sub-menu">
                    <div class="sub-item" data-page="groups">• Group</div>
                    <div class="sub-item" data-page="products">• Products</div>
                </div>
                
                <div class="nav-item" data-page="customers">
                    <i class="fas fa-user-friends"></i>
                    <span>Customer</span>
                </div>
                
                <div class="nav-item" data-page="loans">
                    <i class="fas fa-coins"></i>
                    <span>Loan</span>
                </div>
                
                <div class="nav-item" data-page="closed-loans">
                    <i class="fas fa-check-circle"></i>
                    <span>Closed Loans</span>
                </div>
                
        
                
                <div class="nav-item has-submenu">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Interest | Closing</span>
                    <i class="fas fa-chevron-down arrow"></i>
                </div>
                <div class="sub-menu">
                    <div class="sub-item" data-page="interest">• Interest</div>
                    <div class="sub-item" data-page="loan-closing">• Loan Closing</div>
                         
                </div>
                
                <div class="nav-item" data-page="transactions">
                    <i class="fas fa-exchange-alt"></i>
                    <span>Transaction</span>
                </div>
                
                <div class="nav-item has-submenu">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                    <i class="fas fa-chevron-right arrow"></i>
                </div>
                <div class="sub-menu">
                    <!--div class="sub-item" data-page="balancesheet">• Balance Sheet</div-->
                    <!--div class="sub-item" data-page="daybook">• Day Book</div-->
                    <!--div class="sub-item" data-page="advance-report">• Advance Report</div-->
                    <div class="sub-item" data-page="pledge-report">• Pledge Report</div>
                    <div class="sub-item" data-page="loan-report">• Loan Report</div>
                    <div class="sub-item" data-page="customer-report">• Customer Report</div>
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
    
    <script src="assets/js/dashboard.js"></script>
</body>
</html>