<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

session_start();

if (function_exists('checkSessionTimeout') && checkSessionTimeout()) {
    redirect('../login.php?timeout=1');
}

if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

$message = '';

// Handle Add/Edit/Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $name = sanitize($conn, $_POST['name']);
            $username = sanitize($conn, $_POST['username']);
            $mobile = sanitize($conn, $_POST['mobile']);
            $bio = sanitize($conn, $_POST['bio']);
            $specialties = sanitize($conn, $_POST['specialties']);
            $image_url = sanitize($conn, $_POST['image_url']);
            $password = $_POST['password'];
            $password_confirm = $_POST['password_confirm'];
            
            // Validate passwords match
            if ($password !== $password_confirm) {
                $message = "Passwords do not match!";
            } elseif (empty($password)) {
                $message = "Password is required!";
            } else {
                // Check if mobile or username already exists
                $check_sql = "SELECT id FROM users WHERE mobile='$mobile' OR username='$username'";
                $check_result = $conn->query($check_sql);
                if ($check_result && $check_result->num_rows > 0) {
                    $message = "Error: Mobile number or username is already registered!";
                } else {
                    // Create user account first
                    $user_sql = "INSERT INTO users (name, username, mobile, password, role) VALUES ('$name', '$username', '$mobile', '$password', 'masseuse')";
                    if ($conn->query($user_sql)) {
                        $user_id = $conn->insert_id;
                        
                        // Create masseuse record
                        $sql = "INSERT INTO masseuses (name, mobile, bio, specialties, image_url, user_id) VALUES ('$name', '$mobile', '$bio', '$specialties', '$image_url', $user_id)";
                        if ($conn->query($sql)) {
                            $message = "Masseuse added successfully!";
                        } else {
                            // Rollback user creation if masseuse creation fails
                            $conn->query("DELETE FROM users WHERE id=$user_id");
                            $message = "Error creating masseuse record.";
                        }
                    } else {
                        $message = "Error creating user account: " . $conn->error;
                    }
                }
            }
        } elseif ($_POST['action'] === 'edit') {
            $id = (int)$_POST['id'];
            $name = sanitize($conn, $_POST['name']);
            $username = sanitize($conn, $_POST['username']);
            $mobile = sanitize($conn, $_POST['mobile']);
            $bio = sanitize($conn, $_POST['bio']);
            $specialties = sanitize($conn, $_POST['specialties']);
            $image_url = sanitize($conn, $_POST['image_url']);
            
            // Update masseuse record
            $sql = "UPDATE masseuses SET name='$name', mobile='$mobile', bio='$bio', specialties='$specialties', image_url='$image_url' WHERE id=$id";
            if ($conn->query($sql)) {
                // Get user_id for this masseuse
                $user_result = $conn->query("SELECT user_id FROM masseuses WHERE id=$id");
                if ($user_result && $user_result->num_rows > 0) {
                    $user_id = $user_result->fetch_assoc()['user_id'];
                    
                    // If masseuse doesn't have a user account yet, create one
                    if (empty($user_id)) {
                        // Check if password is provided for new account
                        if (!empty($_POST['password']) && $_POST['password'] === $_POST['password_confirm']) {
                            // Check if mobile or username already exists
                            $check_sql = "SELECT id FROM users WHERE mobile='$mobile' OR username='$username'";
                            $check_result = $conn->query($check_sql);
                            if ($check_result && $check_result->num_rows > 0) {
                                $message = "Masseuse updated but mobile number or username is already registered!";
                            } else {
                                $password = $_POST['password'];
                                $create_user_sql = "INSERT INTO users (name, username, mobile, password, role) VALUES ('$name', '$username', '$mobile', '$password', 'masseuse')";
                                if ($conn->query($create_user_sql)) {
                                    $user_id = $conn->insert_id;
                                    // Link the user account to the masseuse
                                    $conn->query("UPDATE masseuses SET user_id=$user_id WHERE id=$id");
                                    $message = "Masseuse updated and user account created successfully!";
                                } else {
                                    $message = "Masseuse updated but failed to create user account: " . $conn->error;
                                }
                            }
                        } else {
                            $message = "Masseuse updated. Note: No user account exists. Add a password to create login access.";
                        }
                    } else {
                        // Check for duplicate username/mobile (excluding current user)
                        $check_sql = "SELECT id FROM users WHERE (mobile='$mobile' OR username='$username') AND id != $user_id";
                        $check_result = $conn->query($check_sql);
                        
                        if ($check_result && $check_result->num_rows > 0) {
                             $message = "Masseuse updated but mobile/username change failed: Already taken!";
                        } else {
                            // Update existing user account
                            $user_sql = "UPDATE users SET name='$name', username='$username', mobile='$mobile' WHERE id=$user_id";
                            $conn->query($user_sql);
                            
                            // Update password if provided
                            if (!empty($_POST['password'])) {
                                $password = $_POST['password'];
                                $password_confirm = $_POST['password_confirm'];
                                
                                if ($password === $password_confirm) {
                                    $conn->query("UPDATE users SET password='$password' WHERE id=$user_id");
                                } else {
                                    $message = "Masseuse updated but passwords did not match!";
                                }
                            }
                            
                            if (empty($message)) {
                                $message = "Masseuse updated successfully!";
                            }
                        }
                    }
                }
            }
        } elseif ($_POST['action'] === 'delete') {
            $id = (int)$_POST['id'];
            
            // Get user_id before deleting masseuse
            $user_result = $conn->query("SELECT user_id FROM masseuses WHERE id=$id");
            if ($user_result && $user_result->num_rows > 0) {
                $user_id = $user_result->fetch_assoc()['user_id'];
                
                // Delete masseuse
                $sql = "DELETE FROM masseuses WHERE id=$id";
                if ($conn->query($sql)) {
                    // Delete associated user account
                    $conn->query("DELETE FROM users WHERE id=$user_id");
                    $message = "Masseuse deleted successfully!";
                }
            }
        }
    }
}

$masseuses = getMasseuses($conn);

$pageTitle = 'Manage Masseuses - Kallma Spa';
require_once 'includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <h1>Manage Masseuses</h1>
    <button onclick="openAddModal()" class="btn btn-primary">Add Masseuse</button>
</div>

<?php if ($message): ?>
    <div style="background: rgba(16, 185, 129, 0.2); color: #6ee7b7; padding: 1rem; border-radius: 8px; margin-bottom: 2rem;">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<div class="glass-card">
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Username</th>
                <th>Specialties</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($masseuses as $masseuse): ?>
                <tr>
                    <td><?php echo htmlspecialchars($masseuse['name']); ?></td>
                    <td><?php echo htmlspecialchars($masseuse['username'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($masseuse['specialties']); ?></td>
                    <td>
                        <div style="display: flex; gap: 0.5rem; align-items: center;">
                            <button onclick='openEditModal(<?php echo json_encode($masseuse); ?>)' class="icon-btn" title="Edit" style="color: var(--primary-color);">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                </svg>
                            </button>
                            <button onclick='window.location.href="masseuse_schedule.php?masseuse_id=<?php echo $masseuse['id']; ?>"' class="btn btn-primary btn-small">Schedule</button>

                            <form method="POST" style="display: inline; margin: 0;" onsubmit="return confirm('Delete this masseuse?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $masseuse['id']; ?>">
                                <button type="submit" class="icon-btn" title="Delete" style="color: #ef4444;">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="3 6 5 6 21 6"></polyline>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                        <line x1="10" y1="11" x2="10" y2="17"></line>
                                        <line x1="14" y1="11" x2="14" y2="17"></line>
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Monthly Timeline Table -->
<?php
// Get timeline month and year (can be different from calendar above)
$timeline_month = isset($_GET['timeline_month']) ? (int)$_GET['timeline_month'] : (int)date('n');
$timeline_year = isset($_GET['timeline_year']) ? (int)$_GET['timeline_year'] : (int)date('Y');

// Calculate timeline dates
$timeline_first_day = "$timeline_year-" . str_pad($timeline_month, 2, '0', STR_PAD_LEFT) . "-01";
$timeline_last_day = date('Y-m-t', strtotime($timeline_first_day));
$timeline_days_in_month = (int)date('t', strtotime($timeline_first_day));

// Get all bookings for the timeline month
$timeline_bookings_sql = "SELECT b.*, m.id as masseuse_id, m.name as masseuse_name, s.name as service_name 
                         FROM bookings b 
                         JOIN masseuses m ON b.masseuse_id = m.id 
                         JOIN services s ON b.service_id = s.id 
                         WHERE b.booking_date BETWEEN '$timeline_first_day' AND '$timeline_last_day'
                         AND b.status IN ('pending', 'completed')
                         ORDER BY b.booking_date, b.booking_time";
$timeline_bookings_result = $conn->query($timeline_bookings_sql);

// Organize bookings by masseuse and date
$timeline_data = [];
foreach ($masseuses as $masseuse) {
    $timeline_data[$masseuse['id']] = [
        'name' => $masseuse['name'],
        'bookings' => []
    ];
}

if ($timeline_bookings_result && $timeline_bookings_result->num_rows > 0) {
    while ($booking = $timeline_bookings_result->fetch_assoc()) {
        $masseuse_id = $booking['masseuse_id'];
        $date = $booking['booking_date'];
        
        if (!isset($timeline_data[$masseuse_id]['bookings'][$date])) {
            $timeline_data[$masseuse_id]['bookings'][$date] = [
                'pending' => 0,
                'completed' => 0,
                'details' => []
            ];
        }
        
        $timeline_data[$masseuse_id]['bookings'][$date][$booking['status']]++;
        $timeline_data[$masseuse_id]['bookings'][$date]['details'][] = [
            'time' => date('g:i A', strtotime($booking['booking_time'])),
            'service' => $booking['service_name'],
            'status' => $booking['status']
        ];
    }
}

// Timeline navigation
$timeline_prev_month = $timeline_month - 1;
$timeline_prev_year = $timeline_year;
if ($timeline_prev_month < 1) {
    $timeline_prev_month = 12;
    $timeline_prev_year--;
}

$timeline_next_month = $timeline_month + 1;
$timeline_next_year = $timeline_year;
if ($timeline_next_month > 12) {
    $timeline_next_month = 1;
    $timeline_next_year++;
}
?>

<div class="glass-card" id="monthlyTimelineContainer" style="margin-top: 3rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <h2 style="margin: 0;">Monthly Timeline - <?php echo date('F Y', strtotime($timeline_first_day)); ?></h2>
        <div style="display: flex; gap: 0.5rem;">
            <button onclick="loadMonthlyTimeline(<?php echo $timeline_prev_month; ?>, <?php echo $timeline_prev_year; ?>)" class="btn btn-outline btn-small">← Previous</button>
            <span style="color: #94a3b8; font-weight: 600; padding: 0 0.5rem; display: flex; align-items: center;">Month</span>
            <button onclick="loadMonthlyTimeline(<?php echo $timeline_next_month; ?>, <?php echo $timeline_next_year; ?>)" class="btn btn-outline btn-small">Next →</button>
        </div>
    </div>
    
    <!-- Legend -->
    <div style="display: flex; gap: 1.5rem; margin-bottom: 1.5rem; flex-wrap: wrap; padding: 1rem; background: rgba(255, 255, 255, 0.02); border-radius: 8px;">
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <div style="width: 20px; height: 20px; background: rgba(16, 185, 129, 0.3); border: 1px solid rgba(16, 185, 129, 0.6); border-radius: 4px;"></div>
            <span style="color: #94a3b8; font-size: 0.9rem;">Completed</span>
        </div>
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <div style="width: 20px; height: 20px; background: rgba(245, 158, 11, 0.3); border: 1px solid rgba(245, 158, 11, 0.6); border-radius: 4px;"></div>
            <span style="color: #94a3b8; font-size: 0.9rem;">Pending</span>
        </div>
    </div>
    
    <div id="timeline" style="overflow-x: auto;">
        <table style="min-width: 100%; border-collapse: separate; border-spacing: 0;">
            <thead>
                <tr>
                    <th style="position: sticky; left: 0; background: rgba(15, 23, 42, 0.95); z-index: 10; min-width: 150px; border-right: 2px solid rgba(16, 185, 129, 0.3);">Masseuse</th>
                    <?php for ($day = 1; $day <= $timeline_days_in_month; $day++): ?>
                        <?php
                        $date = sprintf('%04d-%02d-%02d', $timeline_year, $timeline_month, $day);
                        $is_today = ($date === date('Y-m-d'));
                        $day_name = date('D', strtotime($date));
                        ?>
                        <th style="min-width: 50px; text-align: center; font-size: 0.85rem; padding: 0.5rem; <?php echo $is_today ? 'background: rgba(6, 182, 212, 0.1); border: 2px solid #06b6d4;' : ''; ?>">
                            <div style="font-weight: 600;"><?php echo $day; ?></div>
                            <div style="font-size: 0.75rem; color: #64748b; font-weight: normal;"><?php echo $day_name; ?></div>
                        </th>
                    <?php endfor; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($timeline_data as $masseuse_id => $masseuse_info): ?>
                    <tr>
                        <td style="position: sticky; left: 0; background: rgba(15, 23, 42, 0.95); z-index: 5; font-weight: 600; border-right: 2px solid rgba(16, 185, 129, 0.3); padding: 1rem;">
                            <?php echo htmlspecialchars($masseuse_info['name']); ?>
                        </td>
                        <?php for ($day = 1; $day <= $timeline_days_in_month; $day++): ?>
                            <?php
                            $date = sprintf('%04d-%02d-%02d', $timeline_year, $timeline_month, $day);
                            $bookings = $masseuse_info['bookings'][$date] ?? null;
                            $is_today = ($date === date('Y-m-d'));
                            ?>
                            <td style="text-align: center; padding: 0.5rem; border: 1px solid rgba(255, 255, 255, 0.05); <?php echo $is_today ? 'background: rgba(6, 182, 212, 0.05);' : ''; ?>">
                                <?php if ($bookings): ?>
                                    <div style="display: flex; flex-direction: column; gap: 0.25rem; align-items: stretch;">
                                        <?php foreach ($bookings['details'] as $booking): ?>
                                            <div style="background: <?php echo $booking['status'] === 'completed' ? 'rgba(16, 185, 129, 0.2)' : 'rgba(245, 158, 11, 0.2)'; ?>; 
                                                        color: <?php echo $booking['status'] === 'completed' ? '#10b981' : '#f59e0b'; ?>; 
                                                        border-left: 3px solid <?php echo $booking['status'] === 'completed' ? '#10b981' : '#f59e0b'; ?>; 
                                                        padding: 0.25rem 0.5rem; 
                                                        border-radius: 3px; 
                                                        font-size: 0.7rem; 
                                                        font-weight: 600; 
                                                        cursor: help;
                                                        white-space: nowrap;"
                                                 title="<?php echo htmlspecialchars($booking['service']) . ' (' . ucfirst($booking['status']) . ')'; ?>">
                                                <?php echo $booking['time']; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span style="color: #334155;">—</span>
                                <?php endif; ?>
                            </td>
                        <?php endfor; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Individual Masseuse Timelines -->
<?php
// Get individual timeline data for each masseuse
foreach ($masseuses as $masseuse):
    $masseuse_id = $masseuse['id'];
    
    // Get availability for this masseuse for the timeline month
    $availability_sql = "SELECT date, start_time, end_time 
                        FROM daily_availability 
                        WHERE masseuse_id = $masseuse_id 
                        AND date BETWEEN '$timeline_first_day' AND '$timeline_last_day'
                        ORDER BY date, start_time";
    $availability_result = $conn->query($availability_sql);
    
    $masseuse_availability = [];
    if ($availability_result && $availability_result->num_rows > 0) {
        while ($avail = $availability_result->fetch_assoc()) {
            $date = $avail['date'];
            if (!isset($masseuse_availability[$date])) {
                $masseuse_availability[$date] = [];
            }
            $masseuse_availability[$date][] = [
                'start' => $avail['start_time'],
                'end' => $avail['end_time']
            ];
        }
    }
    
    // Get bookings for this masseuse
    $masseuse_bookings = $timeline_data[$masseuse_id]['bookings'] ?? [];
?>

<div class="glass-card masseuse-timeline-card" style="margin-top: 2rem;" data-masseuse-id="<?php echo $masseuse_id; ?>">
    <div style="padding: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <h3 style="margin: 0; color: var(--primary-color);">
            <?php echo htmlspecialchars($masseuse['name']); ?>'s Schedule - <?php echo date('F Y', strtotime($timeline_first_day)); ?>
        </h3>
        <div style="display: flex; gap: 0.5rem;">
            <button onclick="loadIndividualTimelines(<?php echo $timeline_prev_month; ?>, <?php echo $timeline_prev_year; ?>)" class="btn btn-outline btn-small">← Prev</button>
            <button onclick="loadIndividualTimelines(<?php echo $timeline_next_month; ?>, <?php echo $timeline_next_year; ?>)" class="btn btn-outline btn-small">Next →</button>
        </div>
    </div>
    
    <div id="masseuse-timeline-<?php echo $masseuse_id; ?>" style="padding: 0 1.5rem 1.5rem;">
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem; margin-top: 1rem;">
            <?php for ($day = 1; $day <= $timeline_days_in_month; $day++): ?>
                <?php
                $date = sprintf('%04d-%02d-%02d', $timeline_year, $timeline_month, $day);
                $is_today = ($date === date('Y-m-d'));
                $day_name = date('l', strtotime($date));
                $has_availability = isset($masseuse_availability[$date]);
                $has_bookings = isset($masseuse_bookings[$date]);
                ?>
                
                <div style="background: rgba(255, 255, 255, 0.02); border: 1px solid <?php echo $is_today ? '#06b6d4' : 'rgba(255, 255, 255, 0.05)'; ?>; border-radius: 8px; padding: 1rem; <?php echo $is_today ? 'box-shadow: 0 0 12px rgba(6, 182, 212, 0.3);' : ''; ?>">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; padding-bottom: 0.5rem; border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                        <div>
                            <div style="font-weight: 600; font-size: 1.1rem; <?php echo $is_today ? 'color: #06b6d4;' : ''; ?>"><?php echo $day; ?></div>
                            <div style="font-size: 0.75rem; color: #64748b;"><?php echo substr($day_name, 0, 3); ?></div>
                        </div>
                        <?php if ($is_today): ?>
                            <span style="background: rgba(6, 182, 212, 0.2); color: #06b6d4; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.7rem; font-weight: 600;">TODAY</span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($has_availability): ?>
                        <!-- Availability Time Slots -->
                        <div style="margin-bottom: 0.75rem;">
                            <div style="font-size: 0.75rem; color: #94a3b8; margin-bottom: 0.5rem; font-weight: 600;">AVAILABLE</div>
                            <?php foreach ($masseuse_availability[$date] as $slot): ?>
                                <div style="background: rgba(16, 185, 129, 0.15); border-left: 3px solid #10b981; padding: 0.5rem; margin-bottom: 0.25rem; border-radius: 4px;">
                                    <div style="font-size: 0.85rem; color: #10b981; font-weight: 600;">
                                        <?php echo date('g:i A', strtotime($slot['start'])); ?> - <?php echo date('g:i A', strtotime($slot['end'])); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 1rem; color: #64748b; font-size: 0.85rem;">
                            No availability
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($has_bookings): ?>
                        <!-- Bookings -->
                        <div>
                            <div style="font-size: 0.75rem; color: #94a3b8; margin-bottom: 0.5rem; font-weight: 600;">BOOKINGS</div>
                            <?php foreach ($masseuse_bookings[$date]['details'] as $booking): ?>
                                <div style="background: <?php echo $booking['status'] === 'completed' ? 'rgba(16, 185, 129, 0.1)' : 'rgba(245, 158, 11, 0.1)'; ?>; border-left: 3px solid <?php echo $booking['status'] === 'completed' ? '#10b981' : '#f59e0b'; ?>; padding: 0.5rem; margin-bottom: 0.25rem; border-radius: 4px;">
                                    <div style="font-size: 0.8rem; font-weight: 600; color: <?php echo $booking['status'] === 'completed' ? '#10b981' : '#f59e0b'; ?>;">
                                        <?php echo $booking['time']; ?>
                                    </div>
                                    <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.25rem;">
                                        <?php echo htmlspecialchars($booking['service']); ?>
                                    </div>
                                    <div style="font-size: 0.7rem; color: #64748b; margin-top: 0.25rem; text-transform: uppercase;">
                                        <?php echo $booking['status']; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endfor; ?>
        </div>
    </div>
</div>

<?php endforeach; ?>

<!-- Add/Edit Modal -->
<div id="masseuseModal" class="modal">
    <div class="modal-content glass-card">
        <h2 id="modalTitle">Add Masseuse</h2>
        <form method="POST" id="masseuseForm">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="masseuseId">
            
            <div class="form-group">
                <label>Name</label>
                <input type="text" name="name" id="masseuseName" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" id="masseuseUsername" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Mobile</label>
                <input type="text" name="mobile" id="masseuseMobile" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Bio</label>
                <textarea name="bio" id="masseuseBio" class="form-control" rows="3"></textarea>
            </div>
            <div class="form-group">
                <label>Specialties</label>
                <input type="text" name="specialties" id="masseuseSpecialties" class="form-control">
            </div>
            <div class="form-group">
                <label>Image URL</label>
                <input type="text" name="image_url" id="masseuseImage" class="form-control">
            </div>
            <div class="form-group" id="passwordGroup">
                <label>Password <span id="passwordOptional" style="color: #94a3b8; font-size: 0.85em;">(leave blank to keep current)</span></label>
                <input type="password" name="password" id="masseusePassword" class="form-control">
            </div>
            <div class="form-group" id="passwordConfirmGroup">
                <label>Confirm Password</label>
                <input type="password" name="password_confirm" id="masseusePasswordConfirm" class="form-control">
            </div>
            <div style="display: flex; gap: 1rem;">
                <button type="submit" class="btn btn-primary" style="flex: 1;">Save</button>
                <button type="button" onclick="closeModal()" class="btn btn-outline" style="flex: 1;">Cancel</button>
            </div>
        </form>
    </div>
</div>



<script>
    function openAddModal() {
        document.getElementById('modalTitle').textContent = 'Add Masseuse';
        document.getElementById('formAction').value = 'add';
        document.getElementById('masseuseForm').reset();
        document.getElementById('passwordOptional').style.display = 'none';
        document.getElementById('masseusePassword').required = true;
        document.getElementById('masseusePasswordConfirm').required = true;
        document.getElementById('masseuseModal').style.display = 'block';
    }

    function openEditModal(masseuse) {
        document.getElementById('modalTitle').textContent = 'Edit Masseuse';
        document.getElementById('formAction').value = 'edit';
        document.getElementById('masseuseId').value = masseuse.id;
        document.getElementById('masseuseName').value = masseuse.name;
        document.getElementById('masseuseUsername').value = masseuse.username || '';
        document.getElementById('masseuseMobile').value = masseuse.mobile || '';
        document.getElementById('masseuseBio').value = masseuse.bio;
        document.getElementById('masseuseSpecialties').value = masseuse.specialties;
        document.getElementById('masseuseImage').value = masseuse.image_url || '';
        document.getElementById('masseusePassword').value = '';
        document.getElementById('masseusePasswordConfirm').value = '';
        document.getElementById('passwordOptional').style.display = 'inline';
        document.getElementById('masseusePassword').required = false;
        document.getElementById('masseusePasswordConfirm').required = false;
        document.getElementById('masseuseModal').style.display = 'block';
    }

    function closeModal() {
        document.getElementById('masseuseModal').style.display = 'none';
    }


    // AJAX function to load monthly timeline
    async function loadMonthlyTimeline(month, year) {
        const container = document.getElementById('monthlyTimelineContainer');
        
        // Add loading state
        container.style.opacity = '0.5';
        container.style.pointerEvents = 'none';
        
        try {
            const response = await fetch(`masseuses.php?ajax=monthly_timeline&timeline_month=${month}&timeline_year=${year}`);
            
            // Check if session expired (redirect to login)
            if (response.redirected && response.url.includes('login.php')) {
                window.location.href = '../login.php?timeout=1';
                return;
            }
            
            const html = await response.text();
            
            // Extract just the monthly timeline section from the response
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newContent = doc.getElementById('monthlyTimelineContainer');
            
            if (newContent) {
                container.innerHTML = newContent.innerHTML;
                container.style.opacity = '1';
                container.style.pointerEvents = 'auto';
                
                // Update URL without page reload
                const newUrl = `?timeline_month=${month}&timeline_year=${year}#timeline`;
                window.history.pushState({timeline_month: month, timeline_year: year}, '', newUrl);
                
                // Scroll to timeline
                container.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } else {
                throw new Error('Failed to load timeline');
            }
        } catch (error) {
            console.error('Error loading monthly timeline:', error);
            container.style.opacity = '1';
            container.style.pointerEvents = 'auto';
            alert('Failed to load timeline. Please try again.');
        }
    }

    // AJAX function to load individual masseuse timelines
    async function loadIndividualTimelines(month, year) {
        const cards = document.querySelectorAll('.masseuse-timeline-card');
        
        // Add loading state to all cards
        cards.forEach(card => {
            card.style.opacity = '0.5';
            card.style.pointerEvents = 'none';
        });
        
        try {
            const response = await fetch(`masseuses.php?ajax=individual_timelines&timeline_month=${month}&timeline_year=${year}`);
            
            // Check if session expired (redirect to login)
            if (response.redirected && response.url.includes('login.php')) {
                window.location.href = '../login.php?timeout=1';
                return;
            }
            
            const html = await response.text();
            
            // Extract individual timeline sections from the response
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newCards = doc.querySelectorAll('.masseuse-timeline-card');
            
            if (newCards.length > 0) {
                // Replace each card with its updated version
                cards.forEach((card, index) => {
                    if (newCards[index]) {
                        card.innerHTML = newCards[index].innerHTML;
                        card.style.opacity = '1';
                        card.style.pointerEvents = 'auto';
                    }
                });
                
                // Update URL without page reload
                const newUrl = `?timeline_month=${month}&timeline_year=${year}`;
                window.history.pushState({timeline_month: month, timeline_year: year}, '', newUrl);
            } else {
                throw new Error('Failed to load individual timelines');
            }
        } catch (error) {
            console.error('Error loading individual timelines:', error);
            cards.forEach(card => {
                card.style.opacity = '1';
                card.style.pointerEvents = 'auto';
            });
            alert('Failed to load timelines. Please try again.');
        }
    }


</script>

<?php require_once 'includes/footer.php'; ?>
