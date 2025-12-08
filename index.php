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
            AND (b.booking_date > CURDATE() OR (b.booking_date = CURDATE() AND b.booking_time >= CURTIME()))
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
                  🗑️
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
<section id="services" class="container">
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

  <h3 style="margin-top: 1rem;">For Customers</h3>
  <div class="glass-card faq-card" style="padding: 1rem;">
    <?php
    $cust_file = __DIR__ . '/data/faq_customer.html';
    if (file_exists($cust_file)) {
      echo file_get_contents($cust_file);
    } else {
      // fallback content
    ?>
      <details>
        <summary><strong>How do I book a service?</strong></summary>
        <p>Go to the service you want and click <em>Book Now</em> (or visit <code>/kallma/booking.php</code>). Provide your name and phone, choose a date and time, and confirm. We'll give you a confirmation number.</p>
      </details>

      <details>
        <summary><strong>Do I need to pay now?</strong></summary>
        <p>You can choose to pay now or at the venue. If you pay now, the booking will be marked as <em>Paid</em>. If you pay later, it will show as <em>Pending Payment</em>.</p>
      </details>

      <details>
        <summary><strong>Will I get a reminder?</strong></summary>
        <p>We recommend checking your confirmation message and we (or the admin) will send a reminder 24 hours before your appointment.</p>
      </details>

      <details>
        <summary><strong>What is the cancellation policy?</strong></summary>
        <p>Please refer to our cancellation policy. If you need to cancel, contact us as soon as possible so we can free up the slot for others.</p>
      </details>
    <?php } ?>
  </div>

  <?php if (isLoggedIn() && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'masseuse')): ?>
    <h3 style="margin-top: 1.5rem;">For Admins & Masseuses</h3>
    <div class="glass-card faq-card" style="padding: 1rem;">
      <?php
      $staff_file = __DIR__ . '/data/faq_staff.html';
      if (file_exists($staff_file)) {
        echo file_get_contents($staff_file);
      } else {
      ?>
        <details>
          <summary><strong>Where do I manage bookings and services?</strong></summary>
          <p>Admins can manage bookings and services from the admin area: <code>/kallma/admin/bookings.php</code> and <code>/kallma/admin/services.php</code>. Masseuses can view their schedule at <code>/kallma/admin/masseuse_schedule.php</code>.</p>
        </details>

        <details>
          <summary><strong>How do I mark a booking as paid or completed?</strong></summary>
          <p>Open the booking in the admin bookings list, update the payment status to <em>Paid</em> when payment is received, and mark the booking <em>Completed</em> after the service. Add notes for feedback or follow-up.</p>
        </details>

        <details>
          <summary><strong>End-of-day and backups</strong></summary>
          <p>At EOD reconcile payments, mark completed bookings, note no-shows, and export or backup bookings as needed. Maintain regular database backups and follow the team's SOP.</p>
        </details>

        <details>
          <summary><strong>Quick links and troubleshooting</strong></summary>
          <p>Quick links: <code>/kallma/admin/bookings.php</code>, <code>/kallma/admin/services.php</code>, <code>/kallma/login.php</code>. If you see raw PHP or missing styles, check the server or contact tech support with the error and time.</p>
        </details>
      <?php } ?>
    </div>
  <?php endif; ?>

</section>

<?php require_once 'includes/footer.php'; ?>