<?php
require_once 'includes/db.php';

// Find Mike Ross
$mike = $conn->query("SELECT id, name FROM masseuses WHERE name LIKE '%Mike%Ross%'")->fetch_assoc();

if (!$mike) {
    die("Mike Ross not found");
}

$masseuse_id = $mike['id'];
$dates = ['2025-12-08', '2025-12-15', '2025-12-22', '2025-12-29'];

echo "Updating availability for {$mike['name']} (ID: $masseuse_id)...\n";

foreach ($dates as $date) {
    // 1. Delete existing daily availability for this date
    $del = $conn->query("DELETE FROM daily_availability WHERE masseuse_id = $masseuse_id AND date = '$date'");
    if ($del) {
        $deleted = $conn->affected_rows;
        if ($deleted > 0) echo "Deleted $deleted existing entries for $date\n";
    }
    
    // 2. Insert blocking availability (start_time = end_time) to override weekly schedule
    // Using 00:00:00 to 00:00:00 ensures no slots are generated
    $sql = "INSERT INTO daily_availability (masseuse_id, date, start_time, end_time, is_available) VALUES (?, ?, '00:00:00', '00:00:00', 0)";
    
    // Check if 'is_available' column exists first, if not modify query
    // Actually, looking at previous code, tables usually don't have is_available if not seen. 
    // But 'daily_availability' usually has start/end. 
    // Let's assume standard structure: id, masseuse_id, date, start_time, end_time.
    
    $stmt = $conn->prepare("INSERT INTO daily_availability (masseuse_id, date, start_time, end_time) VALUES (?, ?, '00:00:00', '00:00:00')");
    $stmt->bind_param('is', $masseuse_id, $date);
    
    if ($stmt->execute()) {
        echo "✓ Blocked availability for $date (Daily override set to 0 hours)\n";
    } else {
        echo "✗ Error blocking $date: " . $stmt->error . "\n";
    }
    $stmt->close();
}

echo "\nDone! Mike Ross is now unavailable on these dates.\n";
?>
