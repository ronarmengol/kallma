<?php
require_once 'includes/db.php';

// Find Mike Ross
$mike = $conn->query("SELECT id, name FROM masseuses WHERE name LIKE '%Mike%Ross%'")->fetch_assoc();

if (!$mike) {
    echo "Mike Ross not found in database\n";
    exit;
}

echo "Found: {$mike['name']} (ID: {$mike['id']})\n\n";

// Check daily availability for December 8, 15, 22, 29 (2025)
$dates = ['2025-12-08', '2025-12-15', '2025-12-22', '2025-12-29'];

echo "Daily Availability for Mike Ross:\n";
echo str_repeat("-", 60) . "\n";

foreach ($dates as $date) {
    $result = $conn->query("SELECT * FROM daily_availability WHERE masseuse_id = {$mike['id']} AND date = '$date'");
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "Date: $date | Time: {$row['start_time']} - {$row['end_time']}\n";
        }
    } else {
        echo "Date: $date | No daily availability set\n";
    }
}

echo "\n" . str_repeat("-", 60) . "\n";
echo "Weekly Availability for Mike Ross:\n";
echo str_repeat("-", 60) . "\n";

$weekly = $conn->query("SELECT * FROM availability WHERE masseuse_id = {$mike['id']}");
if ($weekly && $weekly->num_rows > 0) {
    while ($row = $weekly->fetch_assoc()) {
        echo "{$row['day_of_week']}: {$row['start_time']} - {$row['end_time']}\n";
    }
} else {
    echo "No weekly availability set\n";
}
?>
