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

<script>
// Modal functions
function showModal(modalId) {
    document.getElementById(modalId).style.display = 'block';
}

function hideModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

function showAddInterestModal() {
    showModal('addInterestModal');
    // Set default date to today
    document.getElementById('interestDate').value = new Date().toISOString().split('T')[0];
}

function addInterest(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    
    fetch('api/interest.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            hideModal('addInterestModal');
            event.target.reset();
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while adding the interest record.');
    });
}

function updateInterestLoanDetails() {
    // This function can be used to update loan details when a loan is selected
    const loanSelect = document.getElementById('loanId');
    const selectedOption = loanSelect.options[loanSelect.selectedIndex];
    
    if (selectedOption.value) {
        // You can add logic here to fetch and display loan details if needed
        console.log('Selected loan:', selectedOption.text);
    }
}

function changePage(page) {
    const search = document.getElementById('interestSearch').value;
    const url = new URL(window.location);
    url.searchParams.set('page', page);
    if (search) {
        url.searchParams.set('search', search);
    }
    window.location.href = url.toString();
}

function showInterestActions(interestId) {
    // Implement interest actions dropdown
    console.log('Show actions for interest:', interestId);
}

// Search functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('interestSearch');
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
