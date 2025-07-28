<?php
// Define the base path
$basePath = dirname(__DIR__);
require_once $basePath . '/config/database.php';

try {
    $pdo = getDBConnection();
    
    // Get loan closing records with pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $whereClause = '';
    $params = [];
    
    if (!empty($search)) {
        $whereClause = "WHERE c.name LIKE ? OR c.mobile LIKE ? OR l.loan_no LIKE ?";
        $searchTerm = "%$search%";
        $params = [$searchTerm, $searchTerm, $searchTerm];
    }
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM loan_closings lc 
        JOIN loans l ON lc.loan_id = l.id 
        JOIN customers c ON l.customer_id = c.id 
        $whereClause
    ");
    $stmt->execute($params);
    $totalRecords = $stmt->fetch()['total'];
    $totalPages = ceil($totalRecords / $limit);
    
    $stmt = $pdo->prepare("
        SELECT lc.*, l.loan_date, l.loan_no, c.name as customer_name, c.mobile 
        FROM loan_closings lc 
        JOIN loans l ON lc.loan_id = l.id 
        JOIN customers c ON l.customer_id = c.id 
        $whereClause 
        ORDER BY lc.closing_date DESC 
        LIMIT ? OFFSET ?
    ");
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $loanClosings = $stmt->fetchAll();
    
    // Get active loans for dropdown
    $stmt = $pdo->query("
        SELECT l.id, l.loan_no, c.name as customer_name, l.principal_amount 
        FROM loans l 
        JOIN customers c ON l.customer_id = c.id 
        WHERE l.status = 'active' 
        ORDER BY l.loan_date DESC
    ");
    $activeLoans = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<div class="content-card">
    <div class="page-title">Loan Closing</div>
    
    <div class="search-section">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Name, mobile number" id="loanClosingSearch" value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <button class="add-btn" onclick="showAddLoanClosingModal()">Add New</button>
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
                <th>No</th>
                <th>Loan Date</th>
                <th>Loan Closing Date</th>
                <th>Loan Number</th>
                <th>Customer Name</th>
                <th>Mobile Number</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (isset($loanClosings) && !empty($loanClosings)): ?>
                <?php foreach ($loanClosings as $index => $closing): ?>
                    <tr>
                        <td><?php echo $offset + $index + 1; ?></td>
                        <td><?php echo date('d-m-Y', strtotime($closing['loan_date'])); ?></td>
                        <td><?php echo date('d-m-Y', strtotime($closing['closing_date'])); ?></td>
                        <td><?php echo htmlspecialchars($closing['loan_no']); ?></td>
                        <td><?php echo htmlspecialchars($closing['customer_name']); ?></td>
                        <td><?php echo htmlspecialchars($closing['mobile']); ?></td>
                        <td>
                            <button class="action-btn" onclick="showLoanClosingActions(<?php echo $closing['id']; ?>)">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" style="text-align: center;">No loan closing records found</td>
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

<!-- Add Loan Closing Modal -->
<div id="addLoanClosingModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="hideModal('addLoanClosingModal')">&times;</span>
        <h2>Add New Loan Closing</h2>
        <form id="addLoanClosingForm" onsubmit="addLoanClosing(event)">
            <div class="form-group">
                <label for="loanId">Loan</label>
                <select id="loanId" name="loan_id" required onchange="updateLoanDetails()">
                    <option value="">Select Loan</option>
                    <?php if (isset($activeLoans)): ?>
                        <?php foreach ($activeLoans as $loan): ?>
                            <option value="<?php echo $loan['id']; ?>" data-amount="<?php echo $loan['principal_amount']; ?>">
                                <?php echo htmlspecialchars($loan['loan_no'] . ' - ' . $loan['customer_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="closingDate">Closing Date</label>
                    <input type="date" id="closingDate" name="closing_date" required>
                </div>
                <div class="form-group">
                    <label for="totalInterestPaid">Total Interest Paid</label>
                    <input type="number" id="totalInterestPaid" name="total_interest_paid" step="0.01" required>
                </div>
            </div>
            
            <div class="form-group">
                <label>Principal Amount</label>
                <input type="text" id="principalAmount" readonly style="background-color: #f5f5f5;">
            </div>
            
            <div class="form-actions">
                <button type="button" onclick="hideModal('addLoanClosingModal')" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary">Close Loan</button>
            </div>
        </form>
    </div>
</div>

<script>
// Search functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('loanClosingSearch');
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
                url.searchParams.delete('page');
                window.location.href = url.toString();
            }, 500);
        });
    }
});
</script> 