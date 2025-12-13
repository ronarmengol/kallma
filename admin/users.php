<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

session_start();

if (!isLoggedIn()) {
    redirect('../login.php');
}

if (!isAdmin()) {
    redirect('index.php');
}

$message = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'edit_user') {
            $id = (int)$_POST['id'];
            $name = sanitize($conn, $_POST['name']);
            $mobile = sanitize($conn, $_POST['mobile']);
            $password = sanitize($conn, $_POST['password']);
            
            // Check if role is provided (it might not be for masseuses)
            if (isset($_POST['role']) && !empty($_POST['role'])) {
                $role = sanitize($conn, $_POST['role']);
                // Update user with password and role
                $sql = "UPDATE users SET name='$name', mobile='$mobile', role='$role', password='$password' WHERE id=$id";
            } else {
                // Update user with password only (keep existing role)
                $sql = "UPDATE users SET name='$name', mobile='$mobile', password='$password' WHERE id=$id";
            }
            
            if ($conn->query($sql)) {
                $message = "User updated successfully!";
            }
        } elseif ($_POST['action'] === 'delete_user') {
            $id = (int)$_POST['id'];
            if ($id !== $_SESSION['user_id']) { // Prevent self-deletion
                $sql = "DELETE FROM users WHERE id=$id";
                if ($conn->query($sql)) {
                    $message = "User deleted successfully!";
                }
            } else {
                $message = "You cannot delete yourself!";
            }
        }
    }
}

// Handle sorting
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$order = isset($_GET['order']) ? $_GET['order'] : 'DESC';

$allowed_sort = ['name', 'role', 'created_at'];
if (!in_array($sort, $allowed_sort)) {
    $sort = 'created_at';
}
$order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

$users = $conn->query("SELECT * FROM users ORDER BY $sort $order")->fetch_all(MYSQLI_ASSOC);
$pageTitle = 'Manage Users - Kallma Spa';
require_once 'includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <h1>Manage Users</h1>
    <a href="masseuses.php" class="btn btn-primary">Add Masseuse</a>
</div>

<?php if ($message): ?>
    <div style="background: rgba(16, 185, 129, 0.2); color: #6ee7b7; padding: 1rem; border-radius: 8px; margin-bottom: 2rem;">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<div class="glass-card">
    <div style="overflow-x: auto;">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>
                    <a href="?sort=name&order=<?php echo $sort === 'name' && $order === 'ASC' ? 'DESC' : 'ASC'; ?>" style="color: inherit; text-decoration: none; display: flex; align-items: center; gap: 0.5rem;">
                        Name
                        <?php if ($sort === 'name'): ?>
                            <span style="color: var(--primary-color)"><?php echo $order === 'ASC' ? '↑' : '↓'; ?></span>
                        <?php else: ?>
                            <span style="color: #64748b">↕</span>
                        <?php endif; ?>
                    </a>
                </th>
                <th>Mobile</th>
                <th>Password</th>
                <th>
                    <a href="?sort=role&order=<?php echo $sort === 'role' && $order === 'ASC' ? 'DESC' : 'ASC'; ?>" style="color: inherit; text-decoration: none; display: flex; align-items: center; gap: 0.5rem;">
                        Role
                        <?php if ($sort === 'role'): ?>
                            <span style="color: var(--primary-color)"><?php echo $order === 'ASC' ? '↑' : '↓'; ?></span>
                        <?php else: ?>
                            <span style="color: #64748b">↕</span>
                        <?php endif; ?>
                    </a>
                </th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo str_pad($user['id'], 4, '0', STR_PAD_LEFT); ?></td>
                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                    <td><?php echo htmlspecialchars($user['mobile']); ?></td>
                    <td><code style="background: rgba(255,255,255,0.05); padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.9em;"><?php echo htmlspecialchars($user['password']); ?></code></td>
                    <td>
                        <?php 
                        $roleBg = 'rgba(59, 130, 246, 0.2)'; 
                        $roleColor = '#3b82f6';
                        
                        if ($user['role'] === 'admin') {
                            $roleBg = 'rgba(139, 92, 246, 0.2)'; 
                            $roleColor = '#8b5cf6';
                        } elseif ($user['role'] === 'masseuse') {
                            $roleBg = 'rgba(236, 72, 153, 0.2)'; 
                            $roleColor = '#ec4899';
                        }
                        ?>
                        <span class="badge" style="background: <?php echo $roleBg; ?>; color: <?php echo $roleColor; ?>;">
                            <?php echo ucfirst($user['role']); ?>
                        </span>
                    </td>
                    <td>
                        <div style="display: flex; gap: 0.5rem; align-items: center;">
                            <button onclick='openEditUserModal(<?php echo json_encode($user); ?>)' class="icon-btn" title="Edit">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                </svg>
                            </button>
                            <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                            <form method="POST" style="display: inline; margin: 0;" onsubmit="return confirm('Delete this user?');">
                                <input type="hidden" name="action" value="delete_user">
                                <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                <button type="submit" class="icon-btn delete" title="Delete">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="3 6 5 6 21 6"></polyline>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                        <line x1="10" y1="11" x2="10" y2="17"></line>
                                        <line x1="14" y1="11" x2="14" y2="17"></line>
                                    </svg>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editUserModal" class="modal">
    <div class="modal-content glass-card">
        <h2>Edit User</h2>
        <form method="POST">
            <input type="hidden" name="action" value="edit_user">
            <input type="hidden" name="id" id="editUserId">
            
            <div class="form-group">
                <label>Name</label>
                <input type="text" name="name" id="editUserName" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Mobile</label>
                <input type="text" name="mobile" id="editUserMobile" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="text" name="password" id="editUserPassword" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Role</label>
                <select name="role" id="editUserRole" class="form-control">
                    <option value="customer">Customer</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div style="display: flex; gap: 1rem;">
                <button type="submit" class="btn btn-primary" style="flex: 1;">Save</button>
                <button type="button" onclick="document.getElementById('editUserModal').style.display='none'" class="btn btn-outline" style="flex: 1;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openEditUserModal(user) {
        document.getElementById('editUserId').value = user.id;
        document.getElementById('editUserName').value = user.name;
        document.getElementById('editUserMobile').value = user.mobile;
        document.getElementById('editUserPassword').value = user.password;
        document.getElementById('editUserRole').value = user.role;
        document.getElementById('editUserModal').style.display = 'block';
    }

    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    }
</script>

<?php require_once 'includes/footer.php'; ?>
