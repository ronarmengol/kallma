<?php
require_once '../includes/db.php';

header('Content-Type: application/json');

if (!isset($_GET['masseuse_id']) || !isset($_GET['year']) || !isset($_GET['month'])) {
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

$masseuse_id = (int)$_GET['masseuse_id'];
$year = (int)$_GET['year'];
$month = (int)$_GET['month'];

// Get number of days in the month
$days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);

$availability = [];

// Loop through each day of the month
for ($day = 1; $day <= $days_in_month; $day++) {
    $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
    $day_of_week = date('l', strtotime($date));
    
    // First check for daily availability (specific date)
    $sql_daily = "SELECT start_time, end_time FROM daily_availability 
                  WHERE masseuse_id = $masseuse_id AND date = '$date'";
    $result_daily = $conn->query($sql_daily);
    
    if ($result_daily->num_rows > 0) {
        // Has daily availability set
        $total_slots = 0;
        while ($row = $result_daily->fetch_assoc()) {
            $start_time = strtotime($date . ' ' . $row['start_time']);
            $end_time = strtotime($date . ' ' . $row['end_time']);
            $current_time = $start_time;
            while ($current_time < $end_time) {
                $total_slots++;
                $current_time = strtotime('+1 hour', $current_time);
            }
        }
        
        if ($total_slots === 0) {
            $availability[$day] = 'unavailable';
        } else {
            // Count booked slots
            $sql_bookings = "SELECT COUNT(*) as booked FROM bookings 
                            WHERE masseuse_id = $masseuse_id 
                            AND booking_date = '$date' 
                            AND status != 'cancelled'";
            $bookings_result = $conn->query($sql_bookings);
            $booked_count = $bookings_result->fetch_assoc()['booked'];
            
            if ($booked_count >= $total_slots) {
                // All slots are booked - show as booked (orange/partial styling)
                $availability[$day] = 'partial';
            } else {
                // At least one slot is available - show as available (green)
                $availability[$day] = 'available';
            }
        }
    } else {
        // No daily availability set for this date - mark as unavailable
        $availability[$day] = 'unavailable';
    }
}

echo json_encode(['availability' => $availability]);
?>
