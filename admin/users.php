<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

session_start();

if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

$message = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'edit_user') {
            $id = (int)$_POST['id'];
            $name = sanitize($conn, $_POST['name']);
            $mobile = sanitize($conn, $_POST['mobile']);
            $role = sanitize($conn, $_POST['role']);
            
            $sql = "UPDATE users SET name='$name', mobile='$mobile', role='$role' WHERE id=$id";
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
        } elseif ($_POST['action'] === 'change_password') {
            $id = $_SESSION['user_id'];
            $new_password = $_POST['new_password'];
            // Using plain text as per previous instructions to remove hashing
            $sql = "UPDATE users SET password='$new_password' WHERE id=$id";
            if ($conn->query($sql)) {
                $message = "Password updated successfully!";
            }
        }
    }
}

$users = $conn->query("SELECT * FROM users ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Kallma Spa</title>
    <link rel="stylesheet" href="../assets/css/style.css">

</head>
<body>
    <nav class="admin-nav">
        <div class="container">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <a href="index.php" class="logo">Kallma Admin</a>
                <button class="menu-toggle" onclick="document.querySelector('.nav-content').classList.toggle('active')">☰</button>
                
                <div class="nav-content">
                    <ul class="nav-links">
                        <li><a href="index.php">Dashboard</a></li>
                        <li><a href="services.php">Services</a></li>
                        <li><a href="masseuses.php">Masseuses</a></li>
                        <li><a href="bookings.php">Bookings</a></li>
                        <li><a href="users.php">Users</a></li>
                        <li><a href="../index.php">View Site</a></li>
                    </ul>
                    <a href="../logout.php" class="btn btn-outline logout-btn" style="padding: 0.5rem 1rem;">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container" style="padding: 3rem 2rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
            <h1>Manage Users</h1>
            <div style="display: flex; gap: 1rem;">
                <button onclick="openPasswordModal()" class="btn btn-outline">Change My Password</button>
                <a href="masseuses.php" class="btn btn-primary">Add Masseuse</a>
            </div>
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
                        <th>Name</th>
                        <th>Mobile</th>
                        <th>Password</th>
                        <th>Role</th>
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
                                <span class="badge" style="background: <?php echo $user['role'] === 'admin' ? 'rgba(139, 92, 246, 0.2)' : 'rgba(59, 130, 246, 0.2)'; ?>; color: <?php echo $user['role'] === 'admin' ? '#8b5cf6' : '#3b82f6'; ?>;">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                            <td style="display: flex; gap: 0.5rem; align-items: center;">
                                <button onclick='openEditUserModal(<?php echo json_encode($user); ?>)' class="icon-btn" title="Edit">
                                    ✎
                                </button>
                                <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                <form method="POST" style="display: inline; margin: 0;" onsubmit="return confirm('Delete this user?');">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" class="icon-btn delete" title="Delete">
                                        🗑️
                                    </button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
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

    <!-- Change Password Modal -->
    <div id="passwordModal" class="modal">
        <div class="modal-content glass-card">
            <h2>Change Password</h2>
            <form method="POST">
                <input type="hidden" name="action" value="change_password">
                
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" class="form-control" required>
                </div>
                <div style="display: flex; gap: 1rem;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">Update Password</button>
                    <button type="button" onclick="document.getElementById('passwordModal').style.display='none'" class="btn btn-outline" style="flex: 1;">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditUserModal(user) {
            document.getElementById('editUserId').value = user.id;
            document.getElementById('editUserName').value = user.name;
            document.getElementById('editUserMobile').value = user.mobile;
            document.getElementById('editUserRole').value = user.role;
            document.getElementById('editUserModal').style.display = 'block';
        }

        function openPasswordModal() {
            document.getElementById('passwordModal').style.display = 'block';
        }

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>
