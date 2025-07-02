<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../auth/session.php';

// Require admin role
requireRole(ROLE_ADMIN);

$page_title = "Admin Dashboard";

// Get dashboard statistics
$conn = getDatabaseConnection();

try {
    // Total students
    $stmt = prepareStatement($conn, "SELECT COUNT(*) as total FROM students WHERE status = 'active'");
    $stmt->execute();
    $totalStudents = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    
    // Total teachers
    $stmt = prepareStatement($conn, "SELECT COUNT(*) as total FROM teachers WHERE status = 'active'");
    $stmt->execute();
    $totalTeachers = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    
    // Total subjects
    $stmt = prepareStatement($conn, "SELECT COUNT(*) as total FROM subjects");
    $stmt->execute();
    $totalSubjects = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    
    // Total classes
    $stmt = prepareStatement($conn, "SELECT COUNT(DISTINCT CONCAT(class_name, '-', section)) as total FROM classes WHERE academic_year = YEAR(CURDATE())");
    $stmt->execute();
    $totalClasses = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    
    // Recent students (last 5)
    $stmt = prepareStatement($conn, "SELECT * FROM students ORDER BY created_at DESC LIMIT 5");
    $stmt->execute();
    $recentStudents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Recent grades (last 5)
    $stmt = prepareStatement($conn, "SELECT g.*, s.first_name, s.last_name, sub.subject_name 
                                   FROM grades g 
                                   JOIN students s ON g.student_id = s.id 
                                   JOIN subjects sub ON g.subject_id = sub.id 
                                   ORDER BY g.created_at DESC LIMIT 5");
    $stmt->execute();
    $recentGrades = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Attendance summary for today
    $stmt = prepareStatement($conn, "SELECT 
                                        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
                                        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
                                        COUNT(*) as total
                                     FROM attendance 
                                     WHERE attendance_date = CURDATE()");
    $stmt->execute();
    $todayAttendance = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
} catch (Exception $e) {
    error_log("Dashboard query error: " . $e->getMessage());
    $totalStudents = $totalTeachers = $totalSubjects = $totalClasses = 0;
    $recentStudents = $recentGrades = [];
    $todayAttendance = ['present' => 0, 'absent' => 0, 'total' => 0];
} finally {
    closeDatabaseConnection($conn);
}

require_once '../includes/header.php';
?>

<!-- Dashboard Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon stat-primary">
            <i class="fas fa-user-graduate"></i>
        </div>
        <div class="stat-info">
            <h3 id="totalStudents"><?php echo $totalStudents; ?></h3>
            <p>Total Students</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon stat-success">
            <i class="fas fa-chalkboard-teacher"></i>
        </div>
        <div class="stat-info">
            <h3 id="totalTeachers"><?php echo $totalTeachers; ?></h3>
            <p>Total Teachers</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon stat-warning">
            <i class="fas fa-book"></i>
        </div>
        <div class="stat-info">
            <h3 id="totalSubjects"><?php echo $totalSubjects; ?></h3>
            <p>Total Subjects</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon stat-danger">
            <i class="fas fa-school"></i>
        </div>
        <div class="stat-info">
            <h3 id="activeClasses"><?php echo $totalClasses; ?></h3>
            <p>Active Classes</p>
        </div>
    </div>
</div>

<!-- Main Content Grid -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
    <!-- Recent Students -->
    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-user-graduate"></i> Recent Students</h5>
        </div>
        <div class="card-body">
            <?php if (!empty($recentStudents)): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Class</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentStudents as $student): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                                    <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($student['class'] . '-' . $student['section']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $student['status'] === 'active' ? 'bg-success' : 'bg-danger'; ?>">
                                            <?php echo ucfirst($student['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted text-center">No students registered yet.</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Today's Attendance -->
    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-calendar-check"></i> Today's Attendance</h5>
        </div>
        <div class="card-body">
            <?php if ($todayAttendance['total'] > 0): ?>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="text-center">
                        <div style="font-size: 2rem; font-weight: bold; color: var(--success-color);">
                            <?php echo $todayAttendance['present']; ?>
                        </div>
                        <div style="color: var(--secondary-color);">Present</div>
                    </div>
                    <div class="text-center">
                        <div style="font-size: 2rem; font-weight: bold; color: var(--danger-color);">
                            <?php echo $todayAttendance['absent']; ?>
                        </div>
                        <div style="color: var(--secondary-color);">Absent</div>
                    </div>
                </div>
                <div class="mt-3">
                    <div style="background: #f8f9fa; padding: 1rem; border-radius: 8px; text-center;">
                        <strong>Attendance Rate: 
                            <?php echo round(($todayAttendance['present'] / $todayAttendance['total']) * 100, 1); ?>%
                        </strong>
                    </div>
                </div>
            <?php else: ?>
                <p class="text-muted text-center">No attendance recorded for today.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Recent Grades -->
<div class="card">
    <div class="card-header">
        <h5><i class="fas fa-star"></i> Recent Grades</h5>
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
            <p class="text-muted text-center">No grades recorded yet.</p>
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
            <a href="manage_students.php" class="btn btn-primary">
                <i class="fas fa-user-plus"></i> Add New Student
            </a>
            <a href="manage_teachers.php" class="btn btn-success">
                <i class="fas fa-chalkboard-teacher"></i> Add New Teacher
            </a>
            <a href="subjects.php" class="btn btn-warning">
                <i class="fas fa-book-open"></i> Manage Subjects
            </a>
            <a href="reports.php" class="btn btn-info">
                <i class="fas fa-chart-line"></i> Generate Reports
            </a>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>