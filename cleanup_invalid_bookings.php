<?php
require_once 'includes/db.php';

echo "Checking daily_availability table for masseuse_id = 2 on Dec 15 and 22:\n\n";

$sql = "SELECT * FROM daily_availability 
        WHERE masseuse_id = 2 
        AND (date = '2025-12-15' OR date = '2025-12-22')
        ORDER BY date, start_time";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    echo "Found availability entries:\n";
    while ($row = $result->fetch_assoc()) {
        echo "ID: {$row['id']}, Date: {$row['date']}, Start: {$row['start_time']}, End: {$row['end_time']}\n";
    }
    
    echo "\n\nChecking for entries with 00:00:00 times...\n";
    
    // Check for 00:00:00 entries
    $check_sql = "SELECT COUNT(*) as count FROM daily_availability 
                  WHERE masseuse_id = 2 
                  AND (date = '2025-12-15' OR date = '2025-12-22')
                  AND (start_time = '00:00:00' OR end_time = '00:00:00')";
    
    $check_result = $conn->query($check_sql);
    $count = $check_result->fetch_assoc()['count'];
    
    if ($count > 0) {
        echo "Found $count entries with 00:00:00 times.\n";
        echo "Deleting invalid entries...\n";
        
        $delete_sql = "DELETE FROM daily_availability 
                       WHERE masseuse_id = 2 
                       AND (date = '2025-12-15' OR date = '2025-12-22')
                       AND (start_time = '00:00:00' OR end_time = '00:00:00')";
        
        if ($conn->query($delete_sql)) {
            echo "✓ Successfully deleted " . $conn->affected_rows . " invalid availability entry/entries.\n";
        } else {
            echo "✗ Error: " . $conn->error . "\n";
        }
    } else {
        echo "No entries with 00:00:00 times found.\n";
    }
} else {
    echo "No availability entries found for those dates.\n";
}

$conn->close();
