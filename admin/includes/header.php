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
            
            <!-- Date/Time Widget -->
            <div style="margin-bottom: 1.5rem;">
                <div style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.05) 0%, rgba(6, 182, 212, 0.05) 100%);
                            backdrop-filter: blur(10px);
                            border: 1px solid rgba(16, 185, 129, 0.15);
                            border-radius: 12px;
                            padding: 1rem;
                            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.05);
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            gap: 2rem;">
                    
                    <!-- Date Section -->
                    <div style="text-align: center;">
                        <div style="display: flex; align-items: center; justify-content: center; gap: 0.35rem; margin-bottom: 0.25rem;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                            <span style="color: #10b981; font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Today</span>
                        </div>
                        <div style="color: #e2e8f0; font-size: 0.95rem; font-weight: 600;">
                            <?php echo date('l, M j, Y'); ?>
                        </div>
                    </div>
                    
                    <!-- Divider -->
                    <div style="width: 1px; height: 40px; background: linear-gradient(180deg, transparent, rgba(16, 185, 129, 0.2), transparent);"></div>
                    
                    <!-- Time Section -->
                    <div style="text-align: center;">
                        <div style="display: flex; align-items: center; justify-content: center; gap: 0.35rem; margin-bottom: 0.25rem;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#06b6d4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 16 14"></polyline>
                            </svg>
                            <span style="color: #06b6d4; font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Time</span>
                        </div>
                        <div id="adminCurrentTime" style="color: #10b981; 
                                                    font-size: 1.5rem; 
                                                    font-weight: 700; 
                                                    letter-spacing: 1px;
                                                    font-family: 'Courier New', monospace;">
                            <?php echo date('H:i:s'); ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <script>
            (function() {
                function updateAdminTime() {
                    const now = new Date();
                    const hours = String(now.getHours()).padStart(2, '0');
                    const minutes = String(now.getMinutes()).padStart(2, '0');
                    const seconds = String(now.getSeconds()).padStart(2, '0');
                    
                    const timeString = hours + ':' + minutes + ':' + seconds;
                    const timeEl = document.getElementById('adminCurrentTime');
                    if (timeEl) {
                        timeEl.textContent = timeString;
                    }
                }
                
                // Update time every second
                setInterval(updateAdminTime, 1000);
                // Initial update
                updateAdminTime();
            })();
            </script>
            
            <div class="content-body">
