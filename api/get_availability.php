<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

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

$booked_times = [];
while ($b = $bookings_result->fetch_assoc()) {
    $booked_times[] = $b['booking_time'];
}
$stmt->close();

// Convert to hash set for O(1) lookup instead of O(n) with in_array
$booked_times_set = array_flip($booked_times);

$slots = [];
$current_server_time = time();

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
        $start_time = strtotime($date . ' ' . $row['start_time']);
        $end_time = strtotime($date . ' ' . $row['end_time']);
        
        // Generate slots for this time range
        $current_time = $start_time;
        while ($current_time < $end_time) {
            $time_str = date('H:i', $current_time);
            
            if ($current_time < $current_server_time) {
                $status = 'past';
            } else {
                $status = isset($booked_times_set[$time_str]) ? 'booked' : 'available';
            }
            
            $slots[] = [
                'time' => $time_str,
                'status' => $status
            ];
            
            $current_time = strtotime('+1 hour', $current_time);
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
        exit;
    }

    $row = $result->fetch_assoc();
    $stmt_weekly->close();
    
    $start_time = strtotime($date . ' ' . $row['start_time']);
    $end_time = strtotime($date . ' ' . $row['end_time']);

    // Generate slots
    $current_time = $start_time;
    while ($current_time < $end_time) {
        $time_str = date('H:i', $current_time);
        
        if ($current_time < $current_server_time) {
            $status = 'past';
        } else {
            $status = isset($booked_times_set[$time_str]) ? 'booked' : 'available';
        }
        
        $slots[] = [
            'time' => $time_str,
            'status' => $status
        ];
        
        $current_time = strtotime('+1 hour', $current_time);
    }
}

echo json_encode(['slots' => $slots]);
?>
