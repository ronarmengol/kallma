<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
require_once __DIR__ . '/functions.php';

// If the user's session has timed out, destroy session and redirect to login with a timeout flag
if (function_exists('checkSessionTimeout') && checkSessionTimeout()) {
  header('Location: login.php?timeout=1');
  exit();
}

// Fetch user name if not in session to display in header
if (isset($_SESSION['user_id']) && !isset($_SESSION['user_name']) && isset($conn)) {
    $uid = (int)$_SESSION['user_id'];
    $u_res = $conn->query("SELECT name FROM users WHERE id = $uid");
    if ($u_res && $u_row = $u_res->fetch_assoc()) {
        $_SESSION['user_name'] = $u_row['name'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Kallma Spa & Wellness</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
  <?php if (basename($_SERVER['PHP_SELF']) === 'booking.php'): ?>
    <link rel="stylesheet" href="assets/css/booking-steps.css">
  <?php endif; ?>
  <?php $__kallma_logged_in = isLoggedIn(); ?>
  <script>
    // Client-side inactivity logout
    (function() {
      var LOGGED_IN = <?php echo $__kallma_logged_in ? 'true' : 'false'; ?>;
      if (!LOGGED_IN) return;
      var TIMEOUT_MS = 5 * 60 * 1000; // 5 minutes
      var timeoutId;

      function keepAlive() {
        // Ping server to update last activity
        fetch('/kallma/api/keepalive.php', {
          method: 'POST',
          credentials: 'same-origin'
        }).catch(function() {});
      }

      function resetTimer(ev) {
        if (timeoutId) clearTimeout(timeoutId);
        keepAlive();
        timeoutId = setTimeout(function() {
          window.location = 'logout.php?timeout=1';
        }, TIMEOUT_MS);
      }

      ['click', 'mousemove', 'keydown', 'scroll', 'touchstart'].forEach(function(evt) {
        window.addEventListener(evt, resetTimer, {
          passive: true
        });
      });

      // Start timer
      resetTimer();
    })();
  </script>
</head>

<body>
  <header>
    <div class="container">
      <nav>
        <a href="index.php" class="logo">Kallma.</a>
        <button class="menu-toggle" onclick="document.querySelector('.nav-links').classList.toggle('active')">☰</button>
        <ul class="nav-links">
          <li><a href="index.php">Home</a></li>
          <li><a href="index.php#services">Services</a></li>
          <!-- FAQ page removed -->
          <?php /* functions already required above */ ?>
          <?php if (isLoggedIn()): ?>
            <li class="user-greeting">Hi, <?php echo isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'Guest'; ?></li>
            <?php if ($_SESSION['role'] !== 'admin'): ?>
              <li><a href="booking.php">Book Now</a></li>
            <?php endif; ?>
            <?php if ($_SESSION['role'] === 'admin'): ?>
              <li><a href="admin/index.php">Admin</a></li>
            <?php endif; ?>
            <li><a href="logout.php">Logout</a></li>
          <?php else: ?>
            <li><a href="login.php">Login</a></li>
            <li><a href="register.php" class="btn btn-primary">Sign Up</a></li>
          <?php endif; ?>
        </ul>
      </nav>
    </div>
  </header>
  <main>