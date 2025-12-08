<?php
require_once 'includes/db.php';

$result = $conn->query("SELECT id, name, image_url FROM services WHERE name LIKE '%deep%tissue%'");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "ID: " . $row['id'] . "\n";
        echo "Name: " . $row['name'] . "\n";
        echo "Image URL: " . ($row['image_url'] ?: 'NULL') . "\n";
        echo "---\n";
    }
} else {
    echo "No deep tissue massage service found.\n";
}

// Also check all services
echo "\nAll services:\n";
$all = $conn->query("SELECT id, name, image_url FROM services");
while ($row = $all->fetch_assoc()) {
    echo "ID: " . $row['id'] . " | Name: " . $row['name'] . " | Image: " . ($row['image_url'] ?: 'NULL') . "\n";
}
?>
