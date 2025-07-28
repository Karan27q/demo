<?php
// Define the base path
$basePath = dirname(__DIR__);
require_once $basePath . '/config/database.php';

try {
    $pdo = getDBConnection();
    
    // Get dashboard statistics
    $stmt = $pdo->query("SELECT COUNT(*) as customer_count FROM customers");
    $customerCount = $stmt->fetch()['customer_count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as loan_count FROM loans WHERE status = 'active'");
    $loanCount = $stmt->fetch()['loan_count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as recovery_count FROM loans WHERE status = 'closed'");
    $recoveryCount = $stmt->fetch()['recovery_count'];
    
    // Get recent loan details
    $stmt = $pdo->query("
        SELECT l.*, c.name as customer_name, c.mobile 
        FROM loans l 
        JOIN customers c ON l.customer_id = c.id 
        ORDER BY l.loan_date DESC 
        LIMIT 10
    ");
    $loans = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<div class="content-card">
    <!-- Dashboard Summary Cards -->
    <div class="dashboard-cards">
        <div class="dashboard-card">
            <div class="card-icon customer">
                <i class="fas fa-users"></i>
            </div>
            <div class="card-number"><?php echo $customerCount ?? 0; ?></div>
            <div class="card-label">Customer</div>
        </div>
        
        <div class="dashboard-card">
            <div class="card-icon loan">
                <i class="fas fa-coins"></i>
            </div>
            <div class="card-number"><?php echo $loanCount ?? 0; ?></div>
            <div class="card-label">Jewelry Pawn</div>
        </div>
        
        <div class="dashboard-card">
            <div class="card-icon recovery">
                <i class="fas fa-sync-alt"></i>
            </div>
            <div class="card-number"><?php echo $recoveryCount ?? 0; ?></div>
            <div class="card-label">Jewelry Recovery</div>
        </div>
    </div>
    
    <!-- Jewelry Pawn Details Section -->
    <div class="section-title">
        <h2>Jewelry Pawn Details</h2>
    </div>
    
    <!-- Search Section -->
    <div class="search-section">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Mobile number, pawn number or" id="searchInput">
        </div>
        <div class="search-box">
            <input type="text" placeholder="Place" id="placeInput">
        </div>
        <button class="clear-btn" onclick="clearSearch()">Clear</button>
    </div>
    
    <!-- Loan Details Table -->
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Loan Number</th>
                    <th>Customer Number</th>
                    <th>Customer Name</th>
                    <th>Location</th>
                    <th>Mobile Number</th>
                    <th>Principal Amount</th>
                    <th>Interest Rate</th>
                    <th>Pawned Items</th>
                    <th>Jewelry Weight</th>
                    <th>Net Weight</th>
                    <th>Jewelry Value (Pawned)</th>
                    <th>Interest Outstanding</th>
                    <th>Interest Paid</th>
                    <th>Total Appraisal</th>
                </tr>
            </thead>
            <tbody>
                <?php if (isset($loans) && !empty($loans)): ?>
                    <?php foreach ($loans as $loan): ?>
                        <tr>
                            <td><?php echo date('d-m-Y', strtotime($loan['loan_date'])); ?></td>
                            <td><?php echo htmlspecialchars($loan['loan_no']); ?></td>
                            <td><?php echo htmlspecialchars($loan['customer_id']); ?></td>
                            <td><?php echo htmlspecialchars($loan['customer_name']); ?></td>
                            <td>VNR</td>
                            <td><?php echo htmlspecialchars($loan['mobile']); ?></td>
                            <td>₹<?php echo number_format($loan['principal_amount']); ?></td>
                            <td><?php echo $loan['interest_rate']; ?>%</td>
                            <td><?php echo htmlspecialchars($loan['pledge_items']); ?></td>
                            <td><?php echo number_format($loan['total_weight'], 2); ?></td>
                            <td><?php echo number_format($loan['net_weight'], 2); ?></td>
                            <td>₹<?php echo number_format($loan['principal_amount'] / 15); ?></td>
                            <td>
                                <span class="status-badge status-active">0 days</span>
                                <br>
                                <span class="status-badge status-active">₹0</span>
                            </td>
                            <td>15 days<br>₹<?php echo number_format($loan['principal_amount'] * $loan['interest_rate'] / 100); ?></td>
                            <td>₹<?php echo number_format($loan['principal_amount']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="15" style="text-align: center;">No loan records found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function clearSearch() {
    document.getElementById('searchInput').value = '';
    document.getElementById('placeInput').value = '';
    // Add functionality to reload table data
}
</script> 