<?php
// Define the base path
$basePath = dirname(__DIR__);
require_once $basePath . '/config/database.php';

try {
    $pdo = getDBConnection();
    
    // Get products with pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $whereClause = '';
    $params = [];
    
    if (!empty($search)) {
        $whereClause = "WHERE p.name LIKE ? OR p.name_tamil LIKE ?";
        $searchTerm = "%$search%";
        $params = [$searchTerm, $searchTerm];
    }
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM products p 
        $whereClause
    ");
    $stmt->execute($params);
    $totalRecords = $stmt->fetch()['total'];
    $totalPages = ceil($totalRecords / $limit);
    
    $stmt = $pdo->prepare("
        SELECT p.*, g.name as group_name 
        FROM products p 
        LEFT JOIN groups g ON p.group_id = g.id 
        $whereClause 
        ORDER BY p.name 
        LIMIT ? OFFSET ?
    ");
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $products = $stmt->fetchAll();
    
    // Get groups for dropdown
    $stmt = $pdo->query("SELECT id, name FROM groups ORDER BY name");
    $groups = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<div class="content-card">
    <div class="page-title">Products</div>
    
    <div class="search-section">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Search Product" id="productSearch" value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <button class="add-btn" onclick="showAddProductModal()">Add New</button>
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
                <th>Product Name</th>
                <th>பொருட்களின் பெயர் தமிழ்</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (isset($products) && !empty($products)): ?>
                <?php foreach ($products as $index => $product): ?>
                    <tr>
                        <td><?php echo $offset + $index + 1; ?></td>
                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                        <td><?php echo htmlspecialchars($product['name_tamil']); ?></td>
                        <td>
                            <button class="action-btn" onclick="showProductActions(<?php echo $product['id']; ?>)">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" style="text-align: center;">No products found</td>
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

<!-- Add Product Modal -->
<div id="addProductModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="hideModal('addProductModal')">&times;</span>
        <h2>Add New Product</h2>
        <form id="addProductForm" onsubmit="addProduct(event)">
            <div class="form-group">
                <label for="productName">Product Name (English)</label>
                <input type="text" id="productName" name="name" required>
            </div>
            <div class="form-group">
                <label for="productNameTamil">Product Name (Tamil)</label>
                <input type="text" id="productNameTamil" name="name_tamil">
            </div>
            <div class="form-group">
                <label for="productGroup">Group</label>
                <select id="productGroup" name="group_id">
                    <option value="">Select Group</option>
                    <?php if (isset($groups)): ?>
                        <?php foreach ($groups as $group): ?>
                            <option value="<?php echo $group['id']; ?>">
                                <?php echo htmlspecialchars($group['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="form-actions">
                <button type="button" onclick="hideModal('addProductModal')" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary">Add Product</button>
            </div>
        </form>
    </div>
</div>

<script>
function showAddProductModal() {
    showModal('addProductModal');
}

function addProduct(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    
    fetch('api/products.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            hideModal('addProductModal');
            event.target.reset();
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while adding the product.');
    });
}

function changePage(page) {
    const search = document.getElementById('productSearch').value;
    const url = new URL(window.location);
    url.searchParams.set('page', page);
    if (search) {
        url.searchParams.set('search', search);
    }
    window.location.href = url.toString();
}

// Search functionality
document.getElementById('productSearch').addEventListener('input', function() {
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

function showProductActions(productId) {
    // Implement product actions dropdown
    console.log('Show actions for product:', productId);
}
</script> 