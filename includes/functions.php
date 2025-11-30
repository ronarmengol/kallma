<?php
require_once 'db.php';

function sanitize($conn, $input) {
    return mysqli_real_escape_string($conn, htmlspecialchars(trim($input)));
}

function checkSessionTimeout() {
    $timeout_duration = 300; // 5 minutes in seconds

    if (isset($_SESSION['last_activity'])) {
        $elapsed_time = time() - $_SESSION['last_activity'];
        if ($elapsed_time > $timeout_duration) {
            session_unset();
            session_destroy();
            return true;
        }
    }
    $_SESSION['last_activity'] = time();
    return false;
}

function isLoggedIn() {
    if (isset($_SESSION['user_id'])) {
        if (checkSessionTimeout()) {
            return false;
        }
        return true;
    }
    return false;
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
