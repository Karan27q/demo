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
        $whereClause = " AND (c.name LIKE ? OR c.mobile LIKE ? OR c.customer_no LIKE ?)";
        $searchTerm = "%$search%";
        $params = [$searchTerm, $searchTerm, $searchTerm];
    }
    
    // Count query - count customers by CUSTOMER NUMBER (not by loan)
    $countQuery = "
        SELECT COUNT(DISTINCT c.customer_no) as total 
        FROM customers c
        WHERE 1=1
    ";
    if (!empty($search)) {
        $countQuery .= " AND (c.name LIKE ? OR c.mobile LIKE ? OR c.customer_no LIKE ?)";
        $countParams = [$searchTerm, $searchTerm, $searchTerm];
    } else {
        $countParams = [];
    }
    
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute($countParams);
    $totalRecords = $stmt->fetch()['total'];
    $totalPages = ceil($totalRecords / $limit);
    
    // Fetch customers by CUSTOMER NUMBER - each customer appears once
    $query = "
        SELECT 
            c.id as customer_id,
            c.customer_no,
            c.name as customer_name,
            c.mobile,
            c.address,
            c.place,
            c.customer_photo,
            c.created_at,
            COUNT(DISTINCT l.id) as total_loans,
            SUM(CASE WHEN l.status = 'active' THEN 1 ELSE 0 END) as active_loans,
            COALESCE(SUM(CASE WHEN l.status = 'active' THEN l.principal_amount ELSE 0 END), 0) as total_principal
        FROM customers c
        LEFT JOIN loans l ON c.id = l.customer_id
        WHERE 1=1
    ";
    
    if (!empty($whereClause)) {
        $query .= " $whereClause ";
    }
    
    $query .= " GROUP BY c.id, c.customer_no, c.name, c.mobile, c.address, c.place, c.customer_photo, c.created_at";
    $query .= " ORDER BY c.customer_no ASC LIMIT ? OFFSET ?";
    
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
    $customers = $stmt->fetchAll();
    
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
                Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $limit, $totalRecords); ?> of <?php echo $totalRecords; ?> customers
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
                        <th style="width: 50px;">S.No</th>
                        <th style="width: 80px;">Photo</th>
                        <th>Customer No</th>
                        <th>Customer Name</th>
                        <th>Mobile No.</th>
                        <th>Address</th>
                        <th>Place</th>
                        <th>Total Loans</th>
                        <th>Active Loans</th>
                        <th>Total Principal</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (isset($customers) && !empty($customers)): ?>
                        <?php foreach ($customers as $index => $customer): ?>
                            <?php
                            // Get customer folder name from customer_photo path or generate from name
                            $customerFolderName = '';
                            $photoPath = '';
                            if (!empty($customer['customer_photo'])) {
                                // Extract folder name from path like "uploads/customer_name/customer_photo.jpg"
                                $pathParts = explode('/', $customer['customer_photo']);
                                if (count($pathParts) >= 2) {
                                    $customerFolderName = $pathParts[1];
                                    $photoPath = $customer['customer_photo'];
                                }
                            } else {
                                // Generate folder name from customer name
                                $customerFolderName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $customer['customer_name']);
                                $customerFolderName = str_replace(' ', '_', $customerFolderName);
                                $customerFolderName = strtolower($customerFolderName);
                                // Try to find photo in customer folder
                                $possiblePhotoPath = 'uploads/' . $customerFolderName . '/customer_photo.jpg';
                                if (file_exists($basePath . '/' . $possiblePhotoPath)) {
                                    $photoPath = $possiblePhotoPath;
                                } else {
                                    // Try other extensions
                                    $extensions = ['png', 'jpeg', 'gif'];
                                    foreach ($extensions as $ext) {
                                        $testPath = 'uploads/' . $customerFolderName . '/customer_photo.' . $ext;
                                        if (file_exists($basePath . '/' . $testPath)) {
                                            $photoPath = $testPath;
                                            break;
                                        }
                                    }
                                }
                            }
                            ?>
                            <tr data-customer-id="<?php echo $customer['customer_id']; ?>">
                                <td><?php echo $offset + $index + 1; ?></td>
                                <td style="text-align: center;">
                                    <?php if (!empty($photoPath) && file_exists($basePath . '/' . $photoPath)): ?>
                                        <img src="<?php echo htmlspecialchars($photoPath); ?>" 
                                             alt="<?php echo htmlspecialchars($customer['customer_name']); ?>" 
                                             style="width: 50px; height: 50px; object-fit: cover; border-radius: 50%; border: 2px solid #ddd;">
                                    <?php else: ?>
                                        <div style="width: 50px; height: 50px; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                                            <i class="fas fa-user" style="color: #999; font-size: 20px;"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo htmlspecialchars($customer['customer_no']); ?></strong></td>
                                <td><?php echo htmlspecialchars($customer['customer_name']); ?></td>
                                <td><?php echo htmlspecialchars($customer['mobile']); ?></td>
                                <td><?php echo htmlspecialchars($customer['address'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($customer['place'] ?? ''); ?></td>
                                <td><strong><?php echo $customer['total_loans'] ?? 0; ?></strong></td>
                                <td>
                                    <?php if (($customer['active_loans'] ?? 0) > 0): ?>
                                        <span class="badge badge-success"><?php echo $customer['active_loans']; ?></span>
                                    <?php else: ?>
                                        <span style="color: #999;">0</span>
                                    <?php endif; ?>
                                </td>
                                <td><strong>₹<?php echo number_format($customer['total_principal'] ?? 0, 2); ?></strong></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="action-btn btn-view" onclick="viewCustomer(<?php echo $customer['customer_id']; ?>)" title="View Customer">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="action-btn btn-edit" onclick="editCustomer(<?php echo $customer['customer_id']; ?>)" title="Edit Customer">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="action-btn btn-delete" onclick="deleteCustomer(<?php echo $customer['customer_id']; ?>, '<?php echo htmlspecialchars($customer['customer_no']); ?>')" title="Delete Customer">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="11" style="text-align: center; padding: 40px;">
                                <i class="fas fa-inbox" style="font-size: 48px; color: #ddd; margin-bottom: 10px;"></i>
                                <p style="color: #999;">No customers found</p>
                                <?php if (isset($totalRecords)): ?>
                                    <p style="color: #999; font-size: 12px; margin-top: 10px;">
                                        Total customers in database: <?php echo $totalRecords; ?>
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
                Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $limit, $totalRecords); ?> of <?php echo $totalRecords; ?> customers
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
    <div class="modal-content" style="max-width: 900px;">
        <span class="close" onclick="hideModal('addCustomerModal')">&times;</span>
        <h2><i class="fas fa-user-plus"></i> Customer Creation</h2>
        <form id="addCustomerForm" onsubmit="addCustomer(event)" enctype="multipart/form-data">
            <div class="form-row">
                <div class="form-group">
                    <label for="customerNo">Customer No *</label>
                    <input type="text" id="customerNo" name="customer_no" required placeholder="Customer No">
                </div>
                <div class="form-group">
                    <label for="customerName">Name *</label>
                    <input type="text" id="customerName" name="name" required placeholder="Name">
                </div>
                <div class="form-group">
                    <label for="customerAddress">Address</label>
                    <input type="text" id="customerAddress" name="address" placeholder="Address">
                </div>
                <div class="form-group">
                    <label for="customerPlace">Place</label>
                    <input type="text" id="customerPlace" name="place" placeholder="Place">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="customerPincode">Pincode</label>
                    <input type="text" id="customerPincode" name="pincode" placeholder="Pincode" maxlength="10">
                </div>
                <div class="form-group">
                    <label for="customerMobile">Mobile Number *</label>
                    <input type="text" id="customerMobile" name="mobile" required placeholder="Mobile Number" maxlength="15">
                </div>
                <div class="form-group">
                    <label for="customerAdditionalNumber">Additional Number</label>
                    <input type="text" id="customerAdditionalNumber" name="additional_number" placeholder="Additional Number" maxlength="15">
                </div>
                <div class="form-group">
                    <label for="customerReference">Reference</label>
                    <input type="text" id="customerReference" name="reference" placeholder="Reference">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="proofType">Select Proof Type</label>
                    <select id="proofType" name="proof_type">
                        <option value="">-- Select Proof Type --</option>
                        <option value="Aadhar">Aadhar</option>
                        <option value="PAN">PAN</option>
                        <option value="Driving License">Driving License</option>
                        <option value="Voter ID">Voter ID</option>
                        <option value="Passport">Passport</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="customerPhoto">Upload Customer Photo</label>
                    <input type="file" id="customerPhoto" name="customer_photo" accept="image/*">
                </div>
                <div class="form-group">
                    <label for="proofFile">Upload Proof</label>
                    <input type="file" id="proofFile" name="proof_file" accept="image/*,.pdf">
                </div>
            </div>
            <div class="form-actions">
                <button type="button" onclick="hideModal('addCustomerModal')" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> Submit
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

// Ensure apiUrl function is available
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

// Ensure helper functions are available
if (typeof showModal === 'undefined') {
    window.showModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'block';
        }
    };
}

if (typeof hideModal === 'undefined') {
    window.hideModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'none';
        }
    };
}

if (typeof showSuccessMessage === 'undefined') {
    window.showSuccessMessage = function(message) {
        const successDiv = document.createElement('div');
        successDiv.className = 'success-message';
        successDiv.textContent = message;
        successDiv.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #4CAF50;
            color: white;
            padding: 15px 20px;
            border-radius: 5px;
            z-index: 10000;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            animation: slideIn 0.3s ease-out;
        `;
        
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
        `;
        if (!document.getElementById('success-message-style')) {
            style.id = 'success-message-style';
            document.head.appendChild(style);
        }
        
        document.body.appendChild(successDiv);
        
        setTimeout(() => {
            successDiv.style.animation = 'slideOut 0.3s ease-in';
            setTimeout(() => {
                if (successDiv.parentNode) {
                    successDiv.parentNode.removeChild(successDiv);
                }
            }, 300);
        }, 3000);
    };
}

function editCustomer(customerId) {
    const getApiUrl = (typeof apiUrl !== 'undefined') ? apiUrl : (typeof window.apiUrl !== 'undefined') ? window.apiUrl : function(path) {
        const p = window.location && window.location.pathname || '';
        const underPages = p.indexOf('/pages/') !== -1;
        return (underPages ? '../' : '') + path;
    };
    
    const getShowModal = (typeof showModal !== 'undefined') ? showModal : (typeof window.showModal !== 'undefined') ? window.showModal : function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'block';
        }
    };
    
    fetch(getApiUrl(`api/customers.php?id=${customerId}`))
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success && data.customer) {
                const customer = data.customer;
                const editIdField = document.getElementById('editCustomerId');
                const editNoField = document.getElementById('editCustomerNo');
                const editNameField = document.getElementById('editCustomerName');
                const editMobileField = document.getElementById('editCustomerMobile');
                const editAddressField = document.getElementById('editCustomerAddress');
                
                if (editIdField) editIdField.value = customer.id || '';
                if (editNoField) editNoField.value = customer.customer_no || '';
                if (editNameField) editNameField.value = customer.name || '';
                if (editMobileField) editMobileField.value = customer.mobile || '';
                if (editAddressField) editAddressField.value = customer.address || '';
                
                // Populate additional fields if they exist
                const editPlaceField = document.getElementById('editCustomerPlace');
                const editPincodeField = document.getElementById('editCustomerPincode');
                const editAdditionalNumberField = document.getElementById('editCustomerAdditionalNumber');
                const editReferenceField = document.getElementById('editCustomerReference');
                const editProofTypeField = document.getElementById('editCustomerProofType');
                
                if (editPlaceField) editPlaceField.value = customer.place || '';
                if (editPincodeField) editPincodeField.value = customer.pincode || '';
                if (editAdditionalNumberField) editAdditionalNumberField.value = customer.additional_number || '';
                if (editReferenceField) editReferenceField.value = customer.reference || '';
                if (editProofTypeField) editProofTypeField.value = customer.proof_type || '';
                
                getShowModal('editCustomerModal');
            } else {
                alert('Error loading customer data: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error loading customer:', error);
            alert('An error occurred while loading customer data: ' + error.message);
        });
}

function updateCustomer(event) {
    event.preventDefault();
    
    const getApiUrl = (typeof apiUrl !== 'undefined') ? apiUrl : (typeof window.apiUrl !== 'undefined') ? window.apiUrl : function(path) {
        const p = window.location && window.location.pathname || '';
        const underPages = p.indexOf('/pages/') !== -1;
        return (underPages ? '../' : '') + path;
    };
    
    const getHideModal = (typeof hideModal !== 'undefined') ? hideModal : (typeof window.hideModal !== 'undefined') ? window.hideModal : function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'none';
        }
    };
    
    const getShowSuccessMessage = (typeof showSuccessMessage !== 'undefined') ? showSuccessMessage : (typeof window.showSuccessMessage !== 'undefined') ? window.showSuccessMessage : function(msg) {
        alert(msg);
    };
    
    const formData = new FormData(event.target);
    const customerId = formData.get('id');
    
    // Use FormData directly for PUT request (supports file uploads)
    fetch(getApiUrl('api/customers.php'), {
        method: 'PUT',
        body: formData
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
            getHideModal('editCustomerModal');
            getShowSuccessMessage('Customer updated successfully!');
            
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
            alert('Error: ' + (data.message || 'Failed to update customer'));
        }
    })
    .catch(error => {
        console.error('Error updating customer:', error);
        alert('An error occurred while updating the customer: ' + error.message);
    });
}

function viewCustomer(customerId) {
    // Navigate to customer view page
    try {
        const p = window.location && window.location.pathname || '';
        const underPages = p.indexOf('/pages/') !== -1;
        const basePath = underPages ? '../' : '';
        window.location.href = `${basePath}pages/view-customer.php?id=${customerId}`;
    } catch (error) {
        console.error('Error navigating to view customer:', error);
        window.location.href = `pages/view-customer.php?id=${customerId}`;
    }
}

function deleteCustomer(customerId, customerName) {
    if (!confirm(`Are you sure you want to delete customer "${customerName}"?\n\nThis action cannot be undone.`)) {
        return;
    }
    
    const getApiUrl = (typeof apiUrl !== 'undefined') ? apiUrl : (typeof window.apiUrl !== 'undefined') ? window.apiUrl : function(path) {
        const p = window.location && window.location.pathname || '';
        const underPages = p.indexOf('/pages/') !== -1;
        return (underPages ? '../' : '') + path;
    };
    
    const getShowSuccessMessage = (typeof showSuccessMessage !== 'undefined') ? showSuccessMessage : (typeof window.showSuccessMessage !== 'undefined') ? window.showSuccessMessage : function(msg) {
        alert(msg);
    };
    
    fetch(getApiUrl(`api/customers.php?id=${customerId}`), {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => {
        // Get response text first to handle both JSON and text responses
        return response.text().then(text => {
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                // If not JSON, treat as error message
                throw new Error(text || `HTTP ${response.status}: Failed to delete customer`);
            }
            
            if (!response.ok) {
                throw new Error(data.message || `HTTP ${response.status}: Failed to delete customer`);
            }
            
            return data;
        });
    })
    .then(data => {
        if (data.success) {
            getShowSuccessMessage('Customer deleted successfully!');
            
            // Remove the row from table immediately
            const row = document.querySelector(`tr[data-customer-id="${customerId}"]`);
            if (row) {
                row.style.transition = 'opacity 0.3s';
                row.style.opacity = '0';
                setTimeout(() => {
                    row.remove();
                    // If no rows left, show empty message
                    const tbody = document.querySelector('.data-table tbody');
                    if (tbody && tbody.children.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="11" style="text-align: center; padding: 40px;"><i class="fas fa-inbox" style="font-size: 48px; color: #ddd; margin-bottom: 10px;"></i><p style="color: #999;">No customers found</p></td></tr>';
                    }
                }, 300);
            } else {
                // Fallback: Try alternative selector if data attribute not found
                const rows = document.querySelectorAll('.data-table tbody tr');
                let found = false;
                rows.forEach(r => {
                    const viewBtn = r.querySelector('button[onclick*="viewCustomer(' + customerId + ')"]');
                    if (viewBtn && !found) {
                        found = true;
                        r.style.transition = 'opacity 0.3s';
                        r.style.opacity = '0';
                        setTimeout(() => {
                            r.remove();
                            const tbody = document.querySelector('.data-table tbody');
                            if (tbody && tbody.children.length === 0) {
                                tbody.innerHTML = '<tr><td colspan="11" style="text-align: center; padding: 40px;"><i class="fas fa-inbox" style="font-size: 48px; color: #ddd; margin-bottom: 10px;"></i><p style="color: #999;">No customers found</p></td></tr>';
                            }
                        }, 300);
                    }
                });
                
                // If still not found, reload the page
                if (!found) {
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
            }
        } else {
            alert('Error: ' + (data.message || 'Failed to delete customer'));
        }
    })
    .catch(error => {
        console.error('Error deleting customer:', error);
        alert('An error occurred while deleting the customer: ' + error.message);
    });
}

// Make functions globally available
window.editCustomer = editCustomer;
window.viewCustomer = viewCustomer;
window.deleteCustomer = deleteCustomer;
window.updateCustomer = updateCustomer;
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
