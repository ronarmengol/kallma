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
$pageTitle = 'Manage Services - Kallma Spa';
require_once 'includes/header.php';
?>

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

<?php require_once 'includes/footer.php'; ?>
