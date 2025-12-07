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
        if ($_POST['action'] === 'add') {
            $question = sanitize($conn, $_POST['question']);
            $answer = sanitize($conn, $_POST['answer']);
            $category = sanitize($conn, $_POST['category']);
            
            $sql = "INSERT INTO faqs (question, answer, category) VALUES ('$question', '$answer', '$category')";
            if ($conn->query($sql)) {
                $message = "FAQ added successfully!";
            }
        } elseif ($_POST['action'] === 'edit') {
            $id = (int)$_POST['id'];
            $question = sanitize($conn, $_POST['question']);
            $answer = sanitize($conn, $_POST['answer']);
            $category = sanitize($conn, $_POST['category']);
            
            $sql = "UPDATE faqs SET question='$question', answer='$answer', category='$category' WHERE id=$id";
            if ($conn->query($sql)) {
                $message = "FAQ updated successfully!";
            }
        } elseif ($_POST['action'] === 'delete') {
            $id = (int)$_POST['id'];
            $sql = "DELETE FROM faqs WHERE id=$id";
            if ($conn->query($sql)) {
                $message = "FAQ deleted successfully!";
            }
        }
    }
}

// Fetch all FAQs
$faqs = $conn->query("SELECT * FROM faqs ORDER BY category ASC, created_at DESC")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Manage FAQs - Kallma Spa';
require_once 'includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <h1>Manage FAQs</h1>
    <button onclick="openAddModal()" class="btn btn-primary">Add FAQ</button>
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
                <th>Category</th>
                <th>Question</th>
                <th>Answer</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($faqs)): ?>
                <tr>
                    <td colspan="4" style="text-align: center; color: #94a3b8; padding: 2rem;">No FAQs found. Add one to get started!</td>
                </tr>
            <?php else: ?>
                <?php foreach ($faqs as $faq): ?>
                    <tr>
                        <td>
                            <span class="badge badge-<?php echo $faq['category'] === 'customer' ? 'confirmed' : 'pending'; ?>">
                                <?php echo ucfirst($faq['category']); ?>
                            </span>
                        </td>
                        <td style="font-weight: 500; color: #e2e8f0; white-space: normal; min-width: 200px; max-width: 300px;">
                            <?php echo htmlspecialchars($faq['question']); ?>
                        </td>
                        <td style="color: #94a3b8; white-space: normal; min-width: 300px; max-width: 500px;">
                            <?php echo htmlspecialchars(substr($faq['answer'], 0, 150)) . (strlen($faq['answer']) > 150 ? '...' : ''); ?>
                        </td>
                        <td>
                            <div style="display: flex; gap: 0.5rem; align-items: center;">
                                <button onclick='openEditModal(<?php echo htmlspecialchars(json_encode($faq), ENT_QUOTES, 'UTF-8'); ?>)' class="icon-btn" title="Edit">
                                    ✎
                                </button>
                                <form method="POST" style="display: inline; margin: 0;" onsubmit="return confirm('Delete this FAQ?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $faq['id']; ?>">
                                    <button type="submit" class="icon-btn delete" title="Delete">
                                        🗑️
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<!-- Add/Edit Modal -->
<div id="faqModal" class="modal">
    <div class="modal-content glass-card">
        <h2 id="modalTitle">Add FAQ</h2>
        <form method="POST" id="faqForm">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="faqId">
            
            <div class="form-group">
                <label>Category</label>
                <select name="category" id="faqCategory" class="form-control">
                    <option value="customer">Customer</option>
                    <option value="staff">Staff</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Question</label>
                <input type="text" name="question" id="faqQuestion" class="form-control" required placeholder="e.g., What are your opening hours?">
            </div>
            
            <div class="form-group">
                <label>Answer</label>
                <textarea name="answer" id="faqAnswer" class="form-control" rows="5" required placeholder="e.g., We are open from 9am to 9pm daily."></textarea>
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
        document.getElementById('modalTitle').textContent = 'Add FAQ';
        document.getElementById('formAction').value = 'add';
        document.getElementById('faqForm').reset();
        document.getElementById('faqModal').style.display = 'block';
    }

    function openEditModal(faq) {
        document.getElementById('modalTitle').textContent = 'Edit FAQ';
        document.getElementById('formAction').value = 'edit';
        document.getElementById('faqId').value = faq.id;
        document.getElementById('faqCategory').value = faq.category;
        document.getElementById('faqQuestion').value = faq.question;
        document.getElementById('faqAnswer').value = faq.answer;
        document.getElementById('faqModal').style.display = 'block';
    }

    function closeModal() {
        document.getElementById('faqModal').style.display = 'none';
    }

    window.onclick = function(event) {
        const modal = document.getElementById('faqModal');
        if (event.target == modal) {
            closeModal();
        }
    }
</script>

<?php require_once 'includes/footer.php'; ?>