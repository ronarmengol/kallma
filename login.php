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
    <style>
        .login-container {
            display: flex;
            min-height: 100vh;
            background: linear-gradient(135deg, #2d5f5d 0%, #1a3a38 100%);
            position: relative;
            overflow: hidden;
        }
        
        /* Decorative circles */
        .login-container::before,
        .login-container::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.03);
        }
        
        .login-container::before {
            width: 400px;
            height: 400px;
            top: -100px;
            left: -100px;
        }
        
        .login-container::after {
            width: 300px;
            height: 300px;
            bottom: -80px;
            right: -80px;
        }
        
        .login-left {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem;
            position: relative;
            z-index: 1;
        }
        
        .login-illustration {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 50% 20% 50% 20% / 20% 50% 20% 50%;
            padding: 3rem;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            position: relative;
        }
        
        .login-illustration img {
            width: 100%;
            height: auto;
            display: block;
        }
        
        .login-illustration::before {
            content: '';
            position: absolute;
            width: 60px;
            height: 60px;
            background: rgba(45, 95, 93, 0.1);
            border-radius: 50%;
            top: -20px;
            right: 10%;
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
        
        .login-right {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem;
            position: relative;
            z-index: 1;
        }
        
        .login-form-container {
            width: 100%;
            max-width: 400px;
        }
        
        .login-form-container h2 {
            color: #ffffff;
            font-size: 2.5rem;
            margin-bottom: 2rem;
            font-weight: 600;
        }
        
        .login-form-group {
            margin-bottom: 1.5rem;
        }
        
        .login-form-group label {
            display: block;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }
        
        .login-form-group input {
            width: 100%;
            padding: 0.875rem 1rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            color: #ffffff;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .login-form-group input::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }
        
        .login-form-group input:focus {
            outline: none;
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.4);
        }
        
        .login-btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #5fa8a3 0%, #4a8a86 100%);
            border: none;
            border-radius: 8px;
            color: #ffffff;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 1rem;
        }
        
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(95, 168, 163, 0.4);
        }
        
        .login-links {
            text-align: center;
            margin-top: 1.5rem;
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
        }
        
        .login-links a {
            color: #a8d5d3;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .login-links a:hover {
            color: #ffffff;
        }
        
        .login-footer {
            margin-top: 3rem;
            text-align: center;
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.5);
        }
        
        .alert {
            padding: 0.875rem 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.2);
            color: #fca5a5;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        
        .alert-info {
            background: rgba(59, 130, 246, 0.15);
            color: #bfdbfe;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }
        
        @media (max-width: 968px) {
            .login-container {
                flex-direction: column;
            }
            
            .login-left {
                display: none; /* Hide illustration on mobile for faster loading */
            }
            
            .login-illustration {
                max-width: 350px;
                padding: 2rem;
            }
            
            .login-right {
                padding: 2rem 1rem;
            }
            
            .login-form-container h2 {
                font-size: 2rem;
            }
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="login-left">
            <div class="login-illustration">
                <img src="assets/images/login-illustration.png" alt="Kallma Spa - Wellness & Relaxation">
            </div>
        </div>
        
        <div class="login-right">
            <div class="login-form-container">
                <h2>Login</h2>
                
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($timeout_notice): ?>
                    <div class="alert alert-info">
                        <?php echo $timeout_notice; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="login-form-group">
                        <label for="name">Username</label>
                        <input type="text" id="name" name="name" placeholder="Enter your username" required autofocus>
                    </div>
                    
                    <div class="login-form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" placeholder="Enter your password" required>
                    </div>
                    
                    <button type="submit" class="login-btn">Login to Kallma</button>
                </form>
                
                <div class="login-links">
                    Don't have an account? <a href="register.php">Register Now</a>
                </div>
                
                <div class="login-footer">
                    <a href="index.php" style="color: rgba(255, 255, 255, 0.6);">← Back to Home</a>
                </div>
            </div>
        </div>
    </div>
</body>

</html>