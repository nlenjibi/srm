<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../auth/session.php';

// Require teacher role
requireRole(ROLE_TEACHER);

$page_title = "Teacher Dashboard";

// Get teacher profile
$teacher_profile = getUserProfile();
if (!$teacher_profile) {
    redirectWithMessage("../index.php", "Teacher profile not found.", "error");
}

$conn = getDatabaseConnection();

try {
    // Get assigned classes
    $stmt = prepareStatement($conn, "SELECT * FROM classes WHERE teacher_id = ? AND academic_year = YEAR(CURDATE()) ORDER BY class_name, section");
    $stmt->bind_param("i", $teacher_profile['id']);
    $stmt->execute();
    $myClasses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Get students in my classes
    $class_conditions = [];
    $class_params = [];
    foreach ($myClasses as $class) {
        $class_conditions[] = "(class = ? AND section = ?)";
        $class_params[] = $class['class_name'];
        $class_params[] = $class['section'];
    }
    
    $myStudentsCount = 0;
    if (!empty($class_conditions)) {
        $sql = "SELECT COUNT(*) as count FROM students WHERE status = 'active' AND (" . implode(' OR ', $class_conditions) . ")";
        $stmt = prepareStatement($conn, $sql);
        if (!empty($class_params)) {
            $stmt->bind_param(str_repeat('s', count($class_params)), ...$class_params);
        }
        $stmt->execute();
        $myStudentsCount = $stmt->get_result()->fetch_assoc()['count'];
    }
    
    // Get recent grades I've entered
    $stmt = prepareStatement($conn, "SELECT g.*, s.first_name, s.last_name, sub.subject_name 
                                   FROM grades g 
                                   JOIN students s ON g.student_id = s.id 
                                   JOIN subjects sub ON g.subject_id = sub.id 
                                   WHERE g.teacher_id = ? 
                                   ORDER BY g.created_at DESC LIMIT 5");
    $stmt->bind_param("i", $teacher_profile['id']);
    $stmt->execute();
    $recentGrades = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Get today's attendance summary for my students
    $todayAttendance = ['present' => 0, 'absent' => 0, 'late' => 0, 'total' => 0];
    if (!empty($class_conditions)) {
        $sql = "SELECT 
                    SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present,
                    SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent,
                    SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late,
                    COUNT(*) as total
                FROM attendance a 
                JOIN students s ON a.student_id = s.id 
                WHERE a.teacher_id = ? AND a.attendance_date = CURDATE()";
        $stmt = prepareStatement($conn, $sql);
        $stmt->bind_param("i", $teacher_profile['id']);
        $stmt->execute();
        $todayAttendance = $stmt->get_result()->fetch_assoc();
    }
    
    // Get subjects I teach
    $stmt = prepareStatement($conn, "SELECT COUNT(DISTINCT subject_id) as count FROM grades WHERE teacher_id = ?");
    $stmt->bind_param("i", $teacher_profile['id']);
    $stmt->execute();
    $subjectsCount = $stmt->get_result()->fetch_assoc()['count'];
    
} catch (Exception $e) {
    error_log("Teacher dashboard error: " . $e->getMessage());
    $myClasses = [];
    $myStudentsCount = 0;
    $recentGrades = [];
    $todayAttendance = ['present' => 0, 'absent' => 0, 'late' => 0, 'total' => 0];
    $subjectsCount = 0;
} finally {
    if (isset($stmt)) $stmt->close();
    closeDatabaseConnection($conn);
}

require_once '../includes/header.php';
?>

<!-- Welcome Message -->
<div class="alert alert-info">
    <h4><i class="fas fa-hand-wave"></i> Welcome back, <?php echo htmlspecialchars($teacher_profile['first_name'] . ' ' . $teacher_profile['last_name']); ?>!</h4>
    <p>Teacher ID: <strong><?php echo htmlspecialchars($teacher_profile['teacher_id']); ?></strong></p>
</div>

<!-- Statistics Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon stat-primary">
            <i class="fas fa-school"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo count($myClasses); ?></h3>
            <p>My Classes</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon stat-success">
            <i class="fas fa-user-graduate"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $myStudentsCount; ?></h3>
            <p>My Students</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon stat-warning">
            <i class="fas fa-book"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $subjectsCount; ?></h3>
            <p>Subjects Teaching</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon stat-danger">
            <i class="fas fa-calendar-check"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $todayAttendance['total']; ?></h3>
            <p>Today's Attendance</p>
        </div>
    </div>
</div>

<!-- Main Content Grid -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
    <!-- My Classes -->
    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-school"></i> My Classes</h5>
        </div>
        <div class="card-body">
            <?php if (!empty($myClasses)): ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Class</th>
                                <th>Section</th>
                                <th>Academic Year</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($myClasses as $class): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($class['class_name']); ?></strong></td>
                                    <td><span class="badge bg-info"><?php echo htmlspecialchars($class['section']); ?></span></td>
                                    <td><?php echo $class['academic_year']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted text-center">No classes assigned yet.</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Today's Attendance Summary -->
    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-calendar-check"></i> Today's Attendance</h5>
        </div>
        <div class="card-body">
            <?php if ($todayAttendance['total'] > 0): ?>
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                    <div class="text-center">
                        <div style="font-size: 1.5rem; font-weight: bold; color: var(--success-color);">
                            <?php echo $todayAttendance['present']; ?>
                        </div>
                        <div style="color: var(--secondary-color);">Present</div>
                    </div>
                    <div class="text-center">
                        <div style="font-size: 1.5rem; font-weight: bold; color: var(--danger-color);">
                            <?php echo $todayAttendance['absent']; ?>
                        </div>
                        <div style="color: var(--secondary-color);">Absent</div>
                    </div>
                    <div class="text-center">
                        <div style="font-size: 1.5rem; font-weight: bold; color: var(--warning-color);">
                            <?php echo $todayAttendance['late']; ?>
                        </div>
                        <div style="color: var(--secondary-color);">Late</div>
                    </div>
                </div>
                <div class="mt-3">
                    <div style="background: #f8f9fa; padding: 1rem; border-radius: 8px; text-center;">
                        <strong>Attendance Rate: 
                            <?php echo round((($todayAttendance['present'] + $todayAttendance['late']) / $todayAttendance['total']) * 100, 1); ?>%
                        </strong>
                    </div>
                </div>
            <?php else: ?>
                <p class="text-muted text-center">No attendance recorded for today.</p>
                <div class="text-center">
                    <a href="attendance.php" class="btn btn-primary">Record Attendance</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Recent Grades -->
<div class="card">
    <div class="card-header">
        <h5><i class="fas fa-star"></i> Recent Grades Entered</h5>
    </div>
    <div class="card-body">
        <?php if (!empty($recentGrades)): ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Subject</th>
                            <th>Exam Type</th>
                            <th>Marks</th>
                            <th>Grade</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentGrades as $grade): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($grade['first_name'] . ' ' . $grade['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($grade['subject_name']); ?></td>
                                <td><?php echo ucfirst($grade['exam_type']); ?></td>
                                <td><?php echo $grade['marks_obtained'] . '/' . $grade['total_marks']; ?></td>
                                <td>
                                    <span class="badge <?php 
                                        echo match($grade['grade']) {
                                            'A' => 'bg-success',
                                            'B' => 'bg-primary',
                                            'C' => 'bg-warning',
                                            'D' => 'bg-info',
                                            default => 'bg-danger'
                                        };
                                    ?>">
                                        <?php echo $grade['grade']; ?>
                                    </span>
                                </td>
                                <td><?php echo formatDate($grade['exam_date']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted text-center">No grades entered yet.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Quick Actions -->
<div class="card">
    <div class="card-header">
        <h5><i class="fas fa-bolt"></i> Quick Actions</h5>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
            <a href="students.php" class="btn btn-primary">
                <i class="fas fa-user-graduate"></i> View My Students
            </a>
            <a href="grades.php" class="btn btn-success">
                <i class="fas fa-star"></i> Enter Grades
            </a>
            <a href="attendance.php" class="btn btn-warning">
                <i class="fas fa-calendar-check"></i> Record Attendance
            </a>
            <a href="profile.php" class="btn btn-info">
                <i class="fas fa-user"></i> My Profile
            </a>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>