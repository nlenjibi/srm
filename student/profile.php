<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../auth/session.php';

// Require student role
requireRole(ROLE_STUDENT);

$page_title = "My Profile";

// Get student profile
$student_profile = getUserProfile();
if (!$student_profile) {
    redirectWithMessage("../index.php", "Student profile not found.", "error");
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDatabaseConnection();
    
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'update_profile':
                    $stmt = prepareStatement($conn, 
                        "UPDATE students SET phone=?, address=?, parent_name=?, parent_phone=?, parent_email=? WHERE id=?"
                    );
                    $stmt->bind_param("sssssi", 
                        $_POST['phone'], $_POST['address'], $_POST['parent_name'], 
                        $_POST['parent_phone'], $_POST['parent_email'], $student_profile['id']
                    );
                    
                    if ($stmt->execute()) {
                        redirectWithMessage($_SERVER['PHP_SELF'], "Profile updated successfully!", "success");
                    } else {
                        throw new Exception("Failed to update profile");
                    }
                    break;
                    
                case 'change_password':
                    // Get current user
                    $userStmt = prepareStatement($conn, "SELECT password FROM users WHERE id = ?");
                    $userStmt->bind_param("i", $_SESSION['user_id']);
                    $userStmt->execute();
                    $user = $userStmt->get_result()->fetch_assoc();
                    
                    if (!password_verify($_POST['current_password'], $user['password'])) {
                        throw new Exception("Current password is incorrect");
                    }
                    
                    if ($_POST['new_password'] !== $_POST['confirm_password']) {
                        throw new Exception("New passwords do not match");
                    }
                    
                    if (strlen($_POST['new_password']) < PASSWORD_MIN_LENGTH) {
                        throw new Exception("Password must be at least " . PASSWORD_MIN_LENGTH . " characters long");
                    }
                    
                    $hashedPassword = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                    $updateStmt = prepareStatement($conn, "UPDATE users SET password = ? WHERE id = ?");
                    $updateStmt->bind_param("si", $hashedPassword, $_SESSION['user_id']);
                    
                    if ($updateStmt->execute()) {
                        redirectWithMessage($_SERVER['PHP_SELF'], "Password changed successfully!", "success");
                    } else {
                        throw new Exception("Failed to update password");
                    }
                    break;
            }
        }
    } catch (Exception $e) {
        redirectWithMessage($_SERVER['PHP_SELF'], "Error: " . $e->getMessage(), "error");
    } finally {
        if (isset($stmt)) $stmt->close();
        if (isset($userStmt)) $userStmt->close();
        if (isset($updateStmt)) $updateStmt->close();
        closeDatabaseConnection($conn);
    }
}

// Get updated profile data and statistics
$conn = getDatabaseConnection();
try {
    $stmt = prepareStatement($conn, "SELECT * FROM students WHERE id = ?");
    $stmt->bind_param("i", $student_profile['id']);
    $stmt->execute();
    $student_profile = $stmt->get_result()->fetch_assoc();
    
    // Get academic statistics
    $statsStmt = prepareStatement($conn, 
        "SELECT 
            COUNT(DISTINCT g.subject_id) as subjects_enrolled,
            COUNT(g.id) as total_grades,
            AVG(g.marks_obtained/g.total_marks*100) as avg_percentage,
            COUNT(DISTINCT a.attendance_date) as attendance_days,
            SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_days
         FROM grades g 
         LEFT JOIN attendance a ON g.student_id = a.student_id
         WHERE g.student_id = ?"
    );
    $statsStmt->bind_param("i", $student_profile['id']);
    $statsStmt->execute();
    $stats = $statsStmt->get_result()->fetch_assoc();
    
    // Get latest grade
    $latestGradeStmt = prepareStatement($conn, 
        "SELECT g.*, sub.subject_name FROM grades g 
         JOIN subjects sub ON g.subject_id = sub.id 
         WHERE g.student_id = ? ORDER BY g.exam_date DESC LIMIT 1"
    );
    $latestGradeStmt->bind_param("i", $student_profile['id']);
    $latestGradeStmt->execute();
    $latestGradeResult = $latestGradeStmt->get_result();
    $latest_grade = $latestGradeResult->num_rows > 0 ? $latestGradeResult->fetch_assoc() : null;
    
} catch (Exception $e) {
    error_log("Student profile error: " . $e->getMessage());
    $stats = ['subjects_enrolled' => 0, 'total_grades' => 0, 'avg_percentage' => 0, 'attendance_days' => 0, 'present_days' => 0];
    $latest_grade = null;
} finally {
    if (isset($stmt)) $stmt->close();
    if (isset($statsStmt)) $statsStmt->close();
    if (isset($latestGradeStmt)) $latestGradeStmt->close();
    closeDatabaseConnection($conn);
}

require_once '../includes/header.php';
?>

<!-- Profile Header -->
<div class="card">
    <div class="card-body">
        <div class="row">
            <div class="col-md-3 text-center">
                <div class="profile-avatar">
                    <div style="width: 120px; height: 120px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto; color: white; font-size: 3rem; font-weight: bold;">
                        <?php echo strtoupper(substr($student_profile['first_name'], 0, 1) . substr($student_profile['last_name'], 0, 1)); ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <h3><?php echo htmlspecialchars($student_profile['first_name'] . ' ' . $student_profile['last_name']); ?></h3>
                <p class="text-muted">Student ID: <strong><?php echo htmlspecialchars($student_profile['student_id']); ?></strong></p>
                <p class="text-muted">Class: <strong><?php echo htmlspecialchars($student_profile['class'] . '-' . $student_profile['section']); ?></strong></p>
                <p class="text-muted">Admission Date: <strong><?php echo formatDate($student_profile['admission_date']); ?></strong></p>
                <p class="text-muted">Status: 
                    <span class="badge <?php echo $student_profile['status'] === 'active' ? 'bg-success' : 'bg-danger'; ?>">
                        <?php echo ucfirst($student_profile['status']); ?>
                    </span>
                </p>
            </div>
            <div class="col-md-3">
                <div class="stats-mini">
                    <div class="stat-item">
                        <h4><?php echo $stats['subjects_enrolled']; ?></h4>
                        <p>Subjects</p>
                    </div>
                    <div class="stat-item">
                        <h4><?php echo $stats['total_grades']; ?></h4>
                        <p>Total Grades</p>
                    </div>
                    <div class="stat-item">
                        <h4><?php echo $stats['avg_percentage'] ? round($stats['avg_percentage'], 1) . '%' : 'N/A'; ?></h4>
                        <p>Average</p>
                    </div>
                    <?php if ($latest_grade): ?>
                    <div class="stat-item">
                        <h4>
                            <span class="badge <?php 
                                echo match($latest_grade['grade']) {
                                    'A' => 'bg-success',
                                    'B' => 'bg-primary',
                                    'C' => 'bg-warning',
                                    'D' => 'bg-info',
                                    default => 'bg-danger'
                                };
                            ?>">
                                <?php echo $latest_grade['grade']; ?>
                            </span>
                        </h4>
                        <p>Latest Grade</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tabs -->
<div class="card">
    <div class="card-header">
        <ul class="nav nav-tabs card-header-tabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#personal-info">Personal Information</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#academic-info">Academic Information</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#contact-info">Contact Information</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#change-password">Change Password</a>
            </li>
        </ul>
    </div>
    <div class="card-body">
        <div class="tab-content">
            <!-- Personal Information Tab -->
            <div class="tab-pane fade show active" id="personal-info">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th width="40%">Full Name:</th>
                                <td><?php echo htmlspecialchars($student_profile['first_name'] . ' ' . $student_profile['last_name']); ?></td>
                            </tr>
                            <tr>
                                <th>Date of Birth:</th>
                                <td><?php echo $student_profile['date_of_birth'] ? formatDate($student_profile['date_of_birth']) : 'Not specified'; ?></td>
                            </tr>
                            <tr>
                                <th>Gender:</th>
                                <td><?php echo $student_profile['gender'] ? ucfirst($student_profile['gender']) : 'Not specified'; ?></td>
                            </tr>
                            <tr>
                                <th>Student ID:</th>
                                <td><strong><?php echo htmlspecialchars($student_profile['student_id']); ?></strong></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th width="40%">Class:</th>
                                <td>
                                    <span class="badge bg-primary">
                                        <?php echo htmlspecialchars($student_profile['class'] . '-' . $student_profile['section']); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>Admission Date:</th>
                                <td><?php echo formatDate($student_profile['admission_date']); ?></td>
                            </tr>
                            <tr>
                                <th>Status:</th>
                                <td>
                                    <span class="badge <?php echo $student_profile['status'] === 'active' ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo ucfirst($student_profile['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>Registered:</th>
                                <td><?php echo formatDate($student_profile['created_at']); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Academic Information Tab -->
            <div class="tab-pane fade" id="academic-info">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Academic Statistics</h6>
                        <table class="table table-borderless">
                            <tr>
                                <th width="50%">Subjects Enrolled:</th>
                                <td><span class="badge bg-info"><?php echo $stats['subjects_enrolled']; ?></span></td>
                            </tr>
                            <tr>
                                <th>Total Grades Received:</th>
                                <td><span class="badge bg-primary"><?php echo $stats['total_grades']; ?></span></td>
                            </tr>
                            <tr>
                                <th>Average Percentage:</th>
                                <td>
                                    <?php if ($stats['avg_percentage']): ?>
                                        <span class="badge <?php 
                                            $avg = round($stats['avg_percentage'], 1);
                                            echo $avg >= 90 ? 'bg-success' : ($avg >= 80 ? 'bg-primary' : ($avg >= 70 ? 'bg-warning' : 'bg-danger'));
                                        ?>">
                                            <?php echo round($stats['avg_percentage'], 1); ?>%
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Attendance Rate:</th>
                                <td>
                                    <?php if ($stats['attendance_days'] > 0): ?>
                                        <span class="badge bg-warning">
                                            <?php echo round(($stats['present_days'] / $stats['attendance_days']) * 100, 1); ?>%
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <?php if ($latest_grade): ?>
                            <h6>Latest Grade</h6>
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-title"><?php echo htmlspecialchars($latest_grade['subject_name']); ?></h6>
                                    <p class="card-text">
                                        <strong>Exam:</strong> <?php echo ucfirst($latest_grade['exam_type']); ?><br>
                                        <strong>Score:</strong> <?php echo $latest_grade['marks_obtained']; ?>/<?php echo $latest_grade['total_marks']; ?> 
                                        (<?php echo round(($latest_grade['marks_obtained'] / $latest_grade['total_marks']) * 100, 1); ?>%)<br>
                                        <strong>Grade:</strong> 
                                        <span class="badge <?php 
                                            echo match($latest_grade['grade']) {
                                                'A' => 'bg-success',
                                                'B' => 'bg-primary',
                                                'C' => 'bg-warning',
                                                'D' => 'bg-info',
                                                default => 'bg-danger'
                                            };
                                        ?>">
                                            <?php echo $latest_grade['grade']; ?>
                                        </span><br>
                                        <strong>Date:</strong> <?php echo formatDate($latest_grade['exam_date']); ?>
                                    </p>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <h6>No Grades Yet</h6>
                                <p>You haven't received any grades yet. Check back after your teachers enter your exam results.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Contact Information Tab -->
            <div class="tab-pane fade" id="contact-info">
                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <h6>Student Contact</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($student_profile['phone']); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" rows="3"><?php echo htmlspecialchars($student_profile['address']); ?></textarea>
                        </div>
                    </div>
                    
                    <h6>Parent/Guardian Contact</h6>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Parent/Guardian Name</label>
                            <input type="text" class="form-control" name="parent_name" value="<?php echo htmlspecialchars($student_profile['parent_name']); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Parent Phone</label>
                            <input type="tel" class="form-control" name="parent_phone" value="<?php echo htmlspecialchars($student_profile['parent_phone']); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Parent Email</label>
                            <input type="email" class="form-control" name="parent_email" value="<?php echo htmlspecialchars($student_profile['parent_email']); ?>">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Update Contact Information</button>
                </form>
            </div>
            
            <!-- Change Password Tab -->
            <div class="tab-pane fade" id="change-password">
                <form method="POST" id="passwordForm">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="mb-3">
                        <label class="form-label">Current Password *</label>
                        <input type="password" class="form-control" name="current_password" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">New Password *</label>
                        <input type="password" class="form-control" name="new_password" id="new_password" required>
                        <div class="form-text">Minimum <?php echo PASSWORD_MIN_LENGTH; ?> characters</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Confirm New Password *</label>
                        <input type="password" class="form-control" name="confirm_password" id="confirm_password" required>
                        <div id="password-match" class="form-text"></div>
                    </div>
                    
                    <button type="submit" class="btn btn-warning" id="changePasswordBtn" disabled>Change Password</button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.stats-mini {
    display: grid;
    gap: 1rem;
}

.stat-item {
    text-align: center;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 8px;
}

.stat-item h4 {
    margin: 0;
    color: #495057;
    font-size: 1.5rem;
    font-weight: bold;
}

.stat-item p {
    margin: 0;
    color: #6c757d;
    font-size: 0.9rem;
}

.nav-tabs .nav-link {
    color: #495057;
    border: none;
    background: none;
}

.nav-tabs .nav-link.active {
    color: #007bff;
    border-bottom: 2px solid #007bff;
    background: none;
}
</style>

<script>
// Password validation
document.getElementById('confirm_password').addEventListener('input', function() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = this.value;
    const matchDiv = document.getElementById('password-match');
    const submitBtn = document.getElementById('changePasswordBtn');
    
    if (newPassword && confirmPassword) {
        if (newPassword === confirmPassword) {
            matchDiv.textContent = 'Passwords match';
            matchDiv.style.color = 'green';
            submitBtn.disabled = false;
        } else {
            matchDiv.textContent = 'Passwords do not match';
            matchDiv.style.color = 'red';
            submitBtn.disabled = true;
        }
    } else {
        matchDiv.textContent = '';
        submitBtn.disabled = true;
    }
});

document.getElementById('new_password').addEventListener('input', function() {
    const confirmPassword = document.getElementById('confirm_password');
    if (confirmPassword.value) {
        confirmPassword.dispatchEvent(new Event('input'));
    }
});

// Tab navigation without Bootstrap JS
document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
    tab.addEventListener('click', function(e) {
        e.preventDefault();
        
        // Remove active from all tabs and panes
        document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
        document.querySelectorAll('.tab-pane').forEach(pane => {
            pane.classList.remove('show', 'active');
        });
        
        // Add active to clicked tab
        this.classList.add('active');
        
        // Show corresponding pane
        const targetPane = document.querySelector(this.getAttribute('href'));
        targetPane.classList.add('show', 'active');
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>