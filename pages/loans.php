<?php
// Define the base path
$basePath = dirname(__DIR__);
require_once $basePath . '/config/database.php';

try {
    $pdo = getDBConnection();
    
    // Get loans with pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $status = isset($_GET['status']) ? $_GET['status'] : 'active';
    
    if ($status === 'all') {
        $whereClause = '';
        $params = [];
    } else {
        $whereClause = "WHERE l.status = ?";
        $params = [$status];
    }
    
    if (!empty($search)) {
        if ($status === 'all') {
            $whereClause = "WHERE (l.loan_no LIKE ? OR c.name LIKE ? OR c.mobile LIKE ?)";
            $searchTerm = "%$search%";
            $params = [$searchTerm, $searchTerm, $searchTerm];
        } else {
            $whereClause .= " AND (l.loan_no LIKE ? OR c.name LIKE ? OR c.mobile LIKE ?)";
            $searchTerm = "%$search%";
            $params = array_merge([$status], [$searchTerm, $searchTerm, $searchTerm]);
        }
    }
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM loans l 
        JOIN customers c ON l.customer_id = c.id 
        $whereClause
    ");
    $stmt->execute($params);
    $totalRecords = $stmt->fetch()['total'];
    $totalPages = ceil($totalRecords / $limit);
    
    $stmt = $pdo->prepare("
        SELECT l.*, c.name as customer_name, c.mobile, c.customer_no
        FROM loans l 
        JOIN customers c ON l.customer_id = c.id 
        $whereClause 
        ORDER BY l.loan_date DESC 
        LIMIT ? OFFSET ?
    ");
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $loans = $stmt->fetchAll();
    
    // Get customers for dropdown
    $stmt = $pdo->query("SELECT id, name, customer_no FROM customers ORDER BY name");
    $customers = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<div class="content-card">
    <div class="page-title">Loan</div>
    
    <div class="search-section">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Jewelry pawn, mobile number" id="loanSearch" value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <div class="filter-section" style="display: flex; gap: 10px; align-items: center;">
            <select id="statusFilter" onchange="filterByStatus()" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px;">
                <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active Loans</option>
                <option value="closed" <?php echo $status === 'closed' ? 'selected' : ''; ?>>Closed Loans</option>
                <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Loans</option>
            </select>
        </div>
        <button class="add-btn" onclick="showAddLoanModal()">Add New</button>
    </div>
    
    <!-- Pagination Top -->
    <div class="pagination">
        <div class="pagination-info">Page <?php echo $page; ?> of <?php echo $totalPages; ?></div>
        <div class="pagination-controls">
            <button class="pagination-btn" <?php echo $page <= 1 ? 'disabled' : ''; ?> onclick="changePage(<?php echo $page - 1; ?>)">
                <i class="fas fa-chevron-left"></i>
            </button>
            <button class="pagination-btn" <?php echo $page >= $totalPages ? 'disabled' : ''; ?> onclick="changePage(<?php echo $page + 1; ?>)">
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>
    </div>
    
    <table class="data-table">
        <thead>
            <tr>
                <th>Loan Date</th>
                <th>Loan No</th>
                <th>Customer No</th>
                <th>Customer Name</th>
                <th>Mobile Number</th>
                <th>Principal Amount</th>
                <th>Interest Rate</th>
                <th>Total Weight</th>
                <th>Pledge Items</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (isset($loans) && !empty($loans)): ?>
                <?php foreach ($loans as $index => $loan): ?>
                    <tr>
                        <td><?php echo date('d-m-Y', strtotime($loan['loan_date'])); ?></td>
                        <td><?php echo htmlspecialchars($loan['loan_no']); ?></td>
                        <td><?php echo htmlspecialchars($loan['customer_no']); ?></td>
                        <td><?php echo htmlspecialchars($loan['customer_name']); ?></td>
                        <td><?php echo htmlspecialchars($loan['mobile']); ?></td>
                        <td>₹<?php echo number_format($loan['principal_amount']); ?></td>
                        <td><?php echo $loan['interest_rate']; ?>%</td>
                        <td><?php echo number_format($loan['total_weight'], 2); ?></td>
                        <td><?php echo htmlspecialchars($loan['pledge_items']); ?></td>
                        <td>
                            <?php if ($loan['status'] === 'active'): ?>
                                <span class="status-badge status-active">நகை மீட்கபடவில்லை</span>
                            <?php else: ?>
                                <span class="status-badge status-inactive">நகை மீட்கபட்டது</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="action-btn" onclick="showLoanActions(<?php echo $loan['id']; ?>)">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="11" style="text-align: center;">No loan records found</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    
    <!-- Pagination Bottom -->
    <div class="pagination">
        <div class="pagination-info">Page <?php echo $page; ?> of <?php echo $totalPages; ?></div>
        <div class="pagination-controls">
            <button class="pagination-btn" <?php echo $page <= 1 ? 'disabled' : ''; ?> onclick="changePage(<?php echo $page - 1; ?>)">
                <i class="fas fa-chevron-left"></i>
            </button>
            <button class="pagination-btn" <?php echo $page >= $totalPages ? 'disabled' : ''; ?> onclick="changePage(<?php echo $page + 1; ?>)">
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>
    </div>
</div>

<!-- Add Loan Modal -->
<div id="addLoanModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="hideModal('addLoanModal')">&times;</span>
        <h2>Add New Loan</h2>
        <form id="addLoanForm" onsubmit="addLoan(event)">
            <div class="form-row">
                <div class="form-group">
                    <label for="loanNo">Loan Number</label>
                    <input type="text" id="loanNo" name="loan_no" required>
                </div>
                <div class="form-group">
                    <label for="loanDate">Loan Date</label>
                    <input type="date" id="loanDate" name="loan_date" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="customerId">Customer</label>
                <select id="customerId" name="customer_id" required>
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
                    <label for="principalAmount">Principal Amount</label>
                    <input type="number" id="principalAmount" name="principal_amount" step="0.01" required>
                </div>
                <div class="form-group">
                    <label for="interestRate">Interest Rate (%)</label>
                    <input type="number" id="interestRate" name="interest_rate" step="0.01" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="totalWeight">Total Weight (g)</label>
                    <input type="number" id="totalWeight" name="total_weight" step="0.01">
                </div>
                <div class="form-group">
                    <label for="netWeight">Net Weight (g)</label>
                    <input type="number" id="netWeight" name="net_weight" step="0.01">
                </div>
            </div>
            
            <div class="form-group">
                <label for="pledgeItems">Pledge Items</label>
                <textarea id="pledgeItems" name="pledge_items" rows="3" placeholder="e.g., RING - 1, BANGLE - 2"></textarea>
            </div>
            
            <div class="form-actions">
                <button type="button" onclick="hideModal('addLoanModal')" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary">Add Loan</button>
            </div>
        </form>
    </div>
</div>

<script>
function showAddLoanModal() {
    showModal('addLoanModal');
    // Set default date to today
    document.getElementById('loanDate').value = new Date().toISOString().split('T')[0];
}

function addLoan(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    
    fetch('api/loans.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            hideModal('addLoanModal');
            event.target.reset();
            // Reload the page to show new loan
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while adding the loan.');
    });
}

function changePage(page) {
    const search = document.getElementById('loanSearch').value;
    const status = document.getElementById('statusFilter').value;
    const url = new URL(window.location);
    url.searchParams.set('page', page);
    if (search) {
        url.searchParams.set('search', search);
    }
    if (status && status !== 'active') {
        url.searchParams.set('status', status);
    }
    window.location.href = url.toString();
}

function filterByStatus() {
    const status = document.getElementById('statusFilter').value;
    const search = document.getElementById('loanSearch').value;
    const url = new URL(window.location);
    
    if (status && status !== 'active') {
        url.searchParams.set('status', status);
    } else {
        url.searchParams.delete('status');
    }
    
    if (search) {
        url.searchParams.set('search', search);
    }
    
    url.searchParams.delete('page');
    window.location.href = url.toString();
}

// Search functionality
document.getElementById('loanSearch').addEventListener('input', function() {
    clearTimeout(this.searchTimeout);
    this.searchTimeout = setTimeout(() => {
        const search = this.value;
        const status = document.getElementById('statusFilter').value;
        const url = new URL(window.location);
        
        if (search) {
            url.searchParams.set('search', search);
        } else {
            url.searchParams.delete('search');
        }
        
        if (status && status !== 'active') {
            url.searchParams.set('status', status);
        }
        
        url.searchParams.delete('page');
        window.location.href = url.toString();
    }, 500);
});

function showLoanActions(loanId) {
    // Implement loan actions dropdown
    console.log('Show actions for loan:', loanId);
}
</script> 