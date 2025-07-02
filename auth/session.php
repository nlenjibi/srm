<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Function to start secure session
function startSecureSession() {
    // Prevent session hijacking
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0); // Set to 1 for HTTPS
    
    if (!session_id()) {
        session_start();
    }
    
    // Check session timeout
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        destroySession();
        return false;
    }
    
    $_SESSION['last_activity'] = time();
    return true;
}

// Function to authenticate user
function authenticateUser($username, $password) {
    $conn = getDatabaseConnection();
    
    try {
        $stmt = prepareStatement($conn, "SELECT id, username, email, password, user_type FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['user_type'] = $user['user_type'];
                $_SESSION['last_activity'] = time();
                
                // Update last login
                $updateStmt = prepareStatement($conn, "UPDATE users SET updated_at = NOW() WHERE id = ?");
                $updateStmt->bind_param("i", $user['id']);
                $updateStmt->execute();
                
                return true;
            }
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Authentication error: " . $e->getMessage());
        return false;
    } finally {
        if (isset($stmt)) $stmt->close();
        closeDatabaseConnection($conn);
    }
}

// Function to check if user is authenticated
function requireAuth() {
    startSecureSession();
    
    if (!isLoggedIn()) {
        header("Location: ../index.php");
        exit;
    }
}

// Function to check role-based access
function requireRole($required_role) {
    requireAuth();
    
    if (!hasRole($required_role)) {
        redirectWithMessage("../index.php", "Access denied. Insufficient permissions.", "error");
    }
}

// Function to destroy session
function destroySession() {
    if (session_id()) {
        $_SESSION = array();
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
    }
}

// Function to get current user info
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $conn = getDatabaseConnection();
    
    try {
        $stmt = prepareStatement($conn, "SELECT * FROM users WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            return $result->fetch_assoc();
        }
        
        return null;
    } catch (Exception $e) {
        error_log("Get current user error: " . $e->getMessage());
        return null;
    } finally {
        if (isset($stmt)) $stmt->close();
        closeDatabaseConnection($conn);
    }
}

// Function to get user profile (student or teacher)
function getUserProfile() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $conn = getDatabaseConnection();
    $user_type = $_SESSION['user_type'];
    $user_id = $_SESSION['user_id'];
    
    try {
        if ($user_type === 'student') {
            $stmt = prepareStatement($conn, "SELECT * FROM students WHERE user_id = ?");
        } elseif ($user_type === 'teacher') {
            $stmt = prepareStatement($conn, "SELECT * FROM teachers WHERE user_id = ?");
        } else {
            return null;
        }
        
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            return $result->fetch_assoc();
        }
        
        return null;
    } catch (Exception $e) {
        error_log("Get user profile error: " . $e->getMessage());
        return null;
    } finally {
        if (isset($stmt)) $stmt->close();
        closeDatabaseConnection($conn);
    }
}
?>