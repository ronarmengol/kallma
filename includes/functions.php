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
    $sql = "SELECT m.*, u.username FROM masseuses m LEFT JOIN users u ON m.user_id = u.id ORDER BY m.name ASC";
    $result = $conn->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}

function isMasseuse() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'masseuse';
}

function getMasseuseIdByUserId($conn, $user_id) {
    $sql = "SELECT id FROM masseuses WHERE user_id = $user_id";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc()['id'];
    }
    return null;
}
?>
