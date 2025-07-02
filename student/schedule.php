<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../auth/session.php';

// Require student role
requireRole(ROLE_STUDENT);

$page_title = "Class Schedule";

// Get student profile
$student_profile = getUserProfile();
if (!$student_profile) {
    redirectWithMessage("../index.php", "Student profile not found.", "error");
}

$conn = getDatabaseConnection();

try {
    // Get subjects for my class
    $stmt = prepareStatement($conn, 
        "SELECT DISTINCT s.*, t.first_name as teacher_first_name, t.last_name as teacher_last_name
         FROM subjects s 
         LEFT JOIN grades g ON s.id = g.subject_id AND g.student_id = ?
         LEFT JOIN teachers t ON g.teacher_id = t.id
         WHERE s.class = ?
         ORDER BY s.subject_name"
    );
    $stmt->bind_param("is", $student_profile['id'], $student_profile['class']);
    $stmt->execute();
    $subjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Get class teacher
    $classTeacherStmt = prepareStatement($conn, 
        "SELECT t.* FROM teachers t 
         JOIN classes c ON t.id = c.teacher_id 
         WHERE c.class_name = ? AND c.section = ? AND c.academic_year = YEAR(CURDATE())"
    );
    $classTeacherStmt->bind_param("ss", $student_profile['class'], $student_profile['section']);
    $classTeacherStmt->execute();
    $classTeacherResult = $classTeacherStmt->get_result();
    $class_teacher = $classTeacherResult->num_rows > 0 ? $classTeacherResult->fetch_assoc() : null;
    
    // Get classmates
    $classmatesStmt = prepareStatement($conn, 
        "SELECT first_name, last_name, student_id FROM students 
         WHERE class = ? AND section = ? AND status = 'active' AND id != ?
         ORDER BY first_name, last_name"
    );
    $classmatesStmt->bind_param("ssi", $student_profile['class'], $student_profile['section'], $student_profile['id']);
    $classmatesStmt->execute();
    $classmates = $classmatesStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Get recent attendance for schedule context
    $recentAttendanceStmt = prepareStatement($conn, 
        "SELECT a.attendance_date, a.status, s.subject_name 
         FROM attendance a 
         JOIN subjects s ON a.subject_id = s.id 
         WHERE a.student_id = ? 
         ORDER BY a.attendance_date DESC 
         LIMIT 7"
    );
    $recentAttendanceStmt->bind_param("i", $student_profile['id']);
    $recentAttendanceStmt->execute();
    $recent_attendance = $recentAttendanceStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    error_log("Student schedule error: " . $e->getMessage());
    $subjects = [];
    $class_teacher = null;
    $classmates = [];
    $recent_attendance = [];
} finally {
    if (isset($stmt)) $stmt->close();
    if (isset($classTeacherStmt)) $classTeacherStmt->close();
    if (isset($classmatesStmt)) $classmatesStmt->close();
    if (isset($recentAttendanceStmt)) $recentAttendanceStmt->close();
    closeDatabaseConnection($conn);
}

// Sample schedule structure (in a real system, this would come from database)
$schedule = [
    'Monday' => [
        ['time' => '08:00-08:45', 'subject' => 'Mathematics', 'teacher' => 'Mr. Johnson'],
        ['time' => '08:45-09:30', 'subject' => 'English', 'teacher' => 'Ms. Smith'],
        ['time' => '09:30-09:45', 'subject' => 'Break', 'teacher' => ''],
        ['time' => '09:45-10:30', 'subject' => 'Science', 'teacher' => 'Dr. Brown'],
        ['time' => '10:30-11:15', 'subject' => 'History', 'teacher' => 'Mr. Davis'],
        ['time' => '11:15-12:00', 'subject' => 'Geography', 'teacher' => 'Ms. Wilson'],
        ['time' => '12:00-13:00', 'subject' => 'Lunch Break', 'teacher' => ''],
        ['time' => '13:00-13:45', 'subject' => 'Physical Education', 'teacher' => 'Mr. Taylor'],
        ['time' => '13:45-14:30', 'subject' => 'Art', 'teacher' => 'Ms. Anderson'],
    ],
    'Tuesday' => [
        ['time' => '08:00-08:45', 'subject' => 'Science', 'teacher' => 'Dr. Brown'],
        ['time' => '08:45-09:30', 'subject' => 'Mathematics', 'teacher' => 'Mr. Johnson'],
        ['time' => '09:30-09:45', 'subject' => 'Break', 'teacher' => ''],
        ['time' => '09:45-10:30', 'subject' => 'English', 'teacher' => 'Ms. Smith'],
        ['time' => '10:30-11:15', 'subject' => 'Geography', 'teacher' => 'Ms. Wilson'],
        ['time' => '11:15-12:00', 'subject' => 'History', 'teacher' => 'Mr. Davis'],
        ['time' => '12:00-13:00', 'subject' => 'Lunch Break', 'teacher' => ''],
        ['time' => '13:00-13:45', 'subject' => 'Computer Science', 'teacher' => 'Mr. Lee'],
        ['time' => '13:45-14:30', 'subject' => 'Library', 'teacher' => 'Ms. Clark'],
    ],
    'Wednesday' => [
        ['time' => '08:00-08:45', 'subject' => 'English', 'teacher' => 'Ms. Smith'],
        ['time' => '08:45-09:30', 'subject' => 'Science', 'teacher' => 'Dr. Brown'],
        ['time' => '09:30-09:45', 'subject' => 'Break', 'teacher' => ''],
        ['time' => '09:45-10:30', 'subject' => 'Mathematics', 'teacher' => 'Mr. Johnson'],
        ['time' => '10:30-11:15', 'subject' => 'History', 'teacher' => 'Mr. Davis'],
        ['time' => '11:15-12:00', 'subject' => 'Geography', 'teacher' => 'Ms. Wilson'],
        ['time' => '12:00-13:00', 'subject' => 'Lunch Break', 'teacher' => ''],
        ['time' => '13:00-13:45', 'subject' => 'Music', 'teacher' => 'Mr. Garcia'],
        ['time' => '13:45-14:30', 'subject' => 'Study Hall', 'teacher' => 'Various'],
    ],
    'Thursday' => [
        ['time' => '08:00-08:45', 'subject' => 'History', 'teacher' => 'Mr. Davis'],
        ['time' => '08:45-09:30', 'subject' => 'Geography', 'teacher' => 'Ms. Wilson'],
        ['time' => '09:30-09:45', 'subject' => 'Break', 'teacher' => ''],
        ['time' => '09:45-10:30', 'subject' => 'Science', 'teacher' => 'Dr. Brown'],
        ['time' => '10:30-11:15', 'subject' => 'English', 'teacher' => 'Ms. Smith'],
        ['time' => '11:15-12:00', 'subject' => 'Mathematics', 'teacher' => 'Mr. Johnson'],
        ['time' => '12:00-13:00', 'subject' => 'Lunch Break', 'teacher' => ''],
        ['time' => '13:00-13:45', 'subject' => 'Physical Education', 'teacher' => 'Mr. Taylor'],
        ['time' => '13:45-14:30', 'subject' => 'Computer Science', 'teacher' => 'Mr. Lee'],
    ],
    'Friday' => [
        ['time' => '08:00-08:45', 'subject' => 'Mathematics', 'teacher' => 'Mr. Johnson'],
        ['time' => '08:45-09:30', 'subject' => 'English', 'teacher' => 'Ms. Smith'],
        ['time' => '09:30-09:45', 'subject' => 'Break', 'teacher' => ''],
        ['time' => '09:45-10:30', 'subject' => 'Science', 'teacher' => 'Dr. Brown'],
        ['time' => '10:30-11:15', 'subject' => 'Geography', 'teacher' => 'Ms. Wilson'],
        ['time' => '11:15-12:00', 'subject' => 'History', 'teacher' => 'Mr. Davis'],
        ['time' => '12:00-13:00', 'subject' => 'Lunch Break', 'teacher' => ''],
        ['time' => '13:00-13:45', 'subject' => 'Art', 'teacher' => 'Ms. Anderson'],
        ['time' => '13:45-14:30', 'subject' => 'Assembly', 'teacher' => 'All Teachers'],
    ]
];

$current_day = date('l');

require_once '../includes/header.php';
?>

<!-- Class Information -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <h5>Class Information</h5>
                <table class="table table-borderless">
                    <tr>
                        <th width="50%">Class:</th>
                        <td>
                            <span class="badge bg-primary fs-6">
                                <?php echo htmlspecialchars($student_profile['class'] . '-' . $student_profile['section']); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Class Teacher:</th>
                        <td>
                            <?php if ($class_teacher): ?>
                                <strong><?php echo htmlspecialchars($class_teacher['first_name'] . ' ' . $class_teacher['last_name']); ?></strong>
                            <?php else: ?>
                                <span class="text-muted">Not assigned</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Total Students:</th>
                        <td><?php echo count($classmates) + 1; ?> students</td>
                    </tr>
                    <tr>
                        <th>Academic Year:</th>
                        <td><?php echo date('Y'); ?></td>
                    </tr>
                </table>
            </div>
            <div class="col-md-4">
                <h5>Today's Schedule</h5>
                <div class="alert alert-info">
                    <strong><?php echo $current_day; ?>, <?php echo date('F j, Y'); ?></strong>
                    <br>
                    <small class="text-muted">Current time: <?php echo date('h:i A'); ?></small>
                </div>
                <?php if (isset($schedule[$current_day])): ?>
                    <div class="small">
                        <strong>Next classes:</strong>
                        <?php 
                        $current_time = date('H:i');
                        $next_classes = 0;
                        foreach ($schedule[$current_day] as $period):
                            if ($next_classes >= 3) break;
                            $period_start = substr($period['time'], 0, 5);
                            if ($period_start > $current_time && !in_array($period['subject'], ['Break', 'Lunch Break'])):
                                $next_classes++;
                        ?>
                            <div><?php echo $period['time']; ?> - <?php echo $period['subject']; ?></div>
                        <?php 
                            endif;
                        endforeach; 
                        if ($next_classes == 0): ?>
                            <div class="text-muted">No more classes today</div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-md-4">
                <h5>Recent Attendance</h5>
                <?php if (!empty($recent_attendance)): ?>
                    <div class="small">
                        <?php foreach (array_slice($recent_attendance, 0, 5) as $attendance): ?>
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span><?php echo date('M j', strtotime($attendance['attendance_date'])); ?> - <?php echo $attendance['subject_name']; ?></span>
                                <span class="badge badge-sm <?php 
                                    echo match($attendance['status']) {
                                        'present' => 'bg-success',
                                        'late' => 'bg-warning',
                                        default => 'bg-danger'
                                    };
                                ?>">
                                    <?php echo ucfirst($attendance['status']); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                        <a href="attendance.php" class="btn btn-sm btn-outline-primary mt-2">View Full Attendance</a>
                    </div>
                <?php else: ?>
                    <p class="text-muted small">No recent attendance records</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Weekly Schedule -->
<div class="card">
    <div class="card-header">
        <h5><i class="fas fa-calendar-week"></i> Weekly Class Schedule</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered schedule-table">
                <thead class="table-dark">
                    <tr>
                        <th width="10%">Time</th>
                        <th width="18%">Monday</th>
                        <th width="18%">Tuesday</th>
                        <th width="18%">Wednesday</th>
                        <th width="18%">Thursday</th>
                        <th width="18%">Friday</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    // Get maximum number of periods across all days
                    $max_periods = max(array_map('count', $schedule));
                    
                    for ($i = 0; $i < $max_periods; $i++): 
                    ?>
                        <tr>
                            <td class="time-slot">
                                <?php echo isset($schedule['Monday'][$i]) ? $schedule['Monday'][$i]['time'] : ''; ?>
                            </td>
                            <?php foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'] as $day): ?>
                                <td class="schedule-cell <?php echo $day === $current_day ? 'current-day' : ''; ?>">
                                    <?php if (isset($schedule[$day][$i])): ?>
                                        <?php $period = $schedule[$day][$i]; ?>
                                        <div class="period <?php echo strtolower(str_replace(' ', '-', $period['subject'])); ?>">
                                            <div class="subject-name"><?php echo htmlspecialchars($period['subject']); ?></div>
                                            <?php if ($period['teacher']): ?>
                                                <div class="teacher-name"><?php echo htmlspecialchars($period['teacher']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- My Subjects -->
<div class="card">
    <div class="card-header">
        <h5><i class="fas fa-book"></i> My Subjects</h5>
    </div>
    <div class="card-body">
        <?php if (!empty($subjects)): ?>
            <div class="row">
                <?php foreach ($subjects as $subject): ?>
                    <div class="col-md-4 mb-3">
                        <div class="card subject-card">
                            <div class="card-body text-center">
                                <h6><?php echo htmlspecialchars($subject['subject_name']); ?></h6>
                                <p class="text-muted"><?php echo htmlspecialchars($subject['subject_code']); ?></p>
                                <div class="small">
                                    <span class="badge bg-info"><?php echo $subject['credits']; ?> Credits</span>
                                </div>
                                <?php if ($subject['teacher_first_name']): ?>
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            Teacher: <?php echo htmlspecialchars($subject['teacher_first_name'] . ' ' . $subject['teacher_last_name']); ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info text-center">
                <h6>No Subjects Found</h6>
                <p>No subjects found for your class. Please contact your class teacher.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Classmates -->
<div class="card">
    <div class="card-header">
        <h5><i class="fas fa-users"></i> My Classmates</h5>
    </div>
    <div class="card-body">
        <?php if (!empty($classmates)): ?>
            <div class="row">
                <?php foreach ($classmates as $classmate): ?>
                    <div class="col-md-4 col-lg-3 mb-2">
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm me-2">
                                <div style="width: 30px; height: 30px; background: #6c757d; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 0.8rem; font-weight: bold;">
                                    <?php echo strtoupper(substr($classmate['first_name'], 0, 1) . substr($classmate['last_name'], 0, 1)); ?>
                                </div>
                            </div>
                            <div>
                                <div class="fw-bold small"><?php echo htmlspecialchars($classmate['first_name'] . ' ' . $classmate['last_name']); ?></div>
                                <div class="text-muted" style="font-size: 0.75rem;"><?php echo htmlspecialchars($classmate['student_id']); ?></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="mt-3">
                <small class="text-muted">Total: <?php echo count($classmates) + 1; ?> students (including you)</small>
            </div>
        <?php else: ?>
            <p class="text-muted">No other students found in your class.</p>
        <?php endif; ?>
    </div>
</div>

<style>
.schedule-table th {
    text-align: center;
    vertical-align: middle;
}

.schedule-cell {
    padding: 8px;
    vertical-align: top;
    height: 80px;
}

.current-day {
    background-color: #e3f2fd !important;
}

.time-slot {
    font-weight: bold;
    text-align: center;
    vertical-align: middle;
    background-color: #f8f9fa;
}

.period {
    padding: 4px;
    border-radius: 4px;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.subject-name {
    font-weight: bold;
    font-size: 0.85rem;
    line-height: 1.2;
}

.teacher-name {
    font-size: 0.75rem;
    color: #6c757d;
    margin-top: 2px;
}

.mathematics, .english, .science, .history, .geography {
    background-color: #e3f2fd;
    border-left: 4px solid #2196f3;
}

.physical-education, .art, .music {
    background-color: #f3e5f5;
    border-left: 4px solid #9c27b0;
}

.computer-science, .library {
    background-color: #e8f5e8;
    border-left: 4px solid #4caf50;
}

.break, .lunch-break {
    background-color: #fff3e0;
    border-left: 4px solid #ff9800;
    font-style: italic;
}

.study-hall, .assembly {
    background-color: #fafafa;
    border-left: 4px solid #757575;
}

.subject-card {
    transition: transform 0.2s;
}

.subject-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.badge-sm {
    font-size: 0.65rem;
    padding: 0.25rem 0.4rem;
}
</style>

<?php require_once '../includes/footer.php'; ?>