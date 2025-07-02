<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../auth/session.php';

// Require teacher role
requireRole(ROLE_TEACHER);

$page_title = "My Profile";

// Get teacher profile
$teacher_profile = getUserProfile();
if (!$teacher_profile) {
    redirectWithMessage("../index.php", "Teacher profile not found.", "error");
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDatabaseConnection();
    
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'update_profile':
                    $stmt = prepareStatement($conn, 
                        "UPDATE teachers SET first_name=?, last_name=?, date_of_birth=?, gender=?, phone=?, address=?, qualification=?, subject_specialization=? 
                         WHERE id=?"
                    );
                    $stmt->bind_param("ssssssssi", 
                        $_POST['first_name'], $_POST['last_name'], $_POST['date_of_birth'], 
                        $_POST['gender'], $_POST['phone'], $_POST['address'], 
                        $_POST['qualification'], $_POST['subject_specialization'], $teacher_profile['id']
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

// Get updated profile data
$conn = getDatabaseConnection();
try {
    $stmt = prepareStatement($conn, "SELECT * FROM teachers WHERE id = ?");
    $stmt->bind_param("i", $teacher_profile['id']);
    $stmt->execute();
    $teacher_profile = $stmt->get_result()->fetch_assoc();
    
    // Get assigned classes
    $classStmt = prepareStatement($conn, 
        "SELECT c.*, COUNT(s.id) as student_count 
         FROM classes c 
         LEFT JOIN students s ON c.class_name = s.class AND c.section = s.section AND s.status = 'active'
         WHERE c.teacher_id = ? 
         GROUP BY c.id
         ORDER BY c.academic_year DESC, c.class_name, c.section"
    );
    $classStmt->bind_param("i", $teacher_profile['id']);
    $classStmt->execute();
    $assigned_classes = $classStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Get teaching statistics
    $statsStmt = prepareStatement($conn, 
        "SELECT 
            COUNT(DISTINCT g.student_id) as students_taught,
            COUNT(DISTINCT g.subject_id) as subjects_taught,
            COUNT(g.id) as grades_entered,
            AVG(g.marks_obtained/g.total_marks*100) as avg_grade_given
         FROM grades g 
         WHERE g.teacher_id = ?"
    );
    $statsStmt->bind_param("i", $teacher_profile['id']);
    $statsStmt->execute();
    $stats = $statsStmt->get_result()->fetch_assoc();
    
} catch (Exception $e) {
    error_log("Teacher profile error: " . $e->getMessage());
    $assigned_classes = [];
    $stats = ['students_taught' => 0, 'subjects_taught' => 0, 'grades_entered' => 0, 'avg_grade_given' => 0];
} finally {
    if (isset($stmt)) $stmt->close();
    if (isset($classStmt)) $classStmt->close();
    if (isset($statsStmt)) $statsStmt->close();
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
                        <?php echo strtoupper(substr($teacher_profile['first_name'], 0, 1) . substr($teacher_profile['last_name'], 0, 1)); ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <h3><?php echo htmlspecialchars($teacher_profile['first_name'] . ' ' . $teacher_profile['last_name']); ?></h3>
                <p class="text-muted">Teacher ID: <strong><?php echo htmlspecialchars($teacher_profile['teacher_id']); ?></strong></p>
                <p class="text-muted">Qualification: <strong><?php echo htmlspecialchars($teacher_profile['qualification']); ?></strong></p>
                <p class="text-muted">Specialization: <strong><?php echo htmlspecialchars($teacher_profile['subject_specialization'] ?: 'Not specified'); ?></strong></p>
                <p class="text-muted">Joining Date: <strong><?php echo formatDate($teacher_profile['joining_date']); ?></strong></p>
            </div>
            <div class="col-md-3">
                <div class="stats-mini">
                    <div class="stat-item">
                        <h4><?php echo $stats['students_taught']; ?></h4>
                        <p>Students Taught</p>
                    </div>
                    <div class="stat-item">
                        <h4><?php echo $stats['subjects_taught']; ?></h4>
                        <p>Subjects</p>
                    </div>
                    <div class="stat-item">
                        <h4><?php echo $stats['grades_entered']; ?></h4>
                        <p>Grades Entered</p>
                    </div>
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
                <a class="nav-link active" data-bs-toggle="tab" href="#profile-info">Profile Information</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#my-classes">My Classes</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#change-password">Change Password</a>
            </li>
        </ul>
    </div>
    <div class="card-body">
        <div class="tab-content">
            <!-- Profile Information Tab -->
            <div class="tab-pane fade show active" id="profile-info">
                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">First Name *</label>
                            <input type="text" class="form-control" name="first_name" value="<?php echo htmlspecialchars($teacher_profile['first_name']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Last Name *</label>
                            <input type="text" class="form-control" name="last_name" value="<?php echo htmlspecialchars($teacher_profile['last_name']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" class="form-control" name="date_of_birth" value="<?php echo $teacher_profile['date_of_birth']; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Gender</label>
                            <select class="form-control" name="gender">
                                <option value="">Select Gender</option>
                                <option value="male" <?php echo $teacher_profile['gender'] === 'male' ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo $teacher_profile['gender'] === 'female' ? 'selected' : ''; ?>>Female</option>
                                <option value="other" <?php echo $teacher_profile['gender'] === 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($teacher_profile['phone']); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea class="form-control" name="address" rows="3"><?php echo htmlspecialchars($teacher_profile['address']); ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Qualification *</label>
                            <input type="text" class="form-control" name="qualification" value="<?php echo htmlspecialchars($teacher_profile['qualification']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Subject Specialization</label>
                            <input type="text" class="form-control" name="subject_specialization" value="<?php echo htmlspecialchars($teacher_profile['subject_specialization']); ?>">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Update Profile</button>
                </form>
            </div>
            
            <!-- My Classes Tab -->
            <div class="tab-pane fade" id="my-classes">
                <?php if (!empty($assigned_classes)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Class</th>
                                    <th>Section</th>
                                    <th>Academic Year</th>
                                    <th>Students</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assigned_classes as $class): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($class['class_name']); ?></strong></td>
                                        <td><span class="badge bg-info"><?php echo htmlspecialchars($class['section']); ?></span></td>
                                        <td><?php echo $class['academic_year']; ?></td>
                                        <td>
                                            <span class="badge bg-primary"><?php echo $class['student_count']; ?> students</span>
                                        </td>
                                        <td>
                                            <a href="students.php?class_filter=<?php echo $class['class_name']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-users"></i> View Students
                                            </a>
                                            <a href="grades.php" class="btn btn-sm btn-outline-success">
                                                <i class="fas fa-star"></i> Manage Grades
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info text-center">
                        <h5>No Classes Assigned</h5>
                        <p>You don't have any classes assigned yet. Please contact the administrator.</p>
                    </div>
                <?php endif; ?>
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