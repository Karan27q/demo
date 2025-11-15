<?php
// Define the base path
$basePath = dirname(__DIR__);
require_once $basePath . '/config/database.php';
require_once $basePath . '/config/interest_calculator.php';

try {
    $pdo = getDBConnection();
    
    // Get comprehensive summary statistics
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM customers");
    $totalCustomers = $stmt->fetch()['total'];
    
    // Get customers with active loans
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT customer_id) as count 
        FROM loans 
        WHERE status = 'active'
    ");
    $customersWithLoans = $stmt->fetch()['count'];
    
    // Total loans (all statuses)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM loans");
    $totalLoans = $stmt->fetch()['total'];
    
    // Total principal amount from all active loans
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(principal_amount), 0) as total 
        FROM loans 
        WHERE status = 'active'
    ");
    $totalPrincipal = $stmt->fetch()['total'];
    
    // Total interest collected from all customers
    $stmt = $pdo->query("SELECT COALESCE(SUM(interest_amount), 0) as total FROM interest");
    $totalInterestCollected = $stmt->fetch()['total'];
    
    // New customers today
    $stmt = $pdo->query("
        SELECT COUNT(*) as total 
        FROM customers 
        WHERE DATE(created_at) = CURDATE()
    ");
    $newCustomersToday = $stmt->fetch()['total'];
    
    // New customers this month
    $stmt = $pdo->query("
        SELECT COUNT(*) as total 
        FROM customers 
        WHERE MONTH(created_at) = MONTH(CURDATE()) 
        AND YEAR(created_at) = YEAR(CURDATE())
    ");
    $newCustomersThisMonth = $stmt->fetch()['total'];
    
    // Get customers with pagination
    $page = isset($_GET['p']) ? (int)$_GET['p'] : (isset($_GET['page']) ? (int)$_GET['page'] : 1);
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $whereClause = '';
    $params = [];
    
    if (!empty($search)) {
        $whereClause = " AND (c.name LIKE ? OR c.mobile LIKE ? OR c.customer_no LIKE ? OR l.loan_no LIKE ?)";
        $searchTerm = "%$search%";
        $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
    }
    
    // Count query - count loans by LOAN NUMBER only (not by customer)
    $countQuery = "
        SELECT COUNT(DISTINCT l.loan_no) as total 
        FROM loans l
        INNER JOIN customers c ON l.customer_id = c.id
        WHERE l.id IN (
            SELECT MAX(id) 
            FROM loans 
            GROUP BY loan_no
        )
    ";
    if (!empty($search)) {
        $countQuery .= " AND (c.name LIKE ? OR c.mobile LIKE ? OR c.customer_no LIKE ? OR l.loan_no LIKE ?)";
        $countParams = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
    } else {
        $countParams = [];
    }
    
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute($countParams);
    $totalRecords = $stmt->fetch()['total'];
    $totalPages = ceil($totalRecords / $limit);
    
    // Fetch loans by LOAN NUMBER only - each unique loan_no appears once
    // This ensures loans are fetched by loan number, not by customer name or other criteria
    $query = "
        SELECT 
            c.id as customer_id,
            c.customer_no,
            c.name as customer_name,
            c.mobile,
            c.address,
            l.id as loan_id,
            l.loan_no,
            l.loan_date,
            l.principal_amount,
            l.interest_rate,
            l.status as loan_status,
            l.pledge_items,
            (SELECT COALESCE(SUM(interest_amount), 0) 
             FROM interest 
             WHERE loan_id = l.id) as interest_paid,
            l.created_at as loan_created_at
        FROM loans l
        INNER JOIN customers c ON l.customer_id = c.id
        WHERE l.id IN (
            SELECT MAX(id) 
            FROM loans 
            GROUP BY loan_no
        )
    ";
    
    if (!empty($whereClause)) {
        $query .= " $whereClause ";
    }
    
    $query .= " ORDER BY l.loan_no ASC LIMIT ? OFFSET ?";
    
    $stmt = $pdo->prepare($query);
    // Bind all parameters including limit and offset
    $paramIndex = 1;
    foreach ($params as $value) {
        $stmt->bindValue($paramIndex++, $value);
    }
    // Bind limit and offset as integers
    $stmt->bindValue($paramIndex++, (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue($paramIndex++, (int)$offset, PDO::PARAM_INT);
    $stmt->execute();
    $loans = $stmt->fetchAll();
    
    // Debug: Log query and results
    // error_log("Customer query: " . $query);
    // error_log("Customer params: " . print_r($params, true));
    // error_log("Customers found: " . count($customers));
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    error_log("Customer page error: " . $e->getMessage());
    // Initialize empty arrays to prevent undefined variable errors
    $loans = [];
    $customers = [];
    $totalCustomers = 0;
    $customersWithLoans = 0;
    $totalLoans = 0;
    $totalPrincipal = 0;
    $totalInterestCollected = 0;
    $newCustomersThisMonth = 0;
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
            <div class="card-icon customer">
                <i class="fas fa-users"></i>
            </div>
            <div class="card-content">
                <div class="card-number"><?php echo number_format($totalCustomers ?? 0); ?></div>
                <div class="card-label">Total Customers</div>
            </div>
        </div>
        
        <div class="dashboard-card">
            <div class="card-icon loan">
                <i class="fas fa-hand-holding-usd"></i>
            </div>
            <div class="card-content">
                <div class="card-number"><?php echo number_format($customersWithLoans ?? 0); ?></div>
                <div class="card-label">With Active Loans</div>
            </div>
        </div>
        
        <div class="dashboard-card">
            <div class="card-icon principal">
                <i class="fas fa-coins"></i>
            </div>
            <div class="card-content">
                <div class="card-number"><?php echo number_format($totalLoans ?? 0); ?></div>
                <div class="card-label">Total Loans</div>
            </div>
        </div>
        
        <div class="dashboard-card">
            <div class="card-icon interest">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="card-content">
                <div class="card-number">₹<?php echo number_format($totalPrincipal ?? 0, 2); ?></div>
                <div class="card-label">Total Principal</div>
            </div>
        </div>
        
        <div class="dashboard-card">
            <div class="card-icon recovery">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="card-content">
                <div class="card-number">₹<?php echo number_format($totalInterestCollected ?? 0, 2); ?></div>
                <div class="card-label">Interest Collected</div>
            </div>
        </div>
        
        <div class="dashboard-card">
            <div class="card-icon outstanding">
                <i class="fas fa-user-plus"></i>
            </div>
            <div class="card-content">
                <div class="card-number"><?php echo number_format($newCustomersThisMonth ?? 0); ?></div>
                <div class="card-label">New This Month</div>
            </div>
        </div>
    </div>
    
    <!-- Main Content Card -->
    <div class="content-card">
        <div class="section-header">
            <h2 class="page-title">
                <i class="fas fa-user-friends"></i> Customer Loans Management
            </h2>
            <div class="section-actions">
                <button class="btn-primary" onclick="showAddCustomerModal()">
                    <i class="fas fa-plus"></i> Add New Customer
                </button>
            </div>
        </div>
        
        <div class="search-section">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search by customer name, mobile, customer number, or loan number" id="customerSearch" value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <?php if (!empty($search)): ?>
                <button class="clear-btn" onclick="clearCustomerSearch()">
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
                        <th>Interest Paid</th>
                        <th>Outstanding</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (isset($loans) && !empty($loans)): ?>
                        <?php 
                        // Debug: Uncomment to see data
                        // echo "<!-- Debug: Found " . count($loans) . " loans -->";
                        ?>
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
                                    <?php if ($loan['interest_paid'] > 0): ?>
                                        <strong style="color: #38a169;">₹<?php echo number_format($loan['interest_paid'], 2); ?></strong>
                                    <?php else: ?>
                                        <span style="color: #999;">₹0.00</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    if ($loan['loan_status'] === 'active') {
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
                                    <?php if ($loan['loan_status'] === 'active'): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Closed</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="action-btn btn-view" onclick="viewLoanDetails(<?php echo $loan['loan_id']; ?>)" title="View Loan">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="action-btn btn-edit" onclick="editCustomer(<?php echo $loan['customer_id']; ?>)" title="Edit Customer">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="13" style="text-align: center; padding: 40px;">
                                <i class="fas fa-inbox" style="font-size: 48px; color: #ddd; margin-bottom: 10px;"></i>
                                <p style="color: #999;">No loans found</p>
                                <?php if (isset($totalRecords)): ?>
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

<!-- Add Customer Modal -->
<div id="addCustomerModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="hideModal('addCustomerModal')">&times;</span>
        <h2><i class="fas fa-user-plus"></i> Add New Customer</h2>
        <form id="addCustomerForm" onsubmit="addCustomer(event)">
            <div class="form-group">
                <label for="customerNo">Customer Number *</label>
                <input type="text" id="customerNo" name="customer_no" required placeholder="Enter customer number">
            </div>
            <div class="form-group">
                <label for="customerName">Customer Name *</label>
                <input type="text" id="customerName" name="name" required placeholder="Enter customer name">
            </div>
            <div class="form-group">
                <label for="customerMobile">Mobile Number *</label>
                <input type="text" id="customerMobile" name="mobile" required placeholder="Enter mobile number" maxlength="15">
            </div>
            <div class="form-group">
                <label for="customerAddress">Address</label>
                <textarea id="customerAddress" name="address" rows="3" placeholder="Enter customer address"></textarea>
            </div>
            <div class="form-actions">
                <button type="button" onclick="hideModal('addCustomerModal')" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> Add Customer
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Customer Modal -->
<div id="editCustomerModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="hideModal('editCustomerModal')">&times;</span>
        <h2><i class="fas fa-user-edit"></i> Edit Customer</h2>
        <form id="editCustomerForm" onsubmit="updateCustomer(event)">
            <input type="hidden" id="editCustomerId" name="id">
            <div class="form-group">
                <label for="editCustomerNo">Customer Number *</label>
                <input type="text" id="editCustomerNo" name="customer_no" required>
            </div>
            <div class="form-group">
                <label for="editCustomerName">Customer Name *</label>
                <input type="text" id="editCustomerName" name="name" required>
            </div>
            <div class="form-group">
                <label for="editCustomerMobile">Mobile Number *</label>
                <input type="text" id="editCustomerMobile" name="mobile" required maxlength="15">
            </div>
            <div class="form-group">
                <label for="editCustomerAddress">Address</label>
                <textarea id="editCustomerAddress" name="address" rows="3"></textarea>
            </div>
            <div class="form-actions">
                <button type="button" onclick="hideModal('editCustomerModal')" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> Update Customer
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Search functionality - works both on initial load and dynamic injection
(function() {
    function initCustomerSearch() {
        const searchInput = document.getElementById('customerSearch');
        if (searchInput && !searchInput.dataset.listenerAttached) {
            searchInput.dataset.listenerAttached = 'true';
            searchInput.addEventListener('input', function() {
                clearTimeout(this.searchTimeout);
                this.searchTimeout = setTimeout(() => {
                    const search = this.value;
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
        document.addEventListener('DOMContentLoaded', initCustomerSearch);
    } else {
        initCustomerSearch();
    }
    
    window.initCustomerSearch = initCustomerSearch;
})();

function clearCustomerSearch() {
    const searchInput = document.getElementById('customerSearch');
    if (searchInput) {
        searchInput.value = '';
        const url = new URL(window.location);
        const currentPage = url.searchParams.get('page');
        if (currentPage) {
            url.searchParams.set('page', currentPage);
        }
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

function editCustomer(customerId) {
    fetch(apiUrl(`api/customers.php?id=${customerId}`))
        .then(response => response.json())
        .then(data => {
            if (data.success && data.customer) {
                const customer = data.customer;
                document.getElementById('editCustomerId').value = customer.id;
                document.getElementById('editCustomerNo').value = customer.customer_no;
                document.getElementById('editCustomerName').value = customer.name;
                document.getElementById('editCustomerMobile').value = customer.mobile;
                document.getElementById('editCustomerAddress').value = customer.address || '';
                showModal('editCustomerModal');
            } else {
                alert('Error loading customer data');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while loading customer data.');
        });
}

function updateCustomer(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const customerId = formData.get('id');
    
    // Convert FormData to URL-encoded format for PUT request
    const data = new URLSearchParams();
    data.append('id', customerId);
    data.append('name', formData.get('name'));
    data.append('mobile', formData.get('mobile'));
    data.append('address', formData.get('address'));
    
    fetch(apiUrl('api/customers.php'), {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: data.toString()
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            hideModal('editCustomerModal');
            showSuccessMessage('Customer updated successfully!');
            
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
        alert('An error occurred while updating the customer.');
    });
}

function viewLoanDetails(loanId) {
    // Navigate to loan details or show modal
    if (typeof window.loadPage === 'function') {
        window.loadPage('loans');
        // You can implement a loan details modal or page here
        // For now, just navigate to loans page
    } else {
        window.location.href = 'dashboard.php?page=loans';
    }
}

function deleteCustomer(customerId, customerName) {
    if (!confirm(`Are you sure you want to delete customer "${customerName}"?\n\nThis action cannot be undone.`)) {
        return;
    }
    
    fetch(apiUrl(`api/customers.php?id=${customerId}`), {
        method: 'DELETE'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccessMessage('Customer deleted successfully!');
            
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
        alert('An error occurred while deleting the customer.');
    });
}
</script>

<style>
.dashboard-page {
    padding: 0;
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

.action-buttons {
    display: flex;
    gap: 8px;
}

.action-btn {
    padding: 6px 12px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.2s;
}

.btn-edit {
    background: #4299e1;
    color: white;
}

.btn-edit:hover {
    background: #3182ce;
}

.btn-delete {
    background: #f56565;
    color: white;
}

.btn-delete:hover {
    background: #e53e3e;
}

.btn-view {
    background: #48bb78;
    color: white;
}

.btn-view:hover {
    background: #38a169;
}

.badge-info {
    background: #bee3f8;
    color: #2c5282;
}

.badge-warning {
    background: #feebc8;
    color: #c05621;
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

.badge-secondary {
    background: #e2e8f0;
    color: #718096;
}

.pagination-page {
    padding: 0 15px;
    color: #718096;
    font-weight: 500;
}
</style>
