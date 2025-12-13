<?php
require_once 'includes/db.php';

// Find Sarah Jenkins
$sarah = $conn->query("SELECT id, name FROM masseuses WHERE name LIKE '%Sarah%Jenkins%'")->fetch_assoc();

if (!$sarah) {
    die("Sarah Jenkins not found");
}

$masseuse_id = $sarah['id'];
$dates = ['2025-12-08', '2025-12-15', '2025-12-22', '2025-12-29'];

echo "Updating availability for {$sarah['name']} (ID: $masseuse_id)...\n";

foreach ($dates as $date) {
    // 1. Delete existing daily availability for this date
    $del = $conn->query("DELETE FROM daily_availability WHERE masseuse_id = $masseuse_id AND date = '$date'");
    if ($del) {
        $deleted = $conn->affected_rows;
        if ($deleted > 0) echo "Deleted $deleted existing entries for $date\n";
    }
    
    // 2. Insert blocking availability to override weekly schedule
    $stmt = $conn->prepare("INSERT INTO daily_availability (masseuse_id, date, start_time, end_time) VALUES (?, ?, '00:00:00', '00:00:00')");
    $stmt->bind_param('is', $masseuse_id, $date);
    
    if ($stmt->execute()) {
        echo "✓ Blocked availability for $date (Daily override set to 0 hours)\n";
    } else {
        echo "✗ Error blocking $date: " . $stmt->error . "\n";
    }
    $stmt->close();
}

echo "\nDone! Sarah Jenkins is now unavailable on these dates.\n";
?>
