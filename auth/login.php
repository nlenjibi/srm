<?php
require_once 'session.php';

// If user is already logged in, redirect to appropriate dashboard
if (isLoggedIn()) {
    $user_type = $_SESSION['user_type'];
    switch ($user_type) {
        case 'admin':
            header("Location: ../admin/dashboard.php");
            break;
        case 'teacher':
            header("Location: ../teacher/dashboard.php");
            break;
        case 'student':
            header("Location: ../student/dashboard.php");
            break;
    }
    exit;
}

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validate input
    if (empty($username) || empty($password)) {
        $_SESSION['login_error'] = "Please enter both username and password.";
        header("Location: ../index.php");
        exit;
    }
    
    // Check for too many failed attempts (basic rate limiting)
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = 0;
    }
    
    if ($_SESSION['login_attempts'] >= MAX_LOGIN_ATTEMPTS) {
        $_SESSION['login_error'] = "Too many failed login attempts. Please try again later.";
        header("Location: ../index.php");
        exit;
    }
    
    // Attempt authentication
    if (authenticateUser($username, $password)) {
        // Reset login attempts on successful login
        unset($_SESSION['login_attempts']);
        
        // Redirect based on user type
        $user_type = $_SESSION['user_type'];
        switch ($user_type) {
            case 'admin':
                redirectWithMessage("../admin/dashboard.php", "Welcome back, Administrator!", "success");
                break;
            case 'teacher':
                redirectWithMessage("../teacher/dashboard.php", "Welcome back, Teacher!", "success");
                break;
            case 'student':
                redirectWithMessage("../student/dashboard.php", "Welcome back, Student!", "success");
                break;
            default:
                $_SESSION['login_error'] = "Invalid user type.";
                header("Location: ../index.php");
                exit;
        }
    } else {
        // Increment failed attempts
        $_SESSION['login_attempts']++;
        $_SESSION['login_error'] = "Invalid username or password.";
        header("Location: ../index.php");
        exit;
    }
} else {
    // If not POST request, redirect to login page
    header("Location: ../index.php");
    exit;
}
?>