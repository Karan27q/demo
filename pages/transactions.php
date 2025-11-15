<?php
// Define the base path
$basePath = dirname(__DIR__);
require_once $basePath . '/config/database.php';

try {
    $pdo = getDBConnection();
    
    // Get summary statistics
    $stmt = $pdo->query("
        SELECT 
            SUM(CASE WHEN transaction_type = 'credit' THEN amount ELSE 0 END) as total_credit,
            SUM(CASE WHEN transaction_type = 'debit' THEN amount ELSE 0 END) as total_debit
        FROM transactions
    ");
    $balanceData = $stmt->fetch();
    $totalCredit = $balanceData['total_credit'] ?? 0;
    $totalDebit = $balanceData['total_debit'] ?? 0;
    $netBalance = $totalCredit - $totalDebit;
    
    // Get transactions with pagination
    $page = isset($_GET['p']) ? (int)$_GET['p'] : (isset($_GET['page']) ? (int)$_GET['page'] : 1);
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
    $dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
    $typeFilter = isset($_GET['type']) ? $_GET['type'] : '';
    
    $whereClause = '';
    $params = [];
    
    $conditions = [];
    
    if (!empty($search)) {
        $conditions[] = "(transaction_name LIKE ? OR description LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if (!empty($dateFrom)) {
        $conditions[] = "date >= ?";
        $params[] = $dateFrom;
    }
    
    if (!empty($dateTo)) {
        $conditions[] = "date <= ?";
        $params[] = $dateTo;
    }
    
    if (!empty($typeFilter)) {
        $conditions[] = "transaction_type = ?";
        $params[] = $typeFilter;
    }
    
    if (!empty($conditions)) {
        $whereClause = "WHERE " . implode(" AND ", $conditions);
    }
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM transactions $whereClause");
    $stmt->execute($params);
    $totalRecords = $stmt->fetch()['total'];
    $totalPages = ceil($totalRecords / $limit);
    
    $stmt = $pdo->prepare("
        SELECT * FROM transactions 
        $whereClause 
        ORDER BY date DESC, created_at DESC
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
    $transactions = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<div class="dashboard-page">
    <!-- Financial Summary Cards -->
    <div class="dashboard-cards">
        <div class="dashboard-card">
            <div class="card-icon interest">
                <i class="fas fa-arrow-down"></i>
            </div>
            <div class="card-content">
                <div class="card-number">₹<?php echo number_format($totalCredit, 2); ?></div>
                <div class="card-label">Total Credit</div>
            </div>
        </div>
        
        <div class="dashboard-card">
            <div class="card-icon outstanding">
                <i class="fas fa-arrow-up"></i>
            </div>
            <div class="card-content">
                <div class="card-number">₹<?php echo number_format($totalDebit, 2); ?></div>
                <div class="card-label">Total Debit</div>
            </div>
        </div>
        
        <div class="dashboard-card">
            <div class="card-icon <?php echo $netBalance >= 0 ? 'loan' : 'outstanding'; ?>">
                <i class="fas fa-balance-scale"></i>
            </div>
            <div class="card-content">
                <div class="card-number" style="color: <?php echo $netBalance >= 0 ? '#38a169' : '#e53e3e'; ?>;">
                    ₹<?php echo number_format($netBalance, 2); ?>
                </div>
                <div class="card-label">Net Balance</div>
            </div>
        </div>
    </div>
    
    <!-- Main Content Card -->
    <div class="content-card">
        <div class="section-header">
            <h2 class="page-title">
                <i class="fas fa-exchange-alt"></i> Transaction Management
            </h2>
            <div class="section-actions">
                <button class="btn-primary" onclick="showAddTransactionModal()">
                    <i class="fas fa-plus"></i> Add Transaction
                </button>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filters-section">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search by transaction name or description" id="transactionSearch" value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="filter-row">
                <input type="date" id="dateFrom" class="filter-input" placeholder="From Date" value="<?php echo htmlspecialchars($dateFrom); ?>">
                <input type="date" id="dateTo" class="filter-input" placeholder="To Date" value="<?php echo htmlspecialchars($dateTo); ?>">
                <select id="typeFilter" class="filter-select">
                    <option value="">All Types</option>
                    <option value="credit" <?php echo $typeFilter === 'credit' ? 'selected' : ''; ?>>Credit</option>
                    <option value="debit" <?php echo $typeFilter === 'debit' ? 'selected' : ''; ?>>Debit</option>
                </select>
                <button class="clear-btn" onclick="clearTransactionFilters()">
                    <i class="fas fa-times"></i> Clear
                </button>
            </div>
        </div>
        
        <!-- Pagination Top -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <div class="pagination-info">
                Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $limit, $totalRecords); ?> of <?php echo $totalRecords; ?> transactions
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
                        <th>Date</th>
                        <th>Transaction Name</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Description</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (isset($transactions) && !empty($transactions)): ?>
                        <?php foreach ($transactions as $index => $transaction): ?>
                            <tr>
                                <td><?php echo $offset + $index + 1; ?></td>
                                <td><?php echo date('d-m-Y', strtotime($transaction['date'])); ?></td>
                                <td><strong><?php echo htmlspecialchars($transaction['transaction_name']); ?></strong></td>
                                <td>
                                    <span class="badge <?php echo $transaction['transaction_type'] === 'credit' ? 'badge-success' : 'badge-danger'; ?>">
                                        <?php echo ucfirst($transaction['transaction_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong style="color: <?php echo $transaction['transaction_type'] === 'credit' ? '#38a169' : '#e53e3e'; ?>;">
                                        <?php echo $transaction['transaction_type'] === 'credit' ? '+' : '-'; ?>₹<?php echo number_format($transaction['amount'], 2); ?>
                                    </strong>
                                </td>
                                <td title="<?php echo htmlspecialchars($transaction['description'] ?? ''); ?>">
                                    <?php 
                                    $desc = $transaction['description'] ?? '';
                                    echo htmlspecialchars(strlen($desc) > 30 ? substr($desc, 0, 30) . '...' : $desc); 
                                    ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="action-btn btn-edit" onclick="editTransaction(<?php echo $transaction['id']; ?>)" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="action-btn btn-delete" onclick="deleteTransaction(<?php echo $transaction['id']; ?>, '<?php echo htmlspecialchars($transaction['transaction_name']); ?>')" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px;">
                                <i class="fas fa-inbox" style="font-size: 48px; color: #ddd; margin-bottom: 10px;"></i>
                                <p style="color: #999;">No transactions found</p>
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
                Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $limit, $totalRecords); ?> of <?php echo $totalRecords; ?> transactions
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

<!-- Add Transaction Modal -->
<div id="addTransactionModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="hideModal('addTransactionModal')">&times;</span>
        <h2><i class="fas fa-plus-circle"></i> Add New Transaction</h2>
        <form id="addTransactionForm" onsubmit="addTransaction(event)">
            <div class="form-row">
                <div class="form-group">
                    <label for="transactionDate">Date *</label>
                    <input type="date" id="transactionDate" name="date" required value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label for="transactionType">Transaction Type *</label>
                    <select id="transactionType" name="transaction_type" required>
                        <option value="">Select Type</option>
                        <option value="credit">Credit</option>
                        <option value="debit">Debit</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label for="transactionName">Transaction Name *</label>
                <input type="text" id="transactionName" name="transaction_name" required placeholder="Enter transaction name">
            </div>
            
            <div class="form-group">
                <label for="transactionAmount">Amount (₹) *</label>
                <input type="number" id="transactionAmount" name="amount" step="0.01" required placeholder="0.00">
            </div>
            
            <div class="form-group">
                <label for="transactionDescription">Description</label>
                <textarea id="transactionDescription" name="description" rows="3" placeholder="Enter description (optional)"></textarea>
            </div>
            
            <div class="form-actions">
                <button type="button" onclick="hideModal('addTransactionModal')" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> Add Transaction
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Transaction Modal -->
<div id="editTransactionModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="hideModal('editTransactionModal')">&times;</span>
        <h2><i class="fas fa-edit"></i> Edit Transaction</h2>
        <form id="editTransactionForm" onsubmit="updateTransaction(event)">
            <input type="hidden" id="editTransactionId" name="id">
            <div class="form-row">
                <div class="form-group">
                    <label for="editTransactionDate">Date *</label>
                    <input type="date" id="editTransactionDate" name="date" required>
                </div>
                <div class="form-group">
                    <label for="editTransactionType">Transaction Type *</label>
                    <select id="editTransactionType" name="transaction_type" required>
                        <option value="credit">Credit</option>
                        <option value="debit">Debit</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label for="editTransactionName">Transaction Name *</label>
                <input type="text" id="editTransactionName" name="transaction_name" required>
            </div>
            
            <div class="form-group">
                <label for="editTransactionAmount">Amount (₹) *</label>
                <input type="number" id="editTransactionAmount" name="amount" step="0.01" required>
            </div>
            
            <div class="form-group">
                <label for="editTransactionDescription">Description</label>
                <textarea id="editTransactionDescription" name="description" rows="3"></textarea>
            </div>
            
            <div class="form-actions">
                <button type="button" onclick="hideModal('editTransactionModal')" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> Update Transaction
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Initialize transactions page
(function() {
    function initTransactionsPage() {
        const searchInput = document.getElementById('transactionSearch');
        if (searchInput && !searchInput.dataset.listenerAttached) {
            searchInput.dataset.listenerAttached = 'true';
            searchInput.addEventListener('input', function() {
                clearTimeout(this.searchTimeout);
                this.searchTimeout = setTimeout(() => {
                    applyTransactionFilters();
                }, 500);
            });
        }
        
        const dateFrom = document.getElementById('dateFrom');
        const dateTo = document.getElementById('dateTo');
        const typeFilter = document.getElementById('typeFilter');
        
        if (dateFrom) dateFrom.addEventListener('change', applyTransactionFilters);
        if (dateTo) dateTo.addEventListener('change', applyTransactionFilters);
        if (typeFilter) typeFilter.addEventListener('change', applyTransactionFilters);
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTransactionsPage);
    } else {
        initTransactionsPage();
    }
    
    window.initTransactionsPage = initTransactionsPage;
})();

function applyTransactionFilters() {
    const search = document.getElementById('transactionSearch').value;
    const dateFrom = document.getElementById('dateFrom').value;
    const dateTo = document.getElementById('dateTo').value;
    const type = document.getElementById('typeFilter').value;
    
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
    
    if (dateFrom) {
        url.searchParams.set('date_from', dateFrom);
    } else {
        url.searchParams.delete('date_from');
    }
    
    if (dateTo) {
        url.searchParams.set('date_to', dateTo);
    } else {
        url.searchParams.delete('date_to');
    }
    
    if (type) {
        url.searchParams.set('type', type);
    } else {
        url.searchParams.delete('type');
    }
    
    url.searchParams.delete('p');
    
    if (typeof window.loadPage === 'function' && currentPage) {
        window.history.pushState({ page: currentPage }, '', url.toString());
        window.loadPage(currentPage, false);
    } else {
        window.location.href = url.toString();
    }
}

function clearTransactionFilters() {
    document.getElementById('transactionSearch').value = '';
    document.getElementById('dateFrom').value = '';
    document.getElementById('dateTo').value = '';
    document.getElementById('typeFilter').value = '';
    applyTransactionFilters();
}

function editTransaction(transactionId) {
    fetch(apiUrl(`api/transactions.php?id=${transactionId}`))
        .then(response => response.json())
        .then(data => {
            if (data.success && data.transaction) {
                const t = data.transaction;
                document.getElementById('editTransactionId').value = t.id;
                document.getElementById('editTransactionDate').value = t.date;
                document.getElementById('editTransactionType').value = t.transaction_type;
                document.getElementById('editTransactionName').value = t.transaction_name;
                document.getElementById('editTransactionAmount').value = t.amount;
                document.getElementById('editTransactionDescription').value = t.description || '';
                showModal('editTransactionModal');
            } else {
                alert('Error loading transaction data');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while loading transaction data.');
        });
}

function updateTransaction(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const transactionId = formData.get('id');
    
    const data = new URLSearchParams();
    data.append('id', transactionId);
    data.append('date', formData.get('date'));
    data.append('transaction_type', formData.get('transaction_type'));
    data.append('transaction_name', formData.get('transaction_name'));
    data.append('amount', formData.get('amount'));
    data.append('description', formData.get('description'));
    
    fetch(apiUrl('api/transactions.php'), {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: data.toString()
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            hideModal('editTransactionModal');
            showSuccessMessage('Transaction updated successfully!');
            
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
        alert('An error occurred while updating the transaction.');
    });
}

function deleteTransaction(transactionId, transactionName) {
    if (!confirm(`Are you sure you want to delete transaction "${transactionName}"?\n\nThis action cannot be undone.`)) {
        return;
    }
    
    fetch(apiUrl(`api/transactions.php?id=${transactionId}`), {
        method: 'DELETE'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccessMessage('Transaction deleted successfully!');
            
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
        alert('An error occurred while deleting the transaction.');
    });
}
</script>

<style>
.filters-section {
    margin-bottom: 20px;
}

.filter-row {
    display: flex;
    gap: 10px;
    align-items: center;
    margin-top: 10px;
    flex-wrap: wrap;
}

.filter-input {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
}

.badge-success {
    background: #c6f6d5;
    color: #22543d;
}

.badge-danger {
    background: #fed7d7;
    color: #742a2a;
}
</style>
