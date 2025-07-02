<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../auth/session.php';

// Require teacher role
requireRole(ROLE_TEACHER);

$page_title = "Manage Attendance";

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
                case 'mark_attendance':
                    $attendance_date = $_POST['attendance_date'];
                    $subject_id = $_POST['subject_id'];
                    
                    // Delete existing attendance for this date and subject
                    $deleteStmt = prepareStatement($conn, 
                        "DELETE FROM attendance WHERE teacher_id = ? AND attendance_date = ? AND subject_id = ?"
                    );
                    $deleteStmt->bind_param("isi", $teacher_profile['id'], $attendance_date, $subject_id);
                    $deleteStmt->execute();
                    
                    // Insert new attendance records
                    $success_count = 0;
                    foreach ($_POST['students'] as $student_id => $status) {
                        $remarks = $_POST['remarks'][$student_id] ?? '';
                        
                        $stmt = prepareStatement($conn, 
                            "INSERT INTO attendance (student_id, subject_id, teacher_id, attendance_date, status, remarks) 
                             VALUES (?, ?, ?, ?, ?, ?)"
                        );
                        $stmt->bind_param("iiisss", 
                            $student_id, $subject_id, $teacher_profile['id'], 
                            $attendance_date, $status, $remarks
                        );
                        
                        if ($stmt->execute()) {
                            $success_count++;
                        }
                    }
                    
                    redirectWithMessage($_SERVER['PHP_SELF'], "Attendance marked for $success_count students!", "success");
                    break;
                    
                case 'bulk_present':
                    $attendance_date = $_POST['attendance_date'];
                    $subject_id = $_POST['subject_id'];
                    $student_ids = $_POST['student_ids'];
                    
                    foreach ($student_ids as $student_id) {
                        $stmt = prepareStatement($conn, 
                            "INSERT INTO attendance (student_id, subject_id, teacher_id, attendance_date, status) 
                             VALUES (?, ?, ?, ?, 'present')
                             ON DUPLICATE KEY UPDATE status = 'present'"
                        );
                        $stmt->bind_param("iiis", $student_id, $subject_id, $teacher_profile['id'], $attendance_date);
                        $stmt->execute();
                    }
                    
                    redirectWithMessage($_SERVER['PHP_SELF'], "All selected students marked present!", "success");
                    break;
            }
        }
    } catch (Exception $e) {
        redirectWithMessage($_SERVER['PHP_SELF'], "Error: " . $e->getMessage(), "error");
    } finally {
        if (isset($stmt)) $stmt->close();
        if (isset($deleteStmt)) $deleteStmt->close();
        closeDatabaseConnection($conn);
    }
}

$conn = getDatabaseConnection();

try {
    // Get my students
    $stmt = prepareStatement($conn, "SELECT DISTINCT c.class_name, c.section FROM classes c WHERE c.teacher_id = ? AND c.academic_year = YEAR(CURDATE())");
    $stmt->bind_param("i", $teacher_profile['id']);
    $stmt->execute();
    $myClasses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $students = [];
    if (!empty($myClasses)) {
        $class_conditions = [];
        $class_params = [];
        foreach ($myClasses as $class) {
            $class_conditions[] = "(class = ? AND section = ?)";
            $class_params[] = $class['class_name'];
            $class_params[] = $class['section'];
        }
        
        $sql = "SELECT id, student_id, first_name, last_name, class, section FROM students WHERE status = 'active' AND (" . implode(' OR ', $class_conditions) . ") ORDER BY class, section, first_name";
        $stmt = prepareStatement($conn, $sql);
        $stmt->bind_param(str_repeat('s', count($class_params)), ...$class_params);
        $stmt->execute();
        $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    // Get subjects
    $stmt = prepareStatement($conn, "SELECT * FROM subjects ORDER BY class, subject_name");
    $stmt->execute();
    $subjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Get attendance for selected date
    $selected_date = $_GET['date'] ?? date('Y-m-d');
    $selected_subject = $_GET['subject_id'] ?? '';
    $student_filter = $_GET['student_id'] ?? '';
    
    $attendance_records = [];
    if (!empty($selected_subject)) {
        $sql = "SELECT a.*, s.first_name, s.last_name, s.student_id, s.class, s.section 
                FROM attendance a 
                JOIN students s ON a.student_id = s.id 
                WHERE a.teacher_id = ? AND a.attendance_date = ? AND a.subject_id = ?";
        $params = [$teacher_profile['id'], $selected_date, $selected_subject];
        $types = "isi";
        
        if (!empty($student_filter)) {
            $sql .= " AND a.student_id = ?";
            $params[] = $student_filter;
            $types .= "i";
        }
        
        $sql .= " ORDER BY s.class, s.section, s.first_name";
        
        $stmt = prepareStatement($conn, $sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $attendance_records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
} catch (Exception $e) {
    error_log("Teacher attendance error: " . $e->getMessage());
    $students = [];
    $subjects = [];
    $attendance_records = [];
} finally {
    if (isset($stmt)) $stmt->close();
    closeDatabaseConnection($conn);
}

require_once '../includes/header.php';
?>

<!-- Attendance Filters -->
<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Date</label>
                <input type="date" class="form-control" name="date" value="<?php echo htmlspecialchars($selected_date); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Subject</label>
                <select class="form-control" name="subject_id">
                    <option value="">Select Subject</option>
                    <?php foreach ($subjects as $subject): ?>
                        <option value="<?php echo $subject['id']; ?>" <?php echo $selected_subject == $subject['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($subject['subject_name'] . ' (Class ' . $subject['class'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Student (Optional)</label>
                <select class="form-control" name="student_id">
                    <option value="">All Students</option>
                    <?php foreach ($students as $student): ?>
                        <option value="<?php echo $student['id']; ?>" <?php echo $student_filter == $student['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary d-block">View Attendance</button>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($selected_subject) && !empty($students)): ?>
<!-- Mark Attendance Form -->
<div class="card">
    <div class="card-header">
        <h5><i class="fas fa-calendar-check"></i> Mark Attendance - <?php echo formatDate($selected_date); ?></h5>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-success" onclick="markAllPresent()">Mark All Present</button>
            <button type="button" class="btn btn-sm btn-danger" onclick="markAllAbsent()">Mark All Absent</button>
        </div>
    </div>
    <div class="card-body">
        <form method="POST" id="attendanceForm">
            <input type="hidden" name="action" value="mark_attendance">
            <input type="hidden" name="attendance_date" value="<?php echo htmlspecialchars($selected_date); ?>">
            <input type="hidden" name="subject_id" value="<?php echo htmlspecialchars($selected_subject); ?>">
            
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Class</th>
                            <th>Status</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): 
                            // Check if attendance already exists
                            $existing_status = 'present';
                            $existing_remarks = '';
                            foreach ($attendance_records as $record) {
                                if ($record['student_id'] == $student['id']) {
                                    $existing_status = $record['status'];
                                    $existing_remarks = $record['remarks'];
                                    break;
                                }
                            }
                        ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($student['student_id']); ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo htmlspecialchars($student['class'] . '-' . $student['section']); ?></span>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <input type="radio" class="btn-check" name="students[<?php echo $student['id']; ?>]" 
                                               id="present_<?php echo $student['id']; ?>" value="present" 
                                               <?php echo $existing_status === 'present' ? 'checked' : ''; ?>>
                                        <label class="btn btn-outline-success btn-sm" for="present_<?php echo $student['id']; ?>">Present</label>
                                        
                                        <input type="radio" class="btn-check" name="students[<?php echo $student['id']; ?>]" 
                                               id="absent_<?php echo $student['id']; ?>" value="absent"
                                               <?php echo $existing_status === 'absent' ? 'checked' : ''; ?>>
                                        <label class="btn btn-outline-danger btn-sm" for="absent_<?php echo $student['id']; ?>">Absent</label>
                                        
                                        <input type="radio" class="btn-check" name="students[<?php echo $student['id']; ?>]" 
                                               id="late_<?php echo $student['id']; ?>" value="late"
                                               <?php echo $existing_status === 'late' ? 'checked' : ''; ?>>
                                        <label class="btn btn-outline-warning btn-sm" for="late_<?php echo $student['id']; ?>">Late</label>
                                    </div>
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm" 
                                           name="remarks[<?php echo $student['id']; ?>]" 
                                           placeholder="Optional remarks"
                                           value="<?php echo htmlspecialchars($existing_remarks); ?>">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="text-center">
                <button type="submit" class="btn btn-primary">Save Attendance</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Attendance History -->
<?php if (!empty($attendance_records)): ?>
<div class="card">
    <div class="card-header">
        <h5><i class="fas fa-history"></i> Attendance Records</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Class</th>
                        <th>Status</th>
                        <th>Remarks</th>
                        <th>Recorded At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($attendance_records as $record): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></strong><br>
                                <small class="text-muted"><?php echo htmlspecialchars($record['student_id']); ?></small>
                            </td>
                            <td>
                                <span class="badge bg-info"><?php echo htmlspecialchars($record['class'] . '-' . $record['section']); ?></span>
                            </td>
                            <td>
                                <span class="badge <?php 
                                    echo match($record['status']) {
                                        'present' => 'bg-success',
                                        'late' => 'bg-warning',
                                        default => 'bg-danger'
                                    };
                                ?>">
                                    <?php echo ucfirst($record['status']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($record['remarks'] ?: 'No remarks'); ?></td>
                            <td><?php echo formatDate($record['created_at'], DISPLAY_DATETIME_FORMAT); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (empty($students)): ?>
<div class="alert alert-warning text-center">
    <h5>No Students Assigned</h5>
    <p>You don't have any students assigned yet. Please contact the administrator.</p>
</div>
<?php elseif (empty($selected_subject)): ?>
<div class="alert alert-info text-center">
    <h5>Select Subject and Date</h5>
    <p>Please select a subject and date to view or mark attendance.</p>
</div>
<?php endif; ?>

<script>
function markAllPresent() {
    const presentRadios = document.querySelectorAll('input[type="radio"][value="present"]');
    presentRadios.forEach(radio => {
        radio.checked = true;
    });
}

function markAllAbsent() {
    const absentRadios = document.querySelectorAll('input[type="radio"][value="absent"]');
    absentRadios.forEach(radio => {
        radio.checked = true;
    });
}

// Auto-submit form when date or subject changes
document.querySelector('input[name="date"]').addEventListener('change', function() {
    this.closest('form').submit();
});

document.querySelector('select[name="subject_id"]').addEventListener('change', function() {
    this.closest('form').submit();
});
</script>

<?php require_once '../includes/footer.php'; ?>