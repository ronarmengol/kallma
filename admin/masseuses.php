<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

session_start();

if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

$message = '';

// Handle Add/Edit/Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $name = sanitize($conn, $_POST['name']);
            $username = sanitize($conn, $_POST['username']);
            $mobile = sanitize($conn, $_POST['mobile']);
            $bio = sanitize($conn, $_POST['bio']);
            $specialties = sanitize($conn, $_POST['specialties']);
            $image_url = sanitize($conn, $_POST['image_url']);
            $password = $_POST['password'];
            $password_confirm = $_POST['password_confirm'];
            
            // Validate passwords match
            if ($password !== $password_confirm) {
                $message = "Passwords do not match!";
            } elseif (empty($password)) {
                $message = "Password is required!";
            } else {
                // Check if mobile or username already exists
                $check_sql = "SELECT id FROM users WHERE mobile='$mobile' OR username='$username'";
                $check_result = $conn->query($check_sql);
                if ($check_result && $check_result->num_rows > 0) {
                    $message = "Error: Mobile number or username is already registered!";
                } else {
                    // Create user account first
                    $user_sql = "INSERT INTO users (name, username, mobile, password, role) VALUES ('$name', '$username', '$mobile', '$password', 'masseuse')";
                    if ($conn->query($user_sql)) {
                        $user_id = $conn->insert_id;
                        
                        // Create masseuse record
                        $sql = "INSERT INTO masseuses (name, mobile, bio, specialties, image_url, user_id) VALUES ('$name', '$mobile', '$bio', '$specialties', '$image_url', $user_id)";
                        if ($conn->query($sql)) {
                            $message = "Masseuse added successfully!";
                        } else {
                            // Rollback user creation if masseuse creation fails
                            $conn->query("DELETE FROM users WHERE id=$user_id");
                            $message = "Error creating masseuse record.";
                        }
                    } else {
                        $message = "Error creating user account: " . $conn->error;
                    }
                }
            }
        } elseif ($_POST['action'] === 'edit') {
            $id = (int)$_POST['id'];
            $name = sanitize($conn, $_POST['name']);
            $username = sanitize($conn, $_POST['username']);
            $mobile = sanitize($conn, $_POST['mobile']);
            $bio = sanitize($conn, $_POST['bio']);
            $specialties = sanitize($conn, $_POST['specialties']);
            $image_url = sanitize($conn, $_POST['image_url']);
            
            // Update masseuse record
            $sql = "UPDATE masseuses SET name='$name', mobile='$mobile', bio='$bio', specialties='$specialties', image_url='$image_url' WHERE id=$id";
            if ($conn->query($sql)) {
                // Get user_id for this masseuse
                $user_result = $conn->query("SELECT user_id FROM masseuses WHERE id=$id");
                if ($user_result && $user_result->num_rows > 0) {
                    $user_id = $user_result->fetch_assoc()['user_id'];
                    
                    // If masseuse doesn't have a user account yet, create one
                    if (empty($user_id)) {
                        // Check if password is provided for new account
                        if (!empty($_POST['password']) && $_POST['password'] === $_POST['password_confirm']) {
                            // Check if mobile or username already exists
                            $check_sql = "SELECT id FROM users WHERE mobile='$mobile' OR username='$username'";
                            $check_result = $conn->query($check_sql);
                            if ($check_result && $check_result->num_rows > 0) {
                                $message = "Masseuse updated but mobile number or username is already registered!";
                            } else {
                                $password = $_POST['password'];
                                $create_user_sql = "INSERT INTO users (name, username, mobile, password, role) VALUES ('$name', '$username', '$mobile', '$password', 'masseuse')";
                                if ($conn->query($create_user_sql)) {
                                    $user_id = $conn->insert_id;
                                    // Link the user account to the masseuse
                                    $conn->query("UPDATE masseuses SET user_id=$user_id WHERE id=$id");
                                    $message = "Masseuse updated and user account created successfully!";
                                } else {
                                    $message = "Masseuse updated but failed to create user account: " . $conn->error;
                                }
                            }
                        } else {
                            $message = "Masseuse updated. Note: No user account exists. Add a password to create login access.";
                        }
                    } else {
                        // Check for duplicate username/mobile (excluding current user)
                        $check_sql = "SELECT id FROM users WHERE (mobile='$mobile' OR username='$username') AND id != $user_id";
                        $check_result = $conn->query($check_sql);
                        
                        if ($check_result && $check_result->num_rows > 0) {
                             $message = "Masseuse updated but mobile/username change failed: Already taken!";
                        } else {
                            // Update existing user account
                            $user_sql = "UPDATE users SET name='$name', username='$username', mobile='$mobile' WHERE id=$user_id";
                            $conn->query($user_sql);
                            
                            // Update password if provided
                            if (!empty($_POST['password'])) {
                                $password = $_POST['password'];
                                $password_confirm = $_POST['password_confirm'];
                                
                                if ($password === $password_confirm) {
                                    $conn->query("UPDATE users SET password='$password' WHERE id=$user_id");
                                } else {
                                    $message = "Masseuse updated but passwords did not match!";
                                }
                            }
                            
                            if (empty($message)) {
                                $message = "Masseuse updated successfully!";
                            }
                        }
                    }
                }
            }
        } elseif ($_POST['action'] === 'delete') {
            $id = (int)$_POST['id'];
            
            // Get user_id before deleting masseuse
            $user_result = $conn->query("SELECT user_id FROM masseuses WHERE id=$id");
            if ($user_result && $user_result->num_rows > 0) {
                $user_id = $user_result->fetch_assoc()['user_id'];
                
                // Delete masseuse
                $sql = "DELETE FROM masseuses WHERE id=$id";
                if ($conn->query($sql)) {
                    // Delete associated user account
                    $conn->query("DELETE FROM users WHERE id=$user_id");
                    $message = "Masseuse deleted successfully!";
                }
            }
        }
    }
}

$masseuses = getMasseuses($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Masseuses - Kallma Spa</title>
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
                        <?php if (isAdmin()): ?>
                        <li><a href="services.php">Services</a></li>
                        <?php endif; ?>
                        <li><a href="<?php echo isMasseuse() ? 'masseuse_schedule.php' : 'masseuses.php'; ?>">Masseuses</a></li>
                        <li><a href="bookings.php">Bookings</a></li>
                        <?php if (isAdmin()): ?>
                        <li><a href="users.php">Users</a></li>
                        <?php endif; ?>
                        <li><a href="../index.php">View Site</a></li>
                    </ul>
                    <a href="../logout.php" class="btn btn-outline logout-btn" style="padding: 0.5rem 1rem;">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container" style="padding: 3rem 2rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h1>Manage Masseuses</h1>
            <button onclick="openAddModal()" class="btn btn-primary">Add Masseuse</button>
        </div>

        <?php if ($message): ?>
            <div style="background: rgba(16, 185, 129, 0.2); color: #6ee7b7; padding: 1rem; border-radius: 8px; margin-bottom: 2rem;">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="glass-card">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Specialties</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($masseuses as $masseuse): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($masseuse['name']); ?></td>
                            <td><?php echo htmlspecialchars($masseuse['username'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($masseuse['specialties']); ?></td>
                            <td>
                                <button onclick='openEditModal(<?php echo json_encode($masseuse); ?>)' class="btn btn-outline btn-small">Edit</button>
                                <button onclick='window.location.href="masseuse_schedule.php?masseuse_id=<?php echo $masseuse['id']; ?>"' class="btn btn-primary btn-small">Schedule</button>

                                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this masseuse?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $masseuse['id']; ?>">
                                    <button type="submit" class="btn btn-outline btn-small" style="border-color: #ef4444; color: #ef4444;">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add/Edit Modal -->
    <div id="masseuseModal" class="modal">
        <div class="modal-content glass-card">
            <h2 id="modalTitle">Add Masseuse</h2>
            <form method="POST" id="masseuseForm">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="masseuseId">
                
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" name="name" id="masseuseName" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" id="masseuseUsername" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Mobile</label>
                    <input type="text" name="mobile" id="masseuseMobile" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Bio</label>
                    <textarea name="bio" id="masseuseBio" class="form-control" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label>Specialties</label>
                    <input type="text" name="specialties" id="masseuseSpecialties" class="form-control">
                </div>
                <div class="form-group">
                    <label>Image URL</label>
                    <input type="text" name="image_url" id="masseuseImage" class="form-control">
                </div>
                <div class="form-group" id="passwordGroup">
                    <label>Password <span id="passwordOptional" style="color: #94a3b8; font-size: 0.85em;">(leave blank to keep current)</span></label>
                    <input type="password" name="password" id="masseusePassword" class="form-control">
                </div>
                <div class="form-group" id="passwordConfirmGroup">
                    <label>Confirm Password</label>
                    <input type="password" name="password_confirm" id="masseusePasswordConfirm" class="form-control">
                </div>
                <div style="display: flex; gap: 1rem;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">Save</button>
                    <button type="button" onclick="closeModal()" class="btn btn-outline" style="flex: 1;">Cancel</button>
                </div>
            </form>
        </div>
    </div>



    <script>
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add Masseuse';
            document.getElementById('formAction').value = 'add';
            document.getElementById('masseuseForm').reset();
            document.getElementById('passwordOptional').style.display = 'none';
            document.getElementById('masseusePassword').required = true;
            document.getElementById('masseusePasswordConfirm').required = true;
            document.getElementById('masseuseModal').style.display = 'block';
        }

        function openEditModal(masseuse) {
            document.getElementById('modalTitle').textContent = 'Edit Masseuse';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('masseuseId').value = masseuse.id;
            document.getElementById('masseuseName').value = masseuse.name;
            document.getElementById('masseuseUsername').value = masseuse.username || '';
            document.getElementById('masseuseMobile').value = masseuse.mobile || '';
            document.getElementById('masseuseBio').value = masseuse.bio;
            document.getElementById('masseuseSpecialties').value = masseuse.specialties;
            document.getElementById('masseuseImage').value = masseuse.image_url || '';
            document.getElementById('masseusePassword').value = '';
            document.getElementById('masseusePasswordConfirm').value = '';
            document.getElementById('passwordOptional').style.display = 'inline';
            document.getElementById('masseusePassword').required = false;
            document.getElementById('masseusePasswordConfirm').required = false;
            document.getElementById('masseuseModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('masseuseModal').style.display = 'none';
        }


    </script>
</body>
</html>
