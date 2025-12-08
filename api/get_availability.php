<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_GET['masseuse_id']) || !isset($_GET['date'])) {
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

$masseuse_id = (int)$_GET['masseuse_id'];
$date = $_GET['date']; // Format: YYYY-MM-DD
$day_of_week = date('l', strtotime($date));

// First, check for daily availability (specific date overrides)
$sql_daily = "SELECT start_time, end_time FROM daily_availability 
              WHERE masseuse_id = $masseuse_id AND date = '$date'
              ORDER BY start_time";
$result_daily = $conn->query($sql_daily);

$slots = [];

if ($result_daily->num_rows > 0) {
    // Use daily availability if set
    while ($row = $result_daily->fetch_assoc()) {
        $start_time = strtotime($date . ' ' . $row['start_time']);
        $end_time = strtotime($date . ' ' . $row['end_time']);
        
        // Get existing bookings
        // Get existing bookings
        $sql_bookings = "SELECT booking_time FROM bookings 
                        WHERE masseuse_id = $masseuse_id 
                        AND booking_date = '$date' 
                        AND status != 'cancelled'";
                        
        if (isset($_GET['exclude_booking_id'])) {
            $exclude_id = (int)$_GET['exclude_booking_id'];
            $sql_bookings .= " AND id != $exclude_id";
        }
        
        $bookings_result = $conn->query($sql_bookings);
        
        $booked_times = [];
        while ($b = $bookings_result->fetch_assoc()) {
            // Ensure format matches the loop generation (H:i)
            $booked_times[] = date('H:i', strtotime($b['booking_time']));
        }
        
        // Generate slots for this time range
        $current_time = $start_time;
        while ($current_time < $end_time) {
            $time_str = date('H:i', $current_time);
            
            if ($current_time < time()) {
                $status = 'past';
            } else {
                $status = in_array($time_str, $booked_times) ? 'booked' : 'available';
            }
            
            $slots[] = [
                'time' => $time_str,
                'status' => $status
            ];
            
            $current_time = strtotime('+1 hour', $current_time);
        }
    }
} else {
    // Fall back to weekly pattern availability
    $sql = "SELECT start_time, end_time FROM availability 
            WHERE masseuse_id = $masseuse_id AND day_of_week = '$day_of_week'";
    $result = $conn->query($sql);

    if ($result->num_rows === 0) {
        echo json_encode(['slots' => []]); // Not working today
        exit;
    }

    $row = $result->fetch_assoc();
    $start_time = strtotime($date . ' ' . $row['start_time']);
    $end_time = strtotime($date . ' ' . $row['end_time']);

    // Get existing bookings
    $sql_bookings = "SELECT booking_time FROM bookings 
                    WHERE masseuse_id = $masseuse_id 
                    AND booking_date = '$date' 
                    AND status != 'cancelled'";
                    
    if (isset($_GET['exclude_booking_id'])) {
        $exclude_id = (int)$_GET['exclude_booking_id'];
        $sql_bookings .= " AND id != $exclude_id";
    }

    $bookings_result = $conn->query($sql_bookings);

    $booked_times = [];
    while ($b = $bookings_result->fetch_assoc()) {
        // Ensure format matches the loop generation (H:i)
        $booked_times[] = date('H:i', strtotime($b['booking_time']));
    }

    // Generate slots
    $current_time = $start_time;
    while ($current_time < $end_time) {
        $time_str = date('H:i', $current_time);
        
        if ($current_time < time()) {
            $status = 'past';
        } else {
            $status = in_array($time_str, $booked_times) ? 'booked' : 'available';
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
