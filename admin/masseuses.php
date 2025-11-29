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
    <style>
        .admin-nav { background: rgba(15, 23, 42, 0.95); padding: 1rem 0; border-bottom: 1px solid var(--glass-border); }
        .admin-nav .nav-links {
            display: flex;
            gap: 1.5rem;
            align-items: center;
            list-style: none;
        }
        .menu-toggle { display: none; font-size: 1.5rem; background: none; border: none; color: white; cursor: pointer; }
        
        @media (max-width: 768px) {
            .admin-nav .container > div {
                position: relative;
            }
            
            .admin-nav .nav-links {
                display: none;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                width: 100vw;
                margin-left: calc(-50vw + 50%);
                background: #0f172a;
                flex-direction: column;
                padding: 1rem 0;
                border-bottom: 1px solid rgba(255,255,255,0.1);
                z-index: 1000;
                gap: 0;
            }
            
            .admin-nav .nav-links.active {
                display: flex;
            }
            
            .admin-nav .nav-links li {
                width: 100%;
                text-align: center;
                padding: 0.75rem 0;
                border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            }
            
            .admin-nav .nav-links li:last-child {
                border-bottom: none;
            }
            
            .menu-toggle {
                display: block;
            }
        }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid var(--glass-border); }
        th { color: var(--primary-color); font-weight: 600; }
        .btn-small { padding: 0.5rem 1rem; font-size: 0.9rem; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); }
        .modal-content { background: var(--card-bg); margin: 5% auto; padding: 2rem; border-radius: 16px; max-width: 500px; }

    </style>
</head>
<body>
    <nav class="admin-nav">
        <div class="container">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <a href="index.php" class="logo">Kallma Admin</a>
                <button class="menu-toggle" onclick="document.querySelector('.nav-links').classList.toggle('active')">☰</button>
                <ul class="nav-links">
                    <li><a href="index.php">Dashboard</a></li>
                    <li><a href="services.php">Services</a></li>
                    <li><a href="masseuses.php">Masseuses</a></li>
                    <li><a href="bookings.php">Bookings</a></li>
                    <li><a href="../index.php">View Site</a></li>
                    <li><a href="../logout.php" class="btn btn-outline" style="padding: 0.5rem 1rem;">Logout</a></li>
                </ul>
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
