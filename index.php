<?php
require_once 'config/config.php';
require_once 'auth/session.php';

// If user is already logged in, redirect to appropriate dashboard
if (isLoggedIn()) {
    $user_type = $_SESSION['user_type'];
    switch ($user_type) {
        case 'admin':
            header("Location: admin/dashboard.php");
            break;
        case 'teacher':
            header("Location: teacher/dashboard.php");
            break;
        case 'student':
            header("Location: student/dashboard.php");
            break;
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Login</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1><i class="fas fa-graduation-cap"></i> <?php echo APP_NAME; ?></h1>
                <p>Please sign in to your account</p>
            </div>
            
            <?php
            // Display any messages
            displayMessage();
            
            // Display login error if exists
            if (isset($_SESSION['login_error'])) {
                echo '<div class="alert alert-danger">' . $_SESSION['login_error'] . '</div>';
                unset($_SESSION['login_error']);
            }
            ?>
            
            <form method="POST" action="auth/login.php" id="loginForm">
                <div class="form-group">
                    <label for="username" class="form-label">
                        <i class="fas fa-user"></i> Username or Email
                    </label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label">
                        <i class="fas fa-lock"></i> Password
                    </label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                
                <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>
            </form>
            
            <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #eee;">
                <h6>Demo Accounts:</h6>
                <div style="font-size: 0.9rem; color: #6c757d;">
                    <p><strong>Admin:</strong> admin / password</p>
                    <p><strong>Teacher:</strong> teacher / password</p>
                    <p><strong>Student:</strong> student / password</p>
                </div>
            </div>
            
            <div style="margin-top: 2rem; text-align: center; font-size: 0.9rem; color: #6c757d;">
                <p>&copy; 2024 <?php echo APP_NAME; ?>. All rights reserved.</p>
            </div>
        </div>
    </div>
    
    <script src="assets/js/app.js"></script>
</body>
</html>