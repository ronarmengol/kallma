<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

session_start();

// Only admins can reset the database
if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Verify password
if (!isset($_POST['password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Password is required']);
    exit;
}

// Get current user's password from database
$user_id = (int)$_SESSION['user_id'];
$user_result = $conn->query("SELECT password FROM users WHERE id = $user_id");
$user = $user_result->fetch_assoc();

// Verify password
if (!password_verify($_POST['password'], $user['password'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Incorrect password']);
    exit;
}

// Begin transaction
$conn->begin_transaction();

try {
    // Delete all data from tables except users
    $tables_to_reset = [
        'bookings',
        'availability',
        'masseuses',
        'services',
        'faqs'
    ];
    
    foreach ($tables_to_reset as $table) {
        // Check if table exists before truncating
        $check = $conn->query("SHOW TABLES LIKE '$table'");
        if ($check && $check->num_rows > 0) {
            $conn->query("TRUNCATE TABLE `$table`");
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Database reset successfully. All data except users has been deleted.'
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Error resetting database: ' . $e->getMessage()
    ]);
}
