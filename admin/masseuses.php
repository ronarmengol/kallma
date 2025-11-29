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
            $bio = sanitize($conn, $_POST['bio']);
            $specialties = sanitize($conn, $_POST['specialties']);
            $image_url = sanitize($conn, $_POST['image_url']);
            
            $sql = "INSERT INTO masseuses (name, bio, specialties, image_url) VALUES ('$name', '$bio', '$specialties', '$image_url')";
            if ($conn->query($sql)) {
                $message = "Masseuse added successfully!";
            }
        } elseif ($_POST['action'] === 'edit') {
            $id = (int)$_POST['id'];
            $name = sanitize($conn, $_POST['name']);
            $bio = sanitize($conn, $_POST['bio']);
            $specialties = sanitize($conn, $_POST['specialties']);
            $image_url = sanitize($conn, $_POST['image_url']);
            
            $sql = "UPDATE masseuses SET name='$name', bio='$bio', specialties='$specialties', image_url='$image_url' WHERE id=$id";
            if ($conn->query($sql)) {
                $message = "Masseuse updated successfully!";
            }
        } elseif ($_POST['action'] === 'delete') {
            $id = (int)$_POST['id'];
            $sql = "DELETE FROM masseuses WHERE id=$id";
            if ($conn->query($sql)) {
                $message = "Masseuse deleted successfully!";
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
                        <th>Specialties</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($masseuses as $masseuse): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($masseuse['name']); ?></td>
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
            document.getElementById('masseuseModal').style.display = 'block';
        }

        function openEditModal(masseuse) {
            document.getElementById('modalTitle').textContent = 'Edit Masseuse';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('masseuseId').value = masseuse.id;
            document.getElementById('masseuseName').value = masseuse.name;
            document.getElementById('masseuseBio').value = masseuse.bio;
            document.getElementById('masseuseSpecialties').value = masseuse.specialties;
            document.getElementById('masseuseImage').value = masseuse.image_url || '';
            document.getElementById('masseuseModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('masseuseModal').style.display = 'none';
        }


    </script>
</body>
</html>
