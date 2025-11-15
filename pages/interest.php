<?php
// Define the base path
$basePath = dirname(__DIR__);
require_once $basePath . '/config/database.php';
require_once $basePath . '/config/interest_calculator.php';

try {
    $pdo = getDBConnection();
    
    // Get summary statistics
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM interest");
    $totalInterestRecords = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COALESCE(SUM(interest_amount), 0) as total FROM interest");
    $totalInterestCollected = $stmt->fetch()['total'];
    
    // Get interest collected this month
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(interest_amount), 0) as total 
        FROM interest 
        WHERE MONTH(interest_date) = MONTH(CURDATE()) 
        AND YEAR(interest_date) = YEAR(CURDATE())
    ");
    $monthlyInterest = $stmt->fetch()['total'];
    
    // Calculate expected interest and outstanding using manual days (30-day periods)
    $stmt = $pdo->query("
        SELECT 
            l.id,
            l.principal_amount,
            l.interest_rate,
            l.loan_date,
            COALESCE(SUM(i.interest_amount), 0) as interest_paid
        FROM loans l
        LEFT JOIN interest i ON l.id = i.loan_id
        WHERE l.status = 'active'
        GROUP BY l.id
    ");
    $activeLoans = $stmt->fetchAll();
    
    $totalExpectedInterest = 0;
    $totalOutstandingInterest = 0;
    foreach ($activeLoans as $loan) {
        // Calculate expected interest using daily simple-interest method
        $expectedInterest = calculateExpectedInterestByCalendarMonths(
            $loan['principal_amount'],
            $loan['interest_rate'],
            $loan['loan_date']
        );
        $totalExpectedInterest += $expectedInterest;
        
        $outstanding = max(0, $expectedInterest - $loan['interest_paid']);
        $totalOutstandingInterest += $outstanding;
    }
    
    // Get interest records with pagination and date filters
    $page = isset($_GET['p']) ? (int)$_GET['p'] : (isset($_GET['page']) ? (int)$_GET['page'] : 1);
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $fromDate = isset($_GET['from_date']) ? $_GET['from_date'] : '';
    $toDate = isset($_GET['to_date']) ? $_GET['to_date'] : '';
    
    $whereClause = '';
    $params = [];
    
    // Build WHERE clause with search and date filters
    $conditions = [];
    
    if (!empty($search)) {
        $conditions[] = "(c.name LIKE ? OR c.mobile LIKE ? OR l.loan_no LIKE ? OR c.customer_no LIKE ?)";
        $searchTerm = "%$search%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }
    
    if (!empty($fromDate)) {
        $conditions[] = "DATE(i.interest_date) >= ?";
        $params[] = $fromDate;
    }
    
    if (!empty($toDate)) {
        $conditions[] = "DATE(i.interest_date) <= ?";
        $params[] = $toDate;
    }
    
    if (!empty($conditions)) {
        $whereClause = "WHERE " . implode(" AND ", $conditions);
    }
    
    // Count query with same filters
    $countQuery = "
        SELECT COUNT(*) as total 
        FROM interest i 
        INNER JOIN loans l ON i.loan_id = l.id 
        INNER JOIN customers c ON l.customer_id = c.id 
        $whereClause
    ";
    
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute($params);
    $totalRecords = $stmt->fetch()['total'];
    $totalPages = ceil($totalRecords / $limit);
    
    $query = "
        SELECT i.*, l.loan_no, l.principal_amount, l.interest_rate, c.name as customer_name, c.mobile, c.customer_no
        FROM interest i 
        INNER JOIN loans l ON i.loan_id = l.id 
        INNER JOIN customers c ON l.customer_id = c.id 
        $whereClause 
        ORDER BY i.interest_date DESC, i.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $pdo->prepare($query);
    $paramIndex = 1;
    foreach ($params as $value) {
        $stmt->bindValue($paramIndex++, $value);
    }
    // Bind limit and offset as integers
    $stmt->bindValue($paramIndex++, (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue($paramIndex++, (int)$offset, PDO::PARAM_INT);
    $stmt->execute();
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

<div class="dashboard-page">
    <!-- Financial Summary Cards -->
    <div class="dashboard-cards">
        <div class="dashboard-card">
            <div class="card-icon customer">
                <i class="fas fa-file-invoice-dollar"></i>
            </div>
            <div class="card-content">
                <div class="card-number"><?php echo number_format($totalInterestRecords ?? 0); ?></div>
                <div class="card-label">Total Records</div>
            </div>
        </div>
        
        <div class="dashboard-card">
            <div class="card-icon interest">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="card-content">
                <div class="card-number">₹<?php echo number_format($totalInterestCollected, 2); ?></div>
                <div class="card-label">Total Collected</div>
            </div>
        </div>
        
        <div class="dashboard-card">
            <div class="card-icon recovery">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <div class="card-content">
                <div class="card-number">₹<?php echo number_format($monthlyInterest, 2); ?></div>
                <div class="card-label">This Month</div>
            </div>
        </div>
        
        <div class="dashboard-card">
            <div class="card-icon principal">
                <i class="fas fa-calculator"></i>
            </div>
            <div class="card-content">
                <div class="card-number">₹<?php echo number_format($totalExpectedInterest, 2); ?></div>
                <div class="card-label">Expected Interest (30-day)</div>
            </div>
        </div>
        
        <div class="dashboard-card">
            <div class="card-icon outstanding">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="card-content">
                <div class="card-number">₹<?php echo number_format($totalOutstandingInterest, 2); ?></div>
                <div class="card-label">Outstanding (30-day)</div>
            </div>
        </div>
    </div>
    
    <!-- Main Content Card -->
    <div class="content-card">
        <div class="section-header">
            <h2 class="page-title">
                <i class="fas fa-money-bill-wave"></i> Interest Management
            </h2>
            <div class="section-actions">
                <button class="btn-primary" onclick="showAddInterestModal()">
                    <i class="fas fa-plus"></i> Add Interest
                </button>
            </div>
        </div>
        
        <div class="search-section">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search by customer name, mobile, loan number, or customer number" id="interestSearch" value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="filter-row" style="display: flex; gap: 10px; align-items: center; margin-top: 10px;">
                <div style="display: flex; align-items: center; gap: 5px;">
                    <label for="fromDate" style="font-size: 14px; color: #666;">From:</label>
                    <input type="date" id="fromDate" class="filter-select" style="padding: 6px 10px;" value="<?php echo htmlspecialchars($fromDate); ?>" onchange="filterByDate()">
                </div>
                <div style="display: flex; align-items: center; gap: 5px;">
                    <label for="toDate" style="font-size: 14px; color: #666;">To:</label>
                    <input type="date" id="toDate" class="filter-select" style="padding: 6px 10px;" value="<?php echo htmlspecialchars($toDate); ?>" onchange="filterByDate()">
                </div>
                <?php if (!empty($search) || !empty($fromDate) || !empty($toDate)): ?>
                    <button class="clear-btn" onclick="clearInterestSearch()">
                        <i class="fas fa-times"></i> Clear
                    </button>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Pagination Top -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <div class="pagination-info">
                Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $limit, $totalRecords); ?> of <?php echo $totalRecords; ?> records
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
                        <th>Loan No</th>
                        <th>Customer No</th>
                        <th>Customer Name</th>
                        <th>Mobile No.</th>
                        <th>Principal</th>
                        <th>Interest Rate</th>
                        <th>Interest Amount</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (isset($interestRecords) && !empty($interestRecords)): ?>
                        <?php foreach ($interestRecords as $index => $record): ?>
                            <tr>
                                <td><?php echo $offset + $index + 1; ?></td>
                                <td><?php echo date('d-m-Y', strtotime($record['interest_date'])); ?></td>
                                <td><strong><?php echo htmlspecialchars($record['loan_no']); ?></strong></td>
                                <td><strong><?php echo htmlspecialchars($record['customer_no']); ?></strong></td>
                                <td><?php echo htmlspecialchars($record['customer_name']); ?></td>
                                <td><?php echo htmlspecialchars($record['mobile']); ?></td>
                                <td><strong>₹<?php echo number_format($record['principal_amount'], 2); ?></strong></td>
                                <td><?php echo $record['interest_rate']; ?>%</td>
                                <td><strong style="color: #38a169;">₹<?php echo number_format($record['interest_amount'], 2); ?></strong></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="action-btn btn-edit" onclick="editInterest(<?php echo $record['id']; ?>)" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="action-btn btn-delete" onclick="deleteInterest(<?php echo $record['id']; ?>, '<?php echo htmlspecialchars($record['loan_no']); ?>')" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" style="text-align: center; padding: 40px;">
                                <i class="fas fa-inbox" style="font-size: 48px; color: #ddd; margin-bottom: 10px;"></i>
                                <p style="color: #999;">No interest records found</p>
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
                Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $limit, $totalRecords); ?> of <?php echo $totalRecords; ?> records
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
                    <label for="interestDate">Interest Date *</label>
                    <input type="date" id="interestDate" name="interest_date" required value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label for="interestAmount">Interest Amount (₹) *</label>
                    <input type="number" id="interestAmount" name="interest_amount" step="0.01" required placeholder="0.00">
                </div>
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

<!-- Edit Interest Modal -->
<div id="editInterestModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="hideModal('editInterestModal')">&times;</span>
        <h2><i class="fas fa-edit"></i> Edit Interest Record</h2>
        <form id="editInterestForm" onsubmit="updateInterest(event)">
            <input type="hidden" id="editInterestId" name="id">
            <div class="form-group">
                <label for="editInterestLoanId">Loan *</label>
                <select id="editInterestLoanId" name="loan_id" required>
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
                    <label for="editInterestDate">Interest Date *</label>
                    <input type="date" id="editInterestDate" name="interest_date" required>
                </div>
                <div class="form-group">
                    <label for="editInterestAmount">Interest Amount (₹) *</label>
                    <input type="number" id="editInterestAmount" name="interest_amount" step="0.01" required>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="button" onclick="hideModal('editInterestModal')" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> Update Interest
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Initialize interest page
(function() {
    function initInterestPage() {
        const searchInput = document.getElementById('interestSearch');
        if (searchInput && !searchInput.dataset.listenerAttached) {
            searchInput.dataset.listenerAttached = 'true';
            searchInput.addEventListener('input', function() {
                clearTimeout(this.searchTimeout);
                this.searchTimeout = setTimeout(() => {
                    const search = this.value;
                    const fromDate = document.getElementById('fromDate')?.value || '';
                    const toDate = document.getElementById('toDate')?.value || '';
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
                    
                    if (fromDate) {
                        url.searchParams.set('from_date', fromDate);
                    } else {
                        url.searchParams.delete('from_date');
                    }
                    
                    if (toDate) {
                        url.searchParams.set('to_date', toDate);
                    } else {
                        url.searchParams.delete('to_date');
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
        document.addEventListener('DOMContentLoaded', initInterestPage);
    } else {
        initInterestPage();
    }
    
    window.initInterestPage = initInterestPage;
})();

function clearInterestSearch() {
    const searchInput = document.getElementById('interestSearch');
    const fromDate = document.getElementById('fromDate');
    const toDate = document.getElementById('toDate');
    
    if (searchInput) searchInput.value = '';
    if (fromDate) fromDate.value = '';
    if (toDate) toDate.value = '';
    
    const url = new URL(window.location);
    const currentPage = url.searchParams.get('page');
    if (currentPage) {
        url.searchParams.set('page', currentPage);
    }
    url.searchParams.delete('search');
    url.searchParams.delete('from_date');
    url.searchParams.delete('to_date');
    url.searchParams.delete('p');
    
    if (typeof window.loadPage === 'function' && currentPage) {
        window.history.pushState({ page: currentPage }, '', url.toString());
        window.loadPage(currentPage, false);
    } else {
        window.location.href = url.toString();
    }
}

function filterByDate() {
    const fromDate = document.getElementById('fromDate')?.value || '';
    const toDate = document.getElementById('toDate')?.value || '';
    const search = document.getElementById('interestSearch')?.value || '';
    
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
    
    if (fromDate) {
        url.searchParams.set('from_date', fromDate);
    } else {
        url.searchParams.delete('from_date');
    }
    
    if (toDate) {
        url.searchParams.set('to_date', toDate);
    } else {
        url.searchParams.delete('to_date');
    }
    
    url.searchParams.delete('p');
    
    if (typeof window.loadPage === 'function' && currentPage) {
        window.history.pushState({ page: currentPage }, '', url.toString());
        window.loadPage(currentPage, false);
    } else {
        window.location.href = url.toString();
    }
}

function updateInterestLoanDetails() {
    // You can add loan details display here
}

function editInterest(interestId) {
    fetch(apiUrl(`api/interest.php?id=${interestId}`))
        .then(response => response.json())
        .then(data => {
            if (data.success && data.interest) {
                const i = data.interest;
                document.getElementById('editInterestId').value = i.id;
                document.getElementById('editInterestLoanId').value = i.loan_id;
                document.getElementById('editInterestDate').value = i.interest_date;
                document.getElementById('editInterestAmount').value = i.interest_amount;
                showModal('editInterestModal');
            } else {
                alert('Error loading interest data');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while loading interest data.');
        });
}

function updateInterest(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const interestId = formData.get('id');
    
    const data = new URLSearchParams();
    data.append('id', interestId);
    data.append('loan_id', formData.get('loan_id'));
    data.append('interest_date', formData.get('interest_date'));
    data.append('interest_amount', formData.get('interest_amount'));
    
    fetch(apiUrl('api/interest.php'), {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: data.toString()
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            hideModal('editInterestModal');
            showSuccessMessage('Interest updated successfully!');
            
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
        alert('An error occurred while updating the interest.');
    });
}

function deleteInterest(interestId, loanNo) {
    if (!confirm(`Are you sure you want to delete interest record for loan "${loanNo}"?\n\nThis action cannot be undone.`)) {
        return;
    }
    
    fetch(apiUrl(`api/interest.php?id=${interestId}`), {
        method: 'DELETE'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccessMessage('Interest record deleted successfully!');
            
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
        alert('An error occurred while deleting the interest record.');
    });
}
</script>
