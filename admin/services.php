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
        $uploadDir = '../assets/images/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        if ($_POST['action'] === 'add') {
            $name = sanitize($conn, $_POST['name']);
            $description = sanitize($conn, $_POST['description']);
            $price = (float)$_POST['price'];
            $duration = (int)$_POST['duration_minutes'];
            
            $image_url = '';
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $fileName = time() . '_' . basename($_FILES['image']['name']);
                $targetPath = $uploadDir . $fileName;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                    $image_url = 'assets/images/' . $fileName;
                }
            }
            
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
            
            // Get current image url
            $current_image_sql = "SELECT image_url FROM services WHERE id=$id";
            $result = $conn->query($current_image_sql);
            $current_image = $result->fetch_assoc()['image_url'] ?? '';
            $image_url = $current_image;

            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                // Delete old image if it exists and is not empty
                if (!empty($current_image)) {
                    $old_image_path = '../' . $current_image;
                    if (file_exists($old_image_path)) {
                        unlink($old_image_path);
                    }
                }

                $fileName = time() . '_' . basename($_FILES['image']['name']);
                $targetPath = $uploadDir . $fileName;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                    $image_url = 'assets/images/' . $fileName;
                }
            }
            
            $sql = "UPDATE services SET name='$name', description='$description', price=$price, duration_minutes=$duration, image_url='$image_url' WHERE id=$id";
            if ($conn->query($sql)) {
                $message = "Service updated successfully!";
            }
        } elseif ($_POST['action'] === 'delete') {
            $id = (int)$_POST['id'];
            
            // Get image url before deleting
            $current_image_sql = "SELECT image_url FROM services WHERE id=$id";
            $result = $conn->query($current_image_sql);
            $current_image = $result->fetch_assoc()['image_url'] ?? '';

            // Delete image file
            if (!empty($current_image)) {
                $old_image_path = '../' . $current_image;
                if (file_exists($old_image_path)) {
                    unlink($old_image_path);
                }
            }

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
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid var(--glass-border); white-space: nowrap; }
        th { color: var(--primary-color); font-weight: 600; }
        .btn-small { padding: 0.5rem 1rem; font-size: 0.9rem; }
        .icon-btn {
            background: none;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
            padding: 0.5rem;
            transition: all 0.3s ease;
            border-radius: 8px;
            color: #94a3b8;
        }
        .icon-btn:hover {
            background: rgba(16, 185, 129, 0.1);
            transform: scale(1.1);
        }
        .icon-btn.delete:hover {
            background: rgba(239, 68, 68, 0.1);
        }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); overflow-y: auto; }
        .modal-content { 
            background: var(--card-bg); 
            margin: 2rem auto; 
            padding: 2rem; 
            border-radius: 16px; 
            max-width: 500px; 
            max-height: 90vh; 
            overflow-y: auto;
            position: relative;
        }

        /* Drop Zone CSS */
        .drop-zone {
            width: 100%;
            height: 200px;
            padding: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            font-family: inherit;
            font-weight: 500;
            font-size: 1rem;
            cursor: pointer;
            color: #94a3b8;
            border: 2px dashed rgba(16, 185, 129, 0.3);
            border-radius: 10px;
            background-color: rgba(255, 255, 255, 0.02);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .drop-zone:hover, .drop-zone.drop-zone--over {
            border-color: var(--primary-color);
            background-color: rgba(16, 185, 129, 0.05);
        }

        .drop-zone__input {
            display: none;
        }

        .drop-zone__thumb {
            width: 100%;
            height: 100%;
            border-radius: 10px;
            overflow: hidden;
            background-color: #1e293b;
            background-size: cover;
            background-position: center;
            position: absolute;
            top: 0;
            left: 0;
        }

        .drop-zone__thumb::after {
            content: attr(data-label);
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            padding: 5px 0;
            color: #ffffff;
            background: rgba(0, 0, 0, 0.75);
            font-size: 14px;
            text-align: center;
        }
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
            <div style="overflow-x: auto;">
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
                            <td>K<?php echo number_format($service['price'], 2); ?></td>
                            <td><?php echo $service['duration_minutes']; ?> mins</td>
                            <td style="display: flex; gap: 0.5rem; align-items: center;">
                                <button onclick='openEditModal(<?php echo json_encode($service); ?>)' class="icon-btn" title="Edit">
                                    ✎
                                </button>
                                <form method="POST" style="display: inline; margin: 0;" onsubmit="return confirm('Delete this service?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $service['id']; ?>">
                                    <button type="submit" class="icon-btn delete" title="Delete">
                                        🗑️
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>

    <!-- Add/Edit Modal -->
    <div id="serviceModal" class="modal">
        <div class="modal-content glass-card">
            <h2 id="modalTitle">Add Service</h2>
            <form method="POST" id="serviceForm" enctype="multipart/form-data">
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
                    <label>Price (K)</label>
                    <input type="number" step="0.01" name="price" id="servicePrice" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Duration (minutes)</label>
                    <input type="number" name="duration_minutes" id="serviceDuration" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Service Image</label>
                    <div class="drop-zone" id="dropZone">
                        <span class="drop-zone__prompt">Drop file here or click to upload</span>
                        <input type="file" name="image" id="serviceImage" class="drop-zone__input" accept="image/*">
                    </div>
                </div>
                <div style="display: flex; gap: 1rem;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">Save</button>
                    <button type="button" onclick="closeModal()" class="btn btn-outline" style="flex: 1;">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Drag and Drop Logic
        document.querySelectorAll(".drop-zone__input").forEach((inputElement) => {
            const dropZoneElement = inputElement.closest(".drop-zone");

            dropZoneElement.addEventListener("click", (e) => {
                inputElement.click();
            });

            inputElement.addEventListener("change", (e) => {
                if (inputElement.files.length) {
                    updateThumbnail(dropZoneElement, inputElement.files[0]);
                }
            });

            dropZoneElement.addEventListener("dragover", (e) => {
                e.preventDefault();
                dropZoneElement.classList.add("drop-zone--over");
            });

            ["dragleave", "dragend"].forEach((type) => {
                dropZoneElement.addEventListener(type, (e) => {
                    dropZoneElement.classList.remove("drop-zone--over");
                });
            });

            dropZoneElement.addEventListener("drop", (e) => {
                e.preventDefault();

                if (e.dataTransfer.files.length) {
                    inputElement.files = e.dataTransfer.files;
                    updateThumbnail(dropZoneElement, e.dataTransfer.files[0]);
                }

                dropZoneElement.classList.remove("drop-zone--over");
            });
        });

        function updateThumbnail(dropZoneElement, file) {
            let thumbnailElement = dropZoneElement.querySelector(".drop-zone__thumb");

            // First time - remove the prompt
            if (dropZoneElement.querySelector(".drop-zone__prompt")) {
                dropZoneElement.querySelector(".drop-zone__prompt").remove();
            }

            // First time - there is no thumbnail element, so lets create it
            if (!thumbnailElement) {
                thumbnailElement = document.createElement("div");
                thumbnailElement.classList.add("drop-zone__thumb");
                dropZoneElement.appendChild(thumbnailElement);
            }

            thumbnailElement.dataset.label = file.name;

            // Show thumbnail for image files
            if (file.type.startsWith("image/")) {
                const reader = new FileReader();

                reader.readAsDataURL(file);
                reader.onload = () => {
                    thumbnailElement.style.backgroundImage = `url('${reader.result}')`;
                };
            } else {
                thumbnailElement.style.backgroundImage = null;
            }
        }

        function resetDropZone(dropZoneElement) {
            const thumbnailElement = dropZoneElement.querySelector(".drop-zone__thumb");
            if (thumbnailElement) {
                thumbnailElement.remove();
            }
            if (!dropZoneElement.querySelector(".drop-zone__prompt")) {
                const prompt = document.createElement("span");
                prompt.classList.add("drop-zone__prompt");
                prompt.textContent = "Drop file here or click to upload";
                dropZoneElement.appendChild(prompt);
            }
        }

        function setDropZoneImage(dropZoneElement, imageUrl) {
            // Remove prompt
            if (dropZoneElement.querySelector(".drop-zone__prompt")) {
                dropZoneElement.querySelector(".drop-zone__prompt").remove();
            }
            
            let thumbnailElement = dropZoneElement.querySelector(".drop-zone__thumb");
            if (!thumbnailElement) {
                thumbnailElement = document.createElement("div");
                thumbnailElement.classList.add("drop-zone__thumb");
                dropZoneElement.appendChild(thumbnailElement);
            }
            
            thumbnailElement.style.backgroundImage = `url('${imageUrl}')`;
            thumbnailElement.dataset.label = 'Current Image';
        }

        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add Service';
            document.getElementById('formAction').value = 'add';
            document.getElementById('serviceForm').reset();
            
            // Reset Drop Zone
            const dropZone = document.getElementById('dropZone');
            resetDropZone(dropZone);
            
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
            
            // Handle image preview in Drop Zone
            const dropZone = document.getElementById('dropZone');
            if (service.image_url) {
                setDropZoneImage(dropZone, '../' + service.image_url);
            } else {
                resetDropZone(dropZone);
            }
            
            // Reset file input
            document.getElementById('serviceImage').value = '';
            
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
