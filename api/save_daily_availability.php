<?php
require_once '../includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['masseuse_id']) || !isset($data['date']) || !isset($data['time_slots'])) {
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

$masseuse_id = (int)$data['masseuse_id'];
$date = $data['date'];
$time_slots = $data['time_slots'];

// Start transaction
$conn->begin_transaction();

try {
    // Delete existing availability for this date
    $delete_sql = "DELETE FROM daily_availability WHERE masseuse_id = $masseuse_id AND date = '$date'";
    $conn->query($delete_sql);
    
    // Insert new time slots
    if (!empty($time_slots)) {
        $insert_values = [];
        foreach ($time_slots as $slot) {
            $start = $conn->real_escape_string($slot['start']);
            $end = $conn->real_escape_string($slot['end']);
            $insert_values[] = "($masseuse_id, '$date', '$start', '$end')";
        }
        
        $insert_sql = "INSERT INTO daily_availability (masseuse_id, date, start_time, end_time) VALUES " . implode(', ', $insert_values);
        $conn->query($insert_sql);
    }
    
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Availability updated successfully']);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['error' => 'Failed to update availability: ' . $e->getMessage()]);
}
?>
