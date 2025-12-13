<?php
require_once 'includes/db.php';

echo "Cleaning up invalid availability entries with 00:00:00 times...\n\n";

// Find all entries with 00:00:00 times
$sql = "SELECT id, masseuse_id, date, start_time, end_time 
        FROM daily_availability 
        WHERE (start_time = '00:00:00' OR end_time = '00:00:00')
        ORDER BY masseuse_id, date";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    echo "Found invalid availability entries:\n";
    echo "----------------------------------------\n";
    while ($row = $result->fetch_assoc()) {
        echo "ID: {$row['id']}, Masseuse: {$row['masseuse_id']}, Date: {$row['date']}, ";
        echo "Start: {$row['start_time']}, End: {$row['end_time']}\n";
    }
    
    echo "\n----------------------------------------\n";
    echo "Total: " . $result->num_rows . " invalid entries found.\n\n";
    
    // Delete all invalid entries
    $delete_sql = "DELETE FROM daily_availability 
                   WHERE (start_time = '00:00:00' OR end_time = '00:00:00')";
    
    if ($conn->query($delete_sql)) {
        echo "✓ Successfully deleted " . $conn->affected_rows . " invalid availability entries.\n";
    } else {
        echo "✗ Error: " . $conn->error . "\n";
    }
} else {
    echo "No invalid availability entries found.\n";
}

$conn->close();
