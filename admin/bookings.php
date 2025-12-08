<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

session_start();

if (function_exists('checkSessionTimeout') && checkSessionTimeout()) {
    redirect('../login.php?timeout=1');
}

if (!isLoggedIn() || (!isAdmin() && !isMasseuse())) {
    redirect('../login.php');
}

// Get masseuse ID if logged in as masseuse
$logged_in_masseuse_id = null;
if (isMasseuse()) {
    $logged_in_masseuse_id = getMasseuseIdByUserId($conn, $_SESSION['user_id']);
}

$message = '';

// Get lists for edit modal
$services_list = getServices($conn);
$masseuses_list = getMasseuses($conn);

// Handle booking update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_booking') {
    $id = (int)$_POST['booking_id'];
    $service_id = (int)$_POST['service_id'];
    $masseuse_id = (int)$_POST['masseuse_id'];
    $date = sanitize($conn, $_POST['date']);
    $time = sanitize($conn, $_POST['time']);
    
    // Basic validation could be added here
    
    $sql = "UPDATE bookings SET service_id=$service_id, masseuse_id=$masseuse_id, booking_date='$date', booking_time='$time' WHERE id=$id";
    
    if ($conn->query($sql)) {
        $message = "Booking updated successfully!";
    } else {
        $message = "Error updating booking: " . $conn->error;
    }
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $id = (int)$_POST['booking_id'];
    $status = sanitize($conn, $_POST['status']);
    
    // Verify ownership if masseuse
    if (isMasseuse()) {
        $check_sql = "SELECT id FROM bookings WHERE id=$id AND masseuse_id=$logged_in_masseuse_id";
        if ($conn->query($check_sql)->num_rows === 0) {
            die("Unauthorized access");
        }
    }
    
    $sql = "UPDATE bookings SET status='$status' WHERE id=$id";
    if ($conn->query($sql)) {
        $message = "Booking status updated successfully!";
    }
}

// Handle sorting
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'booking_date';
$order = isset($_GET['order']) ? $_GET['order'] : 'DESC';

// Validate sort column and order
$allowed_sort_columns = ['customer_name', 'masseuse_name', 'booking_date', 'status'];
if (!in_array($sort, $allowed_sort_columns)) {
    $sort = 'booking_date';
}
$order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

// Pagination
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Get total bookings for pagination
if (isMasseuse()) {
    $count_sql = "SELECT COUNT(*) as total FROM bookings WHERE masseuse_id=$logged_in_masseuse_id AND booking_date >= CURDATE()";
} else {
    $count_sql = "SELECT COUNT(*) as total FROM bookings WHERE booking_date >= CURDATE()";
}
$total_bookings = $conn->query($count_sql)->fetch_assoc()['total'];
$total_pages = ceil($total_bookings / $limit);

// Get all bookings
$bookings_sql = "SELECT b.*, u.name as customer_name, u.mobile as customer_mobile, s.name as service_name, s.price, m.name as masseuse_name 
                 FROM bookings b 
                 LEFT JOIN users u ON b.user_id = u.id 
                 JOIN services s ON b.service_id = s.id 
                 JOIN masseuses m ON b.masseuse_id = m.id ";

$where_clauses = ["b.booking_date >= CURDATE()"];
if (isMasseuse()) {
    $where_clauses[] = "b.masseuse_id=$logged_in_masseuse_id";
}

if (!empty($where_clauses)) {
    $bookings_sql .= " WHERE " . implode(' AND ', $where_clauses) . " ";
}

$bookings_sql .= "ORDER BY ";

// Add table prefix for ambiguous columns or use alias for others
if ($sort === 'booking_date') {
    $bookings_sql .= "b.booking_date $order, b.booking_time $order";
} elseif ($sort === 'status') {
    $bookings_sql .= "b.status $order";
} else {
    $bookings_sql .= "$sort $order";
}

$bookings_sql .= " LIMIT $limit OFFSET $offset";

$bookings = $conn->query($bookings_sql)->fetch_all(MYSQLI_ASSOC);

function getSortLink($column, $currentSort, $currentOrder) {
    $newOrder = ($currentSort === $column && $currentOrder === 'ASC') ? 'DESC' : 'ASC';
    $icon = '';
    if ($currentSort === $column) {
        $icon = $currentOrder === 'ASC' ? ' ↑' : ' ↓';
    }
    return "<a href='?sort=$column&order=$newOrder' style='color: inherit; text-decoration: none;'>$column" . ucfirst($icon) . "</a>"; // ucfirst is just a placeholder, icon is text
}

function renderSortHeader($label, $column, $currentSort, $currentOrder) {
    $newOrder = ($currentSort === $column && $currentOrder === 'ASC') ? 'DESC' : 'ASC';
    $isActive = $currentSort === $column;
    
    // Default/Inactive state
    $icon = '↕';
    // Inactive: Text inherits primary color, Arrow is subtle
    $textStyle = "color: inherit;"; 
    $arrowStyle = "color: rgba(255, 255, 255, 0.2);"; 
    
    // Active state
    if ($isActive) {
        $icon = $currentOrder === 'ASC' ? '▲' : '▼';
        // Active: Text is Light Green, Arrow is Dark Green (using primary hover)
        $textStyle = "color: #6ee7b7;"; 
        $arrowStyle = "color: #059669;"; 
    }
    
    return "<a href='?sort=$column&order=$newOrder' style='text-decoration: none; display: flex; align-items: center; gap: 0.5rem; transition: all 0.3s ease; $textStyle'>
                $label <span style='font-size: 0.9em; $arrowStyle'>$icon</span>
            </a>";
}
$pageTitle = 'Manage Bookings - Kallma Spa';
require_once 'includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <h1>Manage Bookings</h1>
    <a href="bookings_history.php" class="btn btn-outline">📜 History</a>
</div>

<?php if ($message): ?>
    <div style="background: rgba(16, 185, 129, 0.2); color: #6ee7b7; padding: 1rem; border-radius: 8px; margin: 2rem 0;">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<div style="margin-bottom: 1rem;">
    <p style="color: #94a3b8; font-size: 0.95rem; margin: 0;">
        <strong style="color: #e2e8f0;">Today:</strong> <?php echo date('l, F j, Y'); ?>
    </p>
</div>

<div class="glass-card">
    <div style="overflow-x: auto;">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th><?php echo renderSortHeader('Customer', 'customer_name', $sort, $order); ?></th>
                <th>Service</th>
                <th><?php echo renderSortHeader('Masseuse', 'masseuse_name', $sort, $order); ?></th>
                <th><?php echo renderSortHeader('Date', 'booking_date', $sort, $order); ?></th>
                <th>Time</th>

                <th><?php echo renderSortHeader('Status', 'status', $sort, $order); ?></th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($bookings)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; padding: 2rem; color: #64748b;">
                        No active bookings found.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($bookings as $booking): ?>
                    <tr>
                        <td><?php echo str_pad($booking['id'], 4, '0', STR_PAD_LEFT); ?></td>
                        <td>
                            <?php echo htmlspecialchars($booking['customer_name'] ?? 'Guest'); ?><br>
                            <small style="color: #64748b;"><?php echo $booking['customer_mobile'] ? htmlspecialchars(str_replace(' ', '', trim($booking['customer_mobile']))) : ''; ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($booking['service_name']); ?></td>
                        <td><?php echo htmlspecialchars($booking['masseuse_name']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($booking['booking_date'])); ?></td>
                        <td><?php echo date('g:i A', strtotime($booking['booking_time'])); ?></td>

                        <td><span class="badge badge-<?php echo $booking['status']; ?>"><?php echo ucfirst($booking['status']); ?></span></td>
                        <td>
                            <div style="display: flex; gap: 0.5rem; align-items: center;">
                                <a href="../booking.php?edit_id=<?php echo $booking['id']; ?>" class="icon-btn" title="Edit">
                                    ✎
                                </a>
                                <form method="POST" style="display: inline; margin: 0;">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                    <select name="status" class="status-select" onchange="this.form.submit()">
                                        <option value="pending" <?php echo $booking['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="completed" <?php echo $booking['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                        <option value="cancelled" <?php echo $booking['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
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

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
<div style="display: flex; justify-content: center; gap: 0.5rem; margin-top: 2rem;">
    <?php if ($page > 1): ?>
        <a href="?page=<?php echo $page - 1; ?>&sort=<?php echo $sort; ?>&order=<?php echo $order; ?>" class="btn btn-outline" style="padding: 0.5rem 1rem;">&laquo; Prev</a>
    <?php endif; ?>

    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <a href="?page=<?php echo $i; ?>&sort=<?php echo $sort; ?>&order=<?php echo $order; ?>" 
            class="btn <?php echo $i === $page ? 'btn-primary' : 'btn-outline'; ?>" 
            style="padding: 0.5rem 1rem;">
            <?php echo $i; ?>
        </a>
    <?php endfor; ?>

    <?php if ($page < $total_pages): ?>
        <a href="?page=<?php echo $page + 1; ?>&sort=<?php echo $sort; ?>&order=<?php echo $order; ?>" class="btn btn-outline" style="padding: 0.5rem 1rem;">Next &raquo;</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
