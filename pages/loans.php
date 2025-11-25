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
    
    // Count query - show ALL loans (no deduplication)
    // Each loan has a unique 'id', so we show all loans for all customers
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM loans l
        INNER JOIN customers c ON l.customer_id = c.id
        $whereClause
    ");
    
    // Bind parameters for count query
    $countParamIndex = 1;
    if ($status !== 'all') {
        $countStmt->bindValue($countParamIndex++, $status);
    }
    if (!empty($search)) {
        $searchTerm = "%$search%";
        $countStmt->bindValue($countParamIndex++, $searchTerm);
        $countStmt->bindValue($countParamIndex++, $searchTerm);
        $countStmt->bindValue($countParamIndex++, $searchTerm);
    }
    $countStmt->execute();
    $totalRecords = $countStmt->fetch()['total'];
    $totalPages = ceil($totalRecords / $limit);
    
    // Fetch query - show ALL loans (no deduplication)
    // Each loan is uniquely identified by its 'id' field
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
        LEFT JOIN (
            SELECT loan_id, SUM(interest_amount) as total_interest_paid
            FROM interest
            GROUP BY loan_id
        ) i ON i.loan_id = l.id
        $whereClause
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
                                        <button class="action-btn btn-view" onclick="navigateToPage('pages/view-loan.php?id=<?php echo $loan['id']; ?>')" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($loan['status'] === 'active'): ?>
                                            <button class="action-btn btn-edit" onclick="navigateToPage('pages/edit-loan.php?id=<?php echo $loan['id']; ?>')" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="action-btn btn-pdf" onclick="openLoanPdf(<?php echo $loan['id']; ?>)" title="PDF">
                                                <i class="fas fa-file-pdf"></i>
                                            </button>
                                            <button class="action-btn btn-delete" onclick="deleteLoan(<?php echo $loan['id']; ?>, '<?php echo htmlspecialchars($loan['loan_no']); ?>')" title="Delete">
                                                <i class="fas fa-trash"></i>
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
    <div class="modal-content" style="max-width: 1000px; margin: 3% auto;">
        <span class="close" onclick="hideModal('addLoanModal')">&times;</span>
        <h2><i class="fas fa-plus-circle"></i> ← Loan Creation</h2>
        
        <form id="addLoanForm" onsubmit="addLoan(event)" enctype="multipart/form-data" autocomplete="off" style="display: flex; flex-direction: column;">
            <!-- Loan Calculation Display Box at Top -->
            <div id="loanCalculationBox" style="background: #f8f9fa; border: 2px solid #e9ecef; border-radius: 8px; padding: 15px; margin-bottom: 20px; display: none;">
                <h3 style="margin: 0 0 12px 0; font-size: 16px; color: #495057;">
                    <i class="fas fa-calculator"></i> Interest Calculation
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
            <div style="flex: 1; overflow-y: auto;">
                <div class="form-row">
                    <div class="form-group">
                        <label for="customerSelect">Customer *</label>
                        <select id="customerSelect" name="customer_id" required autocomplete="off">
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
                    <div class="form-group">
                        <label for="loanNo">Loan No *</label>
                        <input type="text" id="loanNo" name="loan_no" required placeholder="Loan No" autocomplete="off" readonly style="background-color: #f5f5f5; cursor: not-allowed;">
                    </div>
                    <div class="form-group">
                        <label for="loanDate">Date *</label>
                        <input type="date" id="loanDate" name="loan_date" required autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label for="principalAmount">principal amount *</label>
                        <input type="number" id="principalAmount" name="principal_amount" step="0.01" required placeholder="principal amount" autocomplete="off">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="interestRate">Interest Rate (%) *</label>
                        <input type="number" id="interestRate" name="interest_rate" step="0.01" required placeholder="Interest Rate" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label for="loanDays">Loan Days *</label>
                        <input type="number" id="loanDays" name="loan_days" required placeholder="Enter days" min="1" autocomplete="off">
                    </div>
                    <div class="form-group" style="display: none;">
                        <!-- Spacer -->
                    </div>
                    <div class="form-group" style="display: none;">
                        <!-- Spacer -->
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="totalWeight">Total Weight (g)</label>
                        <input type="number" id="totalWeight" name="total_weight" step="0.01" placeholder="0.00" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label for="netWeight">Net Weight (g)</label>
                        <input type="number" id="netWeight" name="net_weight" step="0.01" placeholder="0.00" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label for="ornamentFile">Upload Ornament</label>
                        <input type="file" id="ornamentFile" name="ornament_file" accept="image/*,.pdf">
                    </div>
                    <div class="form-group" style="display: none;">
                        <!-- Spacer -->
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
                    <i class="fas fa-save"></i> Submit
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

<!-- View Loan Details Modal -->
<div id="viewLoanModal" class="modal">
    <div class="modal-content" style="max-width: 900px; margin: 3% auto;">
        <span class="close" onclick="hideModal('viewLoanModal')">&times;</span>
        <h2><i class="fas fa-eye"></i> Loan Details</h2>
        
        <div id="viewLoanContent" style="padding: 20px 0;">
            <div style="text-align: center; padding: 40px;">
                <i class="fas fa-spinner fa-spin" style="font-size: 32px; color: #0d6efd;"></i>
                <p style="margin-top: 15px; color: #666;">Loading loan details...</p>
            </div>
        </div>
        
        <div class="form-actions" style="flex-shrink: 0; margin-top: auto; justify-content: center;">
            <button type="button" onclick="hideModal('viewLoanModal')" class="btn-secondary">Close</button>
        </div>
    </div>
</div>

<!-- Edit Loan Modal -->
<div id="editLoanModal" class="modal">
    <div class="modal-content" style="max-width: 1000px; margin: 3% auto;">
        <span class="close" onclick="hideModal('editLoanModal')">&times;</span>
        <h2><i class="fas fa-edit"></i> ← Edit Loan</h2>
        
        <form id="editLoanForm" onsubmit="updateLoan(event)" enctype="multipart/form-data" autocomplete="off" style="display: flex; flex-direction: column;">
            <input type="hidden" id="editLoanId" name="id">
            
            <!-- Loan Calculation Display Box at Top -->
            <div id="editLoanCalculationBox" style="background: #f8f9fa; border: 2px solid #e9ecef; border-radius: 8px; padding: 15px; margin-bottom: 20px; display: none;">
                <h3 style="margin: 0 0 12px 0; font-size: 16px; color: #495057;">
                    <i class="fas fa-calculator"></i> Interest Calculation
                </h3>
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;">
                    <div>
                        <strong style="color: #6c757d; font-size: 13px;">Principal Amount:</strong>
                        <div id="editCalcPrincipal" style="font-size: 18px; color: #212529; font-weight: 600;">₹0.00</div>
                    </div>
                    <div>
                        <strong style="color: #6c757d; font-size: 13px;">Interest Amount:</strong>
                        <div id="editCalcInterest" style="font-size: 18px; color: #0d6efd; font-weight: 600;">₹0.00</div>
                    </div>
                    <div style="grid-column: 1 / -1; border-top: 2px solid #dee2e6; padding-top: 12px; margin-top: 8px;">
                        <strong style="color: #6c757d; font-size: 13px;">Total Amount (Principal + Interest):</strong>
                        <div id="editCalcTotal" style="font-size: 22px; color: #198754; font-weight: 700;">₹0.00</div>
                    </div>
                </div>
            </div>
            
            <div style="flex: 1; overflow-y: auto;">
                <div class="form-row">
                    <div class="form-group">
                        <label for="editCustomerSelect">Customer *</label>
                        <select id="editCustomerSelect" name="customer_id" required autocomplete="off">
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
                    <div class="form-group">
                        <label for="editLoanNo">Loan No *</label>
                        <input type="text" id="editLoanNo" name="loan_no" required placeholder="Loan No" autocomplete="off" readonly>
                    </div>
                    <div class="form-group">
                        <label for="editLoanDate">Date *</label>
                        <input type="date" id="editLoanDate" name="loan_date" required autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label for="editPrincipalAmount">principal amount *</label>
                        <input type="number" id="editPrincipalAmount" name="principal_amount" step="0.01" required placeholder="principal amount" autocomplete="off">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="editInterestRate">Interest Rate (%) *</label>
                        <input type="number" id="editInterestRate" name="interest_rate" step="0.01" required placeholder="Interest Rate" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label for="editLoanDays">Loan Days *</label>
                        <input type="number" id="editLoanDays" name="loan_days" required placeholder="Enter days" min="1" autocomplete="off">
                    </div>
                    <div class="form-group" style="display: none;">
                        <!-- Spacer -->
                    </div>
                    <div class="form-group" style="display: none;">
                        <!-- Spacer -->
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="editTotalWeight">Total Weight (g)</label>
                        <input type="number" id="editTotalWeight" name="total_weight" step="0.01" placeholder="0.00" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label for="editNetWeight">Net Weight (g)</label>
                        <input type="number" id="editNetWeight" name="net_weight" step="0.01" placeholder="0.00" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label for="editOrnamentFile">Upload Ornament</label>
                        <input type="file" id="editOrnamentFile" name="ornament_file" accept="image/*,.pdf">
                        <small id="editOrnamentFileName" style="color: #666; font-size: 12px;"></small>
                    </div>
                    <div class="form-group" style="display: none;">
                        <!-- Spacer -->
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="editPledgeItems">Pledge Items</label>
                    <textarea id="editPledgeItems" name="pledge_items" rows="3" placeholder="e.g., RING - 1, BANGLE - 2" autocomplete="off"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="editLoanStatus">Status</label>
                    <select id="editLoanStatus" name="status" autocomplete="off">
                        <option value="active">Active</option>
                        <option value="closed">Closed</option>
                    </select>
                </div>
            </div>
            
            <div class="form-actions" style="flex-shrink: 0; margin-top: auto;">
                <button type="button" onclick="hideModal('editLoanModal')" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> Update Loan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Make navigateToPage globally available immediately
window.navigateToPage = function(path) {
    try {
        // Get the current URL
        const currentUrl = window.location;
        const pathname = currentUrl.pathname;
        
        // Extract the base directory (everything before the filename)
        // e.g., /demo/dashboard.php -> /demo/
        let baseDir = pathname.substring(0, pathname.lastIndexOf('/') + 1);
        
        // Remove trailing slash if it's just root
        if (baseDir === '/') {
            baseDir = '';
        }
        
        // Build the full path
        // If path already starts with /, use it as is
        if (path.startsWith('/')) {
            window.location.href = path;
        } else {
            // Relative path - combine with base directory
            const fullPath = baseDir + path;
            console.log('Navigating to:', fullPath, 'from:', pathname);
            window.location.href = fullPath;
        }
    } catch (error) {
        console.error('Navigation error:', error);
        alert('Navigation error: ' + error.message);
    }
};

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

// Override showAddLoanModal to ensure this version is used
window.showAddLoanModal = function() {
    console.log('showAddLoanModal called from loans.php');
    
    // Show modal first
    const modal = document.getElementById('addLoanModal');
    if (modal) {
        modal.style.display = 'block';
    }
    
    // Function to fetch and set loan number with retry logic
    function fetchAndSetLoanNumber(retryCount = 0) {
        const loanNoField = document.getElementById('loanNo');
        console.log('fetchAndSetLoanNumber attempt:', retryCount, 'Field found:', !!loanNoField);
        
        if (!loanNoField) {
            // Retry up to 15 times if field not found
            if (retryCount < 15) {
                setTimeout(() => fetchAndSetLoanNumber(retryCount + 1), 100);
            } else {
                console.error('Loan number field not found after 15 attempts');
            }
            return;
        }
        
        // Use the apiUrl function, with fallback
        const getApiUrl = (typeof apiUrl !== 'undefined') ? apiUrl : (typeof window.apiUrl !== 'undefined') ? window.apiUrl : function(path) {
            const p = window.location && window.location.pathname || '';
            const underPages = p.indexOf('/pages/') !== -1;
            return (underPages ? '../' : '') + path;
        };
        
        const apiPath = getApiUrl('api/loans.php?action=get_next_number');
        console.log('Fetching loan number from:', apiPath);
        
        fetch(apiPath)
            .then(response => {
                console.log('API response status:', response.status, response.statusText);
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.text().then(text => {
                    console.log('API raw response:', text);
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('JSON parse error:', e, 'Response text:', text);
                        throw new Error('Invalid JSON response');
                    }
                });
            })
            .then(data => {
                console.log('API response data:', data);
                if (data.success && data.loan_no !== undefined && data.loan_no !== null) {
                    const loanNo = data.loan_no.toString();
                    loanNoField.value = loanNo;
                    console.log('Loan number successfully set to:', loanNo);
                    // Force a re-render by triggering input event
                    loanNoField.dispatchEvent(new Event('input', { bubbles: true }));
                } else {
                    console.error('Invalid response from API:', data);
                    // Set default to 1 if no valid response
                    loanNoField.value = '1';
                    console.log('Set default loan number to: 1');
                }
            })
            .catch(error => {
                console.error('Error fetching next loan number:', error);
                // Set default to 1 if fetch fails
                if (loanNoField) {
                    loanNoField.value = '1';
                    console.log('Set default loan number to: 1 (due to error)');
                }
            });
    }
    
    // Wait a bit longer to ensure modal is fully rendered and visible
    setTimeout(() => {
        // Reset form completely to clear any cached data
        const form = document.getElementById('addLoanForm');
        if (form) {
            // Explicitly reset all form fields
            form.reset();
            
            // Set default date to today
            const loanDateField = document.getElementById('loanDate');
            if (loanDateField) {
                loanDateField.value = new Date().toISOString().split('T')[0];
            }
            
            // Clear customer fields
            const customerSelect = document.getElementById('customerSelect');
            if (customerSelect) {
                customerSelect.value = '';
                customerSelect.selectedIndex = 0;
            }
            
            // Clear other fields
            const principalAmount = document.getElementById('principalAmount');
            if (principalAmount) principalAmount.value = '';
            const interestRate = document.getElementById('interestRate');
            if (interestRate) interestRate.value = '';
            const loanDays = document.getElementById('loanDays');
            if (loanDays) loanDays.value = '';
            const totalWeight = document.getElementById('totalWeight');
            if (totalWeight) totalWeight.value = '';
            const netWeight = document.getElementById('netWeight');
            if (netWeight) netWeight.value = '';
            const pledgeItems = document.getElementById('pledgeItems');
            if (pledgeItems) pledgeItems.value = '';
            
            // Hide calculation box
            const calcBox = document.getElementById('loanCalculationBox');
            if (calcBox) {
                calcBox.style.display = 'none';
            }
            
            // Fetch and set loan number after form reset
            fetchAndSetLoanNumber();
        } else {
            console.error('addLoanForm not found');
        }
    }, 300);
};

// Customer selection handler removed - no longer needed

// Real-time interest calculation
// Formula: Principal × (Interest Rate / 100) × (Days / 30)
// Example: 10000 × (1 / 100) × (30 / 30) = 100
function calculateInterest() {
    const principal = parseFloat(document.getElementById('principalAmount').value) || 0;
    const interestRate = parseFloat(document.getElementById('interestRate').value) || 0;
    const loanDays = parseFloat(document.getElementById('loanDays').value) || 0;
    const calcBox = document.getElementById('loanCalculationBox');
    
    if (principal > 0 && interestRate > 0 && loanDays > 0) {
        // Calculate interest: (Principal × Interest Rate × Days) / 100
        const interest = (principal * interestRate * loanDays) / 100;
        const total = principal + interest;
        
        // Update calculation display
        document.getElementById('calcPrincipal').textContent = '₹' + principal.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('calcInterest').textContent = '₹' + interest.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('calcTotal').textContent = '₹' + total.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        
        if (calcBox) {
            calcBox.style.display = 'block';
        }
    } else {
        if (calcBox) {
            calcBox.style.display = 'none';
        }
    }
}

// Add event listeners for interest calculation
document.addEventListener('DOMContentLoaded', function() {
    const principalField = document.getElementById('principalAmount');
    const interestRateField = document.getElementById('interestRate');
    const loanDaysField = document.getElementById('loanDays');
    
    if (principalField) {
        principalField.addEventListener('input', calculateInterest);
        principalField.addEventListener('change', calculateInterest);
    }
    
    if (interestRateField) {
        interestRateField.addEventListener('input', calculateInterest);
        interestRateField.addEventListener('change', calculateInterest);
    }
    
    if (loanDaysField) {
        loanDaysField.addEventListener('input', calculateInterest);
        loanDaysField.addEventListener('change', calculateInterest);
    }
});

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
                
                const customerSelect = document.getElementById('customerSelect');
                if (customerSelect) {
                    customerSelect.value = '';
                    customerSelect.selectedIndex = 0;
                }
                
                // Clear customer detail fields
                document.getElementById('customerNo').value = '';
                document.getElementById('customerName').value = '';
                document.getElementById('customerAddress').value = '';
                document.getElementById('customerPlace').value = '';
                document.getElementById('customerMobile').value = '';
                
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
            
            // Reload the loans page to show the new loan
            setTimeout(() => {
                const url = new URL(window.location);
                const currentPage = url.searchParams.get('page'); // 'loans' when loaded via dashboard
                
                // Reset to first page and clear search to show new loan
                url.searchParams.set('page', 'loans');
                url.searchParams.delete('p'); // Reset pagination
                url.searchParams.delete('search'); // Clear search
                // Keep status filter as is (or reset to 'active' if you prefer)
                if (!url.searchParams.get('status')) {
                    url.searchParams.set('status', 'active');
                }
                
                // If loaded via dashboard, use loadPage for smooth navigation
                if (typeof window.loadPage === 'function' && currentPage) {
                    window.history.pushState({ page: 'loans' }, '', url.toString());
                    window.loadPage('loans', false);
                } else {
                    // Direct page access - full reload
                    window.location.href = url.toString();
                }
            }, 500);
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
        
        // Calculate interest: Principal × (Interest Rate / 100) × (Days / 30)
        let interestAmount = 0;
        if (principal > 0 && interestRate > 0 && days > 0) {
            interestAmount = principal * (interestRate / 100) * (days / 30);
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
    const currentPage = url.searchParams.get('page'); // This will be 'loans' when loaded via dashboard
    
    // Set status parameter
    if (status && status !== 'active') {
        url.searchParams.set('status', status);
    } else {
        url.searchParams.delete('status');
    }
    
    // Set search parameter
    if (search) {
        url.searchParams.set('search', search);
    } else {
        url.searchParams.delete('search');
    }
    
    // Reset to first page when filtering
    url.searchParams.delete('p');
    
    // If loaded via dashboard (has 'page' parameter), use loadPage
    if (typeof window.loadPage === 'function' && currentPage) {
        window.history.pushState({ page: currentPage }, '', url.toString());
        window.loadPage(currentPage, false);
    } else {
        // Direct page access - full reload
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

// Ensure apiUrl function is available globally
if (typeof apiUrl === 'undefined') {
    window.apiUrl = function(path) {
        try {
            const p = window.location && window.location.pathname || '';
            const underPages = p.indexOf('/pages/') !== -1;
            return (underPages ? '../' : '') + path;
        } catch (e) {
            return path;
        }
    };
    // Also make it available as a regular function
    function apiUrl(path) {
        return window.apiUrl(path);
    }
}


function viewLoanDetails(loanId) {
    // Show loading state
    const contentDiv = document.getElementById('viewLoanContent');
    contentDiv.innerHTML = `
        <div style="text-align: center; padding: 40px;">
            <i class="fas fa-spinner fa-spin" style="font-size: 32px; color: #0d6efd;"></i>
            <p style="margin-top: 15px; color: #666;">Loading loan details...</p>
        </div>
    `;
    
    // Show modal
    showModal('viewLoanModal');
    
    // Fetch loan details
    fetch(apiUrl(`api/loans.php?id=${loanId}`))
        .then(response => response.json())
        .then(data => {
            if (data.success && data.loan) {
                const loan = data.loan;
                
                // Fetch customer details
                return fetch(apiUrl(`api/customers.php?id=${loan.customer_id}`))
                    .then(response => response.json())
                    .then(customerData => {
                        const customer = customerData.success ? customerData.customer : null;
                        
                        // Calculate interest
                        const principal = parseFloat(loan.principal_amount) || 0;
                        const interestRate = parseFloat(loan.interest_rate) || 0;
                        const loanDays = parseFloat(loan.loan_days) || 0;
                        const interestAmount = (principal > 0 && interestRate > 0 && loanDays > 0) 
                            ? principal * (interestRate / 100) * (loanDays / 30)
                            : (parseFloat(loan.interest_amount) || 0);
                        const totalAmount = principal + interestAmount;
                        
                        // Format date
                        const formatDate = (dateStr) => {
                            if (!dateStr) return 'N/A';
                            const date = new Date(dateStr);
                            return date.toLocaleDateString('en-IN', { day: '2-digit', month: '2-digit', year: 'numeric' });
                        };
                        
                        // Build view content
                        contentDiv.innerHTML = `
                            <div style="background: #f8f9fa; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
                                <h3 style="margin: 0 0 15px 0; color: #495057; border-bottom: 2px solid #dee2e6; padding-bottom: 10px;">
                                    <i class="fas fa-calculator"></i> Loan Summary
                                </h3>
                                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                                    <div>
                                        <strong style="color: #6c757d; font-size: 13px;">Principal Amount:</strong>
                                        <div style="font-size: 18px; color: #212529; font-weight: 600;">₹${principal.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
                                    </div>
                                    <div>
                                        <strong style="color: #6c757d; font-size: 13px;">Interest Amount:</strong>
                                        <div style="font-size: 18px; color: #0d6efd; font-weight: 600;">₹${interestAmount.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
                                    </div>
                                    <div style="grid-column: 1 / -1; border-top: 2px solid #dee2e6; padding-top: 15px; margin-top: 10px;">
                                        <strong style="color: #6c757d; font-size: 13px;">Total Amount (Principal + Interest):</strong>
                                        <div style="font-size: 22px; color: #198754; font-weight: 700;">₹${totalAmount.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
                                <div>
                                    <h4 style="margin: 0 0 15px 0; color: #495057; border-bottom: 1px solid #dee2e6; padding-bottom: 8px;">
                                        <i class="fas fa-file-invoice"></i> Loan Information
                                    </h4>
                                    <table style="width: 100%; border-collapse: collapse;">
                                        <tr style="border-bottom: 1px solid #e9ecef;">
                                            <td style="padding: 10px 0; color: #6c757d; font-weight: 600;">Loan No:</td>
                                            <td style="padding: 10px 0; text-align: right;"><strong>${loan.loan_no || 'N/A'}</strong></td>
                                        </tr>
                                        <tr style="border-bottom: 1px solid #e9ecef;">
                                            <td style="padding: 10px 0; color: #6c757d; font-weight: 600;">Loan Date:</td>
                                            <td style="padding: 10px 0; text-align: right;">${formatDate(loan.loan_date)}</td>
                                        </tr>
                                        <tr style="border-bottom: 1px solid #e9ecef;">
                                            <td style="padding: 10px 0; color: #6c757d; font-weight: 600;">Principal Amount:</td>
                                            <td style="padding: 10px 0; text-align: right;"><strong>₹${(parseFloat(loan.principal_amount) || 0).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong></td>
                                        </tr>
                                        <tr style="border-bottom: 1px solid #e9ecef;">
                                            <td style="padding: 10px 0; color: #6c757d; font-weight: 600;">Interest Rate:</td>
                                            <td style="padding: 10px 0; text-align: right;">${(parseFloat(loan.interest_rate) || 0).toFixed(2)}%</td>
                                        </tr>
                                        <tr style="border-bottom: 1px solid #e9ecef;">
                                            <td style="padding: 10px 0; color: #6c757d; font-weight: 600;">Loan Days:</td>
                                            <td style="padding: 10px 0; text-align: right;">${loan.loan_days || 'N/A'}</td>
                                        </tr>
                                        <tr style="border-bottom: 1px solid #e9ecef;">
                                            <td style="padding: 10px 0; color: #6c757d; font-weight: 600;">Total Weight:</td>
                                            <td style="padding: 10px 0; text-align: right;">${(parseFloat(loan.total_weight) || 0).toFixed(3)} g</td>
                                        </tr>
                                        <tr style="border-bottom: 1px solid #e9ecef;">
                                            <td style="padding: 10px 0; color: #6c757d; font-weight: 600;">Net Weight:</td>
                                            <td style="padding: 10px 0; text-align: right;">${(parseFloat(loan.net_weight) || 0).toFixed(3)} g</td>
                                        </tr>
                                        <tr style="border-bottom: 1px solid #e9ecef;">
                                            <td style="padding: 10px 0; color: #6c757d; font-weight: 600;">Status:</td>
                                            <td style="padding: 10px 0; text-align: right;">
                                                <span style="padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; 
                                                    background: ${loan.status === 'active' ? '#d1e7dd' : '#f8d7da'}; 
                                                    color: ${loan.status === 'active' ? '#0f5132' : '#842029'};">
                                                    ${loan.status === 'active' ? 'Active' : 'Closed'}
                                                </span>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                                
                                <div>
                                    <h4 style="margin: 0 0 15px 0; color: #495057; border-bottom: 1px solid #dee2e6; padding-bottom: 8px;">
                                        <i class="fas fa-user"></i> Customer Information
                                    </h4>
                                    ${customer ? `
                                        <table style="width: 100%; border-collapse: collapse;">
                                            <tr style="border-bottom: 1px solid #e9ecef;">
                                                <td style="padding: 10px 0; color: #6c757d; font-weight: 600;">Customer No:</td>
                                                <td style="padding: 10px 0; text-align: right;"><strong>${customer.customer_no || 'N/A'}</strong></td>
                                            </tr>
                                            <tr style="border-bottom: 1px solid #e9ecef;">
                                                <td style="padding: 10px 0; color: #6c757d; font-weight: 600;">Name:</td>
                                                <td style="padding: 10px 0; text-align: right;">${customer.name || 'N/A'}</td>
                                            </tr>
                                            <tr style="border-bottom: 1px solid #e9ecef;">
                                                <td style="padding: 10px 0; color: #6c757d; font-weight: 600;">Mobile:</td>
                                                <td style="padding: 10px 0; text-align: right;">${customer.mobile || 'N/A'}</td>
                                            </tr>
                                            <tr style="border-bottom: 1px solid #e9ecef;">
                                                <td style="padding: 10px 0; color: #6c757d; font-weight: 600;">Address:</td>
                                                <td style="padding: 10px 0; text-align: right;">${customer.address || 'N/A'}</td>
                                            </tr>
                                            ${customer.place ? `
                                            <tr style="border-bottom: 1px solid #e9ecef;">
                                                <td style="padding: 10px 0; color: #6c757d; font-weight: 600;">Place:</td>
                                                <td style="padding: 10px 0; text-align: right;">${customer.place}</td>
                                            </tr>
                                            ` : ''}
                                        </table>
                                    ` : '<p style="color: #999;">Customer information not available</p>'}
                                </div>
                            </div>
                            
                            ${loan.pledge_items ? `
                                <div style="margin-top: 20px;">
                                    <h4 style="margin: 0 0 10px 0; color: #495057; border-bottom: 1px solid #dee2e6; padding-bottom: 8px;">
                                        <i class="fas fa-gem"></i> Pledge Items
                                    </h4>
                                    <p style="background: #f8f9fa; padding: 15px; border-radius: 6px; margin: 0;">${loan.pledge_items}</p>
                                </div>
                            ` : ''}
                            
                            ${loan.ornament_file ? `
                                <div style="margin-top: 20px;">
                                    <h4 style="margin: 0 0 10px 0; color: #495057; border-bottom: 1px solid #dee2e6; padding-bottom: 8px;">
                                        <i class="fas fa-image"></i> Ornament File
                                    </h4>
                                    <p style="background: #f8f9fa; padding: 15px; border-radius: 6px; margin: 0;">
                                        <a href="${apiUrl(loan.ornament_file)}" target="_blank" style="color: #0d6efd; text-decoration: none;">
                                            <i class="fas fa-file"></i> ${loan.ornament_file.split('/').pop()}
                                        </a>
                                    </p>
                                </div>
                            ` : ''}
                        `;
                    });
            } else {
                contentDiv.innerHTML = `
                    <div style="text-align: center; padding: 40px;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 32px; color: #dc3545;"></i>
                        <p style="margin-top: 15px; color: #dc3545;">Error: Could not load loan details</p>
                        <p style="color: #666; font-size: 14px; margin-top: 5px;">${data.message || 'Unknown error'}</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            contentDiv.innerHTML = `
                <div style="text-align: center; padding: 40px;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 32px; color: #dc3545;"></i>
                    <p style="margin-top: 15px; color: #dc3545;">An error occurred while loading loan details.</p>
                </div>
            `;
        });
}

function editLoan(loanId) {
    // Fetch loan details
    fetch(apiUrl(`api/loans.php?id=${loanId}`))
        .then(response => response.json())
        .then(data => {
            if (data.success && data.loan) {
                const loan = data.loan;
                
                // Populate form fields
                document.getElementById('editLoanId').value = loan.id;
                document.getElementById('editLoanNo').value = loan.loan_no || '';
                document.getElementById('editLoanDate').value = loan.loan_date || '';
                document.getElementById('editCustomerSelect').value = loan.customer_id || '';
                document.getElementById('editPrincipalAmount').value = loan.principal_amount || '';
                document.getElementById('editInterestRate').value = loan.interest_rate || '';
                document.getElementById('editLoanDays').value = loan.loan_days || '';
                document.getElementById('editTotalWeight').value = loan.total_weight || '';
                document.getElementById('editNetWeight').value = loan.net_weight || '';
                document.getElementById('editPledgeItems').value = loan.pledge_items || '';
                document.getElementById('editLoanStatus').value = loan.status || 'active';
                
                // Show existing ornament file if available
                if (loan.ornament_file) {
                    const fileName = loan.ornament_file.split('/').pop();
                    document.getElementById('editOrnamentFileName').textContent = 'Current: ' + fileName;
                }
                
                // Calculate and display interest
                calculateEditInterest();
                
                // Show modal
                showModal('editLoanModal');
            } else {
                alert('Error: Could not load loan details');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while loading loan details.');
        });
}

function updateLoan(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const loanId = formData.get('id');
    
    // Calculate interest amount if not provided
    const principal = parseFloat(formData.get('principal_amount')) || 0;
    const interestRate = parseFloat(formData.get('interest_rate')) || 0;
    const loanDays = parseFloat(formData.get('loan_days')) || 0;
    
    if (principal > 0 && interestRate > 0 && loanDays > 0) {
        const interestAmount = principal * (interestRate / 100) * (loanDays / 30);
        formData.append('interest_amount', interestAmount.toFixed(2));
    }
    
    // Convert FormData to URL-encoded format for PUT request (except files)
    const data = new URLSearchParams();
    for (const [key, value] of formData.entries()) {
        if (key !== 'ornament_file' && value instanceof File === false) {
            data.append(key, value);
        }
    }
    
    // For file uploads, we need to use a different approach or handle separately
    // For now, update without file changes (files can be updated separately if needed)
    
    fetch(apiUrl('api/loans.php'), {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: data.toString()
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            hideModal('editLoanModal');
            showSuccessMessage('Loan updated successfully!');
            
            // Reload the page
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
        alert('An error occurred while updating the loan.');
    });
}

// Real-time interest calculation for edit form
function calculateEditInterest() {
    const principal = parseFloat(document.getElementById('editPrincipalAmount').value) || 0;
    const interestRate = parseFloat(document.getElementById('editInterestRate').value) || 0;
    const loanDays = parseFloat(document.getElementById('editLoanDays').value) || 0;
    const calcBox = document.getElementById('editLoanCalculationBox');
    
    if (principal > 0 && interestRate > 0 && loanDays > 0) {
        // Calculate interest: Principal × (Interest Rate / 100) × (Days / 30)
        const interest = principal * (interestRate / 100) * (loanDays / 30);
        const total = principal + interest;
        
        // Update calculation display
        document.getElementById('editCalcPrincipal').textContent = '₹' + principal.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('editCalcInterest').textContent = '₹' + interest.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('editCalcTotal').textContent = '₹' + total.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        
        if (calcBox) {
            calcBox.style.display = 'block';
        }
    } else {
        if (calcBox) {
            calcBox.style.display = 'none';
        }
    }
}

// Add event listeners for edit form interest calculation
document.addEventListener('DOMContentLoaded', function() {
    const editPrincipalField = document.getElementById('editPrincipalAmount');
    const editInterestRateField = document.getElementById('editInterestRate');
    const editLoanDaysField = document.getElementById('editLoanDays');
    
    if (editPrincipalField) {
        editPrincipalField.addEventListener('input', calculateEditInterest);
        editPrincipalField.addEventListener('change', calculateEditInterest);
    }
    
    if (editInterestRateField) {
        editInterestRateField.addEventListener('input', calculateEditInterest);
        editInterestRateField.addEventListener('change', calculateEditInterest);
    }
    
    if (editLoanDaysField) {
        editLoanDaysField.addEventListener('input', calculateEditInterest);
        editLoanDaysField.addEventListener('change', calculateEditInterest);
    }
});

function openLoanPdf(loanId) {
    // Open loan PDF
    window.open(apiUrl(`api/loan-pdf.php?loan_id=${loanId}`), '_blank');
}

function deleteLoan(loanId, loanNo) {
    // Confirm deletion
    if (!confirm(`Are you sure you want to delete loan ${loanNo}?\n\nThis action cannot be undone.`)) {
        return;
    }
    
    // Use global apiUrl or fallback
    const getApiUrl = (typeof apiUrl !== 'undefined') ? apiUrl : (typeof window.apiUrl !== 'undefined') ? window.apiUrl : function(path) {
        const p = window.location && window.location.pathname || '';
        const underPages = p.indexOf('/pages/') !== -1;
        return (underPages ? '../' : '') + path;
    };
    
    // Delete loan
    fetch(getApiUrl(`api/loans.php?id=${loanId}`), {
        method: 'DELETE'
    })
    .then(response => {
        if (!response.ok) {
            return response.text().then(text => {
                throw new Error(`HTTP ${response.status}: ${text}`);
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Show success message
            const getShowSuccessMessage = (typeof showSuccessMessage !== 'undefined') ? showSuccessMessage : (typeof window.showSuccessMessage !== 'undefined') ? window.showSuccessMessage : function(msg) {
                alert(msg);
            };
            getShowSuccessMessage('Loan deleted successfully!');
            
            // Remove the row from table immediately
            const row = document.getElementById(`loan-row-${loanId}`);
            if (row) {
                row.style.transition = 'opacity 0.3s';
                row.style.opacity = '0';
                setTimeout(() => {
                    row.remove();
                    // If no rows left, show empty message
                    const tbody = document.querySelector('.data-table tbody');
                    if (tbody && tbody.children.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="15" style="text-align: center; padding: 40px;"><i class="fas fa-inbox" style="font-size: 48px; color: #ddd; margin-bottom: 10px;"></i><p style="color: #999;">No loans found</p></td></tr>';
                    }
                }, 300);
            } else {
                // Reload the page if row not found
                const urlParams = new URLSearchParams(window.location.search);
                const currentPage = urlParams.get('page');
                if (currentPage && typeof window.loadPage === 'function') {
                    setTimeout(() => {
                        window.loadPage(currentPage, false);
                    }, 500);
                } else {
                    setTimeout(() => location.reload(), 500);
                }
            }
        } else {
            alert('Error: ' + (data.message || 'Failed to delete loan'));
        }
    })
    .catch(error => {
        console.error('Delete loan error:', error);
        alert('An error occurred while deleting the loan: ' + (error.message || 'Unknown error'));
    });
}

// Make deleteLoan globally available
window.deleteLoan = deleteLoan;

// Expose initLoansPage globally
if (typeof window.initLoansPage === 'function') {
    window.initLoansPage();
}
</script>

<style>
.dashboard-page {
    padding: 0;
    width: 100%;
    max-width: 100%;
    overflow-x: auto;
}

.content-card {
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
}

.table-container {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.section-header .page-title {
    margin: 0;
    color: #2d3748;
    display: flex;
    align-items: center;
    gap: 10px;
}

.filter-select {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    background: white;
    font-size: 14px;
    cursor: pointer;
    min-width: 150px;
}

.action-buttons {
    display: flex;
    gap: 8px;
    justify-content: center;
}

.action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    padding: 0;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.2s;
    color: white;
}

.btn-view {
    background: #48bb78;
    color: white;
}

.btn-view:hover {
    background: #38a169;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(72, 187, 120, 0.3);
}

.btn-edit {
    background: #4299e1;
    color: white;
}

.btn-edit:hover {
    background: #3182ce;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(66, 153, 225, 0.3);
}

.btn-pdf {
    background: #ed8936;
    color: white;
}

.btn-pdf:hover {
    background: #dd6b20;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(237, 137, 54, 0.3);
}

.btn-delete {
    background: #f56565;
    color: white;
}

.btn-delete:hover {
    background: #e53e3e;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(245, 101, 101, 0.3);
}

.badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    background: #e2e8f0;
    color: #4a5568;
}

.badge-success {
    background: #c6f6d5;
    color: #22543d;
}

.badge-warning {
    background: #feebc8;
    color: #c05621;
}

.badge-info {
    background: #bee3f8;
    color: #2c5282;
}

.pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 20px;
}

.pagination-info {
    color: #718096;
    font-size: 14px;
}

.pagination-controls {
    display: flex;
    gap: 10px;
    align-items: center;
}

.pagination-btn {
    background: #4299e1;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.2s;
}

.pagination-btn:hover:not(:disabled) {
    background: #3182ce;
    transform: translateY(-1px);
}

.pagination-btn:disabled {
    background: #cbd5e0;
    cursor: not-allowed;
    opacity: 0.6;
}

.pagination-page {
    padding: 0 15px;
    color: #718096;
    font-weight: 500;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    table-layout: auto;
    min-width: 100%;
}

.data-table th {
    background: #f8f9fa;
    padding: 15px;
    text-align: left;
    font-weight: 600;
    color: #2d3748;
    border-bottom: 2px solid #e2e8f0;
    font-size: 14px;
}

.data-table td {
    padding: 15px;
    border-bottom: 1px solid #e2e8f0;
    color: #4a5568;
    font-size: 14px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.data-table th:nth-child(11),
.data-table td:nth-child(11) {
    white-space: normal;
    max-width: 200px;
}

.data-table tr:hover {
    background: #f7fafc;
}

.data-table tr:last-child td {
    border-bottom: none;
}


.search-section {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    align-items: center;
}

.search-box {
    flex: 1;
    position: relative;
    display: flex;
    align-items: center;
}

.search-box i {
    position: absolute;
    left: 15px;
    color: #718096;
    z-index: 1;
}

.search-box input {
    width: 100%;
    padding: 12px 15px 12px 45px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.2s;
}

.search-box input:focus {
    outline: none;
    border-color: #4299e1;
    box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
}

.clear-btn {
    background: #e2e8f0;
    color: #4a5568;
    border: none;
    padding: 12px 20px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.2s;
}

.clear-btn:hover {
    background: #cbd5e0;
}

@media (max-width: 768px) {
    .dashboard-page {
        padding: 10px;
    }
    
    .content-card {
        padding: 15px;
    }
    
    .section-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .action-buttons {
        flex-wrap: wrap;
        gap: 4px;
    }
    
    .action-btn {
        width: 32px;
        height: 32px;
        font-size: 12px;
    }
    
    .data-table {
        font-size: 12px;
        display: block;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    .data-table thead,
    .data-table tbody,
    .data-table tr {
        display: block;
    }
    
    .data-table th,
    .data-table td {
        padding: 10px 8px;
        display: block;
        text-align: left;
        border-bottom: 1px solid #e2e8f0;
    }
    
    .data-table th {
        background: #f8f9fa;
        font-weight: bold;
    }
    
    .data-table th:before {
        content: attr(data-label) ": ";
        font-weight: bold;
    }
    
    .pagination {
        flex-direction: column;
        gap: 10px;
    }
    
    .pagination-controls {
        width: 100%;
        justify-content: center;
    }
}
</style>
