<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../auth/session.php';

// Require teacher role
requireRole(ROLE_TEACHER);

$page_title = "Manage Grades";

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
                case 'add':
                    $percentage = ($_POST['marks_obtained'] / $_POST['total_marks']) * 100;
                    $grade = calculateGrade($percentage);
                    
                    $stmt = prepareStatement($conn, 
                        "INSERT INTO grades (student_id, subject_id, teacher_id, exam_type, marks_obtained, total_marks, grade, remarks, exam_date) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    );
                    $stmt->bind_param("iiissdsss", 
                        $_POST['student_id'], $_POST['subject_id'], $teacher_profile['id'], 
                        $_POST['exam_type'], $_POST['marks_obtained'], $_POST['total_marks'], 
                        $grade, $_POST['remarks'], $_POST['exam_date']
                    );
                    
                    if ($stmt->execute()) {
                        redirectWithMessage($_SERVER['PHP_SELF'], "Grade added successfully!", "success");
                    } else {
                        throw new Exception("Failed to add grade");
                    }
                    break;
                    
                case 'edit':
                    $percentage = ($_POST['marks_obtained'] / $_POST['total_marks']) * 100;
                    $grade = calculateGrade($percentage);
                    
                    $stmt = prepareStatement($conn, 
                        "UPDATE grades SET student_id=?, subject_id=?, exam_type=?, marks_obtained=?, total_marks=?, grade=?, remarks=?, exam_date=? 
                         WHERE id=? AND teacher_id=?"
                    );
                    $stmt->bind_param("iissdsssii", 
                        $_POST['student_id'], $_POST['subject_id'], $_POST['exam_type'], 
                        $_POST['marks_obtained'], $_POST['total_marks'], $grade, $_POST['remarks'], 
                        $_POST['exam_date'], $_POST['grade_id'], $teacher_profile['id']
                    );
                    
                    if ($stmt->execute()) {
                        redirectWithMessage($_SERVER['PHP_SELF'], "Grade updated successfully!", "success");
                    } else {
                        throw new Exception("Failed to update grade");
                    }
                    break;
                    
                case 'delete':
                    $stmt = prepareStatement($conn, "DELETE FROM grades WHERE id = ? AND teacher_id = ?");
                    $stmt->bind_param("ii", $_POST['grade_id'], $teacher_profile['id']);
                    
                    if ($stmt->execute()) {
                        redirectWithMessage($_SERVER['PHP_SELF'], "Grade deleted successfully!", "success");
                    } else {
                        throw new Exception("Failed to delete grade");
                    }
                    break;
            }
        }
    } catch (Exception $e) {
        redirectWithMessage($_SERVER['PHP_SELF'], "Error: " . $e->getMessage(), "error");
    } finally {
        if (isset($stmt)) $stmt->close();
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
    
    // Get grades with filters
    $search = $_GET['search'] ?? '';
    $subject_filter = $_GET['subject_id'] ?? 'all';
    $exam_type_filter = $_GET['exam_type'] ?? 'all';
    $student_id_filter = $_GET['student_id'] ?? '';
    
    $sql = "SELECT g.*, s.first_name, s.last_name, s.student_id, s.class, s.section, sub.subject_name 
            FROM grades g 
            JOIN students s ON g.student_id = s.id 
            JOIN subjects sub ON g.subject_id = sub.id 
            WHERE g.teacher_id = ?";
    $params = [$teacher_profile['id']];
    $types = "i";
    
    if (!empty($search)) {
        $sql .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_id LIKE ?)";
        $searchTerm = "%$search%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
        $types .= "sss";
    }
    
    if ($subject_filter !== 'all') {
        $sql .= " AND g.subject_id = ?";
        $params[] = $subject_filter;
        $types .= "i";
    }
    
    if ($exam_type_filter !== 'all') {
        $sql .= " AND g.exam_type = ?";
        $params[] = $exam_type_filter;
        $types .= "s";
    }
    
    if (!empty($student_id_filter)) {
        $sql .= " AND g.student_id = ?";
        $params[] = $student_id_filter;
        $types .= "i";
    }
    
    $sql .= " ORDER BY g.exam_date DESC, s.class, s.section, s.first_name";
    
    $stmt = prepareStatement($conn, $sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $grades = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    error_log("Teacher grades error: " . $e->getMessage());
    $students = [];
    $subjects = [];
    $grades = [];
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
            <div class="col-md-3">
                <input type="text" class="form-control" name="search" placeholder="Search students..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-2">
                <select class="form-control" name="subject_id">
                    <option value="all" <?php echo $subject_filter === 'all' ? 'selected' : ''; ?>>All Subjects</option>
                    <?php foreach ($subjects as $subject): ?>
                        <option value="<?php echo $subject['id']; ?>" <?php echo $subject_filter == $subject['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($subject['subject_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-control" name="exam_type">
                    <option value="all" <?php echo $exam_type_filter === 'all' ? 'selected' : ''; ?>>All Exam Types</option>
                    <option value="quiz" <?php echo $exam_type_filter === 'quiz' ? 'selected' : ''; ?>>Quiz</option>
                    <option value="midterm" <?php echo $exam_type_filter === 'midterm' ? 'selected' : ''; ?>>Midterm</option>
                    <option value="final" <?php echo $exam_type_filter === 'final' ? 'selected' : ''; ?>>Final</option>
                    <option value="assignment" <?php echo $exam_type_filter === 'assignment' ? 'selected' : ''; ?>>Assignment</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-control" name="student_id">
                    <option value="">All Students</option>
                    <?php foreach ($students as $student): ?>
                        <option value="<?php echo $student['id']; ?>" <?php echo $student_id_filter == $student['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary">Filter</button>
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-success" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> Add Grade
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Grades Table -->
<div class="card">
    <div class="card-header">
        <h5><i class="fas fa-star"></i> Grades (<?php echo count($grades); ?> total)</h5>
    </div>
    <div class="card-body">
        <?php if (!empty($grades)): ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Subject</th>
                            <th>Exam Type</th>
                            <th>Marks</th>
                            <th>Percentage</th>
                            <th>Grade</th>
                            <th>Exam Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($grades as $grade): ?>
                            <tr>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($grade['first_name'] . ' ' . $grade['last_name']); ?></strong>
                                    </div>
                                    <small class="text-muted"><?php echo htmlspecialchars($grade['student_id'] . ' | ' . $grade['class'] . '-' . $grade['section']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($grade['subject_name']); ?></td>
                                <td>
                                    <span class="badge bg-info"><?php echo ucfirst($grade['exam_type']); ?></span>
                                </td>
                                <td><?php echo $grade['marks_obtained'] . '/' . $grade['total_marks']; ?></td>
                                <td>
                                    <?php 
                                    $percentage = round(($grade['marks_obtained'] / $grade['total_marks']) * 100, 1);
                                    echo $percentage . '%';
                                    ?>
                                </td>
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
                                <td>
                                    <button type="button" class="btn btn-sm btn-primary" onclick="editGrade(<?php echo htmlspecialchars(json_encode($grade)); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="confirmDelete(<?php echo $grade['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <?php if (empty($students)): ?>
                <div class="alert alert-warning text-center">
                    <h5>No Students Assigned</h5>
                    <p>You don't have any students assigned yet. Please contact the administrator.</p>
                </div>
            <?php else: ?>
                <p class="text-muted text-center">No grades found matching your criteria.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Add/Edit Grade Modal -->
<div class="modal" id="gradeModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add Grade</h5>
                <button type="button" class="btn-close" onclick="closeModal()"></button>
            </div>
            <form id="gradeForm" method="POST">
                <div class="modal-body">
                    <input type="hidden" id="action" name="action" value="add">
                    <input type="hidden" id="grade_id" name="grade_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Student *</label>
                        <select class="form-control" id="student_id" name="student_id" required>
                            <option value="">Select Student</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?php echo $student['id']; ?>">
                                    <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name'] . ' (' . $student['student_id'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Subject *</label>
                        <select class="form-control" id="subject_id" name="subject_id" required>
                            <option value="">Select Subject</option>
                            <?php foreach ($subjects as $subject): ?>
                                <option value="<?php echo $subject['id']; ?>">
                                    <?php echo htmlspecialchars($subject['subject_name'] . ' (Class ' . $subject['class'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Exam Type *</label>
                        <select class="form-control" id="exam_type" name="exam_type" required>
                            <option value="">Select Type</option>
                            <option value="quiz">Quiz</option>
                            <option value="midterm">Midterm</option>
                            <option value="final">Final</option>
                            <option value="assignment">Assignment</option>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Marks Obtained *</label>
                            <input type="number" class="form-control" id="marks_obtained" name="marks_obtained" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Total Marks *</label>
                            <input type="number" class="form-control" id="total_marks" name="total_marks" step="0.01" min="1" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Exam Date *</label>
                        <input type="date" class="form-control" id="exam_date" name="exam_date" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Remarks</label>
                        <textarea class="form-control" id="remarks" name="remarks" rows="2"></textarea>
                    </div>
                    
                    <div id="gradePreview" class="alert alert-info" style="display: none;">
                        <strong>Grade Preview:</strong> <span id="previewText"></span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Grade</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add Grade';
    document.getElementById('action').value = 'add';
    document.getElementById('gradeForm').reset();
    document.getElementById('exam_date').value = new Date().toISOString().split('T')[0];
    document.getElementById('gradePreview').style.display = 'none';
    document.getElementById('gradeModal').style.display = 'block';
}

function editGrade(grade) {
    document.getElementById('modalTitle').textContent = 'Edit Grade';
    document.getElementById('action').value = 'edit';
    document.getElementById('grade_id').value = grade.id;
    document.getElementById('student_id').value = grade.student_id;
    document.getElementById('subject_id').value = grade.subject_id;
    document.getElementById('exam_type').value = grade.exam_type;
    document.getElementById('marks_obtained').value = grade.marks_obtained;
    document.getElementById('total_marks').value = grade.total_marks;
    document.getElementById('exam_date').value = grade.exam_date;
    document.getElementById('remarks').value = grade.remarks;
    updateGradePreview();
    document.getElementById('gradeModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('gradeModal').style.display = 'none';
}

function confirmDelete(gradeId) {
    if (confirm('Are you sure you want to delete this grade? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="grade_id" value="${gradeId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function updateGradePreview() {
    const marksObtained = parseFloat(document.getElementById('marks_obtained').value) || 0;
    const totalMarks = parseFloat(document.getElementById('total_marks').value) || 1;
    const percentage = (marksObtained / totalMarks) * 100;
    
    let grade = 'F';
    if (percentage >= 90) grade = 'A';
    else if (percentage >= 80) grade = 'B';
    else if (percentage >= 70) grade = 'C';
    else if (percentage >= 60) grade = 'D';
    
    if (marksObtained > 0 && totalMarks > 0) {
        document.getElementById('previewText').textContent = 
            `${percentage.toFixed(1)}% - Grade ${grade}`;
        document.getElementById('gradePreview').style.display = 'block';
    } else {
        document.getElementById('gradePreview').style.display = 'none';
    }
}

// Update grade preview when marks change
document.getElementById('marks_obtained').addEventListener('input', updateGradePreview);
document.getElementById('total_marks').addEventListener('input', updateGradePreview);

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('gradeModal');
    if (event.target === modal) {
        closeModal();
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>