<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

header('Content-Type: text/plain');

echo "=== Database Debug Info ===\n\n";

if ($conn->connect_error) {
    echo "Connection failed: " . $conn->connect_error . "\n";
} else {
    echo "✓ Database connected successfully\n";
    echo "Host: " . $conn->host_info . "\n\n";
}

echo "=== Testing Users Table ===\n";
$result = $conn->query("SELECT id, name, email, role FROM users");

if (!$result) {
    echo "Query failed: " . $conn->error . "\n";
} else {
    echo "Total users: " . $result->num_rows . "\n\n";
    
    while ($row = $result->fetch_assoc()) {
        echo "User ID: " . $row['id'] . "\n";
        echo "Name: " . $row['name'] . "\n";
        echo "Email: " . $row['email'] . "\n";
        echo "Role: " . $row['role'] . "\n";
        echo "---\n";
    }
}

echo "\n=== Testing Login Query ===\n";
$test_email = 'admin@kallma.com';
$sanitized = sanitize($conn, $test_email);
echo "Original email: $test_email\n";
echo "Sanitized email: $sanitized\n";

$sql = "SELECT * FROM users WHERE email = '$sanitized'";
echo "SQL: $sql\n";
$result2 = $conn->query($sql);

if (!$result2) {
    echo "Query failed: " . $conn->error . "\n";
} else {
    echo "Rows returned: " . $result2->num_rows . "\n";
}
?>
