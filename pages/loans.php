<?php
// Define the base path
$basePath = dirname(__DIR__);
require_once $basePath . '/config/database.php';
require_once $basePath . '/config/interest_calculator.php';

try {
    $pdo = getDBConnection();
    
    // Get summary statistics
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM loans WHERE status = 'active'");
    $activeLoansCount = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM loans WHERE status = 'closed'");
    $closedLoansCount = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COALESCE(SUM(principal_amount), 0) as total FROM loans WHERE status = 'active'");
    $totalPrincipal = $stmt->fetch()['total'];
    
    // Calculate outstanding interest using subquery to avoid duplicates
    $stmt = $pdo->query("
        SELECT 
            l.id,
            l.principal_amount,
            l.interest_rate,
            l.loan_date,
            (SELECT COALESCE(SUM(interest_amount), 0) 
             FROM interest 
             WHERE loan_id = l.id) as interest_paid
        FROM loans l
        WHERE l.status = 'active'
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
    
    // Get total interest collected
    $stmt = $pdo->query("SELECT COALESCE(SUM(interest_amount), 0) as total FROM interest");
    $totalInterestCollected = $stmt->fetch()['total'];
    
    // Get total weight of active loans (for jewelry pawning)
    $stmt = $pdo->query("SELECT COALESCE(SUM(total_weight), 0) as total FROM loans WHERE status = 'active'");
    $totalWeight = $stmt->fetch()['total'];
    
    // Get total net weight of active loans
    $stmt = $pdo->query("SELECT COALESCE(SUM(net_weight), 0) as total FROM loans WHERE status = 'active'");
    $totalNetWeight = $stmt->fetch()['total'];
    
    // Get loans with pagination
    $page = isset($_GET['p']) ? (int)$_GET['p'] : (isset($_GET['page']) ? (int)$_GET['page'] : 1);
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $status = isset($_GET['status']) ? $_GET['status'] : 'active';
    
    // Build WHERE clause exactly like bank-pledge.php
    $whereClause = '';
    $params = [];
    
    if ($status !== 'all') {
        $whereClause = "WHERE l.status = ?";
        $params[] = $status;
    } else {
        $whereClause = "WHERE 1=1";
    }
    
    if (!empty($search)) {
        $searchTerm = "%$search%";
        $whereClause .= " AND (l.loan_no LIKE ? OR c.name LIKE ? OR c.mobile LIKE ?)";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    }
    
    // Count query - must match deduplication logic
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT l.loan_no) as total 
        FROM loans l
        INNER JOIN customers c ON l.customer_id = c.id
        INNER JOIN (
            SELECT loan_no, MAX(id) as max_id
            FROM loans
            GROUP BY loan_no
        ) as latest ON l.loan_no = latest.loan_no AND l.id = latest.max_id
        $whereClause
    ");
    $stmt->execute($params);
    $totalRecords = $stmt->fetch()['total'];
    $totalPages = ceil($totalRecords / $limit);
    
    // Fetch query - exactly like bank-pledge.php but with deduplication in SQL
    // Use JOIN with subquery to get only the latest loan per loan_no
    $stmt = $pdo->prepare("
        SELECT 
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
            COALESCE(i.total_interest_paid, 0) as interest_paid
        FROM loans l
        INNER JOIN customers c ON l.customer_id = c.id
        INNER JOIN (
            SELECT loan_no, MAX(id) as max_id
            FROM loans
            GROUP BY loan_no
        ) as latest ON l.loan_no = latest.loan_no AND l.id = latest.max_id
        LEFT JOIN (
            SELECT loan_id, SUM(interest_amount) as total_interest_paid
            FROM interest
            GROUP BY loan_id
        ) i ON i.loan_id = l.id
        " . ($status !== 'all' ? "WHERE l.status = ?" : "WHERE 1=1") . 
        (!empty($search) ? " AND (l.loan_no LIKE ? OR c.name LIKE ? OR c.mobile LIKE ?)" : "") . "
        GROUP BY l.id
        ORDER BY l.loan_date DESC, l.id DESC
        LIMIT ? OFFSET ?
    ");
    
    // Bind parameters exactly like bank-pledge.php
    $paramIndex = 1;
    if ($status !== 'all') {
        $stmt->bindValue($paramIndex++, $status);
    }
    if (!empty($search)) {
        $searchTerm = "%$search%";
        $stmt->bindValue($paramIndex++, $searchTerm);
        $stmt->bindValue($paramIndex++, $searchTerm);
        $stmt->bindValue($paramIndex++, $searchTerm);
    }
    $stmt->bindValue($paramIndex++, (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue($paramIndex++, (int)$offset, PDO::PARAM_INT);
    $stmt->execute();
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
    unset($loan); // Break the reference with the last element
    
    // Get customers for dropdown
    $stmt = $pdo->query("SELECT id, name, customer_no FROM customers ORDER BY name");
    $customers = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    error_log("Loans page error: " . $e->getMessage());
    // Initialize empty arrays to prevent undefined variable errors
    $loans = [];
    $activeLoansCount = 0;
    $closedLoansCount = 0;
    $totalPrincipal = 0;
    $totalOutstanding = 0;
    $totalInterestCollected = 0;
    $totalWeight = 0;
    $totalNetWeight = 0;
    $totalRecords = 0;
    $totalPages = 0;
}
?>

<?php if (isset($error)): ?>
    <div class="error-message" style="background: #fee; color: #c33; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
        <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<div class="dashboard-page">
    <!-- Financial Summary Cards -->
    <div class="dashboard-cards">
        <div class="dashboard-card">
            <div class="card-icon loan">
                <i class="fas fa-coins"></i>
            </div>
            <div class="card-content">
                <div class="card-number"><?php echo number_format($activeLoansCount ?? 0); ?></div>
                <div class="card-label">Active Loans</div>
            </div>
        </div>
        
        <div class="dashboard-card">
            <div class="card-icon recovery">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="card-content">
                <div class="card-number"><?php echo number_format($closedLoansCount ?? 0); ?></div>
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
        
        <div class="dashboard-card">
            <div class="card-icon customer">
                <i class="fas fa-weight"></i>
            </div>
            <div class="card-content">
                <div class="card-number"><?php echo number_format($totalWeight ?? 0, 2); ?>g</div>
                <div class="card-label">Total Weight</div>
            </div>
        </div>
    </div>
    
    <!-- Main Content Card -->
    <div class="content-card">
        <div class="section-header">
            <h2 class="page-title">
                <i class="fas fa-gem"></i> Jewelry Pawn Details
            </h2>
            <div class="section-actions">
                <button class="btn-primary" onclick="showAddLoanModal()">
                    <i class="fas fa-plus"></i> Add New Loan
                </button>
                <button class="btn-success" onclick="showAddInterestModal()" style="margin-left: 10px;">
                    <i class="fas fa-money-bill-wave"></i> Add Interest
                </button>
            </div>
        </div>
        
        <div class="search-section">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search by loan number, customer name, or mobile" id="loanSearch" value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <select id="statusFilter" class="filter-select" onchange="filterByStatus()">
                <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active Loans</option>
                <option value="closed" <?php echo $status === 'closed' ? 'selected' : ''; ?>>Closed Loans</option>
                <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Loans</option>
            </select>
            <?php if (!empty($search)): ?>
                <button class="clear-btn" onclick="clearLoanSearch()">
                    <i class="fas fa-times"></i> Clear
                </button>
            <?php endif; ?>
        </div>
        
        <!-- Pagination Top -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <div class="pagination-info">
                Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $limit, $totalRecords); ?> of <?php echo $totalRecords; ?> loans
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
                        <th>Outstanding</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (isset($loans) && !empty($loans)): ?>
                        <?php foreach ($loans as $index => $loan): ?>
                            <tr id="loan-row-<?php echo $loan['id']; ?>" data-rand="<?php echo rand(); ?>">
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
                                    <?php 
                                    if ($loan['status'] === 'active') {
                                        $expectedInterest = calculateExpectedInterestByCalendarMonths(
                                            $loan['principal_amount'],
                                            $loan['interest_rate'],
                                            $loan['loan_date']
                                        );
                                        $outstanding = max(0, $expectedInterest - $loan['interest_paid']);
                                        if ($outstanding > 0) {
                                            echo '<strong style="color: #d69e2e;">₹' . number_format($outstanding, 2) . '</strong>';
                                        } else {
                                            echo '<span style="color: #999;">₹0.00</span>';
                                        }
                                    } else {
                                        echo '<span style="color: #999;">-</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php if ($loan['status'] === 'active'): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Closed</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="action-btn btn-view" onclick="viewLoanDetails(<?php echo $loan['id']; ?>)" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($loan['status'] === 'active'): ?>
                                            <button class="action-btn btn-edit" onclick="editLoan(<?php echo $loan['id']; ?>)" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="action-btn btn-pdf" onclick="openLoanPdf(<?php echo $loan['id']; ?>)" title="PDF">
                                                <i class="fas fa-file-pdf"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="15" style="text-align: center; padding: 40px;">
                                <i class="fas fa-inbox" style="font-size: 48px; color: #ddd; margin-bottom: 10px;"></i>
                                <p style="color: #999;">No loans found</p>
                                <?php if (isset($totalRecords) && $totalRecords == 0): ?>
                                    <p style="color: #999; font-size: 12px; margin-top: 10px;">
                                        Total loans in database: <?php echo $totalRecords; ?>
                                    </p>
                                <?php endif; ?>
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
                Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $limit, $totalRecords); ?> of <?php echo $totalRecords; ?> loans
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

<!-- Add Loan Modal -->
<div id="addLoanModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="hideModal('addLoanModal')">&times;</span>
        <h2><i class="fas fa-plus-circle"></i> Add New Loan</h2>
        
        <!-- Loan Calculation Display Box -->
        <div id="loanCalculationBox" style="background: #f8f9fa; border: 2px solid #e9ecef; border-radius: 8px; padding: 15px; margin-bottom: 20px; display: none;">
            <h3 style="margin: 0 0 12px 0; font-size: 16px; color: #495057;">
                <i class="fas fa-calculator"></i> Loan Calculation
            </h3>
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;">
                <div>
                    <strong style="color: #6c757d; font-size: 13px;">Principal Amount:</strong>
                    <div id="calcPrincipal" style="font-size: 18px; color: #212529; font-weight: 600;">₹0.00</div>
                </div>
                <div>
                    <strong style="color: #6c757d; font-size: 13px;">Interest Amount:</strong>
                    <div id="calcInterest" style="font-size: 18px; color: #0d6efd; font-weight: 600;">₹0.00</div>
                </div>
                <div style="grid-column: 1 / -1; border-top: 2px solid #dee2e6; padding-top: 12px; margin-top: 8px;">
                    <strong style="color: #6c757d; font-size: 13px;">Total Amount (Principal + Interest):</strong>
                    <div id="calcTotal" style="font-size: 22px; color: #198754; font-weight: 700;">₹0.00</div>
                </div>
            </div>
        </div>
        
        <form id="addLoanForm" onsubmit="addLoan(event)" autocomplete="off" style="display: flex; flex-direction: column;">
            <div style="flex: 1; overflow-y: auto;">
                <div class="form-row">
                    <div class="form-group">
                        <label for="loanNo">Loan Number *</label>
                        <input type="text" id="loanNo" name="loan_no" required placeholder="Enter loan number" autocomplete="off" value="">
                    </div>
                    <div class="form-group">
                        <label for="loanDate">Loan Date *</label>
                        <input type="date" id="loanDate" name="loan_date" required autocomplete="off">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="customerId">Customer *</label>
                    <select id="customerId" name="customer_id" required autocomplete="off">
                        <option value="">Select Customer</option>
                        <?php if (isset($customers)): ?>
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?php echo $customer['id']; ?>">
                                    <?php echo htmlspecialchars($customer['customer_no'] . ' - ' . $customer['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="principalAmount">Principal Amount (₹) *</label>
                        <input type="number" id="principalAmount" name="principal_amount" step="0.01" required placeholder="0.00" autocomplete="off" value="">
                    </div>
                    <div class="form-group">
                        <label for="interestRate">Interest Rate (%) *</label>
                        <input type="number" id="interestRate" name="interest_rate" step="0.01" required placeholder="0.00" autocomplete="off" value="">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="loanDays">Loan Days *</label>
                        <input type="number" id="loanDays" name="loan_days" required placeholder="Enter number of days" min="1" autocomplete="off" value="">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="totalWeight">Total Weight (g)</label>
                        <input type="number" id="totalWeight" name="total_weight" step="0.01" placeholder="0.00" autocomplete="off" value="">
                    </div>
                    <div class="form-group">
                        <label for="netWeight">Net Weight (g)</label>
                        <input type="number" id="netWeight" name="net_weight" step="0.01" placeholder="0.00" autocomplete="off" value="">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="pledgeItems">Pledge Items</label>
                    <textarea id="pledgeItems" name="pledge_items" rows="3" placeholder="e.g., RING - 1, BANGLE - 2" autocomplete="off"></textarea>
                </div>
            </div>
            
            <div class="form-actions" style="flex-shrink: 0; margin-top: auto;">
                <button type="button" onclick="hideModal('addLoanModal')" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> Add Loan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Add Interest Modal -->
<div id="addInterestModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="hideModal('addInterestModal')">&times;</span>
        <h2><i class="fas fa-plus-circle"></i> Add Interest Record</h2>
        <form id="addInterestForm" onsubmit="addInterest(event)">
            <div class="form-group">
                <label for="loanId">Loan *</label>
                <select id="loanId" name="loan_id" required onchange="updateInterestLoanDetails()">
                    <option value="">Select Loan</option>
                    <?php 
                    // Get active loans for dropdown
                    if (isset($pdo)) {
                        try {
                            $stmt = $pdo->query("
                                SELECT l.id, l.loan_no, c.name as customer_name 
                                FROM loans l 
                                INNER JOIN customers c ON l.customer_id = c.id 
                                WHERE l.status = 'active' 
                                AND l.id IN (
                                    SELECT MAX(id) 
                                    FROM loans 
                                    GROUP BY loan_no
                                )
                                ORDER BY l.loan_no DESC
                            ");
                            $activeLoansForInterest = $stmt->fetchAll();
                            foreach ($activeLoansForInterest as $loan): 
                    ?>
                        <option value="<?php echo $loan['id']; ?>">
                            <?php echo htmlspecialchars($loan['loan_no'] . ' - ' . $loan['customer_name']); ?>
                        </option>
                    <?php 
                            endforeach;
                        } catch (PDOException $e) {
                            // Error handling
                        }
                    }
                    ?>
                </select>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="interestDate">Interest Date *</label>
                    <input type="date" id="interestDate" name="interest_date" required>
                </div>
                <div class="form-group">
                    <label for="interestRate">Interest Rate (%) *</label>
                    <input type="number" id="interestRate" name="interest_rate" step="0.01" required placeholder="0.00">
                </div>
            </div>
            
            <div class="form-group">
                <label for="interestAmount">Interest Amount (₹) *</label>
                <input type="number" id="interestAmount" name="interest_amount" step="0.01" required placeholder="0.00">
            </div>
            
            <div class="form-actions">
                <button type="button" onclick="hideModal('addInterestModal')" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> Add Interest
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Initialize loans page
(function() {
    function initLoansPage() {
        const searchInput = document.getElementById('loanSearch');
        if (searchInput && !searchInput.dataset.listenerAttached) {
            searchInput.dataset.listenerAttached = 'true';
            searchInput.addEventListener('input', function() {
                clearTimeout(this.searchTimeout);
                this.searchTimeout = setTimeout(() => {
                    const search = this.value;
                    const url = new URL(window.location);
                    const currentPage = url.searchParams.get('page');
                    const status = document.getElementById('statusFilter')?.value || 'active';
                    
                    if (currentPage) {
                        url.searchParams.set('page', currentPage);
                    }
                    url.searchParams.set('status', status);
                    
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
        document.addEventListener('DOMContentLoaded', initLoansPage);
    } else {
        initLoansPage();
    }
    
    window.initLoansPage = initLoansPage;
})();

function showAddLoanModal() {
    showModal('addLoanModal');
    
    // Small delay to ensure modal is fully rendered
    setTimeout(() => {
        // Reset form completely to clear any cached data
        const form = document.getElementById('addLoanForm');
        if (form) {
            // Explicitly reset all form fields
            form.reset();
            
            // Clear all input fields explicitly to prevent browser autofill
            const loanNoField = document.getElementById('loanNo');
            if (loanNoField) {
                loanNoField.value = '';
                loanNoField.defaultValue = '';
            }
            
            const loanDateField = document.getElementById('loanDate');
            if (loanDateField) {
                loanDateField.value = new Date().toISOString().split('T')[0];
                loanDateField.defaultValue = new Date().toISOString().split('T')[0];
            }
            
            const customerSelect = document.getElementById('customerId');
            if (customerSelect) {
                customerSelect.value = '';
                customerSelect.selectedIndex = 0;
                // Remove any selected attribute
                Array.from(customerSelect.options).forEach(option => {
                    option.selected = false;
                });
                customerSelect.options[0].selected = true;
            }
            
            const principalAmountField = document.getElementById('principalAmount');
            if (principalAmountField) {
                principalAmountField.value = '';
                principalAmountField.defaultValue = '';
            }
            
            const interestRateField = document.getElementById('interestRate');
            if (interestRateField) {
                interestRateField.value = '';
                interestRateField.defaultValue = '';
            }
            
            const loanDaysField = document.getElementById('loanDays');
            if (loanDaysField) {
                loanDaysField.value = '';
                loanDaysField.defaultValue = '';
            }
            
            const totalWeightField = document.getElementById('totalWeight');
            if (totalWeightField) {
                totalWeightField.value = '';
                totalWeightField.defaultValue = '';
            }
            
            const netWeightField = document.getElementById('netWeight');
            if (netWeightField) {
                netWeightField.value = '';
                netWeightField.defaultValue = '';
            }
            
            const pledgeItemsField = document.getElementById('pledgeItems');
            if (pledgeItemsField) {
                pledgeItemsField.value = '';
                pledgeItemsField.defaultValue = '';
            }
            
            // Hide calculation box
            const calcBox = document.getElementById('loanCalculationBox');
            if (calcBox) {
                calcBox.style.display = 'none';
            }
        }
        
        // Reload customers to ensure fresh data (after clearing form)
        if (typeof loadLoanCustomers === 'function') {
            loadLoanCustomers();
        }
    }, 100);
}

function showAddInterestModal() {
    showModal('addInterestModal');
    // Set default date to today
    const interestDateField = document.getElementById('interestDate');
    if (interestDateField) {
        interestDateField.value = new Date().toISOString().split('T')[0];
    }
    // Clear form fields
    const form = document.getElementById('addInterestForm');
    if (form) {
        form.reset();
        if (interestDateField) {
            interestDateField.value = new Date().toISOString().split('T')[0];
        }
    }
}

function addInterest(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    
    fetch(apiUrl('api/interest.php'), {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            hideModal('addInterestModal');
            event.target.reset();
            showSuccessMessage('Interest added successfully!');
            
            // Reload the loans page
            const urlParams = new URLSearchParams(window.location.search);
            const currentPage = urlParams.get('page');
            if (currentPage && typeof window.loadPage === 'function') {
                setTimeout(() => {
                    window.loadPage(currentPage, false);
                }, 500);
            } else {
                setTimeout(() => location.reload(), 500);
            }
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while adding the interest record.');
    });
}

function addLoan(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    
    fetch(apiUrl('api/loans.php'), {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            hideModal('addLoanModal');
            // Reset form completely
            const form = document.getElementById('addLoanForm');
            if (form) {
                form.reset();
                
                // Explicitly clear all fields
                const loanNoField = document.getElementById('loanNo');
                if (loanNoField) loanNoField.value = '';
                
                const loanDateField = document.getElementById('loanDate');
                if (loanDateField) {
                    loanDateField.value = new Date().toISOString().split('T')[0];
                }
                
                const customerSelect = document.getElementById('customerId');
                if (customerSelect) {
                    customerSelect.value = '';
                    customerSelect.selectedIndex = 0;
                }
                
                const principalAmountField = document.getElementById('principalAmount');
                if (principalAmountField) principalAmountField.value = '';
                
                const interestRateField = document.getElementById('interestRate');
                if (interestRateField) interestRateField.value = '';
                
                const loanDaysField = document.getElementById('loanDays');
                if (loanDaysField) loanDaysField.value = '';
                
                const totalWeightField = document.getElementById('totalWeight');
                if (totalWeightField) totalWeightField.value = '';
                
                const netWeightField = document.getElementById('netWeight');
                if (netWeightField) netWeightField.value = '';
                
                const pledgeItemsField = document.getElementById('pledgeItems');
                if (pledgeItemsField) pledgeItemsField.value = '';
                
                // Hide calculation box
                const calcBox = document.getElementById('loanCalculationBox');
                if (calcBox) {
                    calcBox.style.display = 'none';
                }
            }
            showSuccessMessage('Loan added successfully!');
            
            // Reload the loans page
            const urlParams = new URLSearchParams(window.location.search);
            const currentPage = urlParams.get('page');
            if (currentPage && typeof window.loadPage === 'function') {
                setTimeout(() => {
                    window.loadPage(currentPage, false);
                }, 500);
            } else {
                setTimeout(() => location.reload(), 500);
            }
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while adding the loan.');
    });
}

function updateInterestLoanDetails() {
    const loanSelect = document.getElementById('loanId');
    const selectedLoanId = loanSelect.value;
    
    if (selectedLoanId) {
        // Fetch loan details to show interest rate if needed
        fetch(apiUrl(`api/loans.php?id=${selectedLoanId}`))
            .then(response => response.json())
            .then(data => {
                if (data.success && data.loan) {
                    // Optionally pre-fill interest rate from loan
                    const interestRateField = document.getElementById('interestRate');
                    if (interestRateField && data.loan.interest_rate) {
                        // Don't auto-fill, let user enter manually as requested
                        // interestRateField.value = data.loan.interest_rate;
                    }
                }
            })
            .catch(error => {
                console.error('Error fetching loan details:', error);
            });
    }
}

// Calculate loan total amount based on interest (for Add Loan Modal)
(function() {
    function calculateLoanTotal() {
        const principalInput = document.getElementById('principalAmount');
        const interestRateInput = document.getElementById('interestRate');
        const loanDaysInput = document.getElementById('loanDays');
        const calcBox = document.getElementById('loanCalculationBox');
        const calcPrincipal = document.getElementById('calcPrincipal');
        const calcInterest = document.getElementById('calcInterest');
        const calcTotal = document.getElementById('calcTotal');
        
        if (!principalInput || !interestRateInput || !loanDaysInput || !calcBox) {
            return;
        }
        
        const principal = parseFloat(principalInput.value) || 0;
        const interestRate = parseFloat(interestRateInput.value) || 0;
        const days = parseInt(loanDaysInput.value) || 0;
        
        // Show calculation box if any value is entered
        if (principal > 0 || interestRate > 0 || days > 0) {
            calcBox.style.display = 'block';
        } else {
            calcBox.style.display = 'none';
        }
        
        // Calculate interest: Principal × (Interest Rate / 100) × (Days / 365)
        let interestAmount = 0;
        if (principal > 0 && interestRate > 0 && days > 0) {
            interestAmount = principal * (interestRate / 100) * (days / 365);
        }
        
        const totalAmount = principal + interestAmount;
        
        // Update display
        if (calcPrincipal) {
            calcPrincipal.textContent = '₹' + principal.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }
        if (calcInterest) {
            calcInterest.textContent = '₹' + interestAmount.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }
        if (calcTotal) {
            calcTotal.textContent = '₹' + totalAmount.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }
    }
    
    function initLoanCalculation() {
        const principalInput = document.getElementById('principalAmount');
        const interestRateInput = document.getElementById('interestRate');
        const loanDaysInput = document.getElementById('loanDays');
        
        if (principalInput && interestRateInput && loanDaysInput) {
            // Remove existing listeners to avoid duplicates
            principalInput.removeEventListener('input', calculateLoanTotal);
            interestRateInput.removeEventListener('input', calculateLoanTotal);
            loanDaysInput.removeEventListener('input', calculateLoanTotal);
            
            // Add event listeners
            principalInput.addEventListener('input', calculateLoanTotal);
            interestRateInput.addEventListener('input', calculateLoanTotal);
            loanDaysInput.addEventListener('input', calculateLoanTotal);
        }
    }
    
    // Initialize when modal is opened
    const addLoanModal = document.getElementById('addLoanModal');
    if (addLoanModal) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initLoanCalculation);
        } else {
            initLoanCalculation();
        }
    }
    
    // Also initialize when modal is shown
    if (typeof showModal === 'function') {
        const originalShowModal = window.showModal;
        window.showModal = function(modalId) {
            originalShowModal(modalId);
            if (modalId === 'addLoanModal') {
                setTimeout(initLoanCalculation, 100);
            }
        };
    }
})();

function filterByStatus() {
    const status = document.getElementById('statusFilter').value;
    const search = document.getElementById('loanSearch').value;
    const url = new URL(window.location);
    const currentPage = url.searchParams.get('page');
    
    if (currentPage) {
        url.searchParams.set('page', currentPage);
    }
    url.searchParams.set('status', status);
    
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
}

function clearLoanSearch() {
    const searchInput = document.getElementById('loanSearch');
    if (searchInput) {
        searchInput.value = '';
        const status = document.getElementById('statusFilter').value;
        const url = new URL(window.location);
        const currentPage = url.searchParams.get('page');
        
        if (currentPage) {
            url.searchParams.set('page', currentPage);
        }
        url.searchParams.set('status', status);
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

function viewLoanDetails(loanId) {
    // Navigate to loan details or show modal
    console.log('View loan:', loanId);
    // You can implement a loan details view here
}

function editLoan(loanId) {
    // Navigate to edit loan or show modal
    console.log('Edit loan:', loanId);
    // You can implement loan editing here
}

function openLoanPdf(loanId) {
    // Open loan PDF
    window.open(apiUrl(`api/loan-pdf.php?loan_id=${loanId}`), '_blank');
}

// Expose initLoansPage globally
if (typeof window.initLoansPage === 'function') {
    window.initLoansPage();
}
</script>

<style>
.filter-select {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    background: white;
    font-size: 14px;
    cursor: pointer;
    min-width: 150px;
}

.badge-info {
    background: #bee3f8;
    color: #2c5282;
}

.badge-warning {
    background: #fbd38d;
    color: #744210;
}

.btn-view {
    background: #48bb78;
    color: white;
}

.btn-view:hover {
    background: #38a169;
}

.btn-pdf {
    background: #ed8936;
    color: white;
}

.btn-pdf:hover {
    background: #dd6b20;
}
</style>
