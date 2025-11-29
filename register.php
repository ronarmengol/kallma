<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

session_start();

if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($conn, $_POST['name']);
    $mobile = sanitize($conn, $_POST['mobile']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Check if mobile exists
        $check_sql = "SELECT id FROM users WHERE mobile = '$mobile'";
        if ($conn->query($check_sql)->num_rows > 0) {
            $error = "Mobile number already registered.";
        } else {
            // Development: Store plain text password (NO HASHING)
            $sql = "INSERT INTO users (name, mobile, password) VALUES ('$name', '$mobile', '$password')";
            
            if ($conn->query($sql)) {
                $_SESSION['user_id'] = $conn->insert_id;
                $_SESSION['name'] = $name;
                $_SESSION['role'] = 'customer';
                redirect('index.php');
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Kallma Spa</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body style="display: flex; align-items: center; justify-content: center; min-height: 100vh;">
    <div class="glass-card" style="width: 100%; max-width: 400px;">
        <h2 style="text-align: center; margin-bottom: 2rem;">Create Account</h2>
        <?php if ($error): ?>
            <div style="background: rgba(239, 68, 68, 0.2); color: #fca5a5; padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem; text-align: center;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        <form method="POST" action="">
            <div class="form-group">
                <label for="name">Full Name</label>
                <input type="text" id="name" name="name" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="mobile">Mobile Number</label>
                <input type="tel" id="mobile" name="mobile" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%;">Sign Up</button>
        </form>
        <p style="text-align: center; margin-top: 1.5rem; color: #94a3b8;">
            Already have an account? <a href="login.php" style="color: var(--primary-color);">Login</a>
        </p>
        <p style="text-align: center; margin-top: 0.5rem;">
            <a href="index.php" style="color: #64748b; font-size: 0.9rem;">Back to Home</a>
        </p>
    </div>
</body>
</html>
