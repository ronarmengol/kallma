<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

session_start();

if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';

// Show timeout notice when redirected after inactivity
$timeout_notice = '';
if (isset($_GET['timeout']) && $_GET['timeout'] == '1') {
    $timeout_notice = "You were logged out due to 5 minutes of inactivity. Please log in again.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_input = sanitize($conn, $_POST['name']);
    $password = $_POST['password'];

    // Check against name, mobile, or username
    $sql = "SELECT * FROM users WHERE name = '$login_input' OR mobile = '$login_input' OR username = '$login_input'";
    $result = $conn->query($sql);

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Development: Direct password comparison (NO HASHING)
        if ($password === $user['password']) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['last_activity'] = time();

            if ($user['role'] === 'admin' || $user['role'] === 'masseuse') {
                redirect('admin/index.php');
            } else {
                redirect('index.php');
            }
        } else {
            $error = "Invalid password.";
        }
    } else {
        $error = "User not found.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Kallma Spa</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body style="display: flex; align-items: center; justify-content: center; min-height: 100vh;">
    <div class="glass-card" style="width: 100%; max-width: 400px;">
        <h2 style="text-align: center; margin-bottom: 2rem;">Login</h2>
        <?php if ($error): ?>
            <div style="background: rgba(239, 68, 68, 0.2); color: #fca5a5; padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem; text-align: center;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        <?php if ($timeout_notice): ?>
            <div style="background: rgba(59, 130, 246, 0.12); color: #bfdbfe; padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem; text-align: center;">
                <?php echo $timeout_notice; ?>
            </div>
        <?php endif; ?>
        <form method="POST" action="">
            <div class="form-group">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%;">Login</button>
        </form>
        <p style="text-align: center; margin-top: 1.5rem; color: #94a3b8;">
            Don't have an account? <a href="register.php" style="color: var(--primary-color);">Sign up</a>
        </p>
        <p style="text-align: center; margin-top: 0.5rem;">
            <a href="index.php" style="color: #64748b; font-size: 0.9rem;">Back to Home</a>
        </p>
    </div>
</body>

</html>