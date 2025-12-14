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
    <a href="bookings_history.php" class="btn btn-outline" style="display: flex; align-items: center; gap: 0.5rem;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <polyline points="12 6 12 12 16 14"></polyline>
        </svg>
        History
    </a>
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
                <th>REF</th>
                <th><?php echo renderSortHeader('Customer', 'customer_name', $sort, $order); ?></th>
                <th>Service</th>
                <th><?php echo renderSortHeader('Masseuse', 'masseuse_name', $sort, $order); ?></th>
                <th><?php echo renderSortHeader('Date', 'booking_date', $sort, $order); ?></th>
                <th><?php echo renderSortHeader('Status', 'status', $sort, $order); ?></th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($bookings)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 2rem; color: #64748b;">
                        No active bookings found.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($bookings as $booking): ?>
                    <tr>
                        <td><?php echo str_pad($booking['id'], 4, '0', STR_PAD_LEFT); ?></td>
                        <td>
                            <?php 
                            // Check if this is a walk-in client booking
                            if (!empty($booking['walk_in_client_name'])) {
                                // Show walk-in client name with staff member below
                                echo htmlspecialchars($booking['walk_in_client_name']);
                                echo '<br><small style="color: #64748b;">via ' . htmlspecialchars($booking['customer_name'] ?? 'Staff') . '</small>';
                                echo '<br><small style="color: #64748b;">' . htmlspecialchars($booking['walk_in_client_mobile'] ?? '') . '</small>';
                            } else {
                                // Regular customer booking
                                echo htmlspecialchars($booking['customer_name'] ?? 'Guest');
                                echo '<br><small style="color: #64748b;">' . ($booking['customer_mobile'] ? htmlspecialchars(str_replace(' ', '', trim($booking['customer_mobile']))) : '') . '</small>';
                            }
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars($booking['service_name']); ?></td>
                        <td><?php echo htmlspecialchars($booking['masseuse_name']); ?></td>
                        <td>
                            <?php echo date('M d, Y', strtotime($booking['booking_date'])); ?>
                            <br><small style="color: #64748b;"><?php echo date('g:i A', strtotime($booking['booking_time'])); ?></small>
                        </td>
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

<?php
// Monthly Bookings Calendar Overview
// Get current month and year or from query params
$calendar_month = isset($_GET['calendar_month']) ? (int)$_GET['calendar_month'] : (int)date('n');
$calendar_year = isset($_GET['calendar_year']) ? (int)$_GET['calendar_year'] : (int)date('Y');
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : 'completed';

// Validate status filter
$allowed_statuses = ['completed', 'pending'];
if (!in_array($status_filter, $allowed_statuses)) {
    $status_filter = 'completed';
}

// Get bookings for the month based on status filter
$first_day = "$calendar_year-" . str_pad($calendar_month, 2, '0', STR_PAD_LEFT) . "-01";
$last_day = date('Y-m-t', strtotime($first_day));

$calendar_bookings_sql = "SELECT b.*, m.name as masseuse_name, s.name as service_name 
                FROM bookings b 
                JOIN masseuses m ON b.masseuse_id = m.id 
                JOIN services s ON b.service_id = s.id 
                WHERE b.status = '$status_filter' 
                AND b.booking_date BETWEEN '$first_day' AND '$last_day'";

// Add masseuse filter if logged in as masseuse
if (isMasseuse()) {
    $calendar_bookings_sql .= " AND b.masseuse_id = $logged_in_masseuse_id";
}

$calendar_bookings_sql .= " ORDER BY b.booking_date, b.booking_time";
$calendar_bookings_result = $conn->query($calendar_bookings_sql);

// Organize bookings by date
$bookings_by_date = [];
if ($calendar_bookings_result && $calendar_bookings_result->num_rows > 0) {
    while ($booking = $calendar_bookings_result->fetch_assoc()) {
        $date = $booking['booking_date'];
        if (!isset($bookings_by_date[$date])) {
            $bookings_by_date[$date] = [];
        }
        $bookings_by_date[$date][] = $booking;
    }
}

// Calendar calculations
$days_in_month = (int)date('t', strtotime($first_day));
$first_day_of_week = (int)date('N', strtotime($first_day)); // 1 (Monday) to 7 (Sunday)

// Navigation dates
$prev_month = $calendar_month - 1;
$prev_year = $calendar_year;
if ($prev_month < 1) {
    $prev_month = 12;
    $prev_year--;
}

$next_month = $calendar_month + 1;
$next_year = $calendar_year;
if ($next_month > 12) {
    $next_month = 1;
    $next_year++;
}

// Status display names
$status_names = [
    'completed' => 'Completed',
    'pending' => 'Pending'
];
?>

<div class="glass-card" style="margin-top: 3rem;" id="bookingsCalendar">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <h2 style="margin: 0;"><?php echo $status_names[$status_filter]; ?> Bookings - <?php echo date('F Y', strtotime($first_day)); ?></h2>
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
            <!-- Status Filter Buttons -->
            <div style="display: flex; gap: 0.5rem; padding: 0.25rem; background: rgba(255, 255, 255, 0.05); border-radius: 8px;">
                <a href="?calendar_month=<?php echo $calendar_month; ?>&calendar_year=<?php echo $calendar_year; ?>&status_filter=completed#bookingsCalendar" 
                    class="btn btn-small <?php echo $status_filter === 'completed' ? 'btn-primary' : 'btn-outline'; ?>" 
                    style="padding: 0.5rem 1rem;">Completed</a>
                <a href="?calendar_month=<?php echo $calendar_month; ?>&calendar_year=<?php echo $calendar_year; ?>&status_filter=pending#bookingsCalendar" 
                    class="btn btn-small <?php echo $status_filter === 'pending' ? 'btn-primary' : 'btn-outline'; ?>" 
                    style="padding: 0.5rem 1rem;">Pending</a>
            </div>
            
            <!-- Month Navigation -->
            <a href="?calendar_month=<?php echo $prev_month; ?>&calendar_year=<?php echo $prev_year; ?>&status_filter=<?php echo $status_filter; ?>#bookingsCalendar" class="btn btn-outline btn-small">← Previous</a>
            <span style="color: #94a3b8; font-weight: 600; padding: 0 0.5rem;">Month</span>
            <a href="?calendar_month=<?php echo $next_month; ?>&calendar_year=<?php echo $next_year; ?>&status_filter=<?php echo $status_filter; ?>#bookingsCalendar" class="btn btn-outline btn-small">Next →</a>
        </div>
    </div>
    
    <div class="calendar-overview">
        <div class="calendar-header-row">
            <div class="calendar-header-cell">Mon</div>
            <div class="calendar-header-cell">Tue</div>
            <div class="calendar-header-cell">Wed</div>
            <div class="calendar-header-cell">Thu</div>
            <div class="calendar-header-cell">Fri</div>
            <div class="calendar-header-cell">Sat</div>
            <div class="calendar-header-cell">Sun</div>
        </div>
        
        <div class="calendar-grid-overview">
            <?php
            // Empty cells before first day
            for ($i = 1; $i < $first_day_of_week; $i++) {
                echo '<div class="calendar-cell-overview empty"></div>';
            }
            
            // Days of the month
            for ($day = 1; $day <= $days_in_month; $day++) {
                $date = sprintf('%04d-%02d-%02d', $calendar_year, $calendar_month, $day);
                $is_today = ($date === date('Y-m-d'));
                $has_bookings = isset($bookings_by_date[$date]);
                
                $cell_class = 'calendar-cell-overview';
                if ($is_today) $cell_class .= ' today';
                if ($has_bookings) {
                    $cell_class .= ' has-bookings has-' . $status_filter;
                }
                
                echo '<div class="' . $cell_class . '">';
                echo '<div class="calendar-day-number">' . $day . '</div>';
                
                if ($has_bookings) {
                    echo '<div class="bookings-list">';
                    foreach ($bookings_by_date[$date] as $booking) {
                        $time = date('H:i', strtotime($booking['booking_time']));
                        echo '<div class="booking-item status-' . $status_filter . '" title="' . htmlspecialchars($booking['service_name']) . ' at ' . $time . '">';
                        echo '<span class="booking-time">' . $time . '</span> ';
                        echo '<span class="booking-masseuse">' . htmlspecialchars($booking['masseuse_name']) . '</span>';
                        echo '</div>';
                    }
                    echo '</div>';
                }
                
                echo '</div>';
            }
            
            // Fill remaining cells
            $total_cells = $first_day_of_week + $days_in_month - 1;
            $remaining_cells = (7 - ($total_cells % 7)) % 7;
            for ($i = 0; $i < $remaining_cells; $i++) {
                echo '<div class="calendar-cell-overview empty"></div>';
            }
            ?>
        </div>
    </div>
    
    <div style="margin-top: 1.5rem; padding: 1rem; background: rgba(16, 185, 129, 0.1); border-radius: 8px; border-left: 4px solid var(--primary-color);">
        <strong>Total <?php echo $status_names[$status_filter]; ?> Bookings:</strong> <?php echo count($bookings_by_date) > 0 ? array_sum(array_map('count', $bookings_by_date)) : 0; ?>
    </div>
</div>

<?php
// Monthly Timeline Table
// Get timeline month and year
$timeline_month = isset($_GET['timeline_month']) ? (int)$_GET['timeline_month'] : (int)date('n');
$timeline_year = isset($_GET['timeline_year']) ? (int)$_GET['timeline_year'] : (int)date('Y');

// Calculate timeline dates
$timeline_first_day = "$timeline_year-" . str_pad($timeline_month, 2, '0', STR_PAD_LEFT) . "-01";
$timeline_last_day = date('Y-m-t', strtotime($timeline_first_day));
$timeline_days_in_month = (int)date('t', strtotime($timeline_first_day));

// Get all masseuses (or just the logged-in masseuse)
if (isMasseuse()) {
    $timeline_masseuses = array_filter($masseuses_list, function($m) use ($logged_in_masseuse_id) {
        return $m['id'] == $logged_in_masseuse_id;
    });
} else {
    $timeline_masseuses = $masseuses_list;
}

// Get all bookings for the timeline month
$timeline_bookings_sql = "SELECT b.*, m.id as masseuse_id, m.name as masseuse_name, s.name as service_name 
                         FROM bookings b 
                         JOIN masseuses m ON b.masseuse_id = m.id 
                         JOIN services s ON b.service_id = s.id 
                         WHERE b.booking_date BETWEEN '$timeline_first_day' AND '$timeline_last_day'
                         AND b.status IN ('pending', 'completed')";

// Add masseuse filter if logged in as masseuse
if (isMasseuse()) {
    $timeline_bookings_sql .= " AND b.masseuse_id = $logged_in_masseuse_id";
}

$timeline_bookings_sql .= " ORDER BY b.booking_date, b.booking_time";
$timeline_bookings_result = $conn->query($timeline_bookings_sql);

// Organize bookings by masseuse and date
$timeline_data = [];
foreach ($timeline_masseuses as $masseuse) {
    $timeline_data[$masseuse['id']] = [
        'name' => $masseuse['name'],
        'bookings' => []
    ];
}

if ($timeline_bookings_result && $timeline_bookings_result->num_rows > 0) {
    while ($booking = $timeline_bookings_result->fetch_assoc()) {
        $masseuse_id = $booking['masseuse_id'];
        $date = $booking['booking_date'];
        
        if (!isset($timeline_data[$masseuse_id]['bookings'][$date])) {
            $timeline_data[$masseuse_id]['bookings'][$date] = [
                'pending' => 0,
                'completed' => 0,
                'details' => []
            ];
        }
        
        $timeline_data[$masseuse_id]['bookings'][$date][$booking['status']]++;
        $timeline_data[$masseuse_id]['bookings'][$date]['details'][] = [
            'time' => date('g:i A', strtotime($booking['booking_time'])),
            'service' => $booking['service_name'],
            'status' => $booking['status']
        ];
    }
}

// Timeline navigation
$timeline_prev_month = $timeline_month - 1;
$timeline_prev_year = $timeline_year;
if ($timeline_prev_month < 1) {
    $timeline_prev_month = 12;
    $timeline_prev_year--;
}

$timeline_next_month = $timeline_month + 1;
$timeline_next_year = $timeline_year;
if ($timeline_next_month > 12) {
    $timeline_next_month = 1;
    $timeline_next_year++;
}
?>

<div class="glass-card" id="monthlyTimelineContainer" style="margin-top: 3rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <h2 style="margin: 0;">Monthly Timeline - <?php echo date('F Y', strtotime($timeline_first_day)); ?></h2>
        <div style="display: flex; gap: 0.5rem;">
            <a href="?timeline_month=<?php echo $timeline_prev_month; ?>&timeline_year=<?php echo $timeline_prev_year; ?>#monthlyTimelineContainer" class="btn btn-outline btn-small">← Previous</a>
            <span style="color: #94a3b8; font-weight: 600; padding: 0 0.5rem; display: flex; align-items: center;">Month</span>
            <a href="?timeline_month=<?php echo $timeline_next_month; ?>&timeline_year=<?php echo $timeline_next_year; ?>#monthlyTimelineContainer" class="btn btn-outline btn-small">Next →</a>
        </div>
    </div>
    
    <!-- Legend -->
    <div style="display: flex; gap: 1.5rem; margin-bottom: 1.5rem; flex-wrap: wrap; padding: 1rem; background: rgba(255, 255, 255, 0.02); border-radius: 8px;">
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <div style="width: 20px; height: 20px; background: rgba(16, 185, 129, 0.3); border: 1px solid rgba(16, 185, 129, 0.6); border-radius: 4px;"></div>
            <span style="color: #94a3b8; font-size: 0.9rem;">Completed</span>
        </div>
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <div style="width: 20px; height: 20px; background: rgba(245, 158, 11, 0.3); border: 1px solid rgba(245, 158, 11, 0.6); border-radius: 4px;"></div>
            <span style="color: #94a3b8; font-size: 0.9rem;">Pending</span>
        </div>
    </div>
    
    <div id="timeline" style="overflow-x: auto;">
        <table style="min-width: 100%; border-collapse: separate; border-spacing: 0;">
            <thead>
                <tr>
                    <th style="position: sticky; left: 0; background: rgba(15, 23, 42, 0.95); z-index: 10; min-width: 150px; border-right: 2px solid rgba(16, 185, 129, 0.3);">Masseuse</th>
                    <?php for ($day = 1; $day <= $timeline_days_in_month; $day++): ?>
                        <?php
                        $date = sprintf('%04d-%02d-%02d', $timeline_year, $timeline_month, $day);
                        $is_today = ($date === date('Y-m-d'));
                        $day_name = date('D', strtotime($date));
                        ?>
                        <th style="min-width: 50px; text-align: center; font-size: 0.85rem; padding: 0.5rem; <?php echo $is_today ? 'background: rgba(6, 182, 212, 0.1); border: 2px solid #06b6d4;' : ''; ?>">
                            <div style="font-weight: 600;"><?php echo $day; ?></div>
                            <div style="font-size: 0.75rem; color: #64748b; font-weight: normal;"><?php echo $day_name; ?></div>
                        </th>
                    <?php endfor; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($timeline_data as $masseuse_id => $masseuse_info): ?>
                    <tr>
                        <td style="position: sticky; left: 0; background: rgba(15, 23, 42, 0.95); z-index: 5; font-weight: 600; border-right: 2px solid rgba(16, 185, 129, 0.3); padding: 1rem;">
                            <?php echo htmlspecialchars($masseuse_info['name']); ?>
                        </td>
                        <?php for ($day = 1; $day <= $timeline_days_in_month; $day++): ?>
                            <?php
                            $date = sprintf('%04d-%02d-%02d', $timeline_year, $timeline_month, $day);
                            $bookings = $masseuse_info['bookings'][$date] ?? null;
                            $is_today = ($date === date('Y-m-d'));
                            ?>
                            <td style="text-align: center; padding: 0.5rem; border: 1px solid rgba(255, 255, 255, 0.05); <?php echo $is_today ? 'background: rgba(6, 182, 212, 0.05);' : ''; ?>">
                                <?php if ($bookings): ?>
                                    <div style="display: flex; flex-direction: column; gap: 0.25rem; align-items: stretch;">
                                        <?php foreach ($bookings['details'] as $booking): ?>
                                            <div style="background: <?php echo $booking['status'] === 'completed' ? 'rgba(16, 185, 129, 0.2)' : 'rgba(245, 158, 11, 0.2)'; ?>; 
                                                        color: <?php echo $booking['status'] === 'completed' ? '#10b981' : '#f59e0b'; ?>; 
                                                        border-left: 3px solid <?php echo $booking['status'] === 'completed' ? '#10b981' : '#f59e0b'; ?>; 
                                                        padding: 0.25rem 0.5rem; 
                                                        border-radius: 3px; 
                                                        font-size: 0.7rem; 
                                                        font-weight: 600; 
                                                        cursor: help;
                                                        white-space: nowrap;"
                                                 title="<?php echo htmlspecialchars($booking['service']) . ' (' . ucfirst($booking['status']) . ')'; ?>">
                                                <?php echo $booking['time']; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span style="color: #334155;">—</span>
                                <?php endif; ?>
                            </td>
                        <?php endfor; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
