<?php
// Define the base path
$basePath = dirname(__DIR__);
require_once $basePath . '/config/database.php';

$customerId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($customerId <= 0) {
    header('Location: ../dashboard.php?page=customers');
    exit();
}

try {
    $pdo = getDBConnection();
    
    // Fetch customer details
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$customerId]);
    $customer = $stmt->fetch();
    
    if (!$customer) {
        header('Location: ../dashboard.php?page=customers');
        exit();
    }
    
    // Get customer folder name
    $customerFolderName = '';
    $photoPath = '';
    if (!empty($customer['customer_photo'])) {
        $pathParts = explode('/', $customer['customer_photo']);
        if (count($pathParts) >= 2) {
            $customerFolderName = $pathParts[1];
            $photoPath = $customer['customer_photo'];
        }
    } else {
        $customerFolderName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $customer['name']);
        $customerFolderName = str_replace(' ', '_', $customerFolderName);
        $customerFolderName = strtolower($customerFolderName);
    }
    
    // Get all loans for this customer
    $stmt = $pdo->prepare("
        SELECT l.*, 
               COALESCE(SUM(i.interest_amount), 0) as interest_paid
        FROM loans l
        LEFT JOIN interest i ON l.id = i.loan_id
        WHERE l.customer_id = ?
        GROUP BY l.id
        ORDER BY l.loan_date DESC
    ");
    $stmt->execute([$customerId]);
    $loans = $stmt->fetchAll();
    
} catch (PDOException $e) {
    header('Location: ../dashboard.php?page=customers');
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
    <title>View Customer - <?php echo htmlspecialchars($customer['name']); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-page">
        <div class="content-card">
            <div class="section-header">
                <h2 class="page-title">
                    <i class="fas fa-user"></i> Customer Details
                </h2>
                <button class="btn-secondary" onclick="window.location.href='../dashboard.php?page=customers'">
                    <i class="fas fa-arrow-left"></i> Back
                </button>
            </div>
            
            <div style="display: grid; grid-template-columns: 250px 1fr; gap: 30px; margin-top: 20px;">
                <!-- Customer Photo -->
                <div>
                    <?php if (!empty($photoPath) && file_exists($basePath . '/' . $photoPath)): ?>
                        <img src="../<?php echo htmlspecialchars($photoPath); ?>" 
                             alt="<?php echo htmlspecialchars($customer['name']); ?>" 
                             style="width: 100%; max-width: 250px; height: auto; border-radius: 8px; border: 2px solid #ddd;">
                    <?php else: ?>
                        <div style="width: 100%; max-width: 250px; aspect-ratio: 1; border-radius: 8px; background: #e2e8f0; display: flex; align-items: center; justify-content: center; border: 2px solid #ddd;">
                            <i class="fas fa-user" style="color: #999; font-size: 80px;"></i>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Customer Information -->
                <div>
                    <h3 style="margin-top: 0; color: #2d3748;"><?php echo htmlspecialchars($customer['name']); ?></h3>
                    <table class="data-table" style="width: 100%;">
                        <tr>
                            <td style="width: 150px; font-weight: 600;">Customer No:</td>
                            <td><?php echo htmlspecialchars($customer['customer_no']); ?></td>
                        </tr>
                        <tr>
                            <td style="font-weight: 600;">Mobile:</td>
                            <td><?php echo htmlspecialchars($customer['mobile']); ?></td>
                        </tr>
                        <?php if (!empty($customer['additional_number'])): ?>
                        <tr>
                            <td style="font-weight: 600;">Additional Number:</td>
                            <td><?php echo htmlspecialchars($customer['additional_number']); ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td style="font-weight: 600;">Address:</td>
                            <td><?php echo htmlspecialchars($customer['address'] ?? ''); ?></td>
                        </tr>
                        <?php if (!empty($customer['place'])): ?>
                        <tr>
                            <td style="font-weight: 600;">Place:</td>
                            <td><?php echo htmlspecialchars($customer['place']); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if (!empty($customer['pincode'])): ?>
                        <tr>
                            <td style="font-weight: 600;">Pincode:</td>
                            <td><?php echo htmlspecialchars($customer['pincode']); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if (!empty($customer['reference'])): ?>
                        <tr>
                            <td style="font-weight: 600;">Reference:</td>
                            <td><?php echo htmlspecialchars($customer['reference']); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if (!empty($customer['proof_type'])): ?>
                        <tr>
                            <td style="font-weight: 600;">Proof Type:</td>
                            <td><?php echo htmlspecialchars($customer['proof_type']); ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td style="font-weight: 600;">Created:</td>
                            <td><?php echo date('d-m-Y', strtotime($customer['created_at'])); ?></td>
                        </tr>
                    </table>
                    
                    <div style="margin-top: 20px;">
                        <button class="btn-primary" onclick="window.location.href='edit-customer.php?id=<?php echo $customerId; ?>'">
                            <i class="fas fa-edit"></i> Edit Customer
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Customer Loans -->
            <?php if (!empty($loans)): ?>
            <div style="margin-top: 40px;">
                <h3 style="color: #2d3748; margin-bottom: 20px;">Customer Loans (<?php echo count($loans); ?>)</h3>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Loan No</th>
                                <th>Loan Date</th>
                                <th>Principal</th>
                                <th>Interest Rate</th>
                                <th>Interest Paid</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($loans as $loan): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($loan['loan_no']); ?></strong></td>
                                <td><?php echo date('d-m-Y', strtotime($loan['loan_date'])); ?></td>
                                <td><strong>₹<?php echo number_format($loan['principal_amount'], 2); ?></strong></td>
                                <td><?php echo $loan['interest_rate']; ?>%</td>
                                <td>₹<?php echo number_format($loan['interest_paid'], 2); ?></td>
                                <td>
                                    <?php if ($loan['status'] === 'active'): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Closed</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="action-btn btn-view" onclick="window.location.href='view-loan.php?id=<?php echo $loan['id']; ?>'" title="View Loan">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

