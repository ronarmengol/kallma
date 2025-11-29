<?php
/**
 * Check users table structure and fix for mobile-based login
 */

require_once __DIR__ . '/../includes/db.php';

echo "Checking users table structure...\n\n";

// Check all columns
$columns_sql = "SHOW COLUMNS FROM users";
$result = $conn->query($columns_sql);

echo "Current columns in users table:\n";
echo str_repeat("-", 50) . "\n";
while ($row = $result->fetch_assoc()) {
    echo sprintf("%-15s %-20s %-8s %-10s\n", 
        $row['Field'], 
        $row['Type'], 
        $row['Null'], 
        $row['Key']
    );
}
echo "\n";

// Check if email column exists
$check_email = "SHOW COLUMNS FROM users LIKE 'email'";
$email_result = $conn->query($check_email);

if ($email_result && $email_result->num_rows > 0) {
    echo "Email column exists. Checking if it's required...\n";
    $email_info = $email_result->fetch_assoc();
    
    if ($email_info['Null'] === 'NO') {
        echo "Email column is NOT NULL. Making it nullable...\n";
        $alter_sql = "ALTER TABLE users MODIFY COLUMN email VARCHAR(100) NULL";
        if ($conn->query($alter_sql)) {
            echo "✓ Email column is now nullable\n";
        } else {
            echo "✗ Error: " . $conn->error . "\n";
        }
    } else {
        echo "✓ Email column is already nullable\n";
    }
    
    // Check if email has unique constraint
    if ($email_info['Key'] === 'UNI') {
        echo "\nEmail has UNIQUE constraint. This may cause issues.\n";
        echo "Attempting to drop unique constraint...\n";
        
        // First, find the constraint name
        $constraint_sql = "SELECT CONSTRAINT_NAME 
                          FROM information_schema.KEY_COLUMN_USAGE 
                          WHERE TABLE_SCHEMA = DATABASE() 
                          AND TABLE_NAME = 'users' 
                          AND COLUMN_NAME = 'email'
                          AND CONSTRAINT_NAME != 'PRIMARY'";
        
        $constraint_result = $conn->query($constraint_sql);
        if ($constraint_result && $constraint_result->num_rows > 0) {
            while ($constraint = $constraint_result->fetch_assoc()) {
                $constraint_name = $constraint['CONSTRAINT_NAME'];
                echo "Found constraint: $constraint_name\n";
                
                $drop_sql = "ALTER TABLE users DROP INDEX $constraint_name";
                if ($conn->query($drop_sql)) {
                    echo "✓ Dropped unique constraint on email\n";
                } else {
                    echo "✗ Error dropping constraint: " . $conn->error . "\n";
                }
            }
        }
    }
} else {
    echo "✓ No email column found (good for mobile-based login)\n";
}

// Check mobile column
echo "\nChecking mobile column...\n";
$check_mobile = "SHOW COLUMNS FROM users LIKE 'mobile'";
$mobile_result = $conn->query($check_mobile);

if ($mobile_result && $mobile_result->num_rows > 0) {
    $mobile_info = $mobile_result->fetch_assoc();
    echo "Mobile column exists: " . $mobile_info['Type'] . "\n";
    
    if ($mobile_info['Key'] !== 'UNI') {
        echo "Adding UNIQUE constraint to mobile column...\n";
        $unique_sql = "ALTER TABLE users ADD UNIQUE KEY unique_mobile (mobile)";
        if ($conn->query($unique_sql)) {
            echo "✓ Added unique constraint to mobile\n";
        } else {
            echo "⚠ Note: " . $conn->error . " (may already exist)\n";
        }
    } else {
        echo "✓ Mobile already has unique constraint\n";
    }
} else {
    echo "✗ Mobile column does not exist!\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "Check completed!\n";
echo str_repeat("=", 50) . "\n";

$conn->close();
?>
