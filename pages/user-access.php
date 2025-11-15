<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$basePath = dirname(__DIR__);
require_once $basePath . '/config/database.php';

try {
    $pdo = getDBConnection();
    
    // Get all users
    $stmt = $pdo->query("SELECT id, username, name, email, role, created_at FROM users ORDER BY created_at DESC");
    $users = $stmt->fetchAll();
    
    // Get user count
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    $totalUsers = $stmt->fetch()['total'];
    
    // Get admin count
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'admin'");
    $adminCount = $stmt->fetch()['total'];
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    $users = [];
    $totalUsers = 0;
    $adminCount = 0;
}
?>

<div class="dashboard-page">
    <!-- Summary Cards -->
    <div class="dashboard-cards">
        <div class="dashboard-card">
            <div class="card-icon customer">
                <i class="fas fa-users"></i>
            </div>
            <div class="card-content">
                <div class="card-number"><?php echo number_format($totalUsers); ?></div>
                <div class="card-label">Total Users</div>
            </div>
        </div>
        
        <div class="dashboard-card">
            <div class="card-icon loan">
                <i class="fas fa-user-shield"></i>
            </div>
            <div class="card-content">
                <div class="card-number"><?php echo number_format($adminCount); ?></div>
                <div class="card-label">Administrators</div>
            </div>
        </div>
        
        <div class="dashboard-card">
            <div class="card-icon recovery">
                <i class="fas fa-user-check"></i>
            </div>
            <div class="card-content">
                <div class="card-number"><?php echo number_format($totalUsers - $adminCount); ?></div>
                <div class="card-label">Regular Users</div>
            </div>
        </div>
    </div>
    
    <!-- Main Content Card -->
    <div class="content-card">
        <div class="section-header">
            <h2 class="page-title">
                <i class="fas fa-users-cog"></i> User & Access Management
            </h2>
            <div class="section-actions">
                <button class="btn-primary" onclick="showAddUserModal()">
                    <i class="fas fa-plus"></i> Add New User
                </button>
            </div>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="error-message" style="background: #fee; color: #c33; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Username</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($users)): ?>
                        <?php foreach ($users as $index => $user): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                                <td><?php echo htmlspecialchars($user['name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email'] ?? '-'); ?></td>
                                <td>
                                    <?php if ($user['role'] === 'admin'): ?>
                                        <span class="badge badge-success">Admin</span>
                                    <?php else: ?>
                                        <span class="badge badge-info">User</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d-m-Y H:i', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="action-btn btn-edit" onclick="editUser(<?php echo $user['id']; ?>)" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if (isset($_SESSION['user_id']) && $user['id'] != $_SESSION['user_id']): ?>
                                            <button class="action-btn btn-delete" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px;">
                                <i class="fas fa-inbox" style="font-size: 48px; color: #ddd; margin-bottom: 10px;"></i>
                                <p style="color: #999;">No users found</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div id="addUserModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="hideModal('addUserModal')">&times;</span>
        <h2><i class="fas fa-user-plus"></i> Add New User</h2>
        <form id="addUserForm" onsubmit="addUser(event)">
            <div class="form-group">
                <label for="username">Username *</label>
                <input type="text" id="username" name="username" required placeholder="Enter username">
            </div>
            
            <div class="form-group">
                <label for="name">Full Name *</label>
                <input type="text" id="name" name="name" required placeholder="Enter full name">
            </div>
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="Enter email address">
            </div>
            
            <div class="form-group">
                <label for="password">Password *</label>
                <input type="password" id="password" name="password" required placeholder="Enter password" minlength="6">
            </div>
            
            <div class="form-group">
                <label for="role">Role *</label>
                <select id="role" name="role" required>
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            
            <div class="form-actions">
                <button type="button" onclick="hideModal('addUserModal')" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> Add User
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editUserModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="hideModal('editUserModal')">&times;</span>
        <h2><i class="fas fa-user-edit"></i> Edit User</h2>
        <form id="editUserForm" onsubmit="updateUser(event)">
            <input type="hidden" id="editUserId" name="id">
            
            <div class="form-group">
                <label for="editUsername">Username *</label>
                <input type="text" id="editUsername" name="username" required>
            </div>
            
            <div class="form-group">
                <label for="editName">Full Name *</label>
                <input type="text" id="editName" name="name" required>
            </div>
            
            <div class="form-group">
                <label for="editEmail">Email</label>
                <input type="email" id="editEmail" name="email">
            </div>
            
            <div class="form-group">
                <label for="editPassword">New Password (leave blank to keep current)</label>
                <input type="password" id="editPassword" name="password" placeholder="Enter new password" minlength="6">
            </div>
            
            <div class="form-group">
                <label for="editRole">Role *</label>
                <select id="editRole" name="role" required>
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            
            <div class="form-actions">
                <button type="button" onclick="hideModal('editUserModal')" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> Update User
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showAddUserModal() {
    showModal('addUserModal');
    document.getElementById('addUserForm').reset();
}

function addUser(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    
    fetch(apiUrl('api/users.php'), {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            hideModal('addUserModal');
            showSuccessMessage('User added successfully!');
            setTimeout(() => location.reload(), 500);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while adding the user.');
    });
}

function editUser(userId) {
    fetch(apiUrl(`api/users.php?id=${userId}`))
        .then(response => response.json())
        .then(data => {
            if (data.success && data.user) {
                const user = data.user;
                document.getElementById('editUserId').value = user.id;
                document.getElementById('editUsername').value = user.username;
                document.getElementById('editName').value = user.name;
                document.getElementById('editEmail').value = user.email || '';
                document.getElementById('editRole').value = user.role;
                showModal('editUserModal');
            } else {
                alert('Error loading user data');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while loading user data.');
        });
}

function updateUser(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const userId = formData.get('id');
    
    // Convert FormData to URL-encoded format for PUT request
    const data = new URLSearchParams();
    data.append('id', userId);
    data.append('username', formData.get('username'));
    data.append('name', formData.get('name'));
    data.append('email', formData.get('email'));
    data.append('role', formData.get('role'));
    if (formData.get('password')) {
        data.append('password', formData.get('password'));
    }
    
    fetch(apiUrl('api/users.php'), {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: data.toString()
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            hideModal('editUserModal');
            showSuccessMessage('User updated successfully!');
            setTimeout(() => location.reload(), 500);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while updating the user.');
    });
}

function deleteUser(userId, username) {
    if (!confirm(`Are you sure you want to delete user "${username}"?`)) {
        return;
    }
    
    fetch(apiUrl(`api/users.php?id=${userId}`), {
        method: 'DELETE'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccessMessage('User deleted successfully!');
            setTimeout(() => location.reload(), 500);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while deleting the user.');
    });
}
</script>

