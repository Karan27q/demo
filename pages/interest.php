<?php
// Define the base path
$basePath = dirname(__DIR__);
require_once $basePath . '/config/database.php';

try {
    $pdo = getDBConnection();
    
    // Get interest records with pagination
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
        FROM interest i 
        JOIN loans l ON i.loan_id = l.id 
        JOIN customers c ON l.customer_id = c.id 
        $whereClause
    ");
    $stmt->execute($params);
    $totalRecords = $stmt->fetch()['total'];
    $totalPages = ceil($totalRecords / $limit);
    
    $stmt = $pdo->prepare("
        SELECT i.*, l.loan_no, c.name as customer_name, c.mobile 
        FROM interest i 
        JOIN loans l ON i.loan_id = l.id 
        JOIN customers c ON l.customer_id = c.id 
        $whereClause 
        ORDER BY i.interest_date DESC 
        LIMIT ? OFFSET ?
    ");
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $interestRecords = $stmt->fetchAll();
    
    // Get active loans for dropdown
    $stmt = $pdo->query("
        SELECT l.id, l.loan_no, c.name as customer_name 
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
    <div class="page-title">Interest</div>
    
    <div class="search-section">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Name, mobile number" id="interestSearch" value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <button class="add-btn" onclick="showAddInterestModal()">Add New</button>
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
                <th>Interest Receive Date</th>
                <th>Name</th>
                <th>Loan Number</th>
                <th>Mobile Number</th>
                <th>Interest Amount</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (isset($interestRecords) && !empty($interestRecords)): ?>
                <?php foreach ($interestRecords as $index => $record): ?>
                    <tr>
                        <td><?php echo $offset + $index + 1; ?></td>
                        <td><?php echo date('d-m-Y', strtotime($record['interest_date'])); ?></td>
                        <td><?php echo htmlspecialchars($record['customer_name']); ?></td>
                        <td><?php echo htmlspecialchars($record['loan_no']); ?></td>
                        <td><?php echo htmlspecialchars($record['mobile']); ?></td>
                        <td>₹<?php echo number_format($record['interest_amount'], 2); ?></td>
                        <td>
                            <button class="action-btn" onclick="showInterestActions(<?php echo $record['id']; ?>)">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" style="text-align: center;">No interest records found</td>
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

<!-- Add Interest Modal -->
<div id="addInterestModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="hideModal('addInterestModal')">&times;</span>
        <h2>Add New Interest Record</h2>
        <form id="addInterestForm" onsubmit="addInterest(event)">
            <div class="form-group">
                <label for="loanId">Loan</label>
                <select id="loanId" name="loan_id" required onchange="updateInterestLoanDetails()">
                    <option value="">Select Loan</option>
                    <?php if (isset($activeLoans)): ?>
                        <?php foreach ($activeLoans as $loan): ?>
                            <option value="<?php echo $loan['id']; ?>">
                                <?php echo htmlspecialchars($loan['loan_no'] . ' - ' . $loan['customer_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="interestDate">Interest Date</label>
                    <input type="date" id="interestDate" name="interest_date" required>
                </div>
                <div class="form-group">
                    <label for="interestAmount">Interest Amount</label>
                    <input type="number" id="interestAmount" name="interest_amount" step="0.01" required>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="button" onclick="hideModal('addInterestModal')" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary">Add Interest</button>
            </div>
        </form>
    </div>
</div>

