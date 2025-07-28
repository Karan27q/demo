<?php
// Define the base path
$basePath = dirname(__DIR__);
require_once $basePath . '/config/database.php';

try {
    $pdo = getDBConnection();
    
    // Get groups
    $stmt = $pdo->query("SELECT * FROM groups ORDER BY name");
    $groups = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<div class="content-card">
    <div class="page-title">Group</div>
    
    <div class="search-section">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Search Group" id="groupSearch">
        </div>
        <button class="add-btn" onclick="showAddGroupModal()">Add New</button>
    </div>
    
    <table class="data-table">
        <thead>
            <tr>
                <th>No</th>
                <th>Group</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (isset($groups) && !empty($groups)): ?>
                <?php foreach ($groups as $index => $group): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td><?php echo htmlspecialchars($group['name']); ?></td>
                        <td>
                            <button class="action-btn" onclick="showGroupActions(<?php echo $group['id']; ?>)">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="3" style="text-align: center;">No groups found</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Add Group Modal -->
<div id="addGroupModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="hideModal('addGroupModal')">&times;</span>
        <h2>Add New Group</h2>
        <form id="addGroupForm" onsubmit="addGroup(event)">
            <div class="form-group">
                <label for="groupName">Group Name</label>
                <input type="text" id="groupName" name="name" required>
            </div>
            <div class="form-actions">
                <button type="button" onclick="hideModal('addGroupModal')" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary">Add Group</button>
            </div>
        </form>
    </div>
</div>

<script>
function showAddGroupModal() {
    showModal('addGroupModal');
}

function addGroup(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    
    fetch('api/groups.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            hideModal('addGroupModal');
            event.target.reset();
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while adding the group.');
    });
}

function showGroupActions(groupId) {
    // Implement group actions dropdown
    console.log('Show actions for group:', groupId);
}
</script> 