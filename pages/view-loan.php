<?php
// Define the base path
$basePath = dirname(__DIR__);
require_once $basePath . '/config/database.php';

$loanId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($loanId <= 0) {
    header('Location: ../dashboard.php?page=loans');
    exit();
}

try {
    $pdo = getDBConnection();
    
    // Fetch loan details
    $stmt = $pdo->prepare("SELECT * FROM loans WHERE id = ?");
    $stmt->execute([$loanId]);
    $loan = $stmt->fetch();
    
    if (!$loan) {
        header('Location: ../dashboard.php?page=loans');
        exit();
    }
    
    // Fetch customer details
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$loan['customer_id']]);
    $customer = $stmt->fetch();
    
    // Calculate interest
    // Formula: Principal × (Interest Rate / 100) × (Days / 30)
    $principal = floatval($loan['principal_amount'] ?? 0);
    $interestRate = floatval($loan['interest_rate'] ?? 0);
    $loanDays = floatval($loan['loan_days'] ?? 0);
    $interestAmount = ($principal > 0 && $interestRate > 0 && $loanDays > 0) 
        ? $principal * ($interestRate / 100) * ($loanDays / 30)
        : floatval($loan['interest_amount'] ?? 0);
    $totalAmount = $principal + $interestAmount;
    
    // Get interest paid
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(interest_amount), 0) as total FROM interest WHERE loan_id = ?");
    $stmt->execute([$loanId]);
    $interestPaid = $stmt->fetch()['total'];
    
    // Format date
    function formatDate($dateStr) {
        if (!$dateStr) return 'N/A';
        return date('d-m-Y', strtotime($dateStr));
    }
    
} catch (PDOException $e) {
    header('Location: ../dashboard.php?page=loans');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#2196F3">
    <title>View Loan - Lakshmi Finance</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container" style="max-width: 1200px; margin: 20px auto; padding: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <h1 style="margin: 0; color: #2d3748;">
                <i class="fas fa-eye"></i> Loan Details
            </h1>
            <div style="display: flex; gap: 10px;">
                <?php if ($loan['status'] === 'active'): ?>
                    <a href="edit-loan.php?id=<?php echo $loan['id']; ?>" class="btn-primary" style="text-decoration: none;">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                <?php endif; ?>
                <a href="../dashboard.php?page=loans" class="btn-secondary" style="text-decoration: none;">
                    <i class="fas fa-arrow-left"></i> Back to Loans
                </a>
            </div>
        </div>
        
        <div class="card" style="background: white; border-radius: 8px; padding: 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <!-- Loan Summary -->
            <div style="background: #f8f9fa; border-radius: 8px; padding: 25px; margin-bottom: 30px;">
                <h3 style="margin: 0 0 20px 0; color: #495057; border-bottom: 2px solid #dee2e6; padding-bottom: 15px;">
                    <i class="fas fa-calculator"></i> Loan Summary
                </h3>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                    <div>
                        <strong style="color: #6c757d; font-size: 14px; display: block; margin-bottom: 8px;">Principal Amount:</strong>
                        <div style="font-size: 22px; color: #212529; font-weight: 600;">₹<?php echo number_format($principal, 2); ?></div>
                    </div>
                    <div>
                        <strong style="color: #6c757d; font-size: 14px; display: block; margin-bottom: 8px;">Interest Amount:</strong>
                        <div style="font-size: 22px; color: #0d6efd; font-weight: 600;">₹<?php echo number_format($interestAmount, 2); ?></div>
                    </div>
                    <div>
                        <strong style="color: #6c757d; font-size: 14px; display: block; margin-bottom: 8px;">Total Amount:</strong>
                        <div style="font-size: 24px; color: #198754; font-weight: 700;">₹<?php echo number_format($totalAmount, 2); ?></div>
                    </div>
                    <div>
                        <strong style="color: #6c757d; font-size: 14px; display: block; margin-bottom: 8px;">Interest Paid:</strong>
                        <div style="font-size: 18px; color: #38a169; font-weight: 600;">₹<?php echo number_format($interestPaid, 2); ?></div>
                    </div>
                    <div>
                        <strong style="color: #6c757d; font-size: 14px; display: block; margin-bottom: 8px;">Outstanding:</strong>
                        <div style="font-size: 18px; color: #dc3545; font-weight: 600;">₹<?php echo number_format(max(0, $totalAmount - $interestPaid), 2); ?></div>
                    </div>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 30px;">
                <!-- Loan Information -->
                <div>
                    <h4 style="margin: 0 0 20px 0; color: #495057; border-bottom: 1px solid #dee2e6; padding-bottom: 10px;">
                        <i class="fas fa-file-invoice"></i> Loan Information
                    </h4>
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr style="border-bottom: 1px solid #e9ecef;">
                            <td style="padding: 12px 0; color: #6c757d; font-weight: 600; width: 50%;">Loan No:</td>
                            <td style="padding: 12px 0; text-align: right;"><strong><?php echo htmlspecialchars($loan['loan_no']); ?></strong></td>
                        </tr>
                        <tr style="border-bottom: 1px solid #e9ecef;">
                            <td style="padding: 12px 0; color: #6c757d; font-weight: 600;">Loan Date:</td>
                            <td style="padding: 12px 0; text-align: right;"><?php echo formatDate($loan['loan_date']); ?></td>
                        </tr>
                        <tr style="border-bottom: 1px solid #e9ecef;">
                            <td style="padding: 12px 0; color: #6c757d; font-weight: 600;">Principal Amount:</td>
                            <td style="padding: 12px 0; text-align: right;"><strong>₹<?php echo number_format($principal, 2); ?></strong></td>
                        </tr>
                        <tr style="border-bottom: 1px solid #e9ecef;">
                            <td style="padding: 12px 0; color: #6c757d; font-weight: 600;">Interest Rate:</td>
                            <td style="padding: 12px 0; text-align: right;"><?php echo number_format($interestRate, 2); ?>%</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #e9ecef;">
                            <td style="padding: 12px 0; color: #6c757d; font-weight: 600;">Loan Days:</td>
                            <td style="padding: 12px 0; text-align: right;"><?php echo $loanDays ?: 'N/A'; ?></td>
                        </tr>
                        <tr style="border-bottom: 1px solid #e9ecef;">
                            <td style="padding: 12px 0; color: #6c757d; font-weight: 600;">Total Weight:</td>
                            <td style="padding: 12px 0; text-align: right;"><?php echo $loan['total_weight'] ? number_format($loan['total_weight'], 3) . ' g' : 'N/A'; ?></td>
                        </tr>
                        <tr style="border-bottom: 1px solid #e9ecef;">
                            <td style="padding: 12px 0; color: #6c757d; font-weight: 600;">Net Weight:</td>
                            <td style="padding: 12px 0; text-align: right;"><?php echo $loan['net_weight'] ? number_format($loan['net_weight'], 3) . ' g' : 'N/A'; ?></td>
                        </tr>
                        <tr style="border-bottom: 1px solid #e9ecef;">
                            <td style="padding: 12px 0; color: #6c757d; font-weight: 600;">Status:</td>
                            <td style="padding: 12px 0; text-align: right;">
                                <span style="padding: 6px 14px; border-radius: 12px; font-size: 12px; font-weight: 600; 
                                    background: <?php echo $loan['status'] === 'active' ? '#d1e7dd' : '#f8d7da'; ?>; 
                                    color: <?php echo $loan['status'] === 'active' ? '#0f5132' : '#842029'; ?>;">
                                    <?php echo ucfirst($loan['status']); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 12px 0; color: #6c757d; font-weight: 600;">Created At:</td>
                            <td style="padding: 12px 0; text-align: right;"><?php echo formatDate($loan['created_at']); ?></td>
                        </tr>
                    </table>
                </div>
                
                <!-- Customer Information -->
                <div>
                    <h4 style="margin: 0 0 20px 0; color: #495057; border-bottom: 1px solid #dee2e6; padding-bottom: 10px;">
                        <i class="fas fa-user"></i> Customer Information
                    </h4>
                    <?php if ($customer): ?>
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr style="border-bottom: 1px solid #e9ecef;">
                                <td style="padding: 12px 0; color: #6c757d; font-weight: 600; width: 50%;">Customer No:</td>
                                <td style="padding: 12px 0; text-align: right;"><strong><?php echo htmlspecialchars($customer['customer_no'] ?? 'N/A'); ?></strong></td>
                            </tr>
                            <tr style="border-bottom: 1px solid #e9ecef;">
                                <td style="padding: 12px 0; color: #6c757d; font-weight: 600;">Name:</td>
                                <td style="padding: 12px 0; text-align: right;"><?php echo htmlspecialchars($customer['name'] ?? 'N/A'); ?></td>
                            </tr>
                            <tr style="border-bottom: 1px solid #e9ecef;">
                                <td style="padding: 12px 0; color: #6c757d; font-weight: 600;">Mobile:</td>
                                <td style="padding: 12px 0; text-align: right;"><?php echo htmlspecialchars($customer['mobile'] ?? 'N/A'); ?></td>
                            </tr>
                            <tr style="border-bottom: 1px solid #e9ecef;">
                                <td style="padding: 12px 0; color: #6c757d; font-weight: 600;">Address:</td>
                                <td style="padding: 12px 0; text-align: right;"><?php echo htmlspecialchars($customer['address'] ?? 'N/A'); ?></td>
                            </tr>
                            <?php if (!empty($customer['place'])): ?>
                            <tr style="border-bottom: 1px solid #e9ecef;">
                                <td style="padding: 12px 0; color: #6c757d; font-weight: 600;">Place:</td>
                                <td style="padding: 12px 0; text-align: right;"><?php echo htmlspecialchars($customer['place']); ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if (!empty($customer['pincode'])): ?>
                            <tr style="border-bottom: 1px solid #e9ecef;">
                                <td style="padding: 12px 0; color: #6c757d; font-weight: 600;">Pincode:</td>
                                <td style="padding: 12px 0; text-align: right;"><?php echo htmlspecialchars($customer['pincode']); ?></td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    <?php else: ?>
                        <p style="color: #999;">Customer information not available</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!empty($loan['pledge_items'])): ?>
                <div style="margin-top: 30px;">
                    <h4 style="margin: 0 0 15px 0; color: #495057; border-bottom: 1px solid #dee2e6; padding-bottom: 10px;">
                        <i class="fas fa-gem"></i> Pledge Items
                    </h4>
                    <p style="background: #f8f9fa; padding: 15px; border-radius: 6px; margin: 0;"><?php echo nl2br(htmlspecialchars($loan['pledge_items'])); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($loan['ornament_file'])): ?>
                <div style="margin-top: 30px;">
                    <h4 style="margin: 0 0 15px 0; color: #495057; border-bottom: 1px solid #dee2e6; padding-bottom: 10px;">
                        <i class="fas fa-image"></i> Ornament File
                    </h4>
                    <p style="background: #f8f9fa; padding: 15px; border-radius: 6px; margin: 0;">
                        <a href="../<?php echo htmlspecialchars($loan['ornament_file']); ?>" target="_blank" style="color: #0d6efd; text-decoration: none;">
                            <i class="fas fa-file"></i> <?php echo basename($loan['ornament_file']); ?>
                        </a>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

