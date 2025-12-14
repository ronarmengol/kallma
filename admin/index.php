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
    $pending_total = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE status = 'pending'")->fetch_assoc()['count'];
    
    $total_services = $conn->query("SELECT COUNT(*) as count FROM services")->fetch_assoc()['count'];
    $total_masseuses = $conn->query("SELECT COUNT(*) as count FROM masseuses")->fetch_assoc()['count'];
    $total_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'customer'")->fetch_assoc()['count'];
} else {
    // Masseuse sees only their own bookings
    $pending_today = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE masseuse_id = $logged_in_masseuse_id AND status = 'pending' AND booking_date = CURDATE()")->fetch_assoc()['count'];
    $pending_total = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE masseuse_id = $logged_in_masseuse_id AND status = 'pending'")->fetch_assoc()['count'];

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

<div style="text-align: left;">
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
                <div style="font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Pending</div>
                <div class="stat-number" style="color: #fbbf24; font-size: 2rem; line-height: 1;"><?php echo $pending_total; ?></div>
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

<!-- Analytics Widgets -->
<div id="analyticsContainer">
    <!-- Smart Alerts Panel -->
    <div class="glass-card" style="margin-top: 2rem; border-left: 4px solid #f59e0b;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h2 style="display: flex; align-items: center; gap: 0.5rem;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                </svg>
                Smart Alerts
            </h2>
            <span id="alertBadge" class="badge badge-pending" style="display: none;">0</span>
        </div>
        <div id="alertsContent">
            <div style="text-align: center; padding: 2rem; color: #64748b;">
                <div class="loader"></div>
                <p style="margin-top: 1rem;">Loading alerts...</p>
            </div>
        </div>
    </div>

    <!-- Booking Trends -->
    <div class="glass-card" style="margin-top: 2rem;">
        <h2 style="display: flex; align-items: center; gap: 0.5rem;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #64748b;"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
            Booking Trends
        </h2>
        <div id="bookingTrendsContent" style="padding: 1rem;">
             <!-- Keep summary stats above chart -->
             <div id="bookingTrendsStats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem;"></div>
             <div style="height: 300px;">
                <canvas id="bookingTrendsChart"></canvas>
             </div>
        </div>
    </div>

    <!-- Peak Hours Heatmap -->
    <div class="glass-card" style="margin-top: 2rem;">
        <h2 style="display: flex; align-items: center; gap: 0.5rem;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #f59e0b;"><path d="M12 2a7 7 0 0 0-7 7c0 2.38 1.19 4.47 3 5.74V17a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-2.26c1.81-1.27 3-3.36 3-5.74a7 7 0 0 0-7-7zM9 21v1a1 1 0 0 0 1 1h4a1 1 0 0 0 1-1v-1H9z"></path></svg>
            Peak Hours (Last 30 Days)
        </h2>
        <div id="peakHoursContent" style="padding: 1rem; height: 300px;">
            <canvas id="peakHoursChart"></canvas>
        </div>
    </div>

    <!-- Service Popularity -->
    <div class="glass-card" style="margin-top: 2rem;">
        <h2 style="display: flex; align-items: center; gap: 0.5rem;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #fbbf24;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
            Service Popularity (Last 30 Days)
        </h2>
        <div id="servicePopularityContent" style="padding: 1rem; height: 300px;">
            <canvas id="servicePopularityChart"></canvas>
        </div>
    </div>

    <!-- Booking Heatmap Analysis -->
    <div class="glass-card" style="margin-top: 2rem;">
        <h2 style="display: flex; align-items: center; gap: 0.5rem;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #10b981;">
                <rect x="3" y="3" width="7" height="7"></rect>
                <rect x="14" y="3" width="7" height="7"></rect>
                <rect x="14" y="14" width="7" height="7"></rect>
                <rect x="3" y="14" width="7" height="7"></rect>
            </svg>
            Booking Heatmap Analysis (Last 30 Days)
        </h2>
        <div id="bookingHeatmapContent" style="padding: 1rem;">
            <div style="text-align: center; padding: 2rem; color: #64748b;">
                <div class="loader"></div>
                <p style="margin-top: 1rem;">Loading heatmap...</p>
            </div>
        </div>
    </div>

    <!-- Phase 2 Analytics -->
    
    <!-- Customer Segmentation -->
    <div class="glass-card" style="margin-top: 2rem;">
        <h2 style="display: flex; align-items: center; gap: 0.5rem;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #64748b;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
            Customer Segmentation
        </h2>
        <div id="customerSegmentationContent" style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; padding: 1rem;">
            <div style="height: 300px;">
                <canvas id="customerSegmentationChart"></canvas>
            </div>
            <div id="customerSegmentationLegend"></div>
        </div>
    </div>

    <!-- Masseuse Performance -->
    <div class="glass-card" style="margin-top: 2rem;">
        <h2 style="display: flex; align-items: center; gap: 0.5rem;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #fbbf24;"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"></path><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"></path><path d="M4 22h16"></path><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"></path><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"></path><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"></path></svg>
            Masseuse Performance (Last 30 Days)
        </h2>
        <div id="masseusePerformanceContent" style="padding: 1rem; height: 300px;">
            <canvas id="masseusePerformanceChart"></canvas>
        </div>
    </div>

    <!-- Cancellation Tracking -->
    <div class="glass-card" style="margin-top: 2rem;">
        <h2 style="display: flex; align-items: center; gap: 0.5rem;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #ef4444;"><polyline points="22 17 13.5 8.5 8.5 13.5 2 7"></polyline><polyline points="22 17 22 13 18 17"></polyline></svg>
            Cancellation Tracking
        </h2>
        <div id="cancellationTrackingContent">
            <div style="text-align: center; padding: 2rem; color: #64748b;">
                <div class="loader"></div>
            </div>
        </div>
    </div>
        </div>
    </div>

    <!-- Phase 3 Analytics -->
    
    <!-- Year-over-Year Comparison -->
    <div class="glass-card" style="margin-top: 2rem;">
        <h2 style="display: flex; align-items: center; gap: 0.5rem;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #10b981;"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>
            Year-over-Year Comparison
        </h2>
        <div id="yearOverYearContent">
            <div style="text-align: center; padding: 2rem; color: #64748b;">
                <div class="loader"></div>
            </div>
        </div>
    </div>

    <!-- Service Affinity -->
    <div class="glass-card" style="margin-top: 2rem;">
        <h2 style="display: flex; align-items: center; gap: 0.5rem;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #64748b;"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>
            Service Affinity Analysis
        </h2>
        <div id="serviceAffinityContent">
            <div style="text-align: center; padding: 2rem; color: #64748b;">
                <div class="loader"></div>
            </div>
        </div>
    </div>

    <!-- Seasonal Patterns -->
    <div class="glass-card" style="margin-top: 2rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 1rem;">
            <h2 style="display: flex; align-items: center; gap: 0.5rem; margin: 0;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #fbbf24;"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
                Seasonal Booking Patterns
            </h2>
            <div style="display: flex; gap: 0.5rem; align-items: center;">
                <button onclick="navigateSeasonalMonth(-1)" class="btn btn-outline btn-small">← Previous</button>
                <span id="seasonalMonthDisplay" style="color: #94a3b8; font-weight: 600; min-width: 120px; text-align: center;"></span>
                <button onclick="navigateSeasonalMonth(1)" class="btn btn-outline btn-small">Next →</button>
            </div>
        </div>
        <div id="seasonalPatternsContent" style="padding: 1rem; height: 300px;">
            <canvas id="seasonalPatternsChart"></canvas>
        </div>
    </div>

    <!-- Customer Preferences -->
    <div class="glass-card" style="margin-top: 2rem;">
        <h2 style="display: flex; align-items: center; gap: 0.5rem;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #64748b;"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
            Customer Booking Preferences
        </h2>
        <div id="customerPreferencesContent">
            <div style="text-align: center; padding: 2rem; color: #64748b;">
                <div class="loader"></div>
            </div>
        </div>
</div>

<div class="glass-card" style="margin-top: 3rem; text-align: left;">
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
                        <td>
                            <?php 
                            // Check if this is a walk-in client booking
                            if (!empty($booking['walk_in_client_name'])) {
                                echo htmlspecialchars($booking['walk_in_client_name']);
                                echo '<br><small style="color: #64748b;">via ' . htmlspecialchars($booking['customer_name'] ?? 'Staff') . '</small>';
                            } else {
                                echo htmlspecialchars($booking['customer_name'] ?? 'Guest');
                            }
                            ?>
                        </td>
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

<?php /* DANGER ZONE MOVED TO BOTTOM OF PAGE
<!-- Danger Zone -->
<div class="glass-card" style="margin-top: 3rem; border: 2px solid #dc2626; background: rgba(220, 38, 38, 0.05); text-align: left;">
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
*/ ?>

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
        
        // Check if session expired (redirect to login)
        if (response.redirected && response.url.includes('login.php')) {
            window.location.href = '../login.php?timeout=1';
            return;
        }
        
        const data = await response.json();
        
        if (data.success) {
            alert('Database reset successfully!\n\nAll data except users has been deleted.');
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

// Analytics Functions
async function loadAnalytics() {
    console.log('Loading analytics...');
    try {
        const response = await fetch('../api/get_analytics_data.php?type=all');
        console.log('Response status:', response.status);
        
        // Check if session expired (redirect to login)
        if (response.redirected && response.url.includes('login.php')) {
            window.location.href = '../login.php?timeout=1';
            return;
        }
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const text = await response.text();
        console.log('Response text:', text.substring(0, 200));
        
        const data = JSON.parse(text);
        console.log('Parsed data:', data);
        
        if (data.error) {
            console.error('Analytics error:', data.error);
            showAnalyticsError('Error: ' + data.error);
            return;
        }
        
        // Render Phase 1 widgets
        renderAlerts(data.inactive_customers || []);
        renderBookingTrends(data.booking_trends || {});
        renderPeakHours(data.peak_hours || {});
        renderServicePopularity(data.service_popularity || []);
        renderBookingHeatmap(data.peak_hours || {});
        
        // Render Phase 2 widgets
        renderCustomerSegmentation(data.customer_segmentation || {});
        renderMasseusePerformance(data.masseuse_performance || []);
        renderCancellationTracking(data.cancellation_tracking || {});
        
        // Render Phase 3 widgets
        renderYearOverYear(data.year_over_year || []);
        renderServiceAffinity(data.service_affinity || []);
        renderSeasonalPatterns(data.seasonal_patterns || []);
        renderCustomerPreferences(data.customer_preferences || {});
        
        console.log('Analytics loaded successfully');
    } catch (error) {
        console.error('Failed to load analytics:', error);
        showAnalyticsError('Failed to load analytics: ' + error.message);
    }
}

function showAnalyticsError(message) {
    const containers = [
        'alertsContent', 'bookingTrendsContent', 'peakHoursContent', 'servicePopularityContent',
        'customerSegmentationContent', 'masseusePerformanceContent', 'cancellationTrackingContent',
        'yearOverYearContent', 'serviceAffinityContent', 'seasonalPatternsContent', 'customerPreferencesContent'
    ];
    containers.forEach(id => {
        const container = document.getElementById(id);
        if (container) {
            container.innerHTML = `<p style="text-align: center; padding: 2rem; color: #ef4444;">${message}</p>`;
        }
    });
}

function renderAlerts(inactiveCustomers) {
    const container = document.getElementById('alertsContent');
    const badge = document.getElementById('alertBadge');
    
    if (inactiveCustomers.length === 0) {
        container.innerHTML = '<div style="text-align: center; padding: 2rem; color: #64748b; display: flex; flex-direction: column; align-items: center; gap: 0.5rem;"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg> No alerts at this time</div>';
        badge.style.display = 'none';
        return;
    }
    
    badge.textContent = inactiveCustomers.length;
    badge.style.display = 'inline-block';
    
    let html = '<div style="padding: 1rem;">';
    html += `<div style="margin-bottom: 1rem; padding: 1rem; background: rgba(245, 158, 11, 0.1); border-left: 4px solid #f59e0b; border-radius: 4px;">`;
    html += `<h3 style="color: #f59e0b; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
        Customer Re-engagement (${inactiveCustomers.length})</h3>`;
    html += `<p style="color: #94a3b8; font-size: 0.9rem;">These customers haven't visited recently</p>`;
    html += '</div>';
    
    // Show first 5, with option to view all
    const displayCount = 5;
    const customersToShow = inactiveCustomers.slice(0, displayCount);
    
    customersToShow.forEach(customer => {
        html += `<div style="padding: 1rem; margin-bottom: 0.5rem; background: rgba(15, 23, 42, 0.5); border-radius: 8px;">`;
        html += `<div style="display: flex; justify-content: space-between; align-items: start; flex-wrap: wrap; gap: 1rem;">`;
        html += `<div style="flex: 1; min-width: 200px;">`;
        html += `<strong>${customer.name}</strong><br>`;
        html += `<small style="color: #94a3b8;">Last visit: ${customer.days_since_visit} days ago</small><br>`;
        html += `<small style="color: #64748b;">Total visits: ${customer.total_visits} | Favorite: ${customer.favorite_service || 'N/A'}</small>`;
        html += `</div>`;
        html += `<div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">`;
        html += `<button class="btn btn-small" style="background: #10b981;" onclick="alert('SMS feature coming soon!')">Send SMS</button>`;
        html += `<button class="btn btn-small btn-outline">Dismiss</button>`;
        html += `</div>`;
        html += `</div>`;
        html += `</div>`;
    });
    
    if (inactiveCustomers.length > displayCount) {
        html += `<div style="text-align: center; margin-top: 1rem;">`;
        html += `<button class="btn btn-outline" onclick="alert('View all feature coming soon!')">View All ${inactiveCustomers.length} Inactive Customers</button>`;
        html += `</div>`;
    }
    
    html += '</div>';
    container.innerHTML = html;
}

function renderBookingTrends(trends) {
    const statsContainer = document.getElementById('bookingTrendsStats');
    const canvas = document.getElementById('bookingTrendsChart');
    
    if (!trends || !trends.today) {
        statsContainer.parentElement.innerHTML = '<p style="text-align: center; padding: 2rem; color: #64748b;">No data available</p>';
        return;
    }
    
    const getTrendIcon = (change) => {
        if (change > 0) return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>';
        if (change < 0) return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 18 13.5 8.5 8.5 13.5 1 6"></polyline><polyline points="17 18 23 18 23 12"></polyline></svg>';
        return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>';
    };
    
    const getTrendColor = (change) => {
        if (change > 0) return '#10b981';
        if (change < 0) return '#ef4444';
        return '#64748b';
    };
    
    // Render Stats
    let html = '';
    
    // Today
    html += '<div style="text-align: center; padding: 1.5rem; background: rgba(15, 23, 42, 0.5); border-radius: 8px;">';
    html += '<div style="font-size: 0.85rem; color: #94a3b8; text-transform: uppercase; margin-bottom: 0.5rem;">Today</div>';
    html += `<div style="font-size: 2.5rem; font-weight: bold; color: #10b981;">${trends.today.count}</div>`;
    html += `<div style="font-size: 0.9rem; color: ${getTrendColor(trends.today.change)}; margin-top: 0.5rem;">`;
    html += `${getTrendIcon(trends.today.change)} ${Math.abs(trends.today.change)}% vs yesterday`;
    html += '</div></div>';
    
    // This Week
    html += '<div style="text-align: center; padding: 1.5rem; background: rgba(15, 23, 42, 0.5); border-radius: 8px;">';
    html += '<div style="font-size: 0.85rem; color: #94a3b8; text-transform: uppercase; margin-bottom: 0.5rem;">This Week</div>';
    html += `<div style="font-size: 2.5rem; font-weight: bold; color: #3b82f6;">${trends.week.count}</div>`;
    html += `<div style="font-size: 0.9rem; color: ${getTrendColor(trends.week.change)}; margin-top: 0.5rem;">`;
    html += `${getTrendIcon(trends.week.change)} ${Math.abs(trends.week.change)}% vs last week`;
    html += '</div></div>';
    
    // This Month
    html += '<div style="text-align: center; padding: 1.5rem; background: rgba(15, 23, 42, 0.5); border-radius: 8px;">';
    html += '<div style="font-size: 0.85rem; color: #94a3b8; text-transform: uppercase; margin-bottom: 0.5rem;">This Month</div>';
    html += `<div style="font-size: 2.5rem; font-weight: bold; color: #8b5cf6;">${trends.month.count}</div>`;
    html += `<div style="font-size: 0.9rem; color: ${getTrendColor(trends.month.change)}; margin-top: 0.5rem;">`;
    html += `${getTrendIcon(trends.month.change)} ${Math.abs(trends.month.change)}% vs last month`;
    html += '</div></div>';
    
    statsContainer.innerHTML = html;
    
    // Render Chart
    // Use daily_trend if available, otherwise just mock it or skip
    // We added daily_trend to API, check if it's there
    let chartData = [];
    let chartLabels = [];
    
    if (trends.daily_trend) {
        chartLabels = trends.daily_trend.map(d => d.date);
        chartData = trends.daily_trend.map(d => d.count);
    } else {
        // Fallback for older API call cached
        return;
    }

    new Chart(canvas, {
        type: 'line',
        data: {
            labels: chartLabels,
            datasets: [{
                label: 'Bookings',
                data: chartData,
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                borderColor: '#3b82f6',
                borderWidth: 2,
                pointBackgroundColor: '#3b82f6',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(255, 255, 255, 0.1)' },
                    ticks: { color: '#94a3b8' }
                },
                x: {
                    grid: { display: false },
                    ticks: { color: '#94a3b8' }
                }
            },
            plugins: {
                legend: { display: false }
            }
        }
    });
}

function renderPeakHours(heatmap) {
    const container = document.getElementById('peakHoursContent');
    const canvas = document.getElementById('peakHoursChart');
    
    if (!heatmap || Object.keys(heatmap).length === 0) {
        container.innerHTML = '<p style="text-align: center; padding: 2rem; color: #64748b;">No data available</p>';
        return;
    }
    
    // Process data for chart
    const labels = []; // Hours
    const datasets = [];
    const hours = [8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20];
    
    // Create datasets for each day (optional) or aggregate
    // Aggregating by hour for a simpler bar chart
    const hourlyTotals = hours.map(h => {
        let total = 0;
        Object.values(heatmap).forEach(dayHours => {
            if (dayHours[h]) total += dayHours[h];
        });
        return total;
    });

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: hours.map(h => h + ':00'),
            datasets: [{
                label: 'Total Bookings',
                data: hourlyTotals,
                backgroundColor: 'rgba(245, 158, 11, 0.5)',
                borderColor: '#f59e0b',
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(255, 255, 255, 0.1)' },
                    ticks: { 
                        color: '#94a3b8',
                        stepSize: 1
                    }
                },
                x: {
                    grid: { display: false },
                    ticks: { color: '#94a3b8' }
                }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(15, 23, 42, 0.9)',
                    titleColor: '#fff',
                    bodyColor: '#cbd5e1',
                    borderColor: 'rgba(255, 255, 255, 0.1)',
                    borderWidth: 1
                }
            }
        }
    });
}

function renderServicePopularity(services) {
    const container = document.getElementById('servicePopularityContent');
    const canvas = document.getElementById('servicePopularityChart');
    
    if (!services || services.length === 0) {
        container.innerHTML = '<p style="text-align: center; padding: 2rem; color: #64748b;">No data available</p>';
        return;
    }
    
    const labels = services.map(s => s.name);
    const data = services.map(s => s.booking_count);
    const colors = [
        '#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6',
        '#ec4899', '#6366f1', '#14b8a6', '#f97316', '#a855f7'
    ];

    new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: colors.slice(0, data.length),
                borderWidth: 0,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: { color: '#94a3b8', boxWidth: 12 }
                }
            },
            cutout: '70%'
        }
    });
}

function renderBookingHeatmap(heatmapData) {
    const container = document.getElementById('bookingHeatmapContent');
    
    if (!heatmapData || Object.keys(heatmapData).length === 0) {
        container.innerHTML = '<p style="text-align: center; padding: 2rem; color: #64748b;">No data available</p>';
        return;
    }
    
    const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    const hours = ['00:00', '01:00', '02:00', '03:00', '04:00', '05:00', '06:00', '07:00', '08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00', '20:00', '21:00', '22:00', '23:00'];
    const daysFull = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    
    // Find max value for color scaling
    let maxBookings = 0;
    Object.values(heatmapData).forEach(dayData => {
        Object.values(dayData).forEach(count => {
            if (count > maxBookings) maxBookings = count;
        });
    });
    
    // Color function: dark blue (low) to teal/green (high) - spa theme
    function getColor(count, max) {
        if (count === 0) return 'rgba(15, 23, 42, 0.6)'; // Dark background
        
        const intensity = count / max;
        
        if (intensity < 0.2) return 'rgba(30, 58, 95, 0.8)'; // Dark blue
        if (intensity < 0.4) return 'rgba(37, 99, 235, 0.7)'; // Blue
        if (intensity < 0.6) return 'rgba(6, 182, 212, 0.7)'; // Cyan
        if (intensity < 0.8) return 'rgba(20, 184, 166, 0.8)'; // Teal
        return 'rgba(16, 185, 129, 0.9)'; // Green (spa primary)
    }
    
    // Build heatmap HTML
    let html = '<div style="padding: 1rem;">';
    html += '<h3 style="color: #e2e8f0; margin: 0 0 1.5rem 0; font-size: 1.25rem; font-weight: 600;">Booking Activity by Hour & Day</h3>';
    
    html += '<div style="display: flex; gap: 2rem; align-items: flex-start;">';
    
    // Main heatmap table
    html += '<div style="flex: 1; overflow-x: auto;">';
    html += '<table style="border-collapse: collapse; width: 100%; background: rgba(15, 23, 42, 0.4); border-radius: 8px; overflow: hidden; backdrop-filter: blur(10px);">';
    
    // Header row with hours
    html += '<thead><tr><th style="padding: 0.75rem; text-align: left; background: rgba(30, 41, 59, 0.6); color: #94a3b8; font-size: 0.875rem; font-weight: 600; border: 1px solid rgba(255, 255, 255, 0.05); min-width: 60px;">Day</th>';
    hours.forEach(hour => {
        html += `<th style="padding: 0.5rem 0.25rem; text-align: center; background: rgba(30, 41, 59, 0.6); color: #94a3b8; font-size: 0.7rem; font-weight: 500; border: 1px solid rgba(255, 255, 255, 0.05); min-width: 32px; writing-mode: vertical-rl; transform: rotate(180deg);">${hour}</th>`;
    });
    html += '</tr></thead>';
    
    // Data rows
    html += '<tbody>';
    days.forEach((day, dayIndex) => {
        html += '<tr>';
        html += `<td style="padding: 0.75rem; font-weight: 600; color: #cbd5e1; font-size: 0.875rem; background: rgba(30, 41, 59, 0.4); border: 1px solid rgba(255, 255, 255, 0.05); white-space: nowrap;">${day}</td>`;
        
        hours.forEach((hour, hourIndex) => {
            const dayFull = daysFull[dayIndex];
            const hourNum = hourIndex;
            const count = (heatmapData[dayFull] && heatmapData[dayFull][hourNum]) || 0;
            const bgColor = getColor(count, maxBookings);
            
            html += `<td style="
                padding: 0;
                text-align: center;
                background: ${bgColor};
                border: 1px solid rgba(255, 255, 255, 0.1);
                cursor: help;
                transition: all 0.2s ease;
                min-width: 32px;
                height: 40px;
                position: relative;
            " title="${dayFull} at ${hour} - ${count} booking${count !== 1 ? 's' : ''}" 
            onmouseover="this.style.transform='scale(1.15)'; this.style.zIndex='10'; this.style.boxShadow='0 4px 12px rgba(16, 185, 129, 0.4)'; this.style.border='1px solid rgba(16, 185, 129, 0.6)';" 
            onmouseout="this.style.transform='scale(1)'; this.style.zIndex='1'; this.style.boxShadow='none'; this.style.border='1px solid rgba(255, 255, 255, 0.1)';">
            </td>`;
        });
        
        html += '</tr>';
    });
    html += '</tbody></table>';
    html += '</div>';
    
    // Vertical color scale legend
    html += '<div style="display: flex; flex-direction: column; align-items: center; gap: 0.5rem;">';
    html += '<div style="color: #94a3b8; font-size: 0.75rem; font-weight: 600; writing-mode: vertical-rl; transform: rotate(180deg); margin-bottom: 0.5rem;">Bookings</div>';
    
    // Color gradient bar with spa colors
    html += '<div style="width: 30px; height: 200px; background: linear-gradient(to top, rgba(15, 23, 42, 0.8), rgba(30, 58, 95, 0.9), rgba(37, 99, 235, 0.8), rgba(6, 182, 212, 0.8), rgba(20, 184, 166, 0.9), rgba(16, 185, 129, 1)); border-radius: 4px; border: 1px solid rgba(255, 255, 255, 0.1); position: relative; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);">';
    html += '</div>';
    
    // Scale labels
    html += '<div style="display: flex; flex-direction: column; align-items: center; gap: 0.25rem; margin-top: 0.5rem;">';
    html += `<span style="color: #10b981; font-size: 0.75rem; font-weight: 600;">${maxBookings}</span>`;
    html += '<span style="color: #94a3b8; font-size: 0.65rem;">High</span>';
    html += '<div style="height: 60px;"></div>';
    html += '<span style="color: #94a3b8; font-size: 0.65rem;">Low</span>';
    html += '<span style="color: #64748b; font-size: 0.75rem; font-weight: 500;">0</span>';
    html += '</div>';
    
    html += '</div>'; // End legend
    html += '</div>'; // End flex container
    html += '</div>'; // End main container
    
    container.innerHTML = html;
}

// Phase 2 Rendering Functions

function renderCustomerSegmentation(segmentation) {
    const canvas = document.getElementById('customerSegmentationChart');
    const legendContainer = document.getElementById('customerSegmentationLegend');
    
    if (!segmentation || (!segmentation.vip && !segmentation.at_risk && !segmentation.one_time)) {
        canvas.parentElement.parentElement.innerHTML = '<p style="text-align: center; padding: 2rem; color: #64748b;">No data available</p>';
        return;
    }
    
    const vipCount = segmentation.vip?.length || 0;
    const atRiskCount = segmentation.at_risk?.length || 0;
    const oneTimeCount = segmentation.one_time?.length || 0;
    
    new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: ['VIP', 'At-Risk', 'One-Time'],
            datasets: [{
                data: [vipCount, atRiskCount, oneTimeCount],
                backgroundColor: ['#fbbf24', '#f59e0b', '#3b82f6'],
                borderWidth: 0,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            cutout: '60%'
        }
    });

    // Custom Legend / Details
    let html = '<div>';
    // VIP
    html += `<div style="margin-bottom: 1rem; padding: 1rem; background: rgba(251, 191, 36, 0.1); border-left: 4px solid #fbbf24; border-radius: 4px;">`;
    html += `<strong>VIP Customers:</strong> ${vipCount}`;
    html += `</div>`;
    // At-Risk
    html += `<div style="margin-bottom: 1rem; padding: 1rem; background: rgba(245, 158, 11, 0.1); border-left: 4px solid #f59e0b; border-radius: 4px;">`;
    html += `<strong>At-Risk:</strong> ${atRiskCount}`;
    html += `</div>`;
    // One-Time
    html += `<div style="margin-bottom: 1rem; padding: 1rem; background: rgba(59, 130, 246, 0.1); border-left: 4px solid #3b82f6; border-radius: 4px;">`;
    html += `<strong>One-Time:</strong> ${oneTimeCount}`;
    html += `</div>`;
    html += '</div>';

    legendContainer.innerHTML = html;
}

function renderMasseusePerformance(performance) {
    const container = document.getElementById('masseusePerformanceContent');
    const canvas = document.getElementById('masseusePerformanceChart');
    
    if (!performance || performance.length === 0) {
        container.innerHTML = '<p style="text-align: center; padding: 2rem; color: #64748b;">No data available</p>';
        return;
    }
    
    const labels = performance.slice(0, 10).map(p => p.name); // Top 10
    const data = performance.slice(0, 10).map(p => p.bookings_30_days);
    
    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Bookings (30 Days)',
                data: data,
                backgroundColor: 'rgba(251, 191, 36, 0.5)',
                borderColor: '#fbbf24',
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            indexAxis: 'y', // Horizontal bar chart
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    beginAtZero: true,
                    grid: { color: 'rgba(255, 255, 255, 0.1)' },
                    ticks: { 
                        color: '#94a3b8',
                        stepSize: 1,
                        precision: 0
                    }
                },
                y: {
                    grid: { display: false },
                    ticks: { color: '#fff' }
                }
            },
            plugins: {
                legend: { display: false }
            }
        }
    });
}

function renderCancellationTracking(cancellations) {
    const container = document.getElementById('cancellationTrackingContent');
    
    if (!cancellations || cancellations.total_bookings === 0) {
        container.innerHTML = '<p style="text-align: center; padding: 2rem; color: #64748b;">No data available</p>';
        return;
    }
    
    const rate = cancellations.cancellation_rate;
    const rateColor = rate < 10 ? '#10b981' : rate < 20 ? '#f59e0b' : '#ef4444';
    const rateStatus = rate < 10 ? 'Excellent' : rate < 20 ? 'Good' : 'Needs Attention';
    
    let html = '<div style="padding: 1rem;">';
    
    // Summary card
    html += '<div style="padding: 2rem; background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.1)); border: 2px solid rgba(239, 68, 68, 0.3); border-radius: 12px; text-align: center; margin-bottom: 2rem;">';
    html += '<div style="font-size: 3rem; font-weight: bold; color: ' + rateColor + ';">' + rate + '%</div>';
    html += '<div style="color: #94a3b8; font-size: 1.1rem; margin-top: 0.5rem;">Cancellation Rate</div>';
    html += '<div style="color: ' + rateColor + '; font-size: 0.9rem; margin-top: 0.5rem;">' + rateStatus + '</div>';
    html += '<div style="color: #64748b; font-size: 0.85rem; margin-top: 1rem;">' + cancellations.total_cancelled + ' cancelled out of ' + cancellations.total_bookings + ' bookings (last 30 days)</div>';
    html += '</div>';
    
    // Two columns
    html += '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">';
    
    // Top Cancellers
    if (cancellations.top_cancellers && cancellations.top_cancellers.length > 0) {
        html += '<div style="padding: 1.5rem; background: rgba(15, 23, 42, 0.5); border-radius: 12px;">';
        html += '<h3 style="margin-bottom: 1rem; color: #ef4444;">Top Cancellers (Last 90 Days)</h3>';
        cancellations.top_cancellers.forEach((customer, index) => {
            html += '<div style="padding: 0.75rem; margin-bottom: 0.5rem; background: rgba(239, 68, 68, 0.1); border-radius: 6px;">';
            html += '<div style="display: flex; justify-content: space-between; align-items: center;">';
            html += '<div><strong>' + customer.name + '</strong><br><small style="color: #94a3b8;">' + customer.mobile + '</small></div>';
            html += '<div style="background: rgba(239, 68, 68, 0.2); padding: 0.5rem 1rem; border-radius: 20px; font-weight: bold; color: #ef4444;">' + customer.cancellation_count + '</div>';
            html += '</div></div>';
        });
        html += '</div>';
    }
    
    // Service Cancellations
    if (cancellations.service_cancellations && cancellations.service_cancellations.length > 0) {
        html += '<div style="padding: 1.5rem; background: rgba(15, 23, 42, 0.5); border-radius: 12px;">';
        html += '<h3 style="margin-bottom: 1rem; color: #f59e0b;">Services with Most Cancellations</h3>';
        cancellations.service_cancellations.forEach((service, index) => {
            html += '<div style="padding: 0.75rem; margin-bottom: 0.5rem; background: rgba(245, 158, 11, 0.1); border-radius: 6px;">';
            html += '<div style="display: flex; justify-content: space-between; align-items: center;">';
            html += '<div><strong>' + service.service_name + '</strong></div>';
            html += '<div style="background: rgba(245, 158, 11, 0.2); padding: 0.5rem 1rem; border-radius: 20px; font-weight: bold; color: #f59e0b;">' + service.cancellation_count + '</div>';
            html += '</div></div>';
        });
        html += '</div>';
    }
    
    html += '</div></div>';
    container.innerHTML = html;
}

// Phase 3 Rendering Functions

function renderYearOverYear(yoyData) {
    const container = document.getElementById('yearOverYearContent');
    
    if (!yoyData || yoyData.length === 0) {
        container.innerHTML = '<p style="text-align: center; padding: 2rem; color: #64748b;">No data available</p>';
        return;
    }
    
    const currentYear = new Date().getFullYear();
    const previousYear = currentYear - 1;
    
    let html = '<div style="padding: 1rem;">';
    html += '<div style="overflow-x: auto;">';
    html += '<table style="width: 100%; border-collapse: collapse; min-width: 600px;">';
    
    // Header
    html += '<thead><tr>';
    html += '<th style="padding: 1rem; text-align: left; border-bottom: 2px solid rgba(255,255,255,0.1);">Month</th>';
    html += '<th style="padding: 1rem; text-align: center; border-bottom: 2px solid rgba(255,255,255,0.1);">' + currentYear + '</th>';
    html += '<th style="padding: 1rem; text-align: center; border-bottom: 2px solid rgba(255,255,255,0.1);">' + previousYear + '</th>';
    html += '<th style="padding: 1rem; text-align: center; border-bottom: 2px solid rgba(255,255,255,0.1);">Change</th>';
    html += '</tr></thead>';
    
    // Body
    html += '<tbody>';
    yoyData.forEach(row => {
        const changeColor = row.change > 0 ? '#10b981' : row.change < 0 ? '#ef4444' : '#64748b';
        const changeIcon = row.change > 0 ? '↗' : row.change < 0 ? '↘' : '→';
        
        html += '<tr>';
        html += '<td style="padding: 1rem; border-bottom: 1px solid rgba(255,255,255,0.05);"><strong>' + row.month + '</strong></td>';
        html += '<td style="padding: 1rem; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.05);"><span style="font-size: 1.1rem; font-weight: 600; color: #10b981;">' + row.current_year + '</span></td>';
        html += '<td style="padding: 1rem; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.05);"><span style="color: #94a3b8;">' + row.previous_year + '</span></td>';
        html += '<td style="padding: 1rem; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.05);"><span style="color: ' + changeColor + '; font-weight: 600;">' + changeIcon + ' ' + Math.abs(row.change) + '%</span></td>';
        html += '</tr>';
    });
    html += '</tbody></table>';
    html += '</div></div>';
    
    container.innerHTML = html;
}

function renderServiceAffinity(affinity) {
    const container = document.getElementById('serviceAffinityContent');
    
    if (!affinity || affinity.length === 0) {
        container.innerHTML = '<p style="text-align: center; padding: 2rem; color: #64748b;">No service combinations found yet</p>';
        return;
    }
    
    let html = '<div style="padding: 1rem;">';
    html += '<p style="color: #94a3b8; margin-bottom: 1.5rem;">Services frequently booked together by the same customers:</p>';
    
    affinity.forEach((pair, index) => {
        html += '<div style="padding: 1rem; margin-bottom: 1rem; background: rgba(59, 130, 246, 0.1); border-left: 4px solid #3b82f6; border-radius: 8px;">';
        html += '<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">';
        html += '<div style="flex: 1;">';
        html += '<strong style="color: #3b82f6;">' + pair.service1 + '</strong>';
        html += ' <span style="color: #64748b;">+</span> ';
        html += '<strong style="color: #8b5cf6;">' + pair.service2 + '</strong>';
        html += '</div>';
        html += '<div style="background: rgba(59, 130, 246, 0.2); padding: 0.5rem 1rem; border-radius: 20px; font-weight: bold; color: #3b82f6;">';
        html += pair.count + ' customers';
        html += '</div>';
        html += '</div>';
        html += '<div style="margin-top: 0.5rem; font-size: 0.85rem; color: #64748b;">💡 Upsell opportunity: Recommend ' + pair.service2 + ' to ' + pair.service1 + ' customers</div>';
        html += '</div>';
    });
    
    html += '</div>';
    container.innerHTML = html;
}

// Seasonal patterns state
let seasonalCurrentMonth = new Date().getMonth() + 1; // 1-12
let seasonalCurrentYear = new Date().getFullYear();
let seasonalChartInstance = null;

function renderSeasonalPatterns(patterns) {
    const container = document.getElementById('seasonalPatternsContent');
    const canvas = document.getElementById('seasonalPatternsChart');
    const monthDisplay = document.getElementById('seasonalMonthDisplay');
    
    if (!patterns || patterns.length === 0) {
        container.innerHTML = '<p style="text-align: center; padding: 2rem; color: #64748b;">No data available</p>';
        return;
    }
    
    // Update month display
    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    monthDisplay.textContent = `${monthNames[seasonalCurrentMonth - 1]} ${seasonalCurrentYear}`;
    
    const labels = patterns.map(p => p.month);
    const data = patterns.map(p => p.bookings);
    const backgroundColors = patterns.map(p => {
        if (p.type === 'peak') return '#10b981';
        if (p.type === 'slow') return '#3b82f6';
        return '#64748b';
    });

    // Destroy existing chart if it exists
    if (seasonalChartInstance) {
        seasonalChartInstance.destroy();
    }

    seasonalChartInstance = new Chart(canvas, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Bookings',
                data: data,
                backgroundColor: backgroundColors,
                borderRadius: 4,
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(255, 255, 255, 0.1)' },
                    ticks: { 
                        color: '#94a3b8',
                        stepSize: 1,
                        precision: 0
                    }
                },
                x: {
                    grid: { display: false },
                    ticks: { color: '#94a3b8' }
                }
            },
            plugins: {
                legend: { display: false }
            }
        }
    });
}

async function navigateSeasonalMonth(direction) {
    // Update month/year
    seasonalCurrentMonth += direction;
    
    if (seasonalCurrentMonth > 12) {
        seasonalCurrentMonth = 1;
        seasonalCurrentYear++;
    } else if (seasonalCurrentMonth < 1) {
        seasonalCurrentMonth = 12;
        seasonalCurrentYear--;
    }
    
    // Fetch new data
    const container = document.getElementById('seasonalPatternsContent');
    container.style.opacity = '0.5';
    
    try {
        const response = await fetch(`../api/get_analytics_data.php?type=seasonal&month=${seasonalCurrentMonth}&year=${seasonalCurrentYear}`);
        
        // Check if session expired
        if (response.redirected && response.url.includes('login.php')) {
            window.location.href = '../login.php?timeout=1';
            return;
        }
        
        const data = await response.json();
        
        if (data.seasonal_patterns) {
            renderSeasonalPatterns(data.seasonal_patterns);
        }
        
        container.style.opacity = '1';
    } catch (error) {
        console.error('Error loading seasonal patterns:', error);
        container.style.opacity = '1';
    }
}

function renderCustomerPreferences(preferences) {
    const container = document.getElementById('customerPreferencesContent');
    
    if (!preferences || (!preferences.time_slots && !preferences.days)) {
        container.innerHTML = '<p style="text-align: center; padding: 2rem; color: #64748b;">No data available</p>';
        return;
    }
    
    let html = '<div style="padding: 1rem;">';
    html += '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">';
    
    // Time Slots
    if (preferences.time_slots && preferences.time_slots.length > 0) {
        html += '<div>';
        html += '<h3 style="margin-bottom: 1rem; color: #f59e0b; display: flex; align-items: center; gap: 0.5rem;"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg> Most Popular Times</h3>';
        preferences.time_slots.forEach((slot, index) => {
            html += '<div style="padding: 0.75rem; margin-bottom: 0.5rem; background: rgba(245, 158, 11, 0.1); border-radius: 6px;">';
            html += '<div style="display: flex; justify-content: space-between; align-items: center;">';
            html += '<div><strong>' + (index + 1) + '. ' + slot.time + '</strong></div>';
            html += '<div style="color: #f59e0b; font-weight: 600;">' + slot.bookings + ' bookings</div>';
            html += '</div></div>';
        });
        html += '</div>';
    }
    
    // Days
    if (preferences.days && preferences.days.length > 0) {
        html += '<div>';
        html += '<h3 style="margin-bottom: 1rem; color: #8b5cf6; display: flex; align-items: center; gap: 0.5rem;"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg> Most Popular Days</h3>';
        const maxDayBookings = Math.max(...preferences.days.map(d => d.bookings));
        preferences.days.forEach(day => {
            const percentage = (day.bookings / maxDayBookings) * 100;
            html += '<div style="margin-bottom: 1rem;">';
            html += '<div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;">';
            html += '<strong>' + day.day + '</strong>';
            html += '<span style="color: #94a3b8;">' + day.bookings + '</span>';
            html += '</div>';
            html += '<div style="width: 100%; height: 8px; background: rgba(139, 92, 246, 0.2); border-radius: 4px; overflow: hidden;">';
            html += '<div style="width: ' + percentage + '%; height: 100%; background: #8b5cf6;"></div>';
            html += '</div></div>';
        });
        html += '</div>';
    }
    
    html += '</div></div>';
    container.innerHTML = html;
}

// Load analytics on page load
document.addEventListener('DOMContentLoaded', loadAnalytics);
</script>
</div>

<div style="text-align: left;">
<?php if (isAdmin()): ?>
<!-- Danger Zone -->
<div class="glass-card" style="margin-top: 3rem; border: 2px solid #dc2626; background: rgba(220, 38, 38, 0.05); text-align: left;">
    <h2 style="color: #dc2626; display: flex; align-items: center; gap: 0.5rem; justify-content: flex-start;">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
            <line x1="12" y1="9" x2="12" y2="13"></line>
            <line x1="12" y1="17" x2="12.01" y2="17"></line>
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
        <h2 style="color: #dc2626; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
            Confirm Database Reset
        </h2>
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
        <p style="color: #10b981; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
            User accounts will be preserved
        </p>
        <form onsubmit="handleDatabaseReset(event)">
            <label style="display: block; margin-bottom: 0.5rem; color: #fff;">
                Enter your admin password to confirm:
            </label>
            <input 
                type="password" 
                id="resetPassword" 
                required 
                style="width: 100%; padding: 0.75rem; background: rgba(15, 23, 42, 0.8); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; color: #fff; margin-bottom: 1rem;"
                placeholder="Your password"
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
<?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
