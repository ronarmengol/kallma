<?php
require_once 'includes/header.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$services = getServices($conn);
$masseuses = getMasseuses($conn);

$selected_service_id = isset($_GET['service_id']) ? (int)$_GET['service_id'] : null;
$message = '';

// Edit Mode Logic
$edit_id = isset($_GET['edit_id']) ? (int)$_GET['edit_id'] : null;
$is_editing = false;
$edit_data = null;

if ($edit_id) {
    // Fetch booking details
    $stmt = $conn->prepare("SELECT * FROM bookings WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $edit_data = $result->fetch_assoc();
        
        // Fetch customer name if editing
        $customer_name_display = '';
        if (isAdmin()) {
            $customer_id = $edit_data['user_id'];
            $cust_res = $conn->query("SELECT name FROM users WHERE id=$customer_id");
            if ($cust_res && $cust_row = $cust_res->fetch_assoc()) {
                $customer_name_display = ' for ' . htmlspecialchars($cust_row['name']);
            }
        }
        
        // Permission check: Admin or Booking Owner
        if (isAdmin() || $edit_data['user_id'] == $_SESSION['user_id']) {
            $is_editing = true;
            $selected_service_id = $edit_data['service_id'];
        } else {
            // Unauthorized
            redirect('index.php');
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $service_id = (int)$_POST['service_id'];
    $masseuse_id = (int)$_POST['masseuse_id'];
    $date = sanitize($conn, $_POST['date']);
    $time = sanitize($conn, $_POST['time']);

    if (isset($_POST['edit_booking_id'])) {
        // Update existing booking
        $update_id = (int)$_POST['edit_booking_id'];
        
        // Validation (ensure user still has permission)
        $check_sql = isAdmin() ? "SELECT id FROM bookings WHERE id=$update_id" : "SELECT id FROM bookings WHERE id=$update_id AND user_id=" . $_SESSION['user_id'];
        if ($conn->query($check_sql)->num_rows > 0) {
            $sql = "UPDATE bookings SET service_id=$service_id, masseuse_id=$masseuse_id, booking_date='$date', booking_time='$time' WHERE id=$update_id";
            if ($conn->query($sql)) {
                $message = "Booking updated successfully!";
                // Refresh data
                $edit_data['service_id'] = $service_id;
                $edit_data['masseuse_id'] = $masseuse_id;
                $edit_data['booking_date'] = $date;
                $edit_data['booking_time'] = $time;
                
                // Redirect back to admin if admin
                if (isAdmin()) {
                     // Adding a small delay or link could be nice, but redirect is requested "populate... in preparation". 
                     // Actually user said "go back to booking page and populate...".
                     // So we stay here.
                }
            } else {
                $message = "Error updating booking: " . $conn->error;
            }
        } else {
            $message = "Unauthorized update attempt.";
        }
    } else {
        // Create new booking
        $user_id = $_SESSION['user_id'];
        
        // Get walk-in client data if provided (for admin/masseuse)
        $walk_in_name = isset($_POST['walk_in_name']) ? sanitize($conn, $_POST['walk_in_name']) : null;
        $walk_in_mobile = isset($_POST['walk_in_mobile']) ? sanitize($conn, $_POST['walk_in_mobile']) : null;
        
        if ($walk_in_name && $walk_in_mobile) {
            $sql = "INSERT INTO bookings (user_id, service_id, masseuse_id, booking_date, booking_time, walk_in_client_name, walk_in_client_mobile) 
                    VALUES ($user_id, $service_id, $masseuse_id, '$date', '$time', '$walk_in_name', '$walk_in_mobile')";
        } else {
            $sql = "INSERT INTO bookings (user_id, service_id, masseuse_id, booking_date, booking_time) 
                    VALUES ($user_id, $service_id, $masseuse_id, '$date', '$time')";
        }
        
        if ($conn->query($sql)) {
            $message = "Booking confirmed successfully!";
        } else {
            $message = "Error: " . $conn->error;
        }
    }
}
?>

<div class="booking-container">
    <div class="booking-card">
        <h1 class="booking-title"><?php echo $is_editing ? 'Edit Booking #' . $edit_id . $customer_name_display : 'Your Booking'; ?></h1>
        
        <?php if ($message): ?>
            <div class="booking-success">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="bookingForm">
            <div class="booking-layout">
                <!-- Left Side: Controls and Time Slots -->
                <div class="booking-controls">
                    <div class="control-group">
                        <label for="service_id"><span class="step-number">1</span> Service / Specialist</label>
                        <select name="service_id" id="service_id" required>
                            <option value="">-- Select Service --</option>
                            <?php foreach ($services as $service): ?>
                                <option value="<?php echo $service['id']; ?>" <?php echo ($selected_service_id == $service['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($service['name']); ?> (K<?php echo $service['price']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="control-group">
                        <label for="masseuse_id"><span class="step-number">2</span> Select Masseuse</label>
                        <select name="masseuse_id" id="masseuse_id" required>
                            <option value="">-- Choose Masseuse --</option>
                            <?php foreach ($masseuses as $masseuse): ?>
                                <option value="<?php echo $masseuse['id']; ?>">
                                    <?php echo htmlspecialchars($masseuse['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Time Slots Section -->
                    <div class="time-slots-container" id="timeSlotsContainer" style="display: none;">
                        <h3 class="time-slots-title"><span class="step-number">4</span> Available Time Slots</h3>
                        <!-- Loader -->
                        <div id="slotsLoader" class="loader-container">
                            <div class="loader"></div>
                        </div>
                        <div class="time-slots-grid" id="timeSlotsGrid">
                            <!-- Time slots will be populated here -->
                        </div>
                    </div>

                    <input type="hidden" name="date" id="selectedDate">
                    <input type="hidden" name="time" id="selectedTime">
                    <?php if (isAdmin() || isMasseuse()): ?>
                    <input type="hidden" name="walk_in_name" id="walkInNameHidden">
                    <input type="hidden" name="walk_in_mobile" id="walkInMobileHidden">
                    <?php endif; ?>
                    <?php if ($is_editing): ?>
                        <input type="hidden" name="edit_booking_id" value="<?php echo $edit_id; ?>">
                    <?php endif; ?>
                </div>

                <!-- Right Side: Calendar -->
                <div class="calendar-section">
                    <div class="control-group">
                        <label><span class="step-number">3</span> Select Date</label>
                    </div>
                    <div class="calendar-widget" style="position: relative;">
                        <!-- Calendar Loader -->
                        <div id="calendarLoader" class="calendar-loader" style="display: none;">
                            <div class="loader"></div>
                            <p style="color: #94a3b8; margin-top: 1rem; font-size: 0.9rem;">Loading availability...</p>
                        </div>
                        
                        <!-- No Availability Message -->
                        <div id="noAvailabilityMessage" class="no-availability-message" style="display: none;">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" style="margin-bottom: 1rem;">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="8" x2="12" y2="12"></line>
                                <line x1="12" y1="16" x2="12.01" y2="16"></line>
                            </svg>
                            <h3 style="color: #f59e0b; margin-bottom: 0.5rem;">No Availability</h3>
                            <p style="color: #94a3b8; text-align: center;" id="noAvailabilityText">
                                My apologies, <span id="masseuseName"></span> is not available for the next 10 days.
                            </p>
                        </div>
                        
                        <!-- Select Masseuse Prompt -->
                        <div id="selectMasseusePrompt" class="select-masseuse-prompt">
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2" style="margin-bottom: 0.75rem; opacity: 0.5;">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                            <p style="color: #64748b; text-align: center; font-size: 0.95rem; margin: 0;">
                                Please select a masseuse first
                            </p>
                        </div>
                        
                        <div class="calendar-header">
                            <div>
                                <h3 class="calendar-title" id="calendarTitle">Calendar</h3>
                                <div id="currentTimeDisplay" style="font-size: 0.85rem; color: #64748b; font-weight: normal; margin-top: 4px;"></div>
                            </div>
                            <div class="calendar-nav">
                                <button type="button" id="prevMonth">‹</button>
                                <button type="button" id="nextMonth">›</button>
                            </div>
                        </div>
                        <div class="calendar-grid" id="calendarGrid">
                            <!-- Calendar will be populated here -->
                        </div>
                        
                        <!-- Calendar Legend -->
                        <div class="calendar-legend" style="margin-top: 1rem; padding: 1rem; background: rgba(15, 23, 42, 0.4); border-radius: 12px; border: 1px solid rgba(255, 255, 255, 0.05);">
                            <div style="display: flex; flex-wrap: wrap; gap: 1rem; justify-content: center; align-items: center;">
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <div style="width: 24px; height: 24px; border: 2px solid #06b6d4; border-radius: 6px; background: rgba(6, 182, 212, 0.15); box-shadow: 0 0 8px rgba(6, 182, 212, 0.4);"></div>
                                    <span style="color: #94a3b8; font-size: 0.85rem;">Today</span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <div style="width: 24px; height: 24px; border: 1px solid rgba(16, 185, 129, 0.8); border-radius: 6px; background: rgba(16, 185, 129, 0.2); box-shadow: 0 0 8px rgba(16, 185, 129, 0.3);"></div>
                                    <span style="color: #94a3b8; font-size: 0.85rem;">Available</span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <div style="width: 24px; height: 24px; border: 1px solid rgba(245, 158, 11, 0.5); border-radius: 6px; background: rgba(245, 158, 11, 0.15); box-shadow: 0 0 8px rgba(245, 158, 11, 0.3);"></div>
                                    <span style="color: #94a3b8; font-size: 0.85rem;">Fully Booked</span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <div style="width: 24px; height: 24px; border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 6px; background: rgba(255, 255, 255, 0.02); color: rgba(255, 255, 255, 0.2); display: flex; align-items: center; justify-content: center; font-size: 0.7rem;">—</div>
                                    <span style="color: #94a3b8; font-size: 0.85rem;">Unavailable</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="booking-actions">
                <button type="button" class="btn-confirm" id="confirmBtn" disabled><?php echo $is_editing ? 'Update Booking' : 'Confirm Booking'; ?></button>
                <button type="button" class="btn-reset" id="resetBtn">Reset</button>
                <button type="button" class="btn-cancel" onclick="window.location.href='index.php'">Cancel</button>
            </div>
        </form>

        <!-- Validation Popover -->
        <div id="validationPopover" class="validation-popover" style="display: none;">
            <div class="popover-content">
                <h3>Please Complete All Steps</h3>
                <ul id="validationList"></ul>
                <button type="button" class="btn-primary" onclick="closeValidationPopover()">Got it!</button>
            </div>
        </div>

        <!-- Confirmation Popover -->
        <div id="confirmationPopover" class="validation-popover" style="display: none;">
            <div class="popover-content confirmation-popover">
                <h3>Confirm Your Booking</h3>
                <div class="booking-summary">
                    <?php if (isAdmin() || isMasseuse()): ?>
                    <div class="summary-item" id="confirmClientNameItem" style="display: none;">
                        <span class="summary-label">Client Name:</span>
                        <span class="summary-value" id="confirmClientName"></span>
                    </div>
                    <div class="summary-item" id="confirmClientMobileItem" style="display: none;">
                        <span class="summary-label">Client Mobile:</span>
                        <span class="summary-value" id="confirmClientMobile"></span>
                    </div>
                    <?php endif; ?>
                    <div class="summary-item">
                        <span class="summary-label">Service:</span>
                        <span class="summary-value" id="confirmService"></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Masseuse:</span>
                        <span class="summary-value" id="confirmMasseuse"></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Date:</span>
                        <span class="summary-value" id="confirmDate"></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Time:</span>
                        <span class="summary-value" id="confirmTime"></span>
                    </div>
                </div>
                <div class="confirmation-actions">
                    <button type="button" class="btn-primary" onclick="submitBooking()">Confirm Booking</button>
                    <button type="button" class="btn-cancel" onclick="closeConfirmationPopover()">Cancel</button>
                </div>
            </div>
        </div>

        <?php if (isAdmin() || isMasseuse()): ?>
        <!-- Walk-in Client Dialog -->
        <div id="walkInDialog" class="validation-popover" style="display: none;">
            <div class="popover-content walk-in-dialog">
                <div style="text-align: center; margin-bottom: 1.5rem;">
                    <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" style="margin-bottom: 1rem;">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    <h3 style="color: #10b981; margin-bottom: 0.5rem;">Walk-in Client</h3>
                    <p style="color: #94a3b8; font-size: 0.9rem; margin: 0;">Please enter client details for reference</p>
                </div>
                
                <form id="walkInForm" onsubmit="handleWalkInSubmit(event)">
                    <div class="form-group">
                        <label style="color: #cbd5e1; margin-bottom: 0.5rem; display: block;">Client Name *</label>
                        <input 
                            type="text" 
                            id="walkInName" 
                            name="walk_in_name"
                            placeholder="Enter client name" 
                            required 
                            style="width: 100%; padding: 0.75rem; background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 8px; color: #fff; font-size: 1rem;"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label style="color: #cbd5e1; margin-bottom: 0.5rem; display: block;">Mobile Number *</label>
                        <input 
                            type="tel" 
                            id="walkInMobile" 
                            name="walk_in_mobile"
                            placeholder="Enter mobile number" 
                            required 
                            pattern="[0-9\s\-\(\)\+]{10,20}"
                            title="Please enter a valid phone number (10-20 characters, can include spaces, dashes, parentheses)"
                            style="width: 100%; padding: 0.75rem; background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 8px; color: #fff; font-size: 1rem;"
                        >
                        <small style="color: #64748b; font-size: 0.85rem; margin-top: 0.25rem; display: block;">For reference purposes only</small>
                    </div>
                    
                    <div class="confirmation-actions" style="margin-top: 1.5rem;">
                        <button type="submit" class="btn-primary" style="flex: 1;">Continue to Booking</button>
                        <button type="button" class="btn-cancel" onclick="closeWalkInDialog()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<script>
// Calendar and Booking Logic
const calendarGrid = document.getElementById('calendarGrid');
const calendarTitle = document.getElementById('calendarTitle');
const prevMonthBtn = document.getElementById('prevMonth');
const nextMonthBtn = document.getElementById('nextMonth');
const masseuseSelect = document.getElementById('masseuse_id');
const timeSlotsContainer = document.getElementById('timeSlotsContainer');
const timeSlotsGrid = document.getElementById('timeSlotsGrid');
const selectedDateInput = document.getElementById('selectedDate');
const selectedTimeInput = document.getElementById('selectedTime');
const confirmBtn = document.getElementById('confirmBtn');

let currentDate = new Date();
let selectedDate = null;
let selectedTime = null;
let monthlyAvailability = {};

const monthNames = ["January", "February", "March", "April", "May", "June",
    "July", "August", "September", "October", "November", "December"
];

const dayNames = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];

// Initialize calendar
function renderCalendar() {
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();
    
    calendarTitle.textContent = `${monthNames[month]} ${year}`;
    
    // Clear grid
    calendarGrid.innerHTML = '';
    
    // Add day headers
    dayNames.forEach(day => {
        const dayHeader = document.createElement('div');
        dayHeader.className = 'calendar-day-header';
        dayHeader.textContent = day;
        calendarGrid.appendChild(dayHeader);
    });
    
    // Get first day of month and number of days
    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    
    // Normalize today to start of day for accurate comparison
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const todayTimestamp = today.getTime();
    
    // Add empty cells for days before month starts
    for (let i = 0; i < firstDay; i++) {
        const emptyDay = document.createElement('div');
        emptyDay.className = 'calendar-day empty';
        calendarGrid.appendChild(emptyDay);
    }
    
    // Add days of month
    for (let day = 1; day <= daysInMonth; day++) {
        const dayCell = document.createElement('div');
        dayCell.className = 'calendar-day';
        dayCell.textContent = day;
        
        const cellDate = new Date(year, month, day);
        // cellDate is already at 00:00:00 local time by default
        
        // Check if it's today
        // We compare timestamps to be safe
        if (cellDate.getTime() === todayTimestamp) {
            dayCell.classList.add('today');
        }
        
        // Check if date is in the past
        if (cellDate.getTime() < todayTimestamp) {
            dayCell.classList.add('disabled');
        } else {
            // Add availability class if we have data
            if (monthlyAvailability[day]) {
                dayCell.classList.add(monthlyAvailability[day]);
            }
            
            // Add click handler
            dayCell.addEventListener('click', () => selectDate(cellDate, dayCell));
        }
        
        calendarGrid.appendChild(dayCell);
    }
}

// Format date as YYYY-MM-DD
function formatDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

// Select a date
function selectDate(date, dayCell) {
    // Remove previous selection
    document.querySelectorAll('.calendar-day.selected').forEach(el => {
        el.classList.remove('selected');
    });
    
    // Add selection to clicked day
    dayCell.classList.add('selected');
    selectedDate = date;
    selectedDateInput.value = formatDate(date);
    
    // Reset time selection
    selectedTime = null;
    selectedTimeInput.value = '';
    updateConfirmButton(); // Disable confirm button until new time picked
    
    // Load time slots for this date
    loadTimeSlots();
}

// Load monthly availability
async function loadMonthlyAvailability() {
    const masseuseId = masseuseSelect.value;
    if (!masseuseId) return;
    
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth() + 1;
    
    // Show loader
    const loader = document.getElementById('calendarLoader');
    const noAvailMsg = document.getElementById('noAvailabilityMessage');
    if (loader) loader.style.display = 'flex';
    if (noAvailMsg) noAvailMsg.style.display = 'none';
    
    try {
        const response = await fetch(`api/get_monthly_availability.php?masseuse_id=${masseuseId}&year=${year}&month=${month}`);
        
        // Check if session expired (redirect to login)
        if (response.redirected && response.url.includes('login.php')) {
            window.location.href = 'login.php?timeout=1';
            return;
        }
        
        const data = await response.json();
        
        if (data.availability) {
            monthlyAvailability = data.availability;
            
            // Check if masseuse has any availability in the next 10 days
            const hasAvailability = checkAvailabilityNext10Days();
            
            if (!hasAvailability) {
                // Get masseuse name
                const masseuseSelect = document.getElementById('masseuse_id');
                const masseuseName = masseuseSelect.options[masseuseSelect.selectedIndex].text;
                document.getElementById('masseuseName').textContent = masseuseName;
                
                // Show no availability message
                if (noAvailMsg) noAvailMsg.style.display = 'flex';
            } else {
                // Hide no availability message and render calendar
                if (noAvailMsg) noAvailMsg.style.display = 'none';
            }
            
            renderCalendar();
        }
    } catch (error) {
        console.error('Error loading monthly availability:', error);
    } finally {
        // Hide loader
        if (loader) loader.style.display = 'none';
    }
}

// Check if masseuse has availability in the next 10 days
function checkAvailabilityNext10Days() {
    // If monthlyAvailability is empty, no availability
    if (!monthlyAvailability || Object.keys(monthlyAvailability).length === 0) {
        return false;
    }
    
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    for (let i = 0; i < 10; i++) {
        const checkDate = new Date(today);
        checkDate.setDate(today.getDate() + i);
        const day = checkDate.getDate();
        
        // Check if this day has availability (available or partial)
        const dayStatus = monthlyAvailability[day];
        if (dayStatus === 'available' || dayStatus === 'partial') {
            return true;
        }
    }
    
    return false;
}

// Load time slots for selected date
async function loadTimeSlots() {
    const masseuseId = masseuseSelect.value;
    const date = selectedDateInput.value;
    
    if (!masseuseId || !date) return;
    
    // Show container and loader
    timeSlotsContainer.style.display = 'block';
    timeSlotsGrid.innerHTML = '';
    const loader = document.getElementById('slotsLoader');
    if (loader) loader.style.display = 'flex';
    
    try {
        const response = await fetch(`api/get_availability.php?masseuse_id=${masseuseId}&date=${date}`);
        
        // Check if session expired (redirect to login)
        if (response.redirected && response.url.includes('login.php')) {
            window.location.href = 'login.php?timeout=1';
            return;
        }
        
        const data = await response.json();
        
        // Hide loader
        if (loader) loader.style.display = 'none';
        
        timeSlotsGrid.innerHTML = '';
        
        if (data.slots && data.slots.length > 0) {
            data.slots.forEach(slot => {
                const timeSlot = document.createElement('div');
                timeSlot.className = `time-slot ${slot.status}`;
                timeSlot.textContent = slot.time;
                
                if (slot.status === 'available') {
                    timeSlot.addEventListener('click', () => selectTimeSlot(slot.time, timeSlot));
                }
                
                timeSlotsGrid.appendChild(timeSlot);
            });
            
            // Ensure container is still visible (it might have been hidden on error before)
            timeSlotsContainer.style.display = 'block';
        } else {
            timeSlotsGrid.innerHTML = '<p style="color: #94a3b8; text-align: center; padding: 1rem;">No slots available</p>';
            timeSlotsContainer.style.display = 'block';
        }
    } catch (error) {
        console.error('Error loading time slots:', error);
        // Hide loader
        if (loader) loader.style.display = 'none';
        // Show error message
        timeSlotsGrid.innerHTML = '<p style="color: #ef4444; text-align: center; padding: 1rem;">Error loading slots. Please try again.</p>';
    }
}

// Select time slot
function selectTimeSlot(time, slotEl) {
    // Remove previous selection
    document.querySelectorAll('.time-slot.selected').forEach(el => {
        el.classList.remove('selected');
    });
    
    // Add selection
    slotEl.classList.add('selected');
    selectedTime = time;
    selectedTimeInput.value = time;
    
    // Enable confirm button
    updateConfirmButton();
}

// Update confirm button state
function updateConfirmButton() {
    const serviceId = document.getElementById('service_id').value;
    const masseuseId = masseuseSelect.value;
    const date = selectedDateInput.value;
    const time = selectedTimeInput.value;
    
    confirmBtn.disabled = !(serviceId && masseuseId && date && time);
}

// Show validation popover
function showValidationPopover() {
    const serviceId = document.getElementById('service_id').value;
    const masseuseId = masseuseSelect.value;
    const date = selectedDateInput.value;
    const time = selectedTimeInput.value;
    
    const missingSteps = [];
    
    if (!serviceId) missingSteps.push('Step 1: Select a service');
    if (!masseuseId) missingSteps.push('Step 2: Choose a masseuse');
    if (!date) missingSteps.push('Step 3: Pick a date from the calendar');
    if (!time) missingSteps.push('Step 4: Select an available time slot');
    
    if (missingSteps.length > 0) {
        const validationList = document.getElementById('validationList');
        validationList.innerHTML = '';
        
        missingSteps.forEach(step => {
            const li = document.createElement('li');
            li.textContent = step;
            validationList.appendChild(li);
        });
        
        document.getElementById('validationPopover').style.display = 'flex';
        return false;
    }
    
    return true;
}

// Close validation popover
function closeValidationPopover() {
    document.getElementById('validationPopover').style.display = 'none';
}

// Show confirmation popover
function showConfirmationPopover() {
    const serviceSelect = document.getElementById('service_id');
    const serviceName = serviceSelect.options[serviceSelect.selectedIndex].text;
    const masseuseName = masseuseSelect.options[masseuseSelect.selectedIndex].text;
    const date = selectedDateInput.value;
    const time = selectedTimeInput.value;
    
    // Format date for display
    const dateObj = new Date(date);
    const formattedDate = dateObj.toLocaleDateString('en-US', { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    });
    
    // Populate confirmation details
    document.getElementById('confirmService').textContent = serviceName;
    document.getElementById('confirmMasseuse').textContent = masseuseName;
    document.getElementById('confirmDate').textContent = formattedDate;
    document.getElementById('confirmTime').textContent = time;
    
    <?php if (isAdmin() || isMasseuse()): ?>
    // Populate walk-in client details if available
    if (walkInClientData) {
        document.getElementById('confirmClientName').textContent = walkInClientData.name;
        document.getElementById('confirmClientMobile').textContent = walkInClientData.mobile;
        document.getElementById('confirmClientNameItem').style.display = 'flex';
        document.getElementById('confirmClientMobileItem').style.display = 'flex';
    }
    <?php endif; ?>
    
    // Show popover
    document.getElementById('confirmationPopover').style.display = 'flex';
}

// Close confirmation popover
function closeConfirmationPopover() {
    document.getElementById('confirmationPopover').style.display = 'none';
}

// Submit booking
function submitBooking() {
    <?php if (isAdmin() || isMasseuse()): ?>
    // Populate hidden fields with walk-in client data
    if (walkInClientData) {
        document.getElementById('walkInNameHidden').value = walkInClientData.name;
        document.getElementById('walkInMobileHidden').value = walkInClientData.mobile;
    }
    <?php endif; ?>
    
    document.getElementById('bookingForm').submit();
}

<?php if (isAdmin() || isMasseuse()): ?>
// Walk-in client dialog functions
let walkInClientData = null;

function showWalkInDialog() {
    document.getElementById('walkInDialog').style.display = 'flex';
    document.getElementById('walkInName').value = '';
    document.getElementById('walkInMobile').value = '';
}

function closeWalkInDialog() {
    document.getElementById('walkInDialog').style.display = 'none';
}

function handleWalkInSubmit(event) {
    event.preventDefault();
    
    // Store walk-in client data
    walkInClientData = {
        name: document.getElementById('walkInName').value,
        mobile: document.getElementById('walkInMobile').value
    };
    
    // Close walk-in dialog
    closeWalkInDialog();
    
    // Show confirmation popover
    showConfirmationPopover();
}
<?php endif; ?>

// Handle confirm button click
confirmBtn.addEventListener('click', function(e) {
    if (this.disabled) {
        e.preventDefault();
        showValidationPopover();
    } else {
        e.preventDefault();
        <?php if (isAdmin() || isMasseuse()): ?>
        // For admin/masseuse, show walk-in dialog first
        showWalkInDialog();
        <?php else: ?>
        // For customers, show confirmation directly
        showConfirmationPopover();
        <?php endif; ?>
    }
});

// Event listeners
prevMonthBtn.addEventListener('click', () => {
    currentDate.setMonth(currentDate.getMonth() - 1);
    renderCalendar();
    loadMonthlyAvailability();
});

nextMonthBtn.addEventListener('click', () => {
    currentDate.setMonth(currentDate.getMonth() + 1);
    renderCalendar();
    loadMonthlyAvailability();
});

masseuseSelect.addEventListener('change', () => {
    selectedDate = null;
    selectedTime = null;
    selectedDateInput.value = '';
    selectedTimeInput.value = '';
    timeSlotsContainer.style.display = 'none';
    
    // Remove selections
    document.querySelectorAll('.calendar-day.selected').forEach(el => {
        el.classList.remove('selected');
    });
    
    // Hide the select masseuse prompt when a masseuse is selected
    const prompt = document.getElementById('selectMasseusePrompt');
    if (masseuseSelect.value) {
        if (prompt) prompt.style.display = 'none';
        loadMonthlyAvailability();
    } else {
        if (prompt) prompt.style.display = 'flex';
    }
    
    updateConfirmButton();
});

document.getElementById('service_id').addEventListener('change', updateConfirmButton);

// Reset button functionality
document.getElementById('resetBtn').addEventListener('click', function() {
    // Reset form fields
    document.getElementById('service_id').value = '';
    masseuseSelect.value = '';
    selectedDateInput.value = '';
    selectedTimeInput.value = '';
    
    // Clear selections
    selectedDate = null;
    selectedTime = null;
    monthlyAvailability = {};
    
    // Remove visual selections
    document.querySelectorAll('.calendar-day.selected').forEach(el => {
        el.classList.remove('selected');
    });
    
    document.querySelectorAll('.time-slot.selected').forEach(el => {
        el.classList.remove('selected');
    });
    
    // Hide time slots
    timeSlotsContainer.style.display = 'none';
    timeSlotsGrid.innerHTML = '';
    
    // Show select masseuse prompt
    const prompt = document.getElementById('selectMasseusePrompt');
    if (prompt) prompt.style.display = 'flex';
    
    // Re-render calendar
    currentDate = new Date();
    renderCalendar();
    
    // Update button state
    updateConfirmButton();
});

// Initial render
// Initial render
renderCalendar();

// Dynamic Time Display
function updateCurrentTime() {
    const now = new Date();
    // Format: "Mon, Dec 8 - 14:30"
    const options = { 
        weekday: 'short', 
        month: 'short', 
        day: 'numeric',
        hour: '2-digit', 
        minute: '2-digit',
        hour12: false // Or true if preferred, but usually 24h is clearer for slots
    };
    const timeString = now.toLocaleTimeString('en-US', options).replace(',', ' -');
    
    // Just time for simpler display if preferred:
    // const timeString = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });

    const display = document.getElementById('currentTimeDisplay');
    if (display) {
        display.textContent = 'Current Time: ' + timeString.split(' - ')[1]; // Showing just time or full date? User said "current time".
        // Let's do full date + time for clarity
        display.textContent = '' + now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
    }
}

// Update immediately and then every second
// Update immediately and then every second
updateCurrentTime();
setInterval(updateCurrentTime, 1000);

// Pre-populate for Edit Mode
<?php if ($is_editing && $edit_data): ?>
    document.addEventListener('DOMContentLoaded', async function() {
        console.log('Initializing Edit Mode...');
        
        // 1. Set Masseuse
        masseuseSelect.value = "<?php echo $edit_data['masseuse_id']; ?>";
        
        // 2. Set Date Context & Render Calendar
        const editDateStr = "<?php echo $edit_data['booking_date']; ?>";
        // Safe parsing: create date as local, avoiding UTC offset issues
        const dateParts = editDateStr.split('-');
        const safeYear = parseInt(dateParts[0]);
        const safeMonth = parseInt(dateParts[1]) - 1; // 0-indexed
        const safeDay = parseInt(dateParts[2]);
        
        const editDate = new Date(safeYear, safeMonth, safeDay);
        // Update global currentDate so calendar renders correct month
        currentDate = new Date(safeYear, safeMonth, 1);
        
        // Load availability for that masseuse/month
        await loadMonthlyAvailability();
        
        // 3. Select the specific date
        // We need to find the element in the now-rendered calendar
        // Since loadMonthlyAvailability calls renderCalendar(), the DOM should be ready after the await
        
        // Find the day element matching our date
        const days = document.querySelectorAll('.calendar-day');
        let targetCell = null;
        
        days.forEach(day => {
            // Check text content AND ensure it's not a filler day from diff month
            // Our standard calendar impl: 'empty' class is for previous month fillers
            // But we might have next month fillers? Logic usually appends them.
            // Simple check: strict text match + not empty + not disabled (unless it's today/past logic)
            // Note: past days have .disabled. Editing a past booking? We might need to allow selection visually.
            // For now, let's assume valid future or present booking, or just force select.
            
            if (parseInt(day.textContent) === safeDay && !day.classList.contains('empty')) {
                targetCell = day;
            }
        });
        
        if (targetCell) {
            targetCell.classList.remove('disabled'); // Force enable just in case it's slight past
            targetCell.click(); // Trigger standard selection logic (sets Input, Global Vars)
            
            // Wait a tiny bit for click handlers if they are async? No, selectDate is sync mostly, but calls loadTimeSlots (async)
            // But we want to call our specialized Exclusion loader.
            
            // Let's manually set values to be safe
            selectedDate = editDate;
            selectedDateInput.value = editDateStr;
            targetCell.classList.add('selected');
            
            // 4. Load Time Slots WITH EXCLUSION
            // We override the click's standard load because we need exclusion
            const excludeId = "<?php echo $edit_data['id']; ?>";
            await loadTimeSlotsWithExclusion(excludeId);
            
            // 5. Select the specific time
            const editTime = "<?php echo substr($edit_data['booking_time'], 0, 5); ?>"; // HH:MM
            
            // We need to find the slot matching exact time string
            // Wait for DOM update from loadTimeSlotsWithExclusion
            // Since we awaited it, DOM should be ready
            
            const slots = document.querySelectorAll('.time-slot');
            let matchedSlot = false;
            
            slots.forEach(slot => {
                if (slot.textContent.trim() === editTime) {
                    slot.classList.remove('booked'); // Ensure it looks valid
                    slot.classList.add('available'); // Force available styling
                    slot.click();
                    matchedSlot = true;
                }
            });
            
            if (!matchedSlot) {
                console.warn('Could not find original time slot:', editTime);
            }
            
            // Re-enable button
            updateConfirmButton();
        } else {
             console.error('Target day cell not found in calendar');
        }
    });

    // Modified load function to support exclusion
    async function loadTimeSlotsWithExclusion(excludeId) {
        const masseuseId = masseuseSelect.value;
        const date = selectedDateInput.value;
        
        if (!masseuseId || !date) return;
        
        // Show container and loader
        timeSlotsContainer.style.display = 'block';
        timeSlotsGrid.innerHTML = '';
        const loader = document.getElementById('slotsLoader');
        if (loader) loader.style.display = 'flex';
        
        try {
            const response = await fetch(`api/get_availability.php?masseuse_id=${masseuseId}&date=${date}&exclude_booking_id=${excludeId}`);
            
            // Check if session expired (redirect to login)
            if (response.redirected && response.url.includes('login.php')) {
                window.location.href = 'login.php?timeout=1';
                return;
            }
            
            const data = await response.json();
            
            // Hide loader
            if (loader) loader.style.display = 'none';
            
            timeSlotsGrid.innerHTML = '';
            
            if (data.slots && data.slots.length > 0) {
                data.slots.forEach(slot => {
                    const timeSlot = document.createElement('div');
                    timeSlot.className = `time-slot ${slot.status}`;
                    timeSlot.textContent = slot.time;
                    
                    if (slot.status === 'available') {
                        timeSlot.addEventListener('click', () => selectTimeSlot(slot.time, timeSlot));
                    }
                    
                    timeSlotsGrid.appendChild(timeSlot);
                });
                
                timeSlotsContainer.style.display = 'block';
            } else {
                timeSlotsGrid.innerHTML = '<p style="color: #94a3b8; text-align: center; padding: 1rem;">No slots available</p>';
                timeSlotsContainer.style.display = 'block';
            }
        } catch (error) {
            console.error('Error loading time slots:', error);
             if (loader) loader.style.display = 'none';
        }
    }
<?php endif; ?>

</script>

<?php require_once 'includes/footer.php'; ?>
