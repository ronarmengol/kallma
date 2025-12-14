<?php
require_once 'includes/db.php';

echo "Adding walk-in client fields to bookings table...\n\n";

$sql = "ALTER TABLE bookings 
        ADD COLUMN walk_in_client_name VARCHAR(100) NULL AFTER status,
        ADD COLUMN walk_in_client_mobile VARCHAR(20) NULL AFTER walk_in_client_name";

if ($conn->query($sql)) {
    echo "✓ Successfully added walk_in_client_name and walk_in_client_mobile columns to bookings table.\n";
} else {
    if (strpos($conn->error, 'Duplicate column name') !== false) {
        echo "ℹ Columns already exist. No changes needed.\n";
    } else {
        echo "✗ Error: " . $conn->error . "\n";
    }
}

$conn->close();
