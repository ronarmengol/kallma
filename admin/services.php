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
            $description = sanitize($conn, $_POST['description']);
            $price = (float)$_POST['price'];
            $duration = (int)$_POST['duration_minutes'];
            $image_url = sanitize($conn, $_POST['image_url']);
            
            $sql = "INSERT INTO services (name, description, price, duration_minutes, image_url) VALUES ('$name', '$description', $price, $duration, '$image_url')";
            if ($conn->query($sql)) {
                $message = "Service added successfully!";
            }
        } elseif ($_POST['action'] === 'edit') {
            $id = (int)$_POST['id'];
            $name = sanitize($conn, $_POST['name']);
            $description = sanitize($conn, $_POST['description']);
            $price = (float)$_POST['price'];
            $duration = (int)$_POST['duration_minutes'];
            $image_url = sanitize($conn, $_POST['image_url']);
            
            $sql = "UPDATE services SET name='$name', description='$description', price=$price, duration_minutes=$duration, image_url='$image_url' WHERE id=$id";
            if ($conn->query($sql)) {
                $message = "Service updated successfully!";
            }
        } elseif ($_POST['action'] === 'delete') {
            $id = (int)$_POST['id'];
            $sql = "DELETE FROM services WHERE id=$id";
            if ($conn->query($sql)) {
                $message = "Service deleted successfully!";
            }
        }
    }
}

$services = getServices($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Services - Kallma Spa</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .admin-nav {
            background: rgba(15, 23, 42, 0.95);
            padding: 1rem 0;
            border-bottom: 1px solid var(--glass-border);
        }
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
            <h1>Manage Services</h1>
            <button onclick="openAddModal()" class="btn btn-primary">Add Service</button>
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
                        <th>Price</th>
                        <th>Duration</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($services as $service): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($service['name']); ?></td>
                            <td>$<?php echo number_format($service['price'], 2); ?></td>
                            <td><?php echo $service['duration_minutes']; ?> mins</td>
                            <td>
                                <button onclick='openEditModal(<?php echo json_encode($service); ?>)' class="btn btn-outline btn-small">Edit</button>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this service?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $service['id']; ?>">
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
    <div id="serviceModal" class="modal">
        <div class="modal-content glass-card">
            <h2 id="modalTitle">Add Service</h2>
            <form method="POST" id="serviceForm">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="serviceId">
                
                <div class="form-group">
                    <label>Service Name</label>
                    <input type="text" name="name" id="serviceName" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="serviceDescription" class="form-control" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label>Price ($)</label>
                    <input type="number" step="0.01" name="price" id="servicePrice" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Duration (minutes)</label>
                    <input type="number" name="duration_minutes" id="serviceDuration" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Image URL</label>
                    <input type="text" name="image_url" id="serviceImage" class="form-control">
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
            document.getElementById('modalTitle').textContent = 'Add Service';
            document.getElementById('formAction').value = 'add';
            document.getElementById('serviceForm').reset();
            document.getElementById('serviceModal').style.display = 'block';
        }

        function openEditModal(service) {
            document.getElementById('modalTitle').textContent = 'Edit Service';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('serviceId').value = service.id;
            document.getElementById('serviceName').value = service.name;
            document.getElementById('serviceDescription').value = service.description;
            document.getElementById('servicePrice').value = service.price;
            document.getElementById('serviceDuration').value = service.duration_minutes;
            document.getElementById('serviceImage').value = service.image_url || '';
            document.getElementById('serviceModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('serviceModal').style.display = 'none';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('serviceModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>
