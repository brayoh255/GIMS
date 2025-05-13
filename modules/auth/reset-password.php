<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . 'modules/dashboard/' . $_SESSION['role'] . '.php');
    exit();
}

$pageTitle = "Reset Password";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="login-container">
    <div class="login-box">
        <div class="login-logo">
            <img src="<?= ASSETS_PATH ?>images/logo.png" alt="<?= APP_NAME ?>">
            <h2>Reset Password</h2>
        </div>
        
        <?php displayFlashMessages(); ?>
        
        <form action="<?= BASE_URL ?>modules/auth/process_reset.php" method="POST">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Reset Password</button>
                <a href="<?= BASE_URL ?>modules/auth/login.php" class="btn btn-secondary">Back to Login</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>