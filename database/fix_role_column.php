<?php
/**
 * Check and fix the users table role column
 * This script checks the current role column definition and updates it if needed
 */

require_once __DIR__ . '/../includes/db.php';

echo "Checking users table role column...\n\n";

// Check current column definition
$check_sql = "SHOW COLUMNS FROM users LIKE 'role'";
$result = $conn->query($check_sql);

if ($result && $result->num_rows > 0) {
    $column_info = $result->fetch_assoc();
    echo "Current role column definition:\n";
    echo "Type: " . $column_info['Type'] . "\n";
    echo "Null: " . $column_info['Null'] . "\n";
    echo "Default: " . $column_info['Default'] . "\n\n";
    
    // Check if it's an ENUM
    if (strpos($column_info['Type'], 'enum') !== false) {
        echo "Role column is an ENUM. Updating to support 'masseuse' role...\n";
        
        // Modify the ENUM to include 'masseuse'
        $alter_sql = "ALTER TABLE users MODIFY COLUMN role ENUM('customer', 'admin', 'masseuse') NOT NULL DEFAULT 'customer'";
        
        if ($conn->query($alter_sql)) {
            echo "✓ Successfully updated role column to include 'masseuse'\n";
        } else {
            echo "✗ Error updating role column: " . $conn->error . "\n";
        }
    } elseif (strpos($column_info['Type'], 'varchar') !== false) {
        // Extract the length
        preg_match('/varchar\((\d+)\)/', $column_info['Type'], $matches);
        $current_length = isset($matches[1]) ? (int)$matches[1] : 0;
        
        if ($current_length < 10) {
            echo "Role column VARCHAR is too small ($current_length). Updating to VARCHAR(20)...\n";
            $alter_sql = "ALTER TABLE users MODIFY COLUMN role VARCHAR(20) NOT NULL DEFAULT 'customer'";
            
            if ($conn->query($alter_sql)) {
                echo "✓ Successfully updated role column to VARCHAR(20)\n";
            } else {
                echo "✗ Error updating role column: " . $conn->error . "\n";
            }
        } else {
            echo "✓ Role column is already large enough (VARCHAR($current_length))\n";
        }
    } else {
        echo "✓ Role column type is: " . $column_info['Type'] . " (should be fine)\n";
    }
} else {
    echo "✗ Could not find role column in users table\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "Check completed!\n";
echo str_repeat("=", 50) . "\n";

$conn->close();
?>
