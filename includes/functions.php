<?php
require_once 'db.php';

function sanitize($conn, $input) {
    return mysqli_real_escape_string($conn, htmlspecialchars(trim($input)));
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function getServices($conn) {
    $sql = "SELECT * FROM services ORDER BY name ASC";
    $result = $conn->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getMasseuses($conn) {
    $sql = "SELECT * FROM masseuses ORDER BY name ASC";
    $result = $conn->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}
?>
