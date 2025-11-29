<?php
/**
 * Migration Script: Add user_id to masseuses table
 * 
 * This script adds the user_id column to the masseuses table
 * to link masseuses with their user accounts for login functionality.
 * 
 * Run this script once to update your database schema.
 */

require_once __DIR__ . '/../includes/db.php';

echo "Starting migration: Add user_id to masseuses table...\n\n";

// Check if user_id column already exists
$check_sql = "SHOW COLUMNS FROM masseuses LIKE 'user_id'";
$result = $conn->query($check_sql);

if ($result->num_rows > 0) {
    echo "✓ Column 'user_id' already exists in masseuses table.\n";
} else {
    echo "Adding 'user_id' column to masseuses table...\n";
    
    // Add user_id column
    $sql = "ALTER TABLE masseuses ADD COLUMN user_id INT NULL";
    if ($conn->query($sql)) {
        echo "✓ Successfully added 'user_id' column.\n";
    } else {
        echo "✗ Error adding 'user_id' column: " . $conn->error . "\n";
        exit(1);
    }
    
    // Add foreign key constraint
    echo "Adding foreign key constraint...\n";
    $fk_sql = "ALTER TABLE masseuses 
               ADD CONSTRAINT fk_masseuse_user 
               FOREIGN KEY (user_id) 
               REFERENCES users(id) 
               ON DELETE CASCADE";
    
    if ($conn->query($fk_sql)) {
        echo "✓ Successfully added foreign key constraint.\n";
    } else {
        echo "⚠ Warning: Could not add foreign key constraint: " . $conn->error . "\n";
        echo "  (This is okay if the constraint already exists)\n";
    }
    
    // Add index for performance
    echo "Adding index on user_id...\n";
    $index_sql = "CREATE INDEX idx_masseuse_user_id ON masseuses(user_id)";
    
    if ($conn->query($index_sql)) {
        echo "✓ Successfully added index.\n";
    } else {
        echo "⚠ Warning: Could not add index: " . $conn->error . "\n";
        echo "  (This is okay if the index already exists)\n";
    }
}

// Check if mobile column exists
echo "\nChecking for 'mobile' column...\n";
$check_mobile_sql = "SHOW COLUMNS FROM masseuses LIKE 'mobile'";
$mobile_result = $conn->query($check_mobile_sql);

if ($mobile_result->num_rows > 0) {
    echo "✓ Column 'mobile' already exists in masseuses table.\n";
} else {
    echo "Adding 'mobile' column to masseuses table...\n";
    $mobile_sql = "ALTER TABLE masseuses ADD COLUMN mobile VARCHAR(20) NULL";
    
    if ($conn->query($mobile_sql)) {
        echo "✓ Successfully added 'mobile' column.\n";
    } else {
        echo "✗ Error adding 'mobile' column: " . $conn->error . "\n";
    }
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "Migration completed successfully!\n";
echo str_repeat("=", 50) . "\n\n";

echo "Next steps:\n";
echo "1. Create masseuse user accounts through the admin panel\n";
echo "2. Masseuses can now log in with their mobile number and password\n";
echo "3. Masseuses will have access to their own schedule and bookings\n\n";

$conn->close();
?>
