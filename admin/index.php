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

<?php if (isAdmin()): ?>
<!-- Danger Zone -->
<div class="glass-card" style="margin-top: 3rem; border: 2px solid #dc2626; background: rgba(220, 38, 38, 0.05);">
    <h2 style="color: #dc2626; display: flex; align-items: center; gap: 0.5rem;">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
            <line x1="12" y1="9" x2="12" y2="13"/>
            <line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>
        Danger Zone
    </h2>
    <p style="color: #94a3b8; margin-bottom: 1.5rem;">
        Irreversible actions that will permanently affect your database. Use with extreme caution.
    </p>
    
    <div style="padding: 1.5rem; background: rgba(15, 23, 42, 0.5); border-radius: 8px; border-left: 4px solid #dc2626;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h3 style="color: #fff; margin-bottom: 0.5rem; font-size: 1.1rem;">Hard Database Reset</h3>
                <p style="color: #94a3b8; margin: 0; font-size: 0.9rem;">
                    Delete all bookings, services, masseuses, availability, and FAQs. <strong style="color: #10b981;">User accounts will be preserved.</strong>
                </p>
            </div>
            <button onclick="showResetConfirmation()" class="btn" style="background: #dc2626; border-color: #dc2626; white-space: nowrap;">
                Reset Database
            </button>
        </div>
    </div>
</div>

<!-- Reset Confirmation Modal -->
<div id="resetModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.8); z-index: 1000; align-items: center; justify-content: center;">
    <div class="glass-card" style="max-width: 500px; margin: 2rem; border: 2px solid #dc2626;">
        <h2 style="color: #dc2626; margin-bottom: 1rem;">⚠️ Confirm Database Reset</h2>
        <p style="color: #fff; margin-bottom: 1rem;">
            This will <strong>permanently delete</strong> all:
        </p>
        <ul style="color: #94a3b8; margin-bottom: 1rem; padding-left: 1.5rem;">
            <li>Bookings</li>
            <li>Services</li>
            <li>Masseuses</li>
            <li>Availability schedules</li>
            <li>FAQs</li>
        </ul>
        <p style="color: #10b981; margin-bottom: 1.5rem;">
            ✓ User accounts will be preserved
        </p>
        <p style="color: #fff; font-weight: 600; margin-bottom: 1rem;">
            Enter your password to confirm:
        </p>
        <form id="resetForm" onsubmit="handleDatabaseReset(event)">
            <input 
                type="password" 
                id="resetPassword" 
                name="password" 
                placeholder="Your password" 
                required 
                style="width: 100%; padding: 0.75rem; margin-bottom: 1rem; background: rgba(15, 23, 42, 0.8); border: 1px solid var(--glass-border); border-radius: 4px; color: #fff;"
            >
            <div id="resetError" style="color: #dc2626; margin-bottom: 1rem; display: none;"></div>
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button type="button" onclick="hideResetConfirmation()" class="btn btn-outline">
                    Cancel
                </button>
                <button type="submit" class="btn" style="background: #dc2626; border-color: #dc2626;">
                    Confirm Reset
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showResetConfirmation() {
    document.getElementById('resetModal').style.display = 'flex';
    document.getElementById('resetPassword').value = '';
    document.getElementById('resetError').style.display = 'none';
}

function hideResetConfirmation() {
    document.getElementById('resetModal').style.display = 'none';
}

async function handleDatabaseReset(event) {
    event.preventDefault();
    
    const password = document.getElementById('resetPassword').value;
    const errorDiv = document.getElementById('resetError');
    const submitBtn = event.target.querySelector('button[type="submit"]');
    
    // Disable submit button
    submitBtn.disabled = true;
    submitBtn.textContent = 'Resetting...';
    
    try {
        const response = await fetch('reset_database.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'password=' + encodeURIComponent(password)
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('✓ Database reset successfully!\n\nAll data except users has been deleted.');
            hideResetConfirmation();
            location.reload();
        } else {
            errorDiv.textContent = data.message || 'Reset failed';
            errorDiv.style.display = 'block';
        }
    } catch (error) {
        errorDiv.textContent = 'Network error: ' + error.message;
        errorDiv.style.display = 'block';
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Confirm Reset';
    }
}

// Close modal on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        hideResetConfirmation();
    }
});
</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
