<?php
require_once 'includes/header.php';

$services = getServices($conn);

// Get pending bookings for logged-in customers
$pending_bookings = [];
$cancel_message = '';

if (isLoggedIn() && isset($_SESSION['user_id']) && $_SESSION['role'] === 'customer') {
    $user_id = $_SESSION['user_id'];
    
    // Handle booking cancellation
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_booking') {
        $booking_id = (int)$_POST['booking_id'];
        
        // Verify the booking belongs to this user
        $verify_sql = "SELECT id FROM bookings WHERE id = ? AND user_id = ?";
        $verify_stmt = $conn->prepare($verify_sql);
        $verify_stmt->bind_param('ii', $booking_id, $user_id);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();
        
        if ($verify_result->num_rows > 0) {
            // Update booking status to cancelled
            $cancel_sql = "UPDATE bookings SET status = 'cancelled' WHERE id = ?";
            $cancel_stmt = $conn->prepare($cancel_sql);
            $cancel_stmt->bind_param('i', $booking_id);
            
            if ($cancel_stmt->execute()) {
                $cancel_message = "Booking cancelled successfully. The time slot is now available for others.";
            } else {
                $cancel_message = "Error cancelling booking. Please try again.";
            }
            $cancel_stmt->close();
        }
        $verify_stmt->close();
    }
    
    $sql = "SELECT b.*, s.name as service_name, m.name as masseuse_name 
            FROM bookings b
            JOIN services s ON b.service_id = s.id
            JOIN masseuses m ON b.masseuse_id = m.id
            WHERE b.user_id = ? 
            AND b.status IN ('pending', 'confirmed')
            AND b.booking_date >= CURDATE()
            ORDER BY b.booking_date ASC, b.booking_time ASC
            LIMIT 5";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $pending_bookings = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>

<section class="hero">
  <div class="container">
    <h1>Find Your Inner Peace</h1>
    <p>Escape the noise and rediscover tranquility at Kallma Spa & Wellness Centre. Expert masseuses, premium treatments, and a sanctuary for your soul.</p>
    <a href="#services" class="btn btn-primary">Explore Services</a>
  </div>
</section>

<?php if (isLoggedIn() && $_SESSION['role'] === 'customer' && !empty($pending_bookings)): ?>
<!-- My Bookings Section -->
<section class="container" style="margin-top: 2rem;">
  <h2 class="section-title">My Upcoming Appointments</h2>
  
  <?php if ($cancel_message): ?>
    <div style="background: rgba(16, 185, 129, 0.2); color: #6ee7b7; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
      <?php echo $cancel_message; ?>
    </div>
  <?php endif; ?>
  
  <div class="glass-card" style="padding: 1.5rem;">
    <div style="overflow-x: auto;">
      <table>
        <thead>
          <tr>
            <th>Service</th>
            <th>Masseuse</th>
            <th>Date & Time</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pending_bookings as $booking): ?>
            <tr>
              <td><?php echo htmlspecialchars($booking['service_name']); ?></td>
              <td><?php echo htmlspecialchars($booking['masseuse_name']); ?></td>
              <td>
                <?php echo date('M d, Y', strtotime($booking['booking_date'])); ?>
                <div style="font-size: 0.85em; color: #94a3b8; margin-top: 2px;">
                  <?php echo date('g:i A', strtotime($booking['booking_time'])); ?>
                </div>
              </td>
              <td>
                <span class="badge badge-<?php echo $booking['status']; ?>">
                  <?php echo ucfirst($booking['status']); ?>
                </span>
              </td>
              <td>
                <button type="button" class="icon-btn delete" title="Cancel Booking" onclick="openCancelModal(<?php echo $booking['id']; ?>, '<?php echo htmlspecialchars($booking['service_name'], ENT_QUOTES); ?>')">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="3 6 5 6 21 6"></polyline>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    <line x1="10" y1="11" x2="10" y2="17"></line>
                    <line x1="14" y1="11" x2="14" y2="17"></line>
                  </svg>
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div style="margin-top: 1rem; text-align: center;">
      <a href="booking.php" class="btn btn-primary">Book Another Appointment</a>
    </div>
  </div>
</section>

<!-- Cancel Booking Modal -->
<div id="cancelBookingModal" class="modal">
  <div class="modal-content glass-card" style="max-width: 400px; text-align: center;">
    <h2 style="color: #ef4444; margin-bottom: 1rem;">Cancel Booking</h2>
    <p style="color: #94a3b8; margin-bottom: 2rem;">
      Are you sure you want to cancel your appointment for <strong id="cancelServiceName"></strong>?
    </p>
    
    <form method="POST">
      <input type="hidden" name="action" value="cancel_booking">
      <input type="hidden" name="booking_id" id="cancelBookingId">
      
      <div style="display: flex; gap: 1rem;">
        <button type="submit" class="btn" style="flex: 1; background: #ef4444; color: white; border: none;">Yes, Cancel</button>
        <button type="button" onclick="closeCancelModal()" class="btn btn-outline" style="flex: 1;">No, Keep It</button>
      </div>
    </form>
  </div>
</div>

<script>
  function openCancelModal(bookingId, serviceName) {
    document.getElementById('cancelBookingId').value = bookingId;
    document.getElementById('cancelServiceName').textContent = serviceName;
    document.getElementById('cancelBookingModal').style.display = 'block';
  }
  
  function closeCancelModal() {
    document.getElementById('cancelBookingModal').style.display = 'none';
  }
  
  // Close modal when clicking outside
  window.addEventListener('click', function(event) {
    const modal = document.getElementById('cancelBookingModal');
    if (event.target === modal) {
      closeCancelModal();
    }
  });
</script>
<?php endif; ?>

<!-- Services Section (Moved up) -->
<section id="services" class="container" style="padding-top: 3rem;">
  <h2 class="section-title">Our Services</h2>
  <div class="services-grid">
    <?php foreach ($services as $service): ?>
      <div class="glass-card service-card">
        <?php if ($service['image_url']): ?>
          <img src="<?php echo htmlspecialchars($service['image_url']); ?>" alt="<?php echo htmlspecialchars($service['name']); ?>" class="service-image">
        <?php else: ?>
          <div class="service-image" style="background: #334155; display: flex; align-items: center; justify-content: center;">
            <span>No Image</span>
          </div>
        <?php endif; ?>
        <div class="service-info">
          <h3><?php echo htmlspecialchars($service['name']); ?></h3>
          <p style="color: #94a3b8; margin-bottom: 1rem; display: -webkit-box; -webkit-line-clamp: 2; line-clamp:2;-webkit-box-orient: vertical; overflow: hidden;"><?php echo htmlspecialchars($service['description']); ?></p>
          <div style="position: absolute; bottom: 80px; left: 50%; transform: translateX(-50%); width:100%; padding: 0px 35px;">
            <div style="display: flex; justify-content: space-between; align-items: center; width:100%">

              <span class="service-price">K<?php echo number_format($service['price'], 2); ?></span>
              <span style="color: #64748b; font-size: 0.9rem;"><?php echo $service['duration_minutes']; ?> mins</span>
            </div>
          </div>
          <a href="booking.php?service_id=<?php echo $service['id']; ?>" class="btn btn-outline" style="width: 90%; position: absolute; bottom: 1rem; left: 50%; transform: translateX(-50%); text-align: center;">Book Now</a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- FAQ Section (Moved down) -->
<section id="faq" class="container" style="margin-top: 3rem;">
  <h2 class="section-title">Frequently Asked Questions</h2>

  <?php
  // Fetch customer FAQs from database
  $customer_faqs = $conn->query("SELECT * FROM faqs WHERE category = 'customer' ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
  
  if (!empty($customer_faqs)):
  ?>
  <h3 style="margin-top: 1rem;">For Customers</h3>
  <div class="glass-card faq-card" style="padding: 1rem;">
    <?php foreach ($customer_faqs as $faq): ?>
      <details>
        <summary><strong><?php echo htmlspecialchars($faq['question']); ?></strong></summary>
        <p><?php echo nl2br(htmlspecialchars($faq['answer'])); ?></p>
      </details>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if (isLoggedIn() && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'masseuse')): ?>
    <?php
    // Fetch staff FAQs from database
    $staff_faqs = $conn->query("SELECT * FROM faqs WHERE category = 'staff' ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
    
    if (!empty($staff_faqs)):
    ?>
    <h3 style="margin-top: 1.5rem;">For Admins & Masseuses</h3>
    <div class="glass-card faq-card" style="padding: 1rem;">
      <?php foreach ($staff_faqs as $faq): ?>
        <details>
          <summary><strong><?php echo htmlspecialchars($faq['question']); ?></strong></summary>
          <p><?php echo nl2br(htmlspecialchars($faq['answer'])); ?></p>
        </details>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  <?php endif; ?>

</section>

<?php require_once 'includes/footer.php'; ?>