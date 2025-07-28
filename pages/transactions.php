<?php
// Define the base path
$basePath = dirname(__DIR__);
require_once $basePath . '/config/database.php';

try {
    $pdo = getDBConnection();
    
    // Get transactions with pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $whereClause = '';
    $params = [];
    
    if (!empty($search)) {
        $whereClause = "WHERE transaction_name LIKE ? OR transaction_type LIKE ?";
        $searchTerm = "%$search%";
        $params = [$searchTerm, $searchTerm];
    }
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM transactions $whereClause");
    $stmt->execute($params);
    $totalRecords = $stmt->fetch()['total'];
    $totalPages = ceil($totalRecords / $limit);
    
    $stmt = $pdo->prepare("
        SELECT * FROM transactions 
        $whereClause 
        ORDER BY date DESC 
        LIMIT ? OFFSET ?
    ");
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<div class="content-card">
    <div class="page-title">Transaction Entries</div>
    
    <div class="search-section">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Search Transaction" id="transactionSearch" value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <button class="add-btn" onclick="showAddTransactionModal()">Add Transaction</button>
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
                <th>Date</th>
                <th>Transaction Name</th>
                <th>Transaction Type</th>
                <th>Amount</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (isset($transactions) && !empty($transactions)): ?>
                <?php foreach ($transactions as $index => $transaction): ?>
                    <tr>
                        <td><?php echo $offset + $index + 1; ?></td>
                        <td><?php echo date('Y-m-d', strtotime($transaction['date'])); ?></td>
                        <td><?php echo htmlspecialchars($transaction['transaction_name']); ?></td>
                        <td>
                            <span class="status-badge <?php echo $transaction['transaction_type'] === 'credit' ? 'status-active' : 'status-inactive'; ?>">
                                <?php echo ucfirst($transaction['transaction_type']); ?>
                            </span>
                        </td>
                        <td>₹<?php echo number_format($transaction['amount'], 2); ?></td>
                        <td>
                            <button class="action-btn" onclick="showTransactionActions(<?php echo $transaction['id']; ?>)">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" style="text-align: center;">No transactions found</td>
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

<!-- Add Transaction Modal -->
<div id="addTransactionModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="hideModal('addTransactionModal')">&times;</span>
        <h2>Add New Transaction</h2>
        <form id="addTransactionForm" onsubmit="addTransaction(event)">
            <div class="form-row">
                <div class="form-group">
                    <label for="transactionDate">Date</label>
                    <input type="date" id="transactionDate" name="date" required>
                </div>
                <div class="form-group">
                    <label for="transactionType">Transaction Type</label>
                    <select id="transactionType" name="transaction_type" required>
                        <option value="">Select Type</option>
                        <option value="credit">Credit</option>
                        <option value="debit">Debit</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label for="transactionName">Transaction Name</label>
                <input type="text" id="transactionName" name="transaction_name" required>
            </div>
            
            <div class="form-group">
                <label for="transactionAmount">Amount</label>
                <input type="number" id="transactionAmount" name="amount" step="0.01" required>
            </div>
            
            <div class="form-group">
                <label for="transactionDescription">Description</label>
                <textarea id="transactionDescription" name="description" rows="3"></textarea>
            </div>
            
            <div class="form-actions">
                <button type="button" onclick="hideModal('addTransactionModal')" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary">Add Transaction</button>
            </div>
        </form>
    </div>
</div>

<script>
// Search functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('transactionSearch');
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