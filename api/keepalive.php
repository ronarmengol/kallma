<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION)) {
  echo json_encode(['ok' => false, 'message' => 'No session']);
  exit();
}

// Update last activity to keep session alive
$_SESSION['last_activity'] = time();

echo json_encode(['ok' => true]);
exit();
