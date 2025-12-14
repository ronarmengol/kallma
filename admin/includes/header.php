<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure DB and Functions are loaded by the parent file, but we can double check or assume.
// Usually header is included after them.

// Fetch user name if not in session
if (!isset($_SESSION['user_name']) && isset($_SESSION['user_id'])) {
    if (isset($conn)) {
        $uid = (int)$_SESSION['user_id'];
        $u_res = $conn->query("SELECT name FROM users WHERE id = $uid");
        if ($u_res && $u_row = $u_res->fetch_assoc()) {
            $_SESSION['user_name'] = $u_row['name'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle : 'Kallma Admin'; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="admin-wrapper">
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="index.php" class="logo" style="text-decoration: none;">Kallma</a>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="index.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">Dashboard</a></li>
                    
                    <?php if (function_exists('isAdmin') && isAdmin()): ?>
                        <li><a href="services.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'services.php' ? 'active' : ''; ?>">Services</a></li>
                        <li><a href="faqs.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'faqs.php' ? 'active' : ''; ?>">FAQs</a></li>
                    <?php endif; ?>
                    
                    <li><a href="<?php echo (function_exists('isMasseuse') && isMasseuse()) ? 'masseuse_schedule.php' : 'masseuses.php'; ?>" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'masseuses.php' || basename($_SERVER['PHP_SELF']) == 'masseuse_schedule.php') ? 'active' : ''; ?>"><?php echo (function_exists('isMasseuse') && isMasseuse()) ? 'My Schedule' : 'Masseuses'; ?></a></li>
                    <li><a href="bookings.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'bookings.php' || basename($_SERVER['PHP_SELF']) == 'bookings_history.php') ? 'active' : ''; ?>">Bookings</a></li>
                    
                    <?php if (function_exists('isAdmin') && isAdmin()): ?>
                        <li><a href="users.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">Users</a></li>
                        <li><a href="../booking.php">Book Now</a></li>
                    <?php endif; ?>
                    
                    <li><a href="../index.php">View Site</a></li>
                </ul>
            </nav>
        </aside>

        <div class="main-content">
            <header class="top-bar">
                <button class="mobile-toggle" onclick="document.querySelector('.sidebar').classList.toggle('active')">☰</button>
                <div class="user-info">
                   <span>Welcome, <strong><?php echo isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'User'; ?></strong></span>
                </div>
                <a href="../logout.php" class="btn btn-outline" style="padding: 0.25rem 0.75rem; font-size: 0.9rem;">Logout</a>
            </header>
            <div class="content-body">
