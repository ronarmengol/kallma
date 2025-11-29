<?php
require_once '../includes/db.php';

header('Content-Type: application/json');

if (!isset($_GET['masseuse_id'])) {
    echo json_encode([]);
    exit;
}

$masseuse_id = (int)$_GET['masseuse_id'];

$sql = "SELECT * FROM availability WHERE masseuse_id = $masseuse_id ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')";
$result = $conn->query($sql);

$availability = [];
while ($row = $result->fetch_assoc()) {
    $availability[] = $row;
}

echo json_encode($availability);
?>
