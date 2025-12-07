<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

session_start();

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('../login.php');
}

// Get masseuse ID from query parameter or from logged-in masseuse
$masseuse_id = null;
$masseuse_name = '';

if (isAdmin()) {
    // Admin can access any masseuse's schedule
    $masseuse_id = isset($_GET['masseuse_id']) ? (int)$_GET['masseuse_id'] : null;
    if (!$masseuse_id) {
        // If no masseuse selected, redirect to masseuses page
        redirect('masseuses.php');
    }
} elseif (isMasseuse()) {
    // Masseuse can only access their own schedule
    $masseuse_id = getMasseuseIdByUserId($conn, $_SESSION['user_id']);
    if (!$masseuse_id) {
        die('Error: Masseuse account not properly configured.');
    }
} else {
    // Regular users don't have access
    redirect('../login.php');
}

if ($masseuse_id) {
    $sql = "SELECT name FROM masseuses WHERE id = $masseuse_id";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $masseuse_name = $result->fetch_assoc()['name'];
    }
}
$pageTitle = 'Availability Scheduler - ' . htmlspecialchars($masseuse_name);
require_once 'includes/header.php';
?>

<style>
    /* Scoped styles for scheduler */
    .scheduler-container {
        /* background: transparent; Removed to blend with admin theme or keep if distinct needed */
        padding: 2rem 0;
    }

    .scheduler-header {
        text-align: center;
        margin-bottom: 3rem;
    }

    .scheduler-title {
        font-size: 2.5rem;
        margin-bottom: 0.5rem;
        background: linear-gradient(to right, #fff, #10b981);
        -webkit-background-clip: text;
        background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .scheduler-subtitle {
        color: #94a3b8;
        font-size: 1.1rem;
    }

    .days-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 1.5rem;
        width: 100%; /* Ensure full width */
    }

    .day-card {
        background: rgba(30, 41, 59, 0.6);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(16, 185, 129, 0.2);
        border-radius: 16px;
        padding: 1.5rem;
        transition: all 0.3s ease;
        cursor: pointer;
        position: relative;
        overflow: hidden;
    }

    .day-card:hover {
        transform: translateY(-4px);
        border-color: rgba(16, 185, 129, 0.5);
        box-shadow: 0 8px 24px rgba(16, 185, 129, 0.2);
    }

    .day-card.has-availability {
        border-color: rgba(16, 185, 129, 0.5);
    }

    .day-card.partial-availability {
        border-color: rgba(245, 158, 11, 0.5);
    }

    .day-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .day-name {
        font-size: 1.25rem;
        font-weight: 600;
        color: #10b981;
    }

    .day-date {
        font-size: 0.9rem;
        color: #94a3b8;
    }

    .status-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .status-available {
        background: rgba(16, 185, 129, 0.2);
        color: #10b981;
    }

    .status-partial {
        background: rgba(245, 158, 11, 0.2);
        color: #f59e0b;
    }

    .status-unavailable {
        background: rgba(100, 116, 139, 0.2);
        color: #94a3b8;
    }

    .time-slots-preview {
        font-size: 0.9rem;
        color: #cbd5e1;
        line-height: 1.6;
    }

    .time-slot-item {
        padding: 0.5rem;
        background: rgba(16, 185, 129, 0.1);
        border-radius: 8px;
        margin-bottom: 0.5rem;
        border-left: 3px solid #10b981;
    }

    /* Modal Styles */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        backdrop-filter: blur(4px);
    }

    .modal-content {
        background: rgba(30, 41, 59, 0.95);
        backdrop-filter: blur(20px);
        margin: 3% auto;
        padding: 2.5rem;
        border: 2px solid rgba(16, 185, 129, 0.3);
        border-radius: 24px;
        max-width: 700px;
        max-height: 85vh;
        overflow-y: auto;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .modal-title {
        font-size: 1.75rem;
        color: #10b981;
    }

    .close-btn {
        background: transparent;
        border: none;
        color: #94a3b8;
        font-size: 2rem;
        cursor: pointer;
        transition: color 0.3s ease;
        line-height: 1;
    }

    .close-btn:hover {
        color: #fff;
    }

    .time-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
        gap: 0.75rem;
        margin-bottom: 2rem;
    }

    .time-slot-btn {
        padding: 0.75rem;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 8px;
        color: #94a3b8;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 0.9rem;
    }

    .time-slot-btn:hover {
        background: rgba(16, 185, 129, 0.1);
        border-color: rgba(16, 185, 129, 0.3);
        color: #10b981;
    }

    .time-slot-btn.selected {
        background: linear-gradient(135deg, #10b981, #059669);
        border-color: #10b981;
        color: #fff;
        font-weight: 600;
    }

    .quick-actions {
        display: flex;
        gap: 0.75rem;
        margin-bottom: 2rem;
        flex-wrap: wrap;
    }

    .quick-btn {
        padding: 0.5rem 1rem;
        background: rgba(124, 58, 237, 0.1);
        border: 1px solid rgba(124, 58, 237, 0.3);
        border-radius: 8px;
        color: #a78bfa;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 0.85rem;
    }

    .quick-btn:hover {
        background: rgba(124, 58, 237, 0.2);
        transform: translateY(-2px);
    }

    .selected-slots {
        background: rgba(15, 23, 42, 0.6);
        padding: 1rem;
        border-radius: 12px;
        margin-bottom: 1.5rem;
    }

    .selected-slots-title {
        font-size: 0.9rem;
        color: #94a3b8;
        margin-bottom: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .selected-slot-tag {
        display: inline-block;
        padding: 0.4rem 0.8rem;
        background: rgba(16, 185, 129, 0.2);
        border: 1px solid rgba(16, 185, 129, 0.4);
        border-radius: 6px;
        color: #10b981;
        margin: 0.25rem;
        font-size: 0.85rem;
    }

    .modal-actions {
        display: flex;
        gap: 1rem;
    }

    .btn-save {
        flex: 1;
        padding: 1rem;
        background: linear-gradient(135deg, #10b981, #059669);
        border: none;
        border-radius: 12px;
        color: #fff;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .btn-save:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(16, 185, 129, 0.4);
    }

    .btn-clear {
        padding: 1rem 1.5rem;
        background: transparent;
        border: 2px solid rgba(239, 68, 68, 0.5);
        border-radius: 12px;
        color: #ef4444;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .btn-clear:hover {
        background: rgba(239, 68, 68, 0.1);
        border-color: #ef4444;
    }

    .back-link {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        color: #94a3b8;
        margin-bottom: 2rem;
        transition: color 0.3s ease;
    }

    .back-link:hover {
        color: #10b981;
    }

    @media (max-width: 768px) {
        .days-grid {
            grid-template-columns: 1fr;
        }
        
        .modal-content {
            margin: 10% 1rem;
            padding: 1.5rem;
        }
        
        .time-grid {
            grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
        }
    }
</style>

<div class="scheduler-container">
    <div>
        <a href="masseuses.php" class="back-link">
            <span>←</span> Back to Masseuses
        </a>

        <div class="scheduler-header">
            <h1 class="scheduler-title">10-Day Availability Scheduler</h1>
            <p class="scheduler-subtitle">Set availability for <?php echo htmlspecialchars($masseuse_name); ?></p>
        </div>

        <div class="days-grid" id="daysGrid">
            <!-- Days will be populated by JavaScript -->
        </div>
    </div>
</div>

<!-- Time Slot Modal -->
<div id="timeSlotModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title" id="modalTitle">Set Availability</h2>
            <button class="close-btn" onclick="closeModal()">&times;</button>
        </div>

        <div class="quick-actions">
            <button class="quick-btn" onclick="selectTemplate('full')">Full Day (9 AM - 6 PM)</button>
            <button class="quick-btn" onclick="selectTemplate('morning')">Morning (9 AM - 12 PM)</button>
            <button class="quick-btn" onclick="selectTemplate('afternoon')">Afternoon (1 PM - 6 PM)</button>
        </div>

        <div class="selected-slots" id="selectedSlotsContainer" style="display: none;">
            <div class="selected-slots-title">Selected Time Ranges</div>
            <div id="selectedSlotsTags"></div>
        </div>

        <div class="time-grid" id="timeGrid">
            <!-- Time slots will be populated by JavaScript -->
        </div>

        <div class="modal-actions">
            <button class="btn-save" onclick="saveAvailability()">Save Availability</button>
            <button class="btn-clear" onclick="clearDay()">Clear All</button>
        </div>
    </div>
</div>

<script>
    const masseuseId = <?php echo $masseuse_id; ?>;
    let currentDate = null;
    let selectedSlots = new Set();
    let availabilityData = {};

    // Generate next 10 days
    function getNext10Days() {
        const days = [];
        const today = new Date();
        
        for (let i = 0; i < 10; i++) {
            const date = new Date(today);
            date.setDate(today.getDate() + i);
            days.push(date);
        }
        
        return days;
    }

    // Format date as YYYY-MM-DD
    function formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    // Format date for display
    function formatDisplayDate(date) {
        const options = { month: 'short', day: 'numeric', year: 'numeric' };
        return date.toLocaleDateString('en-US', options);
    }

    // Get day name
    function getDayName(date) {
        const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        return days[date.getDay()];
    }

    // Load availability data
    async function loadAvailability() {
        const days = getNext10Days();
        const startDate = formatDate(days[0]);
        const endDate = formatDate(days[9]);
        
        try {
            const response = await fetch(`../api/get_daily_availability.php?masseuse_id=${masseuseId}&start_date=${startDate}&end_date=${endDate}`);
            const data = await response.json();
            availabilityData = data.availability || {};
            renderDays();
        } catch (error) {
            console.error('Error loading availability:', error);
            renderDays();
        }
    }

    // Render day cards
    function renderDays() {
        const daysGrid = document.getElementById('daysGrid');
        const days = getNext10Days();
        
        daysGrid.innerHTML = '';
        
        days.forEach(date => {
            const dateStr = formatDate(date);
            const availability = availabilityData[dateStr] || [];
            
            const card = document.createElement('div');
            card.className = 'day-card';
            
            if (availability.length > 0) {
                card.classList.add('has-availability');
            }
            
            let statusBadge = '<span class="status-badge status-unavailable">No Availability</span>';
            let slotsHtml = '<p style="color: #64748b;">Click to set availability</p>';
            
            if (availability.length > 0) {
                statusBadge = '<span class="status-badge status-available">Available</span>';
                slotsHtml = availability.map(slot => 
                    `<div class="time-slot-item">${slot.start.substring(0, 5)} - ${slot.end.substring(0, 5)}</div>`
                ).join('');
            }
            
            card.innerHTML = `
                <div class="day-header">
                    <div>
                        <div class="day-name">${getDayName(date)}</div>
                        <div class="day-date">${formatDisplayDate(date)}</div>
                    </div>
                    ${statusBadge}
                </div>
                <div class="time-slots-preview">
                    ${slotsHtml}
                </div>
            `;
            
            card.onclick = () => openModal(date);
            daysGrid.appendChild(card);
        });
    }

    // Generate time slots (8 AM to 8 PM in 1-hour increments)
    function generateTimeSlots() {
        const slots = [];
        for (let hour = 8; hour <= 20; hour++) {
            const time = `${String(hour).padStart(2, '0')}:00`;
            slots.push(time);
        }
        return slots;
    }

    // Open modal for a specific day
    function openModal(date) {
        currentDate = date;
        selectedSlots.clear();
        
        document.getElementById('modalTitle').textContent = `${getDayName(date)} - ${formatDisplayDate(date)}`;
        
        // Load existing availability for this day
        const dateStr = formatDate(date);
        const existing = availabilityData[dateStr] || [];
        
        // Convert existing slots to selected hours
        existing.forEach(slot => {
            const startHour = parseInt(slot.start.split(':')[0]);
            const endHour = parseInt(slot.end.split(':')[0]);
            for (let h = startHour; h < endHour; h++) {
                selectedSlots.add(`${String(h).padStart(2, '0')}:00`);
            }
        });
        
        renderTimeGrid();
        updateSelectedSlotsDisplay();
        document.getElementById('timeSlotModal').style.display = 'block';
    }

    // Close modal
    function closeModal() {
        document.getElementById('timeSlotModal').style.display = 'none';
    }

    // Render time grid
    function renderTimeGrid() {
        const timeGrid = document.getElementById('timeGrid');
        const slots = generateTimeSlots();
        
        timeGrid.innerHTML = '';
        
        slots.forEach(time => {
            const btn = document.createElement('button');
            btn.className = 'time-slot-btn';
            btn.textContent = time;
            
            if (selectedSlots.has(time)) {
                btn.classList.add('selected');
            }
            
            btn.onclick = () => toggleTimeSlot(time);
            timeGrid.appendChild(btn);
        });
    }

    // Toggle time slot selection
    function toggleTimeSlot(time) {
        if (selectedSlots.has(time)) {
            selectedSlots.delete(time);
        } else {
            selectedSlots.add(time);
        }
        renderTimeGrid();
        updateSelectedSlotsDisplay();
    }

    // Update selected slots display
    function updateSelectedSlotsDisplay() {
        const container = document.getElementById('selectedSlotsContainer');
        const tagsDiv = document.getElementById('selectedSlotsTags');
        
        if (selectedSlots.size === 0) {
            container.style.display = 'none';
            return;
        }
        
        container.style.display = 'block';
        
        // Convert selected slots to ranges
        const ranges = getTimeRanges();
        tagsDiv.innerHTML = ranges.map(range => 
            `<span class="selected-slot-tag">${range.start} - ${range.end}</span>`
        ).join('');
    }

    // Convert selected slots to time ranges
    function getTimeRanges() {
        const sorted = Array.from(selectedSlots).sort();
        const ranges = [];
        
        if (sorted.length === 0) return ranges;
        
        let start = sorted[0];
        let prev = sorted[0];
        
        for (let i = 1; i < sorted.length; i++) {
            const current = sorted[i];
            const prevHour = parseInt(prev.split(':')[0]);
            const currentHour = parseInt(current.split(':')[0]);
            
            if (currentHour - prevHour > 1) {
                // Gap detected, close current range
                const endHour = parseInt(prev.split(':')[0]) + 1;
                ranges.push({
                    start: start,
                    end: `${String(endHour).padStart(2, '0')}:00`
                });
                start = current;
            }
            prev = current;
        }
        
        // Close final range
        const endHour = parseInt(prev.split(':')[0]) + 1;
        ranges.push({
            start: start,
            end: `${String(endHour).padStart(2, '0')}:00`
        });
        
        return ranges;
    }

    // Select template
    function selectTemplate(type) {
        selectedSlots.clear();
        
        if (type === 'full') {
            for (let h = 9; h < 18; h++) {
                selectedSlots.add(`${String(h).padStart(2, '0')}:00`);
            }
        } else if (type === 'morning') {
            for (let h = 9; h < 12; h++) {
                selectedSlots.add(`${String(h).padStart(2, '0')}:00`);
            }
        } else if (type === 'afternoon') {
            for (let h = 13; h < 18; h++) {
                selectedSlots.add(`${String(h).padStart(2, '0')}:00`);
            }
        }
        
        renderTimeGrid();
        updateSelectedSlotsDisplay();
    }

    // Clear day
    function clearDay() {
        if (confirm('Clear all availability for this day?')) {
            selectedSlots.clear();
            renderTimeGrid();
            updateSelectedSlotsDisplay();
        }
    }

    // Save availability
    async function saveAvailability() {
        const dateStr = formatDate(currentDate);
        const timeSlots = getTimeRanges();
        
        try {
            const response = await fetch('../api/save_daily_availability.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    masseuse_id: masseuseId,
                    date: dateStr,
                    time_slots: timeSlots
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Update local data
                if (timeSlots.length > 0) {
                    availabilityData[dateStr] = timeSlots;
                } else {
                    delete availabilityData[dateStr];
                }
                
                renderDays();
                closeModal();
            } else {
                alert('Error saving availability: ' + (data.error || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error saving availability:', error);
            alert('Failed to save availability');
        }
    }

    // Initialize
    loadAvailability();
</script>

<?php require_once 'includes/footer.php'; ?>
