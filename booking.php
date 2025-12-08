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
        $sql = "INSERT INTO bookings (user_id, service_id, masseuse_id, booking_date, booking_time) VALUES ($user_id, $service_id, $masseuse_id, '$date', '$time')";
        
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
                    <?php if ($is_editing): ?>
                        <input type="hidden" name="edit_booking_id" value="<?php echo $edit_id; ?>">
                    <?php endif; ?>
                </div>

                <!-- Right Side: Calendar -->
                <div class="calendar-section">
                    <div class="control-group">
                        <label><span class="step-number">3</span> Select Date</label>
                    </div>
                    <div class="calendar-widget">
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
    
    try {
        const response = await fetch(`api/get_monthly_availability.php?masseuse_id=${masseuseId}&year=${year}&month=${month}`);
        const data = await response.json();
        
        if (data.availability) {
            monthlyAvailability = data.availability;
            renderCalendar();
        }
    } catch (error) {
        console.error('Error loading monthly availability:', error);
    }
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
    
    // Show popover
    document.getElementById('confirmationPopover').style.display = 'flex';
}

// Close confirmation popover
function closeConfirmationPopover() {
    document.getElementById('confirmationPopover').style.display = 'none';
}

// Submit booking
function submitBooking() {
    document.getElementById('bookingForm').submit();
}

// Handle confirm button click
confirmBtn.addEventListener('click', function(e) {
    if (this.disabled) {
        e.preventDefault();
        showValidationPopover();
    } else {
        // Show confirmation popover
        e.preventDefault();
        showConfirmationPopover();
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
    
    loadMonthlyAvailability();
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
