<?php
$basePath = dirname(__DIR__);
require_once $basePath . '/config/database.php';

try {
    $pdo = getDBConnection();
    
    // Get summary statistics
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM loans WHERE status = 'active'");
    $activeLoansCount = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COALESCE(SUM(principal_amount), 0) as total FROM loans WHERE status = 'active'");
    $totalPrincipal = $stmt->fetch()['total'];
    
    // Get bank pledge loans (you can add a field to distinguish bank pledges)
    // For now, using all active loans as placeholder
    $page = isset($_GET['p']) ? (int)$_GET['p'] : (isset($_GET['page']) ? (int)$_GET['page'] : 1);
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $whereClause = "WHERE l.status = 'active'";
    $params = [];
    
    if (!empty($search)) {
        $whereClause .= " AND (l.loan_no LIKE ? OR c.name LIKE ? OR c.mobile LIKE ?)";
        $searchTerm = "%$search%";
        $params = [$searchTerm, $searchTerm, $searchTerm];
    }
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM loans l
        INNER JOIN customers c ON l.customer_id = c.id
        $whereClause
    ");
    $stmt->execute($params);
    $totalRecords = $stmt->fetch()['total'];
    $totalPages = ceil($totalRecords / $limit);
    
    $stmt = $pdo->prepare("
        SELECT 
            l.id,
            l.loan_no,
            l.loan_date,
            l.principal_amount,
            l.interest_rate,
            l.total_weight,
            l.net_weight,
            l.pledge_items,
            c.name as customer_name,
            c.mobile,
            c.customer_no,
            (SELECT COALESCE(SUM(interest_amount), 0) 
             FROM interest 
             WHERE loan_id = l.id) as interest_paid
        FROM loans l
        INNER JOIN customers c ON l.customer_id = c.id
        $whereClause
        ORDER BY l.loan_date DESC
        LIMIT ? OFFSET ?
    ");
    // Bind limit and offset as integers
    $paramIndex = 1;
    foreach ($params as $value) {
        $stmt->bindValue($paramIndex++, $value);
    }
    $stmt->bindValue($paramIndex++, (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue($paramIndex++, (int)$offset, PDO::PARAM_INT);
    $stmt->execute();
    $loans = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    $loans = [];
    $activeLoansCount = 0;
    $totalPrincipal = 0;
    $totalRecords = 0;
    $totalPages = 0;
}
?>

<div class="dashboard-page">
    <!-- Summary Cards -->
    <div class="dashboard-cards">
        <div class="dashboard-card">
            <div class="card-icon loan">
                <i class="fas fa-university"></i>
            </div>
            <div class="card-content">
                <div class="card-number"><?php echo number_format($activeLoansCount); ?></div>
                <div class="card-label">Active Bank Pledges</div>
            </div>
        </div>
        
        <div class="dashboard-card">
            <div class="card-icon principal">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="card-content">
                <div class="card-number">₹<?php echo number_format($totalPrincipal, 2); ?></div>
                <div class="card-label">Total Principal</div>
            </div>
        </div>
    </div>
    
    <!-- Main Content Card -->
    <div class="content-card">
        <div class="section-header">
            <h2 class="page-title">
                <i class="fas fa-university"></i> Bank Pledge Management
            </h2>
            <div class="section-actions">
                <button class="btn-primary" onclick="showAddBankPledgeModal()">
                    <i class="fas fa-plus"></i> Add Bank Pledge
                </button>
            </div>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="error-message" style="background: #fee; color: #c33; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <div class="search-section">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search by loan number, customer name, or mobile" id="bankPledgeSearch" value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <?php if (!empty($search)): ?>
                <button class="clear-btn" onclick="clearBankPledgeSearch()">
                    <i class="fas fa-times"></i> Clear
                </button>
            <?php endif; ?>
        </div>
        
        <!-- Pagination Top -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <div class="pagination-info">
                Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $limit, $totalRecords); ?> of <?php echo $totalRecords; ?> pledges
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
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($loans)): ?>
                        <?php foreach ($loans as $index => $loan): ?>
                            <tr>
                                <td><?php echo $offset + $index + 1; ?></td>
                                <td><strong><?php echo htmlspecialchars($loan['loan_no']); ?></strong></td>
                                <td><?php echo date('d-m-Y', strtotime($loan['loan_date'])); ?></td>
                                <td><strong><?php echo htmlspecialchars($loan['customer_no']); ?></strong></td>
                                <td><?php echo htmlspecialchars($loan['customer_name']); ?></td>
                                <td><?php echo htmlspecialchars($loan['mobile']); ?></td>
                                <td><strong>₹<?php echo number_format($loan['principal_amount'], 2); ?></strong></td>
                                <td><?php echo $loan['interest_rate']; ?>%</td>
                                <td>
                                    <?php if (!empty($loan['total_weight'])): ?>
                                        <?php echo number_format($loan['total_weight'], 2); ?>g
                                    <?php else: ?>
                                        <span style="color: #999;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($loan['net_weight'])): ?>
                                        <?php echo number_format($loan['net_weight'], 2); ?>g
                                    <?php else: ?>
                                        <span style="color: #999;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td title="<?php echo htmlspecialchars($loan['pledge_items'] ?? ''); ?>">
                                    <?php 
                                    $items = $loan['pledge_items'] ?? '';
                                    echo htmlspecialchars(strlen($items) > 25 ? substr($items, 0, 25) . '...' : $items); 
                                    ?>
                                </td>
                                <td>
                                    <?php if ($loan['interest_paid'] > 0): ?>
                                        <strong style="color: #38a169;">₹<?php echo number_format($loan['interest_paid'], 2); ?></strong>
                                    <?php else: ?>
                                        <span style="color: #999;">₹0.00</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="action-btn btn-view" onclick="viewBankPledgeDetails(<?php echo $loan['id']; ?>)" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="action-btn btn-pdf" onclick="openLoanPdf(<?php echo $loan['id']; ?>)" title="PDF">
                                            <i class="fas fa-file-pdf"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="13" style="text-align: center; padding: 40px;">
                                <i class="fas fa-inbox" style="font-size: 48px; color: #ddd; margin-bottom: 10px;"></i>
                                <p style="color: #999;">No bank pledges found</p>
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
                Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $limit, $totalRecords); ?> of <?php echo $totalRecords; ?> pledges
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
function showAddBankPledgeModal() {
    // Redirect to loans page or show similar modal
    if (typeof window.loadPage === 'function') {
        window.loadPage('loans');
    }
}

function viewBankPledgeDetails(loanId) {
    console.log('View bank pledge details:', loanId);
}

function openLoanPdf(loanId) {
    if (!loanId) return;
    const url = apiUrl(`api/loan-pdf.php?loan_id=${loanId}`);
    window.open(url, '_blank');
}

function clearBankPledgeSearch() {
    const searchInput = document.getElementById('bankPledgeSearch');
    if (searchInput) {
        searchInput.value = '';
        const url = new URL(window.location);
        url.searchParams.delete('search');
        url.searchParams.delete('p');
        window.location.href = url.toString();
    }
}

function changePage(page) {
    const url = new URL(window.location);
    url.searchParams.set('p', page);
    url.searchParams.delete('page');
    window.location.href = url.toString();
}

// Initialize search
(function() {
    const searchInput = document.getElementById('bankPledgeSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                const search = this.value;
                const url = new URL(window.location);
                if (search) {
                    url.searchParams.set('search', search);
                } else {
                    url.searchParams.delete('search');
                }
                url.searchParams.delete('p');
                window.location.href = url.toString();
            }, 500);
        });
    }
})();
</script>

