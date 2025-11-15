<?php
// Define the base path
$basePath = dirname(__DIR__);
require_once $basePath . '/config/database.php';
require_once $basePath . '/config/interest_calculator.php';

try {
    $pdo = getDBConnection();
    
    // Get dashboard statistics
    $stmt = $pdo->query("SELECT COUNT(*) as customer_count FROM customers");
    $customerCount = $stmt->fetch()['customer_count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as loan_count FROM loans WHERE status = 'active'");
    $loanCount = $stmt->fetch()['loan_count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as recovery_count FROM loans WHERE status = 'closed'");
    $recoveryCount = $stmt->fetch()['recovery_count'];
    
    // Total principal (active loans)
    $stmt = $pdo->query("SELECT COALESCE(SUM(principal_amount), 0) as total FROM loans WHERE status = 'active'");
    $totalPrincipal = $stmt->fetch()['total'];
    
    // Total interest collected
    $stmt = $pdo->query("SELECT COALESCE(SUM(interest_amount), 0) as total FROM interest");
    $totalInterestCollected = $stmt->fetch()['total'];
    
    // Calculate outstanding interest
    $stmt = $pdo->query("
        SELECT 
            l.id,
            l.principal_amount,
            l.interest_rate,
            l.loan_date,
            COALESCE(SUM(i.interest_amount), 0) as interest_paid
        FROM loans l
        LEFT JOIN interest i ON l.id = i.loan_id
        WHERE l.status = 'active'
        GROUP BY l.id
    ");
    $activeLoans = $stmt->fetchAll();
    
    $totalOutstanding = 0;
    foreach ($activeLoans as $loan) {
        $expectedInterest = calculateExpectedInterestByCalendarMonths(
            $loan['principal_amount'],
            $loan['interest_rate'],
            $loan['loan_date']
        );
        $outstanding = max(0, $expectedInterest - $loan['interest_paid']);
        $totalOutstanding += $outstanding;
    }
    
    // Get recent loan details with interest calculations
    // Using DISTINCT to ensure no duplicate loans appear
    $stmt = $pdo->query("
        SELECT DISTINCT
            l.id,
            l.loan_no,
            l.customer_id,
            l.loan_date,
            l.principal_amount,
            l.interest_rate,
            l.total_weight,
            l.net_weight,
            l.pledge_items,
            l.status,
            l.created_at,
            c.name as customer_name, 
            c.mobile,
            c.customer_no,
            (SELECT COALESCE(SUM(interest_amount), 0) 
             FROM interest 
             WHERE loan_id = l.id) as interest_paid
        FROM loans l 
        JOIN customers c ON l.customer_id = c.id 
        WHERE l.status = 'active' 
        ORDER BY l.loan_date DESC, l.created_at DESC
        LIMIT 20
    ");
    $loans = $stmt->fetchAll();
    
    // Calculate days and outstanding for each loan
    foreach ($loans as &$loan) {
        $loanDate = new DateTime($loan['loan_date']);
        $today = new DateTime();
        $daysDiff = $today->diff($loanDate)->days;
        $loan['days_passed'] = $daysDiff;
        $expectedInterest = calculateExpectedInterestByCalendarMonths(
            $loan['principal_amount'],
            $loan['interest_rate'],
            $loan['loan_date']
        );
        $loan['interest_outstanding'] = max(0, $expectedInterest - $loan['interest_paid']);
    }
    
    // Recent transactions
    $stmt = $pdo->query("
        SELECT * FROM transactions 
        ORDER BY date DESC, created_at DESC 
        LIMIT 5
    ");
    $recentTransactions = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<div class="dashboard-page">
    <!-- Financial Summary Cards -->
    <div class="dashboard-cards">
        <div class="dashboard-card">
            <div class="card-icon customer">
                <i class="fas fa-users"></i>
            </div>
            <div class="card-content">
                <div class="card-number"><?php echo number_format($customerCount ?? 0); ?></div>
                <div class="card-label">Total Customers</div>
            </div>
        </div>
        
        <div class="dashboard-card">
            <div class="card-icon loan">
                <i class="fas fa-coins"></i>
            </div>
            <div class="card-content">
                <div class="card-number"><?php echo number_format($loanCount ?? 0); ?></div>
                <div class="card-label">Active Loans</div>
            </div>
        </div>
        
        <div class="dashboard-card">
            <div class="card-icon recovery">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="card-content">
                <div class="card-number"><?php echo number_format($recoveryCount ?? 0); ?></div>
                <div class="card-label">Closed Loans</div>
            </div>
        </div>
        
        <div class="dashboard-card">
            <div class="card-icon principal">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="card-content">
                <div class="card-number">₹<?php echo number_format($totalPrincipal ?? 0, 2); ?></div>
                <div class="card-label">Total Principal</div>
            </div>
        </div>
        
        <div class="dashboard-card">
            <div class="card-icon interest">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="card-content">
                <div class="card-number">₹<?php echo number_format($totalInterestCollected ?? 0, 2); ?></div>
                <div class="card-label">Interest Collected</div>
            </div>
        </div>
        
        <div class="dashboard-card">
            <div class="card-icon outstanding">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="card-content">
                <div class="card-number">₹<?php echo number_format($totalOutstanding ?? 0, 2); ?></div>
                <div class="card-label">Outstanding Interest</div>
            </div>
        </div>
    </div>
    
    <!-- Charts Section -->
    <div class="dashboard-row">
        <div class="dashboard-chart">
            <div class="content-card">
                <div class="section-header">
                    <h3 class="page-title" style="margin: 0; font-size: 18px;">
                        <i class="fas fa-chart-line"></i> Loan Trends (Last 30 Days)
                    </h3>
                </div>
                <canvas id="loanTrendsChart"></canvas>
            </div>
        </div>
        
        <div class="dashboard-summary">
            <div class="content-card">
                <div class="section-header">
                    <h3 class="page-title" style="margin: 0; font-size: 18px;">
                        <i class="fas fa-exchange-alt"></i> Recent Transactions
                    </h3>
                </div>
                <div class="recent-transactions">
                    <?php if (!empty($recentTransactions)): ?>
                        <?php foreach ($recentTransactions as $transaction): ?>
                            <div class="transaction-item">
                                <div class="transaction-icon <?php echo $transaction['transaction_type']; ?>">
                                    <i class="fas fa-<?php echo $transaction['transaction_type'] === 'credit' ? 'arrow-down' : 'arrow-up'; ?>"></i>
                                </div>
                                <div class="transaction-details">
                                    <div class="transaction-name"><?php echo htmlspecialchars($transaction['transaction_name']); ?></div>
                                    <div class="transaction-date"><?php echo date('d M Y', strtotime($transaction['date'])); ?></div>
                                </div>
                                <div class="transaction-amount <?php echo $transaction['transaction_type']; ?>">
                                    <?php echo $transaction['transaction_type'] === 'credit' ? '+' : '-'; ?>₹<?php echo number_format($transaction['amount'], 2); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="text-align: center; color: #999; padding: 20px;">No recent transactions</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Jewelry Pawn Details Section -->
    <div class="content-card">
        <div class="section-header">
            <h2 class="page-title">
                <i class="fas fa-coins"></i> Jewelry Pawn Details
            </h2>
            <div class="section-actions">
                <button class="btn-primary" onclick="window.loadPage('loans')">
                    <i class="fas fa-plus"></i> Add New Loan
                </button>
            </div>
        </div>
        
        <!-- Search Section -->
        <div class="search-section">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search by mobile, loan number, or customer name" id="dashboardSearchInput">
            </div>
            <button class="clear-btn" onclick="clearDashboardSearch()">
                <i class="fas fa-times"></i> Clear
            </button>
        </div>
        
        <!-- Loan Details Table -->
        <div class="table-container">
            <table class="data-table" id="dashboardLoansTable">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Loan No</th>
                        <th>Customer No</th>
                        <th>Customer Name</th>
                        <th>Mobile</th>
                        <th>Principal</th>
                        <th>Interest Rate</th>
                        <th>Items</th>
                        <th>Days</th>
                        <th>Interest Paid</th>
                        <th>Outstanding</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (isset($loans) && !empty($loans)): ?>
                        <?php foreach ($loans as $loan): ?>
                            <tr>
                                <td><?php echo date('d-m-Y', strtotime($loan['loan_date'])); ?></td>
                                <td><?php echo htmlspecialchars($loan['loan_no']); ?></td>
                                <td><?php echo htmlspecialchars($loan['customer_no']); ?></td>
                                <td><?php echo htmlspecialchars($loan['customer_name']); ?></td>
                                <td><?php echo htmlspecialchars($loan['mobile']); ?></td>
                                <td>₹<?php echo number_format($loan['principal_amount'], 2); ?></td>
                                <td><?php echo $loan['interest_rate']; ?>%</td>
                                <td title="<?php echo htmlspecialchars($loan['pledge_items']); ?>">
                                    <?php echo htmlspecialchars(substr($loan['pledge_items'], 0, 30)) . (strlen($loan['pledge_items']) > 30 ? '...' : ''); ?>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $loan['days_passed'] > 90 ? 'status-warning' : 'status-active'; ?>">
                                        <?php echo $loan['days_passed']; ?> days
                                    </span>
                                </td>
                                <td>₹<?php echo number_format($loan['interest_paid'], 2); ?></td>
                                <td>
                                    <span class="status-badge status-warning">
                                        ₹<?php echo number_format($loan['interest_outstanding'], 2); ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="action-btn" onclick="viewLoanDetails(<?php echo $loan['id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="12" style="text-align: center; padding: 40px;">
                                <i class="fas fa-inbox" style="font-size: 48px; color: #ddd; margin-bottom: 10px;"></i>
                                <p style="color: #999;">No active loans found</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Chart.js for visualizations -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
// Initialize dashboard - only load chart data, stats are already loaded via PHP
function initDashboard() {
    // Only load loan trends chart (this is the only data needed from API)
    // All other stats are already loaded via PHP to avoid duplicate fetching
    loadLoanTrendsChart();
    
    // Initialize search functionality
    const searchInput = document.getElementById('dashboardSearchInput');
    if (searchInput && !searchInput.dataset.listenerAttached) {
        searchInput.dataset.listenerAttached = 'true';
        searchInput.addEventListener('input', function() {
            filterDashboardTable(this.value);
        });
    }
}

// Initialize on page load only if not already initialized
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        // Check if chart canvas exists before initializing
        if (document.getElementById('loanTrendsChart')) {
            initDashboard();
        }
    });
} else {
    // Page already loaded
    if (document.getElementById('loanTrendsChart')) {
        initDashboard();
    }
}

// Expose globally for dynamic page loading
window.initDashboard = initDashboard;

function loadLoanTrendsChart() {
    // Only fetch loan trends data, not all dashboard stats (to avoid duplicate fetching)
    fetch(apiUrl('api/dashboard.php?trends_only=1'))
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data && data.data.loan_trends) {
                const trends = data.data.loan_trends;
                const labels = trends.map(t => {
                    const date = new Date(t.date);
                    return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short' });
                });
                const counts = trends.map(t => parseInt(t.count));
                const amounts = trends.map(t => parseFloat(t.total_amount));
                
                const ctx = document.getElementById('loanTrendsChart');
                if (ctx) {
                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: 'Number of Loans',
                                data: counts,
                                borderColor: 'rgb(75, 192, 192)',
                                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                                tension: 0.1,
                                yAxisID: 'y'
                            }, {
                                label: 'Loan Amount (₹)',
                                data: amounts,
                                borderColor: 'rgb(255, 99, 132)',
                                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                                tension: 0.1,
                                yAxisID: 'y1'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: true,
                            scales: {
                                y: {
                                    type: 'linear',
                                    display: true,
                                    position: 'left',
                                    title: {
                                        display: true,
                                        text: 'Number of Loans'
                                    }
                                },
                                y1: {
                                    type: 'linear',
                                    display: true,
                                    position: 'right',
                                    title: {
                                        display: true,
                                        text: 'Amount (₹)'
                                    },
                                    grid: {
                                        drawOnChartArea: false
                                    }
                                }
                            }
                        }
                    });
                }
            }
        })
        .catch(error => {
            console.error('Error loading loan trends:', error);
        });
}

function filterDashboardTable(searchTerm) {
    const table = document.getElementById('dashboardLoansTable');
    if (!table) return;
    
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    const term = searchTerm.toLowerCase();
    
    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        const text = row.textContent || row.innerText;
        row.style.display = text.toLowerCase().indexOf(term) > -1 ? '' : 'none';
    }
}

function clearDashboardSearch() {
    const searchInput = document.getElementById('dashboardSearchInput');
    if (searchInput) {
        searchInput.value = '';
        filterDashboardTable('');
    }
}

function viewLoanDetails(loanId) {
    // Navigate to loan details or show modal
    if (typeof window.loadPage === 'function') {
        // You can implement a loan details page or modal here
        window.loadPage('loans');
    }
}
</script>

<style>
.dashboard-page {
    padding: 0;
}

.dashboard-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.dashboard-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 15px;
    transition: transform 0.2s, box-shadow 0.2s;
}

.dashboard-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.card-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: white;
}

.card-icon.customer { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.card-icon.loan { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
.card-icon.recovery { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
.card-icon.principal { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
.card-icon.interest { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
.card-icon.outstanding { background: linear-gradient(135deg, #ff6a00 0%, #ee0979 100%); }

.card-content {
    flex: 1;
}

.card-number {
    font-size: 28px;
    font-weight: bold;
    color: #2d3748;
    margin-bottom: 5px;
}

.card-label {
    font-size: 14px;
    color: #718096;
}

.dashboard-row {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
    margin-bottom: 30px;
}

.dashboard-chart, .dashboard-summary {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.dashboard-chart h3, .dashboard-summary h3 {
    margin: 0 0 20px 0;
    color: #2d3748;
    font-size: 18px;
}

.recent-transactions {
    max-height: 400px;
    overflow-y: auto;
}

.transaction-item {
    display: flex;
    align-items: center;
    padding: 15px;
    border-bottom: 1px solid #e2e8f0;
    gap: 15px;
}

.transaction-item:last-child {
    border-bottom: none;
}

.transaction-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 16px;
}

.transaction-icon.credit {
    background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
}

.transaction-icon.debit {
    background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
}

.transaction-details {
    flex: 1;
}

.transaction-name {
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 5px;
}

.transaction-date {
    font-size: 12px;
    color: #718096;
}

.transaction-amount {
    font-weight: bold;
    font-size: 16px;
}

.transaction-amount.credit {
    color: #38a169;
}

.transaction-amount.debit {
    color: #e53e3e;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.section-header h2 {
    margin: 0;
    color: #2d3748;
}

.section-actions {
    display: flex;
    gap: 10px;
}

@media (max-width: 768px) {
    .dashboard-row {
        grid-template-columns: 1fr;
    }
    
    .dashboard-cards {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>
