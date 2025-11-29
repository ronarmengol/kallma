<?php
require_once 'includes/db.php';

echo "Database connection test:\n";
echo "Connected to: " . $conn->host_info . "\n\n";

$result = $conn->query("SELECT id, name, email, role FROM users");
echo "Total users: " . $result->num_rows . "\n\n";

while ($row = $result->fetch_assoc()) {
    echo "ID: " . $row['id'] . "\n";
    echo "Name: " . $row['name'] . "\n";
    echo "Email: " . $row['email'] . "\n";
    echo "Role: " . $row['role'] . "\n\n";
}

// Test specific query
$email = 'admin@kallma.com';
$sql = "SELECT * FROM users WHERE email = '$email'";
$result2 = $conn->query($sql);
echo "Query for admin@kallma.com returned: " . $result2->num_rows . " rows\n";
?>
