<?php
// General application configuration
session_start();

// Application settings
define('APP_NAME', 'Student Management System');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/student-management');
define('ADMIN_EMAIL', 'admin@school.edu');

// Security settings
define('SESSION_TIMEOUT', 3600); // 1 hour in seconds
define('PASSWORD_MIN_LENGTH', 6);
define('MAX_LOGIN_ATTEMPTS', 5);

// Pagination settings
define('RECORDS_PER_PAGE', 10);

// File upload settings
define('MAX_FILE_SIZE', 5242880); // 5MB in bytes
define('ALLOWED_FILE_TYPES', ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx']);
define('UPLOAD_PATH', 'uploads/');

// Date and time settings
date_default_timezone_set('UTC');
define('DATE_FORMAT', 'Y-m-d');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');
define('DISPLAY_DATE_FORMAT', 'd/m/Y');
define('DISPLAY_DATETIME_FORMAT', 'd/m/Y H:i');

// User roles
define('ROLE_ADMIN', 'admin');
define('ROLE_TEACHER', 'teacher');
define('ROLE_STUDENT', 'student');

// Grade calculations
define('GRADE_A_MIN', 90);
define('GRADE_B_MIN', 80);
define('GRADE_C_MIN', 70);
define('GRADE_D_MIN', 60);

// Function to calculate grade based on percentage
function calculateGrade($percentage) {
    if ($percentage >= GRADE_A_MIN) return 'A';
    if ($percentage >= GRADE_B_MIN) return 'B';
    if ($percentage >= GRADE_C_MIN) return 'C';
    if ($percentage >= GRADE_D_MIN) return 'D';
    return 'F';
}

// Function to format date for display
function formatDate($date, $format = DISPLAY_DATE_FORMAT) {
    return date($format, strtotime($date));
}

// Function to generate student ID
function generateStudentId() {
    return 'STU' . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

// Function to generate teacher ID
function generateTeacherId() {
    return 'TCH' . date('Y') . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
}

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Function to check user role
function hasRole($required_role) {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === $required_role;
}

// Function to redirect with message
function redirectWithMessage($url, $message, $type = 'success') {
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $type;
    header("Location: $url");
    exit;
}

// Function to display messages
function displayMessage() {
    if (isset($_SESSION['message'])) {
        $message = $_SESSION['message'];
        $type = $_SESSION['message_type'] ?? 'info';
        unset($_SESSION['message'], $_SESSION['message_type']);
        
        echo "<div class='alert alert-$type alert-dismissible fade show' role='alert'>
                $message
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
              </div>";
    }
}
?>