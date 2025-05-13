<?php
include '../../config/database.php';
include '../../config/constants.php';
session_start();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['name'] = $user['full_name'];
        header("Location: ../dashboard/{$user['role']}.php");
        exit();
    } else {
        $error = "Invalid email or password";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | GAS INVENTORY MANAGEMENT SYSTEM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="login.css">
</head>
<body>
    <div class="floating-bubbles">
        <div class="bubble"></div>
        <div class="bubble"></div>
        <div class="bubble"></div>
        <div class="bubble"></div>
        <div class="bubble"></div>
    </div>
    
    <div class="login-container">
        <div class="header">
            <div class="logo">
                <i class="fas fa-fire-flame-curved"></i>
                <h2>GAS INVENTORY MANAGEMENT SYSTEM</h2>
            </div>
            <p class="tagline">Precision Tracking for Flammable Resources</p>
        </div>
        
        <form method="POST" action="" class="login-form">
            <div class="form-group">
                <i class="fas fa-envelope icon"></i>
                <input type="email" name="email" placeholder="Email" required />
            </div>
            
            <div class="form-group">
                <i class="fas fa-lock icon"></i>
                <input type="password" name="password" placeholder="Password" required />
                <i class="fas fa-eye toggle-password" onclick="togglePasswordVisibility()"></i>
            </div>
            
            <button type="submit" class="login-btn">
                <span>Login</span>
                <i class="fas fa-arrow-right"></i>
            </button>
            
            <?php if ($error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <span><?= $error ?></span>
            </div>
            <?php endif; ?>
            
            <div class="footer-links">
                <a href="#" class="forgot-password">Forgot password?</a>
                <span class="divider">|</span>
                <a href="register.php" class="contact-support">Register here</a>
            </div>
        </form>
    </div>
    
    <div class="gas-tank-animation">
        <div class="tank"></div>
        <div class="valve"></div>
        <div class="base"></div>
    </div>
    
    <script src="js/login.js"></script>
</body>
</html>