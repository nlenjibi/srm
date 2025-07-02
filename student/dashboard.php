<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../auth/session.php';

// Require student role
requireRole(ROLE_STUDENT);

$page_title = "Student Dashboard";

// Get student profile
$student_profile = getUserProfile();
if (!$student_profile) {
    redirectWithMessage("../index.php", "Student profile not found.", "error");
}

$conn = getDatabaseConnection();

try {
    // Get my grades
    $stmt = prepareStatement($conn, 
        "SELECT g.*, sub.subject_name, sub.credits,
         (g.marks_obtained / g.total_marks * 100) as percentage
         FROM grades g 
         JOIN subjects sub ON g.subject_id = sub.id 
         WHERE g.student_id = ? 
         ORDER BY g.exam_date DESC LIMIT 5"
    );
    $stmt->bind_param("i", $student_profile['id']);
    $stmt->execute();
    $recent_grades = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Get grade statistics
    $stmt = prepareStatement($conn, 
        "SELECT 
            COUNT(*) as total_grades,
            AVG(marks_obtained/total_marks*100) as avg_percentage,
            COUNT(CASE WHEN grade = 'A' THEN 1 END) as grade_a,
            COUNT(CASE WHEN grade = 'B' THEN 1 END) as grade_b,
            COUNT(CASE WHEN grade = 'C' THEN 1 END) as grade_c,
            COUNT(CASE WHEN grade = 'D' THEN 1 END) as grade_d,
            COUNT(CASE WHEN grade = 'F' THEN 1 END) as grade_f
         FROM grades WHERE student_id = ?"
    );
    $stmt->bind_param("i", $student_profile['id']);
    $stmt->execute();
    $grade_stats = $stmt->get_result()->fetch_assoc();
    
    // Get attendance statistics (current month)
    $stmt = prepareStatement($conn, 
        "SELECT 
            COUNT(*) as total_attendance,
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count,
            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count,
            SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_count
         FROM attendance 
         WHERE student_id = ? AND attendance_date >= DATE_FORMAT(NOW(), '%Y-%m-01')"
    );
    $stmt->bind_param("i", $student_profile['id']);
    $stmt->execute();
    $attendance_stats = $stmt->get_result()->fetch_assoc();
    
    // Get subjects I'm enrolled in
    $stmt = prepareStatement($conn, 
        "SELECT DISTINCT s.* FROM subjects s 
         JOIN grades g ON s.id = g.subject_id 
         WHERE g.student_id = ?
         ORDER BY s.class, s.subject_name"
    );
    $stmt->bind_param("i", $student_profile['id']);
    $stmt->execute();
    $my_subjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Get upcoming activities (if any)
    $stmt = prepareStatement($conn, 
        "SELECT ea.*, sa.participation_date 
         FROM extracurricular_activities ea 
         LEFT JOIN student_activities sa ON ea.id = sa.activity_id AND sa.student_id = ?
         WHERE ea.activity_date >= CURDATE()
         ORDER BY ea.activity_date ASC LIMIT 3"
    );
    $stmt->bind_param("i", $student_profile['id']);
    $stmt->execute();
    $upcoming_activities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    error_log("Student dashboard error: " . $e->getMessage());
    $recent_grades = [];
    $grade_stats = ['total_grades' => 0, 'avg_percentage' => 0, 'grade_a' => 0, 'grade_b' => 0, 'grade_c' => 0, 'grade_d' => 0, 'grade_f' => 0];
    $attendance_stats = ['total_attendance' => 0, 'present_count' => 0, 'absent_count' => 0, 'late_count' => 0];
    $my_subjects = [];
    $upcoming_activities = [];
} finally {
    if (isset($stmt)) $stmt->close();
    closeDatabaseConnection($conn);
}

require_once '../includes/header.php';
?>

<!-- Welcome Message -->
<div class="alert alert-info">
    <h4><i class="fas fa-graduation-cap"></i> Welcome back, <?php echo htmlspecialchars($student_profile['first_name'] . ' ' . $student_profile['last_name']); ?>!</h4>
    <p>Student ID: <strong><?php echo htmlspecialchars($student_profile['student_id']); ?></strong> | Class: <strong><?php echo htmlspecialchars($student_profile['class'] . '-' . $student_profile['section']); ?></strong></p>
</div>

<!-- Statistics Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon stat-primary">
            <i class="fas fa-star"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $grade_stats['total_grades']; ?></h3>
            <p>Total Grades</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon stat-success">
            <i class="fas fa-chart-line"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $grade_stats['avg_percentage'] ? round($grade_stats['avg_percentage'], 1) . '%' : 'N/A'; ?></h3>
            <p>Average Grade</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon stat-warning">
            <i class="fas fa-calendar-check"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $attendance_stats['total_attendance'] ? round(($attendance_stats['present_count'] + $attendance_stats['late_count']) / $attendance_stats['total_attendance'] * 100, 1) . '%' : 'N/A'; ?></h3>
            <p>Attendance Rate</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon stat-danger">
            <i class="fas fa-book"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo count($my_subjects); ?></h3>
            <p>Subjects</p>
        </div>
    </div>
</div>

<!-- Main Content Grid -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
    <!-- Recent Grades -->
    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-star"></i> Recent Grades</h5>
        </div>
        <div class="card-body">
            <?php if (!empty($recent_grades)): ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>Exam</th>
                                <th>Score</th>
                                <th>Grade</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_grades as $grade): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($grade['subject_name']); ?></td>
                                    <td><?php echo ucfirst($grade['exam_type']); ?></td>
                                    <td><?php echo round($grade['percentage'], 1); ?>%</td>
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
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-center mt-3">
                    <a href="grades.php" class="btn btn-sm btn-primary">View All Grades</a>
                </div>
            <?php else: ?>
                <p class="text-muted text-center">No grades recorded yet.</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Attendance This Month -->
    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-calendar-check"></i> Attendance This Month</h5>
        </div>
        <div class="card-body">
            <?php if ($attendance_stats['total_attendance'] > 0): ?>
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                    <div class="text-center">
                        <div style="font-size: 1.5rem; font-weight: bold; color: var(--success-color);">
                            <?php echo $attendance_stats['present_count']; ?>
                        </div>
                        <div style="color: var(--secondary-color);">Present</div>
                    </div>
                    <div class="text-center">
                        <div style="font-size: 1.5rem; font-weight: bold; color: var(--danger-color);">
                            <?php echo $attendance_stats['absent_count']; ?>
                        </div>
                        <div style="color: var(--secondary-color);">Absent</div>
                    </div>
                    <div class="text-center">
                        <div style="font-size: 1.5rem; font-weight: bold; color: var(--warning-color);">
                            <?php echo $attendance_stats['late_count']; ?>
                        </div>
                        <div style="color: var(--secondary-color);">Late</div>
                    </div>
                </div>
                <div class="mt-3">
                    <div style="background: #f8f9fa; padding: 1rem; border-radius: 8px; text-center;">
                        <strong>Attendance Rate: 
                            <?php echo round((($attendance_stats['present_count'] + $attendance_stats['late_count']) / $attendance_stats['total_attendance']) * 100, 1); ?>%
                        </strong>
                    </div>
                </div>
                <div class="text-center mt-3">
                    <a href="attendance.php" class="btn btn-sm btn-warning">View Full Attendance</a>
                </div>
            <?php else: ?>
                <p class="text-muted text-center">No attendance recorded this month.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Grade Distribution -->
<?php if ($grade_stats['total_grades'] > 0): ?>
<div class="card">
    <div class="card-header">
        <h5><i class="fas fa-chart-pie"></i> Grade Distribution</h5>
    </div>
    <div class="card-body">
        <div class="row text-center">
            <div class="col">
                <div class="grade-stat">
                    <div class="grade-circle grade-a"><?php echo $grade_stats['grade_a']; ?></div>
                    <div>Grade A</div>
                </div>
            </div>
            <div class="col">
                <div class="grade-stat">
                    <div class="grade-circle grade-b"><?php echo $grade_stats['grade_b']; ?></div>
                    <div>Grade B</div>
                </div>
            </div>
            <div class="col">
                <div class="grade-stat">
                    <div class="grade-circle grade-c"><?php echo $grade_stats['grade_c']; ?></div>
                    <div>Grade C</div>
                </div>
            </div>
            <div class="col">
                <div class="grade-stat">
                    <div class="grade-circle grade-d"><?php echo $grade_stats['grade_d']; ?></div>
                    <div>Grade D</div>
                </div>
            </div>
            <div class="col">
                <div class="grade-stat">
                    <div class="grade-circle grade-f"><?php echo $grade_stats['grade_f']; ?></div>
                    <div>Grade F</div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- My Subjects -->
<div class="card">
    <div class="card-header">
        <h5><i class="fas fa-book"></i> My Subjects</h5>
    </div>
    <div class="card-body">
        <?php if (!empty($my_subjects)): ?>
            <div class="row">
                <?php foreach ($my_subjects as $subject): ?>
                    <div class="col-md-4 mb-3">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h6><?php echo htmlspecialchars($subject['subject_name']); ?></h6>
                                <p class="text-muted"><?php echo htmlspecialchars($subject['subject_code']); ?></p>
                                <div class="small">
                                    <span class="badge bg-info"><?php echo $subject['credits']; ?> Credits</span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-muted text-center">No subjects assigned yet.</p>
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
            <a href="grades.php" class="btn btn-primary">
                <i class="fas fa-star"></i> View My Grades
            </a>
            <a href="attendance.php" class="btn btn-warning">
                <i class="fas fa-calendar-check"></i> Check Attendance
            </a>
            <a href="schedule.php" class="btn btn-info">
                <i class="fas fa-calendar"></i> Class Schedule
            </a>
            <a href="profile.php" class="btn btn-success">
                <i class="fas fa-user"></i> My Profile
            </a>
        </div>
    </div>
</div>

<style>
.grade-stat {
    padding: 1rem;
}

.grade-circle {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 0.5rem;
    font-size: 1.5rem;
    font-weight: bold;
    color: white;
}

.grade-a { background: var(--success-color); }
.grade-b { background: var(--primary-color); }
.grade-c { background: var(--warning-color); }
.grade-d { background: var(--info-color); }
.grade-f { background: var(--danger-color); }
</style>

<?php require_once '../includes/footer.php'; ?>