<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../auth/session.php';

// Require teacher role
requireRole(ROLE_TEACHER);

$page_title = "My Students";

// Get teacher profile
$teacher_profile = getUserProfile();
if (!$teacher_profile) {
    redirectWithMessage("../index.php", "Teacher profile not found.", "error");
}

$conn = getDatabaseConnection();

try {
    // Get my assigned classes
    $stmt = prepareStatement($conn, "SELECT * FROM classes WHERE teacher_id = ? AND academic_year = YEAR(CURDATE())");
    $stmt->bind_param("i", $teacher_profile['id']);
    $stmt->execute();
    $myClasses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Get students in my classes
    $students = [];
    if (!empty($myClasses)) {
        $class_conditions = [];
        $class_params = [];
        foreach ($myClasses as $class) {
            $class_conditions[] = "(s.class = ? AND s.section = ?)";
            $class_params[] = $class['class_name'];
            $class_params[] = $class['section'];
        }
        
        $search = $_GET['search'] ?? '';
        $class_filter = $_GET['class_filter'] ?? 'all';
        $status_filter = $_GET['status'] ?? 'active';
        
        $sql = "SELECT s.*, 
                (SELECT AVG(marks_obtained/total_marks*100) FROM grades WHERE student_id = s.id AND teacher_id = ?) as avg_grade
                FROM students s 
                WHERE (" . implode(' OR ', $class_conditions) . ")";
        $params = [$teacher_profile['id'], ...$class_params];
        $types = "i" . str_repeat('s', count($class_params));
        
        if (!empty($search)) {
            $sql .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_id LIKE ?)";
            $searchTerm = "%$search%";
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
            $types .= "sss";
        }
        
        if ($class_filter !== 'all') {
            $sql .= " AND s.class = ?";
            $params[] = $class_filter;
            $types .= "s";
        }
        
        if ($status_filter !== 'all') {
            $sql .= " AND s.status = ?";
            $params[] = $status_filter;
            $types .= "s";
        }
        
        $sql .= " ORDER BY s.class, s.section, s.first_name, s.last_name";
        
        $stmt = prepareStatement($conn, $sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
} catch (Exception $e) {
    error_log("Teacher students error: " . $e->getMessage());
    $students = [];
    $myClasses = [];
} finally {
    if (isset($stmt)) $stmt->close();
    closeDatabaseConnection($conn);
}

require_once '../includes/header.php';
?>

<!-- Filters -->
<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <input type="text" class="form-control" name="search" placeholder="Search students..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
            </div>
            <div class="col-md-3">
                <select class="form-control" name="class_filter">
                    <option value="all" <?php echo ($_GET['class_filter'] ?? 'all') === 'all' ? 'selected' : ''; ?>>All Classes</option>
                    <?php foreach ($myClasses as $class): ?>
                        <option value="<?php echo $class['class_name']; ?>" <?php echo ($_GET['class_filter'] ?? '') === $class['class_name'] ? 'selected' : ''; ?>>
                            Class <?php echo $class['class_name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-control" name="status">
                    <option value="active" <?php echo ($_GET['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo ($_GET['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    <option value="all" <?php echo ($_GET['status'] ?? '') === 'all' ? 'selected' : ''; ?>>All Status</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary">Filter</button>
            </div>
        </form>
    </div>
</div>

<!-- Students Table -->
<div class="card">
    <div class="card-header">
        <h5><i class="fas fa-user-graduate"></i> My Students (<?php echo count($students); ?> total)</h5>
    </div>
    <div class="card-body">
        <?php if (!empty($students)): ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Name</th>
                            <th>Class</th>
                            <th>Contact</th>
                            <th>Average Grade</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($student['student_id']); ?></strong></td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></strong>
                                    </div>
                                    <small class="text-muted"><?php echo htmlspecialchars($student['parent_name']); ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-primary">
                                        <?php echo htmlspecialchars($student['class'] . '-' . $student['section']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($student['phone']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($student['parent_phone']); ?></small>
                                </td>
                                <td>
                                    <?php if ($student['avg_grade']): ?>
                                        <span class="badge <?php 
                                            $avg = round($student['avg_grade'], 1);
                                            echo $avg >= 90 ? 'bg-success' : ($avg >= 80 ? 'bg-primary' : ($avg >= 70 ? 'bg-warning' : 'bg-danger'));
                                        ?>">
                                            <?php echo round($student['avg_grade'], 1); ?>%
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">No grades</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo $student['status'] === 'active' ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo ucfirst($student['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-info" onclick="viewStudent(<?php echo htmlspecialchars(json_encode($student)); ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <a href="grades.php?student_id=<?php echo $student['id']; ?>" class="btn btn-sm btn-success">
                                        <i class="fas fa-star"></i>
                                    </a>
                                    <a href="attendance.php?student_id=<?php echo $student['id']; ?>" class="btn btn-sm btn-warning">
                                        <i class="fas fa-calendar-check"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <?php if (empty($myClasses)): ?>
                <div class="alert alert-warning text-center">
                    <h5>No Classes Assigned</h5>
                    <p>You don't have any classes assigned yet. Please contact the administrator.</p>
                </div>
            <?php else: ?>
                <p class="text-muted text-center">No students found matching your criteria.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Student Details Modal -->
<div class="modal" id="studentModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Student Details</h5>
                <button type="button" class="btn-close" onclick="closeModal()"></button>
            </div>
            <div class="modal-body" id="studentDetails">
                <!-- Student details will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function viewStudent(student) {
    const modal = document.getElementById('studentModal');
    const details = document.getElementById('studentDetails');
    
    details.innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <h6>Personal Information</h6>
                <table class="table table-sm">
                    <tr><th>Student ID:</th><td>${student.student_id}</td></tr>
                    <tr><th>Name:</th><td>${student.first_name} ${student.last_name}</td></tr>
                    <tr><th>Date of Birth:</th><td>${student.date_of_birth || 'N/A'}</td></tr>
                    <tr><th>Gender:</th><td>${student.gender || 'N/A'}</td></tr>
                    <tr><th>Phone:</th><td>${student.phone || 'N/A'}</td></tr>
                    <tr><th>Address:</th><td>${student.address || 'N/A'}</td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6>Academic Information</h6>
                <table class="table table-sm">
                    <tr><th>Class:</th><td>${student.class}-${student.section}</td></tr>
                    <tr><th>Admission Date:</th><td>${student.admission_date || 'N/A'}</td></tr>
                    <tr><th>Status:</th><td><span class="badge ${student.status === 'active' ? 'bg-success' : 'bg-danger'}">${student.status}</span></td></tr>
                </table>
                
                <h6>Parent Information</h6>
                <table class="table table-sm">
                    <tr><th>Parent Name:</th><td>${student.parent_name || 'N/A'}</td></tr>
                    <tr><th>Parent Phone:</th><td>${student.parent_phone || 'N/A'}</td></tr>
                    <tr><th>Parent Email:</th><td>${student.parent_email || 'N/A'}</td></tr>
                </table>
            </div>
        </div>
    `;
    
    modal.style.display = 'block';
}

function closeModal() {
    document.getElementById('studentModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('studentModal');
    if (event.target === modal) {
        closeModal();
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>