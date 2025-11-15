<?php
// Define the base path
$basePath = dirname(__DIR__);
require_once $basePath . '/config/database.php';

try {
    $pdo = getDBConnection();
    
    // Get summary statistics
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM groups");
    $totalGroups = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("
        SELECT COUNT(*) as total 
        FROM products 
        WHERE group_id IS NOT NULL
    ");
    $productsInGroups = $stmt->fetch()['total'];
    
    // Get groups with product counts
    $stmt = $pdo->query("
        SELECT g.*, COUNT(p.id) as product_count
        FROM groups g
        LEFT JOIN products p ON g.id = p.group_id
        GROUP BY g.id
        ORDER BY g.name
    ");
    $groups = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<div class="dashboard-page">
    <!-- Financial Summary Cards -->
    <div class="dashboard-cards">
        <div class="dashboard-card">
            <div class="card-icon interest">
                <i class="fas fa-layer-group"></i>
            </div>
            <div class="card-content">
                <div class="card-number"><?php echo number_format($totalGroups ?? 0); ?></div>
                <div class="card-label">Total Groups</div>
            </div>
        </div>
        
        <div class="dashboard-card">
            <div class="card-icon principal">
                <i class="fas fa-box"></i>
            </div>
            <div class="card-content">
                <div class="card-number"><?php echo number_format($productsInGroups ?? 0); ?></div>
                <div class="card-label">Products in Groups</div>
            </div>
        </div>
    </div>
    
    <!-- Main Content Card -->
    <div class="content-card">
        <div class="section-header">
            <h2 class="page-title">
                <i class="fas fa-layer-group"></i> Group Management
            </h2>
            <div class="section-actions">
                <button class="btn-primary" onclick="showAddGroupModal()">
                    <i class="fas fa-plus"></i> Add New Group
                </button>
            </div>
        </div>
        
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Group Name</th>
                        <th>Products Count</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (isset($groups) && !empty($groups)): ?>
                        <?php foreach ($groups as $index => $group): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><strong><?php echo htmlspecialchars($group['name']); ?></strong></td>
                                <td>
                                    <span class="badge <?php echo $group['product_count'] > 0 ? 'badge-success' : 'badge-secondary'; ?>">
                                        <?php echo $group['product_count']; ?> products
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="action-btn btn-edit" onclick="editGroup(<?php echo $group['id']; ?>)" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="action-btn btn-delete" onclick="deleteGroup(<?php echo $group['id']; ?>, '<?php echo htmlspecialchars($group['name']); ?>', <?php echo $group['product_count']; ?>)" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 40px;">
                                <i class="fas fa-inbox" style="font-size: 48px; color: #ddd; margin-bottom: 10px;"></i>
                                <p style="color: #999;">No groups found</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Group Modal -->
<div id="addGroupModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="hideModal('addGroupModal')">&times;</span>
        <h2><i class="fas fa-plus-circle"></i> Add New Group</h2>
        <form id="addGroupForm" onsubmit="addGroup(event)">
            <div class="form-group">
                <label for="groupName">Group Name *</label>
                <input type="text" id="groupName" name="name" required placeholder="Enter group name">
            </div>
            <div class="form-actions">
                <button type="button" onclick="hideModal('addGroupModal')" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> Add Group
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Group Modal -->
<div id="editGroupModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="hideModal('editGroupModal')">&times;</span>
        <h2><i class="fas fa-edit"></i> Edit Group</h2>
        <form id="editGroupForm" onsubmit="updateGroup(event)">
            <input type="hidden" id="editGroupId" name="id">
            <div class="form-group">
                <label for="editGroupName">Group Name *</label>
                <input type="text" id="editGroupName" name="name" required>
            </div>
            <div class="form-actions">
                <button type="button" onclick="hideModal('editGroupModal')" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> Update Group
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function editGroup(groupId) {
    fetch(apiUrl(`api/groups.php?id=${groupId}`))
        .then(response => response.json())
        .then(data => {
            if (data.success && data.group) {
                const g = data.group;
                document.getElementById('editGroupId').value = g.id;
                document.getElementById('editGroupName').value = g.name;
                showModal('editGroupModal');
            } else {
                alert('Error loading group data');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while loading group data.');
        });
}

function updateGroup(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const groupId = formData.get('id');
    
    const data = new URLSearchParams();
    data.append('id', groupId);
    data.append('name', formData.get('name'));
    
    fetch(apiUrl('api/groups.php'), {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: data.toString()
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            hideModal('editGroupModal');
            showSuccessMessage('Group updated successfully!');
            
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
        alert('An error occurred while updating the group.');
    });
}

function deleteGroup(groupId, groupName, productCount) {
    if (productCount > 0) {
        alert(`Cannot delete group "${groupName}" because it has ${productCount} product(s) assigned to it.`);
        return;
    }
    
    if (!confirm(`Are you sure you want to delete group "${groupName}"?\n\nThis action cannot be undone.`)) {
        return;
    }
    
    fetch(apiUrl(`api/groups.php?id=${groupId}`), {
        method: 'DELETE'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccessMessage('Group deleted successfully!');
            
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
        alert('An error occurred while deleting the group.');
    });
}
</script>
