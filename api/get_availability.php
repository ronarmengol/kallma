<?php
// Enable output buffering for faster response
ob_start();

require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
// Add cache headers for better performance (cache for 30 seconds)
header('Cache-Control: public, max-age=30');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 30) . ' GMT');

if (!isset($_GET['masseuse_id']) || !isset($_GET['date'])) {
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

$masseuse_id = (int)$_GET['masseuse_id'];
$date = $conn->real_escape_string($_GET['date']); // Format: YYYY-MM-DD
$day_of_week = date('l', strtotime($date));

// Get existing bookings ONCE for this masseuse and date
$sql_bookings = "SELECT TIME_FORMAT(booking_time, '%H:%i') as booking_time FROM bookings 
                WHERE masseuse_id = ? 
                AND booking_date = ? 
                AND status != 'cancelled'";

$params = [$masseuse_id, $date];
$types = 'is';

if (isset($_GET['exclude_booking_id'])) {
    $exclude_id = (int)$_GET['exclude_booking_id'];
    $sql_bookings .= " AND id != ?";
    $params[] = $exclude_id;
    $types .= 'i';
}

$stmt = $conn->prepare($sql_bookings);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$bookings_result = $stmt->get_result();

$booked_times_set = [];
while ($b = $bookings_result->fetch_assoc()) {
    $booked_times_set[$b['booking_time']] = true;
}
$stmt->close();

$slots = [];
$current_server_time = time();

// Pre-calculate date timestamp to avoid repeated strtotime calls
$date_timestamp = strtotime($date);

// First, check for daily availability (specific date overrides)
$stmt_daily = $conn->prepare("SELECT start_time, end_time FROM daily_availability 
                               WHERE masseuse_id = ? AND date = ?
                               ORDER BY start_time");
$stmt_daily->bind_param('is', $masseuse_id, $date);
$stmt_daily->execute();
$result_daily = $stmt_daily->get_result();

if ($result_daily->num_rows > 0) {
    // Use daily availability if set
    while ($row = $result_daily->fetch_assoc()) {
        // Parse times once
        list($start_h, $start_m) = explode(':', $row['start_time']);
        list($end_h, $end_m) = explode(':', $row['end_time']);
        
        $start_time = $date_timestamp + ($start_h * 3600) + ($start_m * 60);
        $end_time = $date_timestamp + ($end_h * 3600) + ($end_m * 60);
        
        // Generate slots for this time range
        for ($current_time = $start_time; $current_time < $end_time; $current_time += 3600) {
            $time_str = date('H:i', $current_time);
            
            $slots[] = [
                'time' => $time_str,
                'status' => $current_time < $current_server_time ? 'past' : (isset($booked_times_set[$time_str]) ? 'booked' : 'available')
            ];
        }
    }
    $stmt_daily->close();
} else {
    $stmt_daily->close();
    
    // Fall back to weekly pattern availability
    $stmt_weekly = $conn->prepare("SELECT start_time, end_time FROM availability 
                                    WHERE masseuse_id = ? AND day_of_week = ?");
    $stmt_weekly->bind_param('is', $masseuse_id, $day_of_week);
    $stmt_weekly->execute();
    $result = $stmt_weekly->get_result();

    if ($result->num_rows === 0) {
        $stmt_weekly->close();
        echo json_encode(['slots' => []]); // Not working today
        ob_end_flush();
        exit;
    }

    $row = $result->fetch_assoc();
    $stmt_weekly->close();
    
    // Parse times once
    list($start_h, $start_m) = explode(':', $row['start_time']);
    list($end_h, $end_m) = explode(':', $row['end_time']);
    
    $start_time = $date_timestamp + ($start_h * 3600) + ($start_m * 60);
    $end_time = $date_timestamp + ($end_h * 3600) + ($end_m * 60);

    // Generate slots using for loop instead of while for better performance
    for ($current_time = $start_time; $current_time < $end_time; $current_time += 3600) {
        $time_str = date('H:i', $current_time);
        
        $slots[] = [
            'time' => $time_str,
            'status' => $current_time < $current_server_time ? 'past' : (isset($booked_times_set[$time_str]) ? 'booked' : 'available')
        ];
    }
}

echo json_encode(['slots' => $slots], JSON_UNESCAPED_SLASHES);
ob_end_flush();
?>
