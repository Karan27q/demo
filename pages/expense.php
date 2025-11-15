<?php
$basePath = dirname(__DIR__);
require_once $basePath . '/config/database.php';

try {
    $pdo = getDBConnection();
    
    // Get summary statistics
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_expenses,
            COALESCE(SUM(amount), 0) as total_amount
        FROM transactions
        WHERE transaction_type = 'debit'
    ");
    $expenseStats = $stmt->fetch();
    $totalExpenses = $expenseStats['total_expenses'] ?? 0;
    $totalAmount = $expenseStats['total_amount'] ?? 0;
    
    // Get expenses this month
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(amount), 0) as total 
        FROM transactions 
        WHERE transaction_type = 'debit'
        AND MONTH(date) = MONTH(CURDATE()) 
        AND YEAR(date) = YEAR(CURDATE())
    ");
    $monthlyExpenses = $stmt->fetch()['total'];
    
    // Get expenses with pagination
    $page = isset($_GET['p']) ? (int)$_GET['p'] : (isset($_GET['page']) ? (int)$_GET['page'] : 1);
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
    $dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
    
    $whereClause = "WHERE transaction_type = 'debit'";
    $params = [];
    
    if (!empty($search)) {
        $whereClause .= " AND (transaction_name LIKE ? OR description LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if (!empty($dateFrom)) {
        $whereClause .= " AND date >= ?";
        $params[] = $dateFrom;
    }
    
    if (!empty($dateTo)) {
        $whereClause .= " AND date <= ?";
        $params[] = $dateTo;
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
    $expenses = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    $expenses = [];
    $totalExpenses = 0;
    $totalAmount = 0;
    $monthlyExpenses = 0;
    $totalRecords = 0;
    $totalPages = 0;
}
?>

<div class="dashboard-page">
    <!-- Summary Cards -->
    <div class="dashboard-cards">
        <div class="dashboard-card">
            <div class="card-icon outstanding">
                <i class="fas fa-receipt"></i>
            </div>
            <div class="card-content">
                <div class="card-number"><?php echo number_format($totalExpenses); ?></div>
                <div class="card-label">Total Expenses</div>
            </div>
        </div>
        
        <div class="dashboard-card">
            <div class="card-icon principal">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="card-content">
                <div class="card-number">₹<?php echo number_format($totalAmount, 2); ?></div>
                <div class="card-label">Total Amount</div>
            </div>
        </div>
        
        <div class="dashboard-card">
            <div class="card-icon interest">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <div class="card-content">
                <div class="card-number">₹<?php echo number_format($monthlyExpenses, 2); ?></div>
                <div class="card-label">This Month</div>
            </div>
        </div>
    </div>
    
    <!-- Main Content Card -->
    <div class="content-card">
        <div class="section-header">
            <h2 class="page-title">
                <i class="fas fa-money-bill-wave"></i> Expense Management
            </h2>
            <div class="section-actions">
                <button class="btn-primary" onclick="showAddExpenseModal()">
                    <i class="fas fa-plus"></i> Add Expense
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
                <input type="text" placeholder="Search by expense name or description" id="expenseSearch" value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <input type="date" id="dateFrom" placeholder="From Date" value="<?php echo htmlspecialchars($dateFrom); ?>" style="padding: 8px; border: 1px solid #ddd; border-radius: 6px;">
            <input type="date" id="dateTo" placeholder="To Date" value="<?php echo htmlspecialchars($dateTo); ?>" style="padding: 8px; border: 1px solid #ddd; border-radius: 6px;">
            <?php if (!empty($search) || !empty($dateFrom) || !empty($dateTo)): ?>
                <button class="clear-btn" onclick="clearExpenseSearch()">
                    <i class="fas fa-times"></i> Clear
                </button>
            <?php endif; ?>
        </div>
        
        <!-- Pagination Top -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <div class="pagination-info">
                Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $limit, $totalRecords); ?> of <?php echo $totalRecords; ?> expenses
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
                        <th>Expense Name</th>
                        <th>Description</th>
                        <th>Amount</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($expenses)): ?>
                        <?php foreach ($expenses as $index => $expense): ?>
                            <tr>
                                <td><?php echo $offset + $index + 1; ?></td>
                                <td><?php echo date('d-m-Y', strtotime($expense['date'])); ?></td>
                                <td><strong><?php echo htmlspecialchars($expense['transaction_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($expense['description'] ?? '-'); ?></td>
                                <td><strong style="color: #e53e3e;">₹<?php echo number_format($expense['amount'], 2); ?></strong></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="action-btn btn-edit" onclick="editExpense(<?php echo $expense['id']; ?>)" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="action-btn btn-delete" onclick="deleteExpense(<?php echo $expense['id']; ?>, '<?php echo htmlspecialchars($expense['transaction_name']); ?>')" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px;">
                                <i class="fas fa-inbox" style="font-size: 48px; color: #ddd; margin-bottom: 10px;"></i>
                                <p style="color: #999;">No expenses found</p>
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
                Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $limit, $totalRecords); ?> of <?php echo $totalRecords; ?> expenses
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

<!-- Add Expense Modal -->
<div id="addExpenseModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="hideModal('addExpenseModal')">&times;</span>
        <h2><i class="fas fa-plus-circle"></i> Add Expense</h2>
        <form id="addExpenseForm" onsubmit="addExpense(event)">
            <div class="form-group">
                <label for="expenseDate">Date *</label>
                <input type="date" id="expenseDate" name="date" required value="<?php echo date('Y-m-d'); ?>">
            </div>
            
            <div class="form-group">
                <label for="expenseName">Expense Name *</label>
                <input type="text" id="expenseName" name="transaction_name" required placeholder="Enter expense name">
            </div>
            
            <div class="form-group">
                <label for="expenseAmount">Amount (₹) *</label>
                <input type="number" id="expenseAmount" name="amount" step="0.01" required placeholder="0.00" min="0">
            </div>
            
            <div class="form-group">
                <label for="expenseDescription">Description</label>
                <textarea id="expenseDescription" name="description" rows="3" placeholder="Enter expense description"></textarea>
            </div>
            
            <input type="hidden" name="transaction_type" value="debit">
            
            <div class="form-actions">
                <button type="button" onclick="hideModal('addExpenseModal')" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> Add Expense
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showAddExpenseModal() {
    showModal('addExpenseModal');
    document.getElementById('addExpenseForm').reset();
    document.getElementById('expenseDate').value = new Date().toISOString().split('T')[0];
}

function addExpense(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    
    fetch(apiUrl('api/transactions.php'), {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            hideModal('addExpenseModal');
            showSuccessMessage('Expense added successfully!');
            setTimeout(() => location.reload(), 500);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while adding the expense.');
    });
}

function editExpense(expenseId) {
    // Implement edit functionality
    console.log('Edit expense:', expenseId);
}

function deleteExpense(expenseId, expenseName) {
    if (!confirm(`Are you sure you want to delete expense "${expenseName}"?`)) {
        return;
    }
    
    fetch(apiUrl(`api/transactions.php?id=${expenseId}`), {
        method: 'DELETE'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccessMessage('Expense deleted successfully!');
            setTimeout(() => location.reload(), 500);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while deleting the expense.');
    });
}

function clearExpenseSearch() {
    const url = new URL(window.location);
    url.searchParams.delete('search');
    url.searchParams.delete('date_from');
    url.searchParams.delete('date_to');
    url.searchParams.delete('p');
    window.location.href = url.toString();
}

function changePage(page) {
    const url = new URL(window.location);
    url.searchParams.set('p', page);
    url.searchParams.delete('page');
    window.location.href = url.toString();
}

// Initialize search and date filters
(function() {
    const searchInput = document.getElementById('expenseSearch');
    const dateFrom = document.getElementById('dateFrom');
    const dateTo = document.getElementById('dateTo');
    
    function applyFilters() {
        const url = new URL(window.location);
        if (searchInput.value) {
            url.searchParams.set('search', searchInput.value);
        } else {
            url.searchParams.delete('search');
        }
        if (dateFrom.value) {
            url.searchParams.set('date_from', dateFrom.value);
        } else {
            url.searchParams.delete('date_from');
        }
        if (dateTo.value) {
            url.searchParams.set('date_to', dateTo.value);
        } else {
            url.searchParams.delete('date_to');
        }
        url.searchParams.delete('p');
        window.location.href = url.toString();
    }
    
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(applyFilters, 500);
        });
    }
    
    if (dateFrom) {
        dateFrom.addEventListener('change', applyFilters);
    }
    
    if (dateTo) {
        dateTo.addEventListener('change', applyFilters);
    }
})();
</script>

