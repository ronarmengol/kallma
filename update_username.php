<?php
require_once 'includes/db.php';

$sql = "UPDATE users SET username='mike' WHERE id=5";
if ($conn->query($sql)) {
    echo "Updated username for user 5 to 'mike'\n";
} else {
    echo "Error updating username: " . $conn->error . "\n";
}
?>
