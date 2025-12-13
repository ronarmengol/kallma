<?php
require_once 'includes/db.php';

// Delete all bookings
$result1 = $conn->query("DELETE FROM bookings");

if ($result1) {
    $deleted_bookings = $conn->affected_rows;
    echo "✓ Deleted {$deleted_bookings} bookings from 'bookings' table\n";
} else {
    echo "✗ Error deleting from bookings: " . $conn->error . "\n";
}

// Check if migrated_bookings table exists and delete from it
$table_check = $conn->query("SHOW TABLES LIKE 'migrated_bookings'");
if ($table_check && $table_check->num_rows > 0) {
    $result2 = $conn->query("DELETE FROM migrated_bookings");
    if ($result2) {
        $deleted_migrated = $conn->affected_rows;
        echo "✓ Deleted {$deleted_migrated} entries from 'migrated_bookings' table\n";
    } else {
        echo "✗ Error deleting from migrated_bookings: " . $conn->error . "\n";
    }
} else {
    echo "ℹ Table 'migrated_bookings' does not exist\n";
}

// Reset auto-increment
$conn->query("ALTER TABLE bookings AUTO_INCREMENT = 1");
echo "✓ Reset bookings auto-increment counter\n";

echo "\n✅ All bookings have been deleted successfully!\n";
?>
