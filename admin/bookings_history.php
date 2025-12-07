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

// Get total bookings for pagination (HISTORY ONLY: < CURDATE())
if (isMasseuse()) {
    $count_sql = "SELECT COUNT(*) as total FROM bookings WHERE masseuse_id=$logged_in_masseuse_id AND booking_date < CURDATE()";
} else {
    $count_sql = "SELECT COUNT(*) as total FROM bookings WHERE booking_date < CURDATE()";
}
$total_bookings = $conn->query($count_sql)->fetch_assoc()['total'];
$total_pages = ceil($total_bookings / $limit);

// Get all bookings
$bookings_sql = "SELECT b.*, u.name as customer_name, u.mobile as customer_mobile, s.name as service_name, s.price, m.name as masseuse_name 
                 FROM bookings b 
                 LEFT JOIN users u ON b.user_id = u.id 
                 JOIN services s ON b.service_id = s.id 
                 JOIN masseuses m ON b.masseuse_id = m.id ";

$where_clauses = ["b.booking_date < CURDATE()"];
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
$pageTitle = 'Booking History - Kallma Spa';
require_once 'includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <h1>Booking History</h1>
    <a href="bookings.php" class="btn btn-outline">← Current Bookings</a>
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
            </tr>
        </thead>
        <tbody>
            <?php foreach ($bookings as $booking): ?>
                <tr>
                    <td><?php echo str_pad($booking['id'], 4, '0', STR_PAD_LEFT); ?></td>
                    <td>
                        <?php echo htmlspecialchars($booking['customer_name'] ?? 'Guest'); ?><br>
                        <small style="color: #64748b;"><?php echo $booking['customer_mobile'] ? 'Mobile: ' . htmlspecialchars($booking['customer_mobile']) : ''; ?></small>
                    </td>
                    <td><?php echo htmlspecialchars($booking['service_name']); ?></td>
                    <td><?php echo htmlspecialchars($booking['masseuse_name']); ?></td>
                    <td><?php echo date('M d, Y', strtotime($booking['booking_date'])); ?></td>
                    <td><?php echo date('g:i A', strtotime($booking['booking_time'])); ?></td>
                    <td><span class="badge badge-<?php echo $booking['status']; ?>"><?php echo ucfirst($booking['status']); ?></span></td>
                </tr>
            <?php endforeach; ?>
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
