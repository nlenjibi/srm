<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../auth/session.php';

// Require admin role
requireRole(ROLE_ADMIN);

$page_title = "Reports";

$conn = getDatabaseConnection();

// Get statistics for reports
$stats = [];

try {
    // Student statistics
    $stmt = prepareStatement($conn, "SELECT 
        COUNT(*) as total_students,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_students,
        SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_students
        FROM students");
    $stmt->execute();
    $stats['students'] = $stmt->get_result()->fetch_assoc();
    
    // Teacher statistics
    $stmt = prepareStatement($conn, "SELECT 
        COUNT(*) as total_teachers,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_teachers
        FROM teachers");
    $stmt->execute();
    $stats['teachers'] = $stmt->get_result()->fetch_assoc();
    
    // Grade statistics
    $stmt = prepareStatement($conn, "SELECT 
        grade,
        COUNT(*) as count
        FROM grades 
        GROUP BY grade 
        ORDER BY FIELD(grade, 'A', 'B', 'C', 'D', 'F')");
    $stmt->execute();
    $stats['grades'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Attendance statistics (this month)
    $stmt = prepareStatement($conn, "SELECT 
        status,
        COUNT(*) as count
        FROM attendance 
        WHERE attendance_date >= DATE_FORMAT(NOW(), '%Y-%m-01')
        GROUP BY status");
    $stmt->execute();
    $stats['attendance'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Class-wise student count
    $stmt = prepareStatement($conn, "SELECT 
        CONCAT(class, '-', section) as class_section,
        COUNT(*) as student_count
        FROM students 
        WHERE status = 'active'
        GROUP BY class, section
        ORDER BY class, section");
    $stmt->execute();
    $stats['classes'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    error_log("Reports query error: " . $e->getMessage());
    $stats = [];
} finally {
    if (isset($stmt)) $stmt->close();
    closeDatabaseConnection($conn);
}

require_once '../includes/header.php';
?>

<!-- Reports Overview -->
<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-user-graduate"></i> Student Statistics</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-4">
                        <h3 class="text-primary"><?php echo $stats['students']['total_students'] ?? 0; ?></h3>
                        <p>Total Students</p>
                    </div>
                    <div class="col-4">
                        <h3 class="text-success"><?php echo $stats['students']['active_students'] ?? 0; ?></h3>
                        <p>Active</p>
                    </div>
                    <div class="col-4">
                        <h3 class="text-danger"><?php echo $stats['students']['inactive_students'] ?? 0; ?></h3>
                        <p>Inactive</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-chalkboard-teacher"></i> Teacher Statistics</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6">
                        <h3 class="text-primary"><?php echo $stats['teachers']['total_teachers'] ?? 0; ?></h3>
                        <p>Total Teachers</p>
                    </div>
                    <div class="col-6">
                        <h3 class="text-success"><?php echo $stats['teachers']['active_teachers'] ?? 0; ?></h3>
                        <p>Active</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Grade Distribution -->
<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-star"></i> Grade Distribution</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($stats['grades'])): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Grade</th>
                                    <th>Count</th>
                                    <th>Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_grades = array_sum(array_column($stats['grades'], 'count'));
                                foreach ($stats['grades'] as $grade): 
                                    $percentage = $total_grades > 0 ? round(($grade['count'] / $total_grades) * 100, 1) : 0;
                                ?>
                                    <tr>
                                        <td>
                                            <span class="badge <?php 
                                                echo match($grade['grade']) {
                                                    'A' => 'bg-success',
                                                    'B' => 'bg-primary',
                                                    'C' => 'bg-warning',
                                                    'D' => 'bg-info',
                                                    default => 'bg-danger'
                                                };
                                            ?>"><?php echo $grade['grade']; ?></span>
                                        </td>
                                        <td><?php echo $grade['count']; ?></td>
                                        <td><?php echo $percentage; ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No grades recorded yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-calendar-check"></i> Attendance This Month</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($stats['attendance'])): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Status</th>
                                    <th>Count</th>
                                    <th>Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_attendance = array_sum(array_column($stats['attendance'], 'count'));
                                foreach ($stats['attendance'] as $attendance): 
                                    $percentage = $total_attendance > 0 ? round(($attendance['count'] / $total_attendance) * 100, 1) : 0;
                                ?>
                                    <tr>
                                        <td>
                                            <span class="badge <?php 
                                                echo match($attendance['status']) {
                                                    'present' => 'bg-success',
                                                    'late' => 'bg-warning',
                                                    default => 'bg-danger'
                                                };
                                            ?>"><?php echo ucfirst($attendance['status']); ?></span>
                                        </td>
                                        <td><?php echo $attendance['count']; ?></td>
                                        <td><?php echo $percentage; ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No attendance recorded this month.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Class-wise Distribution -->
<div class="card">
    <div class="card-header">
        <h5><i class="fas fa-school"></i> Class-wise Student Distribution</h5>
    </div>
    <div class="card-body">
        <?php if (!empty($stats['classes'])): ?>
            <div class="row">
                <?php foreach ($stats['classes'] as $class): ?>
                    <div class="col-md-3 mb-3">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h5><?php echo htmlspecialchars($class['class_section']); ?></h5>
                                <h3 class="text-primary"><?php echo $class['student_count']; ?></h3>
                                <p class="text-muted">Students</p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-muted">No classes with students found.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Report Generation -->
<div class="card">
    <div class="card-header">
        <h5><i class="fas fa-file-export"></i> Generate Reports</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4 mb-3">
                <div class="card">
                    <div class="card-body text-center">
                        <i class="fas fa-users fa-3x text-primary mb-3"></i>
                        <h6>Student Report</h6>
                        <p class="text-muted">Complete student list with details</p>
                        <button class="btn btn-primary" onclick="generateReport('students')">Generate</button>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-3">
                <div class="card">
                    <div class="card-body text-center">
                        <i class="fas fa-star fa-3x text-success mb-3"></i>
                        <h6>Grade Report</h6>
                        <p class="text-muted">Grade summary by class/subject</p>
                        <button class="btn btn-success" onclick="generateReport('grades')">Generate</button>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-3">
                <div class="card">
                    <div class="card-body text-center">
                        <i class="fas fa-calendar-check fa-3x text-warning mb-3"></i>
                        <h6>Attendance Report</h6>
                        <p class="text-muted">Attendance summary by date range</p>
                        <button class="btn btn-warning" onclick="generateReport('attendance')">Generate</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function generateReport(type) {
    let url = '';
    let params = new URLSearchParams();
    
    switch(type) {
        case 'students':
            // Generate CSV for all students
            params.append('action', 'export_students');
            url = '../api/reports.php?' + params.toString();
            break;
            
        case 'grades':
            // Generate CSV for grades
            params.append('action', 'export_grades');
            url = '../api/reports.php?' + params.toString();
            break;
            
        case 'attendance':
            // Generate CSV for attendance
            const startDate = prompt('Enter start date (YYYY-MM-DD):');
            const endDate = prompt('Enter end date (YYYY-MM-DD):');
            
            if (startDate && endDate) {
                params.append('action', 'export_attendance');
                params.append('start_date', startDate);
                params.append('end_date', endDate);
                url = '../api/reports.php?' + params.toString();
            } else {
                alert('Please provide both start and end dates.');
                return;
            }
            break;
    }
    
    if (url) {
        // Open in new window to download
        window.open(url, '_blank');
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>