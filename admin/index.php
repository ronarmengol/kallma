<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

session_start();

if (!isLoggedIn() || (!isAdmin() && !isMasseuse())) {
    redirect('../login.php');
}

// Get masseuse ID if logged in as masseuse
$logged_in_masseuse_id = null;
if (isMasseuse()) {
    $logged_in_masseuse_id = getMasseuseIdByUserId($conn, $_SESSION['user_id']);
}

// Get stats
if (isAdmin()) {
    $total_bookings = $conn->query("SELECT COUNT(*) as count FROM bookings")->fetch_assoc()['count'];
    $total_services = $conn->query("SELECT COUNT(*) as count FROM services")->fetch_assoc()['count'];
    $total_masseuses = $conn->query("SELECT COUNT(*) as count FROM masseuses")->fetch_assoc()['count'];
    $total_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'customer'")->fetch_assoc()['count'];
} else {
    // Masseuse sees only their own bookings
    $total_bookings = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE masseuse_id = $logged_in_masseuse_id")->fetch_assoc()['count'];
    $total_services = $conn->query("SELECT COUNT(*) as count FROM services")->fetch_assoc()['count'];
    $total_masseuses = 1; // Only themselves
    $total_users = 0; // Masseuses don't need to see total users
}

// Recent bookings
if (isAdmin()) {
    $recent_bookings_sql = "SELECT b.*, u.name as customer_name, s.name as service_name, m.name as masseuse_name 
                            FROM bookings b 
                            LEFT JOIN users u ON b.user_id = u.id 
                            JOIN services s ON b.service_id = s.id 
                            JOIN masseuses m ON b.masseuse_id = m.id 
                            ORDER BY b.created_at DESC LIMIT 5";
} else {
    // Masseuse sees only their own bookings
    $recent_bookings_sql = "SELECT b.*, u.name as customer_name, s.name as service_name, m.name as masseuse_name 
                            FROM bookings b 
                            LEFT JOIN users u ON b.user_id = u.id 
                            JOIN services s ON b.service_id = s.id 
                            JOIN masseuses m ON b.masseuse_id = m.id 
                            WHERE b.masseuse_id = $logged_in_masseuse_id
                            ORDER BY b.created_at DESC LIMIT 5";
}
$recent_bookings = $conn->query($recent_bookings_sql)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Kallma Spa</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">

</head>
<body>
    <nav class="admin-nav">
        <div class="container">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <a href="index.php" class="logo">Kallma Admin</a>
                <button class="menu-toggle" onclick="document.querySelector('.nav-content').classList.toggle('active')">☰</button>
                
                <div class="nav-content">
                    <ul class="nav-links">
                        <li><a href="index.php">Dashboard</a></li>
                        <?php if (isAdmin()): ?>
                        <li><a href="services.php">Services</a></li>
                        <?php endif; ?>
                        <li><a href="<?php echo isMasseuse() ? 'masseuse_schedule.php' : 'masseuses.php'; ?>">Masseuses</a></li>
                        <li><a href="bookings.php">Bookings</a></li>
                        <?php if (isAdmin()): ?>
                        <li><a href="users.php">Users</a></li>
                        <?php endif; ?>
                        <li><a href="../index.php">View Site</a></li>
                    </ul>
                    <a href="../logout.php" class="btn btn-outline logout-btn" style="padding: 0.5rem 1rem;">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container" style="padding: 3rem 2rem;">
        <h1>Dashboard</h1>
        
        <div class="stats-grid">
            <div class="glass-card stat-card">
                <div class="stat-label">Total Bookings</div>
                <div class="stat-number"><?php echo $total_bookings; ?></div>
            </div>
            <div class="glass-card stat-card">
                <div class="stat-label">Services</div>
                <div class="stat-number"><?php echo $total_services; ?></div>
            </div>
            <div class="glass-card stat-card">
                <div class="stat-label">Masseuses</div>
                <div class="stat-number"><?php echo $total_masseuses; ?></div>
            </div>
            <div class="glass-card stat-card">
                <div class="stat-label">Customers</div>
                <div class="stat-number"><?php echo $total_users; ?></div>
            </div>
        </div>

        <div style="margin: 2rem 0; text-align: right;">
            <a href="users.php" class="btn btn-primary">Manage Users</a>
        </div>

        <div class="glass-card" style="margin-top: 3rem;">
            <h2>Recent Bookings</h2>
            <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Service</th>
                        <th>Masseuse</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_bookings as $booking): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($booking['customer_name'] ?? 'Guest'); ?></td>
                            <td><?php echo htmlspecialchars($booking['service_name']); ?></td>
                            <td><?php echo htmlspecialchars($booking['masseuse_name']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($booking['booking_date'])); ?></td>
                            <td><?php echo date('g:i A', strtotime($booking['booking_time'])); ?></td>
                            <td><span class="badge badge-<?php echo $booking['status']; ?>"><?php echo ucfirst($booking['status']); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>
</body>
</html>
