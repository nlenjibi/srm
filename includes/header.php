<?php
if (!isLoggedIn()) {
    header("Location: ../index.php");
    exit;
}

$current_user = getCurrentUser();
$user_profile = getUserProfile();
$user_initial = strtoupper(substr($_SESSION['username'], 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-wrapper">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h4><i class="fas fa-graduation-cap"></i> SMS</h4>
            </div>
            
            <ul class="sidebar-menu">
                <?php if ($_SESSION['user_type'] === 'admin'): ?>
                    <li>
                        <a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li>
                        <a href="manage_users.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'manage_users.php' ? 'active' : ''; ?>">
                            <i class="fas fa-users"></i> Manage Users
                        </a>
                    </li>
                    <li>
                        <a href="manage_students.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'manage_students.php' ? 'active' : ''; ?>">
                            <i class="fas fa-user-graduate"></i> Students
                        </a>
                    </li>
                    <li>
                        <a href="manage_teachers.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'manage_teachers.php' ? 'active' : ''; ?>">
                            <i class="fas fa-chalkboard-teacher"></i> Teachers
                        </a>
                    </li>
                    <li>
                        <a href="subjects.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'subjects.php' ? 'active' : ''; ?>">
                            <i class="fas fa-book"></i> Subjects
                        </a>
                    </li>
                    <li>
                        <a href="classes.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'classes.php' ? 'active' : ''; ?>">
                            <i class="fas fa-school"></i> Classes
                        </a>
                    </li>
                    <li>
                        <a href="reports.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'active' : ''; ?>">
                            <i class="fas fa-chart-bar"></i> Reports
                        </a>
                    </li>
                <?php elseif ($_SESSION['user_type'] === 'teacher'): ?>
                    <li>
                        <a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li>
                        <a href="students.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'students.php' ? 'active' : ''; ?>">
                            <i class="fas fa-user-graduate"></i> My Students
                        </a>
                    </li>
                    <li>
                        <a href="grades.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'grades.php' ? 'active' : ''; ?>">
                            <i class="fas fa-star"></i> Grades
                        </a>
                    </li>
                    <li>
                        <a href="attendance.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'attendance.php' ? 'active' : ''; ?>">
                            <i class="fas fa-calendar-check"></i> Attendance
                        </a>
                    </li>
                    <li>
                        <a href="profile.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'active' : ''; ?>">
                            <i class="fas fa-user"></i> My Profile
                        </a>
                    </li>
                <?php else: // student ?>
                    <li>
                        <a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li>
                        <a href="profile.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'active' : ''; ?>">
                            <i class="fas fa-user"></i> My Profile
                        </a>
                    </li>
                    <li>
                        <a href="grades.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'grades.php' ? 'active' : ''; ?>">
                            <i class="fas fa-star"></i> My Grades
                        </a>
                    </li>
                    <li>
                        <a href="attendance.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'attendance.php' ? 'active' : ''; ?>">
                            <i class="fas fa-calendar-check"></i> Attendance
                        </a>
                    </li>
                    <li>
                        <a href="schedule.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'schedule.php' ? 'active' : ''; ?>">
                            <i class="fas fa-calendar"></i> Schedule
                        </a>
                    </li>
                    <li>
                        <a href="activities.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'activities.php' ? 'active' : ''; ?>">
                            <i class="fas fa-running"></i> Activities
                        </a>
                    </li>
                <?php endif; ?>
                
                <li style="margin-top: 2rem; border-top: 1px solid #eee; padding-top: 1rem;">
                    <a href="../auth/logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="header">
                <div class="header-title">
                    <button type="button" id="sidebarToggle" class="btn btn-primary d-md-none">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h2><?php echo $page_title ?? 'Dashboard'; ?></h2>
                </div>
                
                <div class="header-actions">
                    <div class="user-info">
                        <div class="user-avatar"><?php echo $user_initial; ?></div>
                        <div>
                            <div style="font-weight: 500;">
                                <?php 
                                echo $user_profile ? 
                                    ($user_profile['first_name'] . ' ' . $user_profile['last_name']) : 
                                    $_SESSION['username']; 
                                ?>
                            </div>
                            <div style="font-size: 0.8rem; color: #6c757d;">
                                <?php echo ucfirst($_SESSION['user_type']); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Content -->
            <div class="content">
                <?php displayMessage(); ?>