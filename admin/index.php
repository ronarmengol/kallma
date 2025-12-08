<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

session_start();

if (function_exists('checkSessionTimeout') && checkSessionTimeout()) {
    redirect('../login.php?timeout=1');
}

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
    // Pending bookings stats
    $pending_today = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE status = 'pending' AND booking_date = CURDATE()")->fetch_assoc()['count'];
    $pending_week = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE status = 'pending' AND YEARWEEK(booking_date, 1) = YEARWEEK(CURDATE(), 1)")->fetch_assoc()['count'];
    
    $total_services = $conn->query("SELECT COUNT(*) as count FROM services")->fetch_assoc()['count'];
    $total_masseuses = $conn->query("SELECT COUNT(*) as count FROM masseuses")->fetch_assoc()['count'];
    $total_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'customer'")->fetch_assoc()['count'];
} else {
    // Masseuse sees only their own bookings
    $pending_today = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE masseuse_id = $logged_in_masseuse_id AND status = 'pending' AND booking_date = CURDATE()")->fetch_assoc()['count'];
    $pending_week = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE masseuse_id = $logged_in_masseuse_id AND status = 'pending' AND YEARWEEK(booking_date, 1) = YEARWEEK(CURDATE(), 1)")->fetch_assoc()['count'];

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

$pageTitle = 'Admin Dashboard - Kallma Spa';
require_once 'includes/header.php';
?>

<h1>Dashboard</h1>

<div class="stats-grid">
    <div class="glass-card stat-card">
        <div class="stat-label" style="margin-bottom: 0.5rem;">Pending Bookings</div>
        <div style="display: flex; justify-content: space-between; align-items: flex-end; height: 100%;">
            <div>
                <div style="font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Today</div>
                <div class="stat-number" style="color: #f59e0b; font-size: 2rem; line-height: 1;"><?php echo $pending_today; ?></div>
            </div>
            <div style="text-align: right;">
                <div style="font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">This Week</div>
                <div class="stat-number" style="color: #fbbf24; font-size: 2rem; line-height: 1;"><?php echo $pending_week; ?></div>
            </div>
        </div>
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
                    <th>Date & Time</th>
                    <th>Booked On</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_bookings as $booking): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($booking['customer_name'] ?? 'Guest'); ?></td>
                        <td><?php echo htmlspecialchars($booking['service_name']); ?></td>
                        <td><?php echo htmlspecialchars($booking['masseuse_name']); ?></td>
                        <td>
                            <?php echo date('M d, Y', strtotime($booking['booking_date'])); ?>
                            <div style="font-size: 0.85em; color: #94a3b8; margin-top: 2px;">
                                <?php echo date('g:i A', strtotime($booking['booking_time'])); ?>
                            </div>
                        </td>
                        <td>
                            <?php echo date('M d, Y', strtotime($booking['created_at'])); ?>
                            <div style="font-size: 0.85em; color: #94a3b8; margin-top: 2px;">
                                <?php echo date('g:i A', strtotime($booking['created_at'])); ?>
                            </div>
                        </td>
                        <td><span class="badge badge-<?php echo $booking['status']; ?>"><?php echo ucfirst($booking['status']); ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>