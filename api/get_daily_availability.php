<?php
require_once '../includes/db.php';

header('Content-Type: application/json');

if (!isset($_GET['masseuse_id']) || !isset($_GET['start_date']) || !isset($_GET['end_date'])) {
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

$masseuse_id = (int)$_GET['masseuse_id'];
$start_date = $_GET['start_date'];
$end_date = $_GET['end_date'];

$sql = "SELECT date, start_time, end_time FROM daily_availability 
        WHERE masseuse_id = $masseuse_id 
        AND date BETWEEN '$start_date' AND '$end_date'
        ORDER BY date, start_time";

$result = $conn->query($sql);

$availability = [];
while ($row = $result->fetch_assoc()) {
    $date = $row['date'];
    if (!isset($availability[$date])) {
        $availability[$date] = [];
    }
    $availability[$date][] = [
        'start' => $row['start_time'],
        'end' => $row['end_time']
    ];
}

echo json_encode(['availability' => $availability]);
?>
