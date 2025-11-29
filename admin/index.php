<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

session_start();

if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

// Get stats
$total_bookings = $conn->query("SELECT COUNT(*) as count FROM bookings")->fetch_assoc()['count'];
$total_services = $conn->query("SELECT COUNT(*) as count FROM services")->fetch_assoc()['count'];
$total_masseuses = $conn->query("SELECT COUNT(*) as count FROM masseuses")->fetch_assoc()['count'];
$total_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'customer'")->fetch_assoc()['count'];

// Recent bookings
$recent_bookings_sql = "SELECT b.*, u.name as customer_name, s.name as service_name, m.name as masseuse_name 
                        FROM bookings b 
                        LEFT JOIN users u ON b.user_id = u.id 
                        JOIN services s ON b.service_id = s.id 
                        JOIN masseuses m ON b.masseuse_id = m.id 
                        ORDER BY b.created_at DESC LIMIT 5";
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
    <style>
        .admin-nav {
            background: rgba(15, 23, 42, 0.95);
            padding: 1rem 0;
            border-bottom: 1px solid var(--glass-border);
        }
        .admin-nav .nav-links {
            display: flex;
            gap: 1.5rem;
            align-items: center;
            list-style: none;
        }
        .menu-toggle { display: none; font-size: 1.5rem; background: none; border: none; color: white; cursor: pointer; }
        
        @media (max-width: 768px) {
            .admin-nav .container > div {
                position: relative;
            }
            
            .admin-nav .nav-links {
                display: none;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                width: 100vw;
                margin-left: calc(-50vw + 50%);
                background: #0f172a;
                flex-direction: column;
                padding: 1rem 0;
                border-bottom: 1px solid rgba(255,255,255,0.1);
                z-index: 1000;
                gap: 0;
            }
            
            .admin-nav .nav-links.active {
                display: flex;
            }
            
            .admin-nav .nav-links li {
                width: 100%;
                text-align: center;
                padding: 0.75rem 0;
                border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            }
            
            .admin-nav .nav-links li:last-child {
                border-bottom: none;
            }
            
            .menu-toggle {
                display: block;
            }
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin: 2rem 0;
        }
        .stat-card {
            text-align: center;
            transition: transform 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-number {
            font-size: 3rem;
            font-weight: bold;
            color: var(--primary-color);
            margin: 1rem 0;
        }
        .stat-label {
            color: #94a3b8;
            font-size: 1.1rem;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--glass-border);
        }
        th {
            color: var(--primary-color);
            font-weight: 600;
        }
        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .badge-pending { background: rgba(251, 191, 36, 0.2); color: #fbbf24; }
        .badge-confirmed { background: rgba(16, 185, 129, 0.2); color: #10b981; }
        .badge-cancelled { background: rgba(239, 68, 68, 0.2); color: #ef4444; }
        .badge-completed { background: rgba(59, 130, 246, 0.2); color: #3b82f6; }
    </style>
</head>
<body>
    <nav class="admin-nav">
        <div class="container">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <a href="index.php" class="logo">Kallma Admin</a>
                <button class="menu-toggle" onclick="document.querySelector('.nav-links').classList.toggle('active')">☰</button>
                <ul class="nav-links">
                    <li><a href="index.php">Dashboard</a></li>
                    <li><a href="services.php">Services</a></li>
                    <li><a href="masseuses.php">Masseuses</a></li>
                    <li><a href="bookings.php">Bookings</a></li>
                    <li><a href="../index.php">View Site</a></li>
                    <li><a href="../logout.php" class="btn btn-outline" style="padding: 0.5rem 1rem;">Logout</a></li>
                </ul>
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

        <div class="glass-card" style="margin-top: 3rem;">
            <h2>Recent Bookings</h2>
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
</body>
</html>
