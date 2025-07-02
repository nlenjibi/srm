<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../auth/session.php';

// Require student role
requireRole(ROLE_STUDENT);

$page_title = "My Attendance";

// Get student profile
$student_profile = getUserProfile();
if (!$student_profile) {
    redirectWithMessage("../index.php", "Student profile not found.", "error");
}

$conn = getDatabaseConnection();

try {
    // Get filters
    $month_filter = $_GET['month'] ?? date('Y-m');
    $subject_filter = $_GET['subject_id'] ?? 'all';
    
    // Get attendance records
    $sql = "SELECT a.*, sub.subject_name, t.first_name as teacher_first_name, t.last_name as teacher_last_name
            FROM attendance a 
            JOIN subjects sub ON a.subject_id = sub.id 
            LEFT JOIN teachers t ON a.teacher_id = t.id
            WHERE a.student_id = ?";
    $params = [$student_profile['id']];
    $types = "i";
    
    if ($month_filter) {
        $sql .= " AND DATE_FORMAT(a.attendance_date, '%Y-%m') = ?";
        $params[] = $month_filter;
        $types .= "s";
    }
    
    if ($subject_filter !== 'all') {
        $sql .= " AND a.subject_id = ?";
        $params[] = $subject_filter;
        $types .= "i";
    }
    
    $sql .= " ORDER BY a.attendance_date DESC, sub.subject_name";
    
    $stmt = prepareStatement($conn, $sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $attendance_records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Get subjects for filter
    $subjectStmt = prepareStatement($conn, 
        "SELECT DISTINCT s.id, s.subject_name FROM subjects s 
         JOIN attendance a ON s.id = a.subject_id 
         WHERE a.student_id = ? 
         ORDER BY s.subject_name"
    );
    $subjectStmt->bind_param("i", $student_profile['id']);
    $subjectStmt->execute();
    $subjects = $subjectStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Get attendance statistics for the selected month
    $statsStmt = prepareStatement($conn, 
        "SELECT 
            COUNT(*) as total_days,
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count,
            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count,
            SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_count
         FROM attendance 
         WHERE student_id = ? AND DATE_FORMAT(attendance_date, '%Y-%m') = ?"
    );
    $statsStmt->bind_param("is", $student_profile['id'], $month_filter);
    $statsStmt->execute();
    $stats = $statsStmt->get_result()->fetch_assoc();
    
    // Get overall attendance statistics
    $overallStatsStmt = prepareStatement($conn, 
        "SELECT 
            COUNT(*) as total_days,
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count,
            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count,
            SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_count,
            MIN(attendance_date) as first_date,
            MAX(attendance_date) as last_date
         FROM attendance 
         WHERE student_id = ?"
    );
    $overallStatsStmt->bind_param("i", $student_profile['id']);
    $overallStatsStmt->execute();
    $overall_stats = $overallStatsStmt->get_result()->fetch_assoc();
    
    // Get subject-wise attendance
    $subjectAttendanceStmt = prepareStatement($conn, 
        "SELECT s.subject_name,
            COUNT(a.id) as total_days,
            SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count,
            SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
            SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_count
         FROM subjects s 
         JOIN attendance a ON s.id = a.subject_id 
         WHERE a.student_id = ?
         GROUP BY s.id, s.subject_name
         ORDER BY s.subject_name"
    );
    $subjectAttendanceStmt->bind_param("i", $student_profile['id']);
    $subjectAttendanceStmt->execute();
    $subject_attendance = $subjectAttendanceStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    error_log("Student attendance error: " . $e->getMessage());
    $attendance_records = [];
    $subjects = [];
    $stats = ['total_days' => 0, 'present_count' => 0, 'absent_count' => 0, 'late_count' => 0];
    $overall_stats = ['total_days' => 0, 'present_count' => 0, 'absent_count' => 0, 'late_count' => 0, 'first_date' => null, 'last_date' => null];
    $subject_attendance = [];
} finally {
    if (isset($stmt)) $stmt->close();
    if (isset($subjectStmt)) $subjectStmt->close();
    if (isset($statsStmt)) $statsStmt->close();
    if (isset($overallStatsStmt)) $overallStatsStmt->close();
    if (isset($subjectAttendanceStmt)) $subjectAttendanceStmt->close();
    closeDatabaseConnection($conn);
}

require_once '../includes/header.php';
?>

<!-- Overall Statistics -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body text-center">
                <h3><?php echo $overall_stats['total_days']; ?></h3>
                <p>Total Days</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body text-center">
                <h3><?php echo $overall_stats['present_count']; ?></h3>
                <p>Present Days</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-white">
            <div class="card-body text-center">
                <h3><?php echo $overall_stats['late_count']; ?></h3>
                <p>Late Days</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-danger text-white">
            <div class="card-body text-center">
                <h3><?php echo $overall_stats['absent_count']; ?></h3>
                <p>Absent Days</p>
            </div>
        </div>
    </div>
</div>

<!-- Attendance Rate Card -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row text-center">
            <div class="col-md-6">
                <h5>Overall Attendance Rate</h5>
                <?php if ($overall_stats['total_days'] > 0): ?>
                    <?php $overall_rate = round((($overall_stats['present_count'] + $overall_stats['late_count']) / $overall_stats['total_days']) * 100, 1); ?>
                    <div style="font-size: 3rem; font-weight: bold; color: <?php echo $overall_rate >= 90 ? '#28a745' : ($overall_rate >= 80 ? '#ffc107' : '#dc3545'); ?>;">
                        <?php echo $overall_rate; ?>%
                    </div>
                    <p class="text-muted">
                        From <?php echo formatDate($overall_stats['first_date']); ?> 
                        to <?php echo formatDate($overall_stats['last_date']); ?>
                    </p>
                <?php else: ?>
                    <div style="font-size: 3rem; font-weight: bold; color: #6c757d;">N/A</div>
                    <p class="text-muted">No attendance records found</p>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <h5>This Month (<?php echo date('F Y', strtotime($month_filter)); ?>)</h5>
                <?php if ($stats['total_days'] > 0): ?>
                    <?php $monthly_rate = round((($stats['present_count'] + $stats['late_count']) / $stats['total_days']) * 100, 1); ?>
                    <div style="font-size: 3rem; font-weight: bold; color: <?php echo $monthly_rate >= 90 ? '#28a745' : ($monthly_rate >= 80 ? '#ffc107' : '#dc3545'); ?>;">
                        <?php echo $monthly_rate; ?>%
                    </div>
                    <p class="text-muted"><?php echo $stats['total_days']; ?> days recorded</p>
                <?php else: ?>
                    <div style="font-size: 3rem; font-weight: bold; color: #6c757d;">N/A</div>
                    <p class="text-muted">No attendance records for this month</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Month</label>
                <input type="month" class="form-control" name="month" value="<?php echo htmlspecialchars($month_filter); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Subject</label>
                <select class="form-control" name="subject_id">
                    <option value="all" <?php echo $subject_filter === 'all' ? 'selected' : ''; ?>>All Subjects</option>
                    <?php foreach ($subjects as $subject): ?>
                        <option value="<?php echo $subject['id']; ?>" <?php echo $subject_filter == $subject['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($subject['subject_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary d-block">Filter Attendance</button>
            </div>
        </form>
    </div>
</div>

<!-- Attendance Records -->
<div class="card">
    <div class="card-header">
        <h5><i class="fas fa-calendar-check"></i> Attendance Records (<?php echo count($attendance_records); ?> records)</h5>
    </div>
    <div class="card-body">
        <?php if (!empty($attendance_records)): ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Day</th>
                            <th>Subject</th>
                            <th>Teacher</th>
                            <th>Status</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendance_records as $record): ?>
                            <tr>
                                <td>
                                    <strong><?php echo formatDate($record['attendance_date']); ?></strong>
                                </td>
                                <td>
                                    <small class="text-muted"><?php echo date('l', strtotime($record['attendance_date'])); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($record['subject_name']); ?></td>
                                <td>
                                    <?php if ($record['teacher_first_name']): ?>
                                        <?php echo htmlspecialchars($record['teacher_first_name'] . ' ' . $record['teacher_last_name']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Not assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?php 
                                        echo match($record['status']) {
                                            'present' => 'bg-success',
                                            'late' => 'bg-warning',
                                            default => 'bg-danger'
                                        };
                                    ?>">
                                        <i class="fas <?php 
                                            echo match($record['status']) {
                                                'present' => 'fa-check',
                                                'late' => 'fa-clock',
                                                default => 'fa-times'
                                            };
                                        ?>"></i>
                                        <?php echo ucfirst($record['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($record['remarks']): ?>
                                        <small class="text-muted"><?php echo htmlspecialchars($record['remarks']); ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info text-center">
                <h5>No Attendance Records Found</h5>
                <p>No attendance records found for the selected filters, or you don't have any attendance recorded yet.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Subject-wise Attendance -->
<?php if (!empty($subject_attendance)): ?>
<div class="card">
    <div class="card-header">
        <h5><i class="fas fa-chart-bar"></i> Subject-wise Attendance</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Total Days</th>
                        <th>Present</th>
                        <th>Late</th>
                        <th>Absent</th>
                        <th>Attendance Rate</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subject_attendance as $attendance): ?>
                        <?php $subject_rate = $attendance['total_days'] > 0 ? round((($attendance['present_count'] + $attendance['late_count']) / $attendance['total_days']) * 100, 1) : 0; ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($attendance['subject_name']); ?></strong></td>
                            <td><?php echo $attendance['total_days']; ?></td>
                            <td>
                                <span class="badge bg-success"><?php echo $attendance['present_count']; ?></span>
                            </td>
                            <td>
                                <span class="badge bg-warning"><?php echo $attendance['late_count']; ?></span>
                            </td>
                            <td>
                                <span class="badge bg-danger"><?php echo $attendance['absent_count']; ?></span>
                            </td>
                            <td>
                                <span class="badge <?php echo $subject_rate >= 90 ? 'bg-success' : ($subject_rate >= 80 ? 'bg-warning' : 'bg-danger'); ?>">
                                    <?php echo $subject_rate; ?>%
                                </span>
                            </td>
                            <td>
                                <?php if ($subject_rate >= 90): ?>
                                    <span class="text-success"><i class="fas fa-check-circle"></i> Excellent</span>
                                <?php elseif ($subject_rate >= 80): ?>
                                    <span class="text-warning"><i class="fas fa-exclamation-triangle"></i> Good</span>
                                <?php elseif ($subject_rate >= 75): ?>
                                    <span class="text-info"><i class="fas fa-info-circle"></i> Average</span>
                                <?php else: ?>
                                    <span class="text-danger"><i class="fas fa-times-circle"></i> Poor</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Attendance Tips -->
<div class="card">
    <div class="card-header">
        <h5><i class="fas fa-lightbulb"></i> Attendance Tips</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h6>Attendance Guidelines</h6>
                <ul class="list-unstyled">
                    <li><i class="fas fa-check text-success"></i> <strong>Present:</strong> You attended the class on time</li>
                    <li><i class="fas fa-clock text-warning"></i> <strong>Late:</strong> You arrived after the class started</li>
                    <li><i class="fas fa-times text-danger"></i> <strong>Absent:</strong> You did not attend the class</li>
                </ul>
            </div>
            <div class="col-md-6">
                <h6>Attendance Standards</h6>
                <ul class="list-unstyled">
                    <li><span class="badge bg-success">90%+</span> Excellent attendance</li>
                    <li><span class="badge bg-warning">80-89%</span> Good attendance</li>
                    <li><span class="badge bg-info">75-79%</span> Average attendance</li>
                    <li><span class="badge bg-danger">Below 75%</span> Poor attendance</li>
                </ul>
                <div class="alert alert-warning mt-3">
                    <small><strong>Note:</strong> Minimum 75% attendance is usually required for exam eligibility.</small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-submit form when month changes
document.querySelector('input[name="month"]').addEventListener('change', function() {
    this.closest('form').submit();
});

document.querySelector('select[name="subject_id"]').addEventListener('change', function() {
    this.closest('form').submit();
});
</script>

<?php require_once '../includes/footer.php'; ?>