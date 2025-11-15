<?php
$basePath = dirname(__DIR__);
require_once $basePath . '/config/database.php';

try {
    $pdo = getDBConnection();
    
    // Get summary statistics
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM loans WHERE status = 'closed'");
    $totalClosedLoans = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(lc.total_interest_paid), 0) as total 
        FROM loan_closings lc
    ");
    $totalInterestPaid = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(l.principal_amount), 0) as total 
        FROM loans l 
        WHERE l.status = 'closed'
    ");
    $totalPrincipalRecovered = $stmt->fetch()['total'];
    
    // Get closed loans this month
    $stmt = $pdo->query("
        SELECT COUNT(*) as total 
        FROM loan_closings lc
        WHERE MONTH(lc.closing_date) = MONTH(CURDATE()) 
        AND YEAR(lc.closing_date) = YEAR(CURDATE())
    ");
    $monthlyClosed = $stmt->fetch()['total'];
    
    $page = isset($_GET['p']) ? (int)$_GET['p'] : (isset($_GET['page']) ? (int)$_GET['page'] : 1);
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $where = "WHERE l.status = 'closed'";
    $params = [];
    if (!empty($search)) {
        $where .= " AND (c.name LIKE ? OR c.mobile LIKE ? OR l.loan_no LIKE ?)";
        $term = "%$search%";
        $params = [$term, $term, $term];
    }
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM loans l JOIN customers c ON c.id=l.customer_id $where");
    $stmt->execute($params);
    $total = (int)$stmt->fetch()['total'];
    $totalPages = max(1, ceil($total / $limit));
    
    $limitNum = (int)$limit;
    $offsetNum = (int)$offset;
    $sql = "SELECT 
            l.id, l.loan_no, l.loan_date, l.principal_amount, l.interest_rate,
            l.total_weight, l.net_weight, l.pledge_items,
            c.name AS customer_name, c.mobile, c.customer_no,
            lc.closing_date, lc.total_interest_paid,
            COALESCE(i.total_interest_paid,0) AS interest_received
        FROM loans l
        JOIN customers c ON c.id=l.customer_id
        LEFT JOIN loan_closings lc ON lc.loan_id = l.id
        LEFT JOIN (
            SELECT loan_id, SUM(interest_amount) AS total_interest_paid
            FROM interest
            GROUP BY loan_id
        ) i ON i.loan_id = l.id
        $where
        ORDER BY lc.closing_date DESC
        LIMIT $limitNum OFFSET $offsetNum";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
} catch (Throwable $e) {
$error = 'Database error: ' . $e->getMessage();
}
?>

<div class="dashboard-page">
    <!-- Financial Summary Cards -->
    <div class="dashboard-cards">
        <div class="dashboard-card">
            <div class="card-icon recovery">
                <i class="fas fa-gem"></i>
            </div>
            <div class="card-content">
                <div class="card-number"><?php echo number_format($totalClosedLoans ?? 0); ?></div>
                <div class="card-label">Total Recoveries</div>
            </div>
        </div>
        
        <div class="dashboard-card">
            <div class="card-icon principal">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="card-content">
                <div class="card-number">₹<?php echo number_format($totalPrincipalRecovered, 2); ?></div>
                <div class="card-label">Principal Recovered</div>
            </div>
        </div>
        
        <div class="dashboard-card">
            <div class="card-icon interest">
                <i class="fas fa-file-invoice-dollar"></i>
            </div>
            <div class="card-content">
                <div class="card-number">₹<?php echo number_format($totalInterestPaid, 2); ?></div>
                <div class="card-label">Total Interest Paid</div>
            </div>
        </div>
        
        <div class="dashboard-card">
            <div class="card-icon customer">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <div class="card-content">
                <div class="card-number"><?php echo number_format($monthlyClosed); ?></div>
                <div class="card-label">Recovered This Month</div>
            </div>
        </div>
    </div>
    
    <!-- Main Content Card -->
    <div class="content-card">
        <div class="section-header">
            <h2 class="page-title">
                <i class="fas fa-gem"></i> Jewel Recovery
            </h2>
        </div>
        
        <div class="search-section">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search by customer name, mobile, or loan number" id="jewelRecoverySearch" value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <?php if (!empty($search)): ?>
                <button class="clear-btn" onclick="clearJewelRecoverySearch()">
                    <i class="fas fa-times"></i> Clear
                </button>
            <?php endif; ?>
        </div>
        
        <!-- Pagination Top -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <div class="pagination-info">
                Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $limit, $total); ?> of <?php echo $total; ?> recoveries
            </div>
            <div class="pagination-controls">
                <button class="pagination-btn" <?php echo $page <= 1 ? 'disabled' : ''; ?> onclick="changePage(<?php echo $page - 1; ?>)">
                    <i class="fas fa-chevron-left"></i> Previous
                </button>
                <span class="pagination-page">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                <button class="pagination-btn" <?php echo $page >= $totalPages ? 'disabled' : ''; ?> onclick="changePage(<?php echo $page + 1; ?>)">
                    Next <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Loan No</th>
                        <th>Loan Date</th>
                        <th>Customer No</th>
                        <th>Customer Name</th>
                        <th>Mobile No.</th>
                        <th>Principal</th>
                        <th>Interest Rate</th>
                        <th>Total Weight</th>
                        <th>Net Weight</th>
                        <th>Pledge Items</th>
                        <th>Interest Paid</th>
                        <th>Closing Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (isset($rows) && !empty($rows)): ?>
                        <?php foreach ($rows as $index => $row): ?>
                            <tr>
                                <td><?php echo $offset + $index + 1; ?></td>
                                <td><strong><?php echo htmlspecialchars($row['loan_no']); ?></strong></td>
                                <td><?php echo date('d-m-Y', strtotime($row['loan_date'])); ?></td>
                                <td><strong><?php echo htmlspecialchars($row['customer_no']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['mobile']); ?></td>
                                <td><strong>₹<?php echo number_format($row['principal_amount'], 2); ?></strong></td>
                                <td><?php echo $row['interest_rate']; ?>%</td>
                                <td>
                                    <?php if (!empty($row['total_weight'])): ?>
                                        <?php echo number_format($row['total_weight'], 2); ?>g
                                    <?php else: ?>
                                        <span style="color: #999;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($row['net_weight'])): ?>
                                        <?php echo number_format($row['net_weight'], 2); ?>g
                                    <?php else: ?>
                                        <span style="color: #999;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td title="<?php echo htmlspecialchars($row['pledge_items'] ?? ''); ?>">
                                    <?php 
                                    $items = $row['pledge_items'] ?? '';
                                    echo htmlspecialchars(strlen($items) > 25 ? substr($items, 0, 25) . '...' : $items); 
                                    ?>
                                </td>
                                <td>
                                    <?php if ($row['interest_received'] > 0): ?>
                                        <strong style="color: #38a169;">₹<?php echo number_format($row['interest_received'], 2); ?></strong>
                                    <?php else: ?>
                                        <span style="color: #999;">₹0.00</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($row['closing_date'])): ?>
                                        <?php echo date('d-m-Y', strtotime($row['closing_date'])); ?>
                                    <?php else: ?>
                                        <span style="color: #999;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="action-btn btn-view" onclick="viewRecoveryDetails(<?php echo $row['id']; ?>)" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="action-btn btn-pdf" onclick="openLoanPdf(<?php echo $row['id']; ?>)" title="PDF">
                                            <i class="fas fa-file-pdf"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="14" style="text-align: center; padding: 40px;">
                                <i class="fas fa-inbox" style="font-size: 48px; color: #ddd; margin-bottom: 10px;"></i>
                                <p style="color: #999;">No recovery records found</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination Bottom -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <div class="pagination-info">
                Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $limit, $total); ?> of <?php echo $total; ?> recoveries
            </div>
            <div class="pagination-controls">
                <button class="pagination-btn" <?php echo $page <= 1 ? 'disabled' : ''; ?> onclick="changePage(<?php echo $page - 1; ?>)">
                    <i class="fas fa-chevron-left"></i> Previous
                </button>
                <span class="pagination-page">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                <button class="pagination-btn" <?php echo $page >= $totalPages ? 'disabled' : ''; ?> onclick="changePage(<?php echo $page + 1; ?>)">
                    Next <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Initialize jewel recovery page
(function() {
    function initJewelRecoveryPage() {
        const searchInput = document.getElementById('jewelRecoverySearch');
        if (searchInput && !searchInput.dataset.listenerAttached) {
            searchInput.dataset.listenerAttached = 'true';
            searchInput.addEventListener('input', function() {
                clearTimeout(this.searchTimeout);
                this.searchTimeout = setTimeout(() => {
                    const search = this.value;
                    const url = new URL(window.location);
                    const currentPage = url.searchParams.get('page');
                    
                    if (currentPage) {
                        url.searchParams.set('page', currentPage);
                    }
                    
                    if (search) {
                        url.searchParams.set('search', search);
                    } else {
                        url.searchParams.delete('search');
                    }
                    
                    url.searchParams.delete('p');
                    
                    if (typeof window.loadPage === 'function' && currentPage) {
                        window.history.pushState({ page: currentPage }, '', url.toString());
                        window.loadPage(currentPage, false);
                    } else {
                        window.location.href = url.toString();
                    }
                }, 500);
            });
        }
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initJewelRecoveryPage);
    } else {
        initJewelRecoveryPage();
    }
    
    window.initJewelRecoveryPage = initJewelRecoveryPage;
})();

function clearJewelRecoverySearch() {
    const searchInput = document.getElementById('jewelRecoverySearch');
    if (searchInput) {
        searchInput.value = '';
        const url = new URL(window.location);
        const currentPage = url.searchParams.get('page');
        
        if (currentPage) {
            url.searchParams.set('page', currentPage);
        }
        url.searchParams.delete('search');
        url.searchParams.delete('p');
        
        if (typeof window.loadPage === 'function' && currentPage) {
            window.history.pushState({ page: currentPage }, '', url.toString());
            window.loadPage(currentPage, false);
        } else {
            window.location.href = url.toString();
        }
    }
}

function viewRecoveryDetails(loanId) {
    console.log('View recovery details:', loanId);
    // You can implement a recovery details view here
}

function openLoanPdf(loanId) {
    if (!loanId) return;
    const url = apiUrl(`api/loan-pdf.php?loan_id=${loanId}`);
    window.open(url, '_blank');
}

function changePage(page) {
    const url = new URL(window.location);
    url.searchParams.set('p', page);
    url.searchParams.delete('page');
    
    if (typeof window.loadPage === 'function') {
        const currentPage = url.searchParams.get('page') || 'jewel-recovery';
        window.history.pushState({ page: currentPage }, '', url.toString());
        window.loadPage('jewel-recovery', false);
    } else {
        window.location.href = url.toString();
    }
}
</script>

