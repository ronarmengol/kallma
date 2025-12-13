<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

session_start();

if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get parameters
$current_month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
$current_year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'completed';

// Validate status filter
$allowed_statuses = ['completed', 'pending'];
if (!in_array($status_filter, $allowed_statuses)) {
    $status_filter = 'completed';
}

// Get bookings for the month based on status filter
$first_day = "$current_year-" . str_pad($current_month, 2, '0', STR_PAD_LEFT) . "-01";
$last_day = date('Y-m-t', strtotime($first_day));

$bookings_sql = "SELECT b.*, m.name as masseuse_name, s.name as service_name 
                FROM bookings b 
                JOIN masseuses m ON b.masseuse_id = m.id 
                JOIN services s ON b.service_id = s.id 
                WHERE b.status = '$status_filter' 
                AND b.booking_date BETWEEN '$first_day' AND '$last_day'
                ORDER BY b.booking_date, b.booking_time";
$bookings_result = $conn->query($bookings_sql);

// Organize bookings by date
$bookings_by_date = [];
if ($bookings_result && $bookings_result->num_rows > 0) {
    while ($booking = $bookings_result->fetch_assoc()) {
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
$prev_month = $current_month - 1;
$prev_year = $current_year;
if ($prev_month < 1) {
    $prev_month = 12;
    $prev_year--;
}

$next_month = $current_month + 1;
$next_year = $current_year;
if ($next_month > 12) {
    $next_month = 1;
    $next_year++;
}

// Status display names
$status_names = [
    'completed' => 'Completed',
    'pending' => 'Pending'
];

// Build calendar HTML
ob_start();
?>
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <h2 style="margin: 0;"><?php echo $status_names[$status_filter]; ?> Bookings - <?php echo date('F Y', strtotime($first_day)); ?></h2>
    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
        <!-- Status Filter Buttons -->
        <div style="display: flex; gap: 0.5rem; padding: 0.25rem; background: rgba(255, 255, 255, 0.05); border-radius: 8px;">
            <button onclick="loadBookings(<?php echo $current_month; ?>, <?php echo $current_year; ?>, 'completed')" 
                class="btn btn-small <?php echo $status_filter === 'completed' ? 'btn-primary' : 'btn-outline'; ?>" 
                style="padding: 0.5rem 1rem;">Completed</button>
            <button onclick="loadBookings(<?php echo $current_month; ?>, <?php echo $current_year; ?>, 'pending')" 
                class="btn btn-small <?php echo $status_filter === 'pending' ? 'btn-primary' : 'btn-outline'; ?>" 
                style="padding: 0.5rem 1rem;">Pending</button>
        </div>
        
        <!-- Month Navigation -->
        <button onclick="loadBookings(<?php echo $prev_month; ?>, <?php echo $prev_year; ?>, '<?php echo $status_filter; ?>')" class="btn btn-outline btn-small">← Previous</button>
        <span style="color: #94a3b8; font-weight: 600; padding: 0 0.5rem;">Month</span>
        <button onclick="loadBookings(<?php echo $next_month; ?>, <?php echo $next_year; ?>, '<?php echo $status_filter; ?>')" class="btn btn-outline btn-small">Next →</button>
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
            $date = sprintf('%04d-%02d-%02d', $current_year, $current_month, $day);
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
<?php
$html = ob_get_clean();

// Return JSON response
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'html' => $html,
    'month' => $current_month,
    'year' => $current_year,
    'status' => $status_filter
]);
