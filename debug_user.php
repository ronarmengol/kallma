<?php
require_once 'includes/db.php';

$search = 'mike';
$sql = "SELECT * FROM users WHERE name LIKE '%$search%' OR username LIKE '%$search%' OR mobile LIKE '%$search%'";
$result = $conn->query($sql);

echo "Users found:\n";
while ($row = $result->fetch_assoc()) {
    print_r($row);
}
?>
