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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $service_id = (int)$_POST['service_id'];
    $masseuse_id = (int)$_POST['masseuse_id'];
    $date = $_POST['date'];
    $time = $_POST['time'];

    $sql = "INSERT INTO bookings (user_id, service_id, masseuse_id, booking_date, booking_time) VALUES ($user_id, $service_id, $masseuse_id, '$date', '$time')";
    
    if ($conn->query($sql)) {
        $message = "Booking confirmed successfully!";
    } else {
        $message = "Error: " . $conn->error;
    }
}
?>

<div class="booking-container">
    <div class="booking-card">
        <h1 class="booking-title">Your Booking</h1>
        
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
                </div>

                <!-- Right Side: Calendar -->
                <div class="calendar-section">
                    <div class="control-group">
                        <label><span class="step-number">3</span> Select Date</label>
                    </div>
                    <div class="calendar-widget">
                        <div class="calendar-header">
                            <h3 class="calendar-title" id="calendarTitle">Calendar</h3>
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
                <button type="button" class="btn-confirm" id="confirmBtn" disabled>Confirm Booking</button>
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
renderCalendar();
</script>

<?php require_once 'includes/footer.php'; ?>
