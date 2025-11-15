<?php
// Define the base path
$basePath = dirname(__DIR__);
require_once $basePath . '/config/database.php';

try {
    $pdo = getDBConnection();
    
    // Get summary statistics
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM products");
    $totalProducts = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT group_id) as total 
        FROM products 
        WHERE group_id IS NOT NULL
    ");
    $groupsWithProducts = $stmt->fetch()['total'];
    
    // Get products with pagination
    $page = isset($_GET['p']) ? (int)$_GET['p'] : (isset($_GET['page']) ? (int)$_GET['page'] : 1);
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
    // Bind limit and offset as integers
    $paramIndex = 1;
    foreach ($params as $value) {
        $stmt->bindValue($paramIndex++, $value);
    }
    $stmt->bindValue($paramIndex++, (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue($paramIndex++, (int)$offset, PDO::PARAM_INT);
    $stmt->execute();
    $products = $stmt->fetchAll();
    
    // Get groups for dropdown
    $stmt = $pdo->query("SELECT id, name FROM groups ORDER BY name");
    $groups = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<div class="dashboard-page">
    <!-- Financial Summary Cards -->
    <div class="dashboard-cards">
        <div class="dashboard-card">
            <div class="card-icon principal">
                <i class="fas fa-box"></i>
            </div>
            <div class="card-content">
                <div class="card-number"><?php echo number_format($totalProducts ?? 0); ?></div>
                <div class="card-label">Total Products</div>
            </div>
        </div>
        
        <div class="dashboard-card">
            <div class="card-icon interest">
                <i class="fas fa-layer-group"></i>
            </div>
            <div class="card-content">
                <div class="card-number"><?php echo number_format($groupsWithProducts ?? 0); ?></div>
                <div class="card-label">Groups with Products</div>
            </div>
        </div>
    </div>
    
    <!-- Main Content Card -->
    <div class="content-card">
        <div class="section-header">
            <h2 class="page-title">
                <i class="fas fa-box"></i> Product Management
            </h2>
            <div class="section-actions">
                <button class="btn-primary" onclick="showAddProductModal()">
                    <i class="fas fa-plus"></i> Add New Product
                </button>
            </div>
        </div>
        
        <div class="search-section">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search by product name (English or Tamil)" id="productSearch" value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <?php if (!empty($search)): ?>
                <button class="clear-btn" onclick="clearProductSearch()">
                    <i class="fas fa-times"></i> Clear
                </button>
            <?php endif; ?>
        </div>
        
        <!-- Pagination Top -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <div class="pagination-info">
                Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $limit, $totalRecords); ?> of <?php echo $totalRecords; ?> products
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
                        <th>Product Name (English)</th>
                        <th>Product Name (Tamil)</th>
                        <th>Group</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (isset($products) && !empty($products)): ?>
                        <?php foreach ($products as $index => $product): ?>
                            <tr>
                                <td><?php echo $offset + $index + 1; ?></td>
                                <td><strong><?php echo htmlspecialchars($product['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($product['name_tamil'] ?? ''); ?></td>
                                <td>
                                    <?php if ($product['group_name']): ?>
                                        <span class="badge"><?php echo htmlspecialchars($product['group_name']); ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">No Group</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="action-btn btn-edit" onclick="editProduct(<?php echo $product['id']; ?>)" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="action-btn btn-delete" onclick="deleteProduct(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>')" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 40px;">
                                <i class="fas fa-inbox" style="font-size: 48px; color: #ddd; margin-bottom: 10px;"></i>
                                <p style="color: #999;">No products found</p>
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
                Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $limit, $totalRecords); ?> of <?php echo $totalRecords; ?> products
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

<!-- Add Product Modal -->
<div id="addProductModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="hideModal('addProductModal')">&times;</span>
        <h2><i class="fas fa-plus-circle"></i> Add New Product</h2>
        <form id="addProductForm" onsubmit="addProduct(event)">
            <div class="form-group">
                <label for="productName">Product Name (English) *</label>
                <input type="text" id="productName" name="name" required placeholder="Enter product name in English">
            </div>
            <div class="form-group">
                <label for="productNameTamil">Product Name (Tamil)</label>
                <input type="text" id="productNameTamil" name="name_tamil" placeholder="Enter product name in Tamil">
            </div>
            <div class="form-group">
                <label for="productGroup">Group</label>
                <select id="productGroup" name="group_id">
                    <option value="">Select Group (Optional)</option>
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
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> Add Product
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Product Modal -->
<div id="editProductModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="hideModal('editProductModal')">&times;</span>
        <h2><i class="fas fa-edit"></i> Edit Product</h2>
        <form id="editProductForm" onsubmit="updateProduct(event)">
            <input type="hidden" id="editProductId" name="id">
            <div class="form-group">
                <label for="editProductName">Product Name (English) *</label>
                <input type="text" id="editProductName" name="name" required>
            </div>
            <div class="form-group">
                <label for="editProductNameTamil">Product Name (Tamil)</label>
                <input type="text" id="editProductNameTamil" name="name_tamil">
            </div>
            <div class="form-group">
                <label for="editProductGroup">Group</label>
                <select id="editProductGroup" name="group_id">
                    <option value="">Select Group (Optional)</option>
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
                <button type="button" onclick="hideModal('editProductModal')" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> Update Product
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Search functionality
(function() {
    function initProductSearch() {
        const searchInput = document.getElementById('productSearch');
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
        document.addEventListener('DOMContentLoaded', initProductSearch);
    } else {
        initProductSearch();
    }
    
    window.initProductSearch = initProductSearch;
})();

function clearProductSearch() {
    const searchInput = document.getElementById('productSearch');
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

function editProduct(productId) {
    fetch(apiUrl(`api/products.php?id=${productId}`))
        .then(response => response.json())
        .then(data => {
            if (data.success && data.product) {
                const p = data.product;
                document.getElementById('editProductId').value = p.id;
                document.getElementById('editProductName').value = p.name;
                document.getElementById('editProductNameTamil').value = p.name_tamil || '';
                document.getElementById('editProductGroup').value = p.group_id || '';
                showModal('editProductModal');
            } else {
                alert('Error loading product data');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while loading product data.');
        });
}

function updateProduct(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const productId = formData.get('id');
    
    const data = new URLSearchParams();
    data.append('id', productId);
    data.append('name', formData.get('name'));
    data.append('name_tamil', formData.get('name_tamil'));
    data.append('group_id', formData.get('group_id') || '');
    
    fetch(apiUrl('api/products.php'), {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: data.toString()
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            hideModal('editProductModal');
            showSuccessMessage('Product updated successfully!');
            
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
        alert('An error occurred while updating the product.');
    });
}

function deleteProduct(productId, productName) {
    if (!confirm(`Are you sure you want to delete product "${productName}"?\n\nThis action cannot be undone.`)) {
        return;
    }
    
    fetch(apiUrl(`api/products.php?id=${productId}`), {
        method: 'DELETE'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccessMessage('Product deleted successfully!');
            
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
        alert('An error occurred while deleting the product.');
    });
}
</script>
