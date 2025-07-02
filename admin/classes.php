<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../auth/session.php';

// Require admin role
requireRole(ROLE_ADMIN);

$page_title = "Manage Classes";

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDatabaseConnection();
    
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add':
                    $stmt = prepareStatement($conn, 
                        "INSERT INTO classes (class_name, section, teacher_id, academic_year) VALUES (?, ?, ?, ?)"
                    );
                    $teacher_id = !empty($_POST['teacher_id']) ? $_POST['teacher_id'] : null;
                    $stmt->bind_param("ssis", 
                        $_POST['class_name'], $_POST['section'], $teacher_id, $_POST['academic_year']
                    );
                    
                    if ($stmt->execute()) {
                        redirectWithMessage($_SERVER['PHP_SELF'], "Class added successfully!", "success");
                    } else {
                        throw new Exception("Failed to add class");
                    }
                    break;
                    
                case 'edit':
                    $stmt = prepareStatement($conn, 
                        "UPDATE classes SET class_name=?, section=?, teacher_id=?, academic_year=? WHERE id=?"
                    );
                    $teacher_id = !empty($_POST['teacher_id']) ? $_POST['teacher_id'] : null;
                    $stmt->bind_param("ssisi", 
                        $_POST['class_name'], $_POST['section'], $teacher_id, $_POST['academic_year'], $_POST['class_id']
                    );
                    
                    if ($stmt->execute()) {
                        redirectWithMessage($_SERVER['PHP_SELF'], "Class updated successfully!", "success");
                    } else {
                        throw new Exception("Failed to update class");
                    }
                    break;
                    
                case 'delete':
                    // Check if class has students
                    $checkStmt = prepareStatement($conn, "SELECT COUNT(*) as count FROM students WHERE class = ? AND section = ?");
                    $checkStmt->bind_param("ss", $_POST['class_name'], $_POST['section']);
                    $checkStmt->execute();
                    $result = $checkStmt->get_result()->fetch_assoc();
                    
                    if ($result['count'] > 0) {
                        throw new Exception("Cannot delete class with existing students");
                    }
                    
                    $stmt = prepareStatement($conn, "DELETE FROM classes WHERE id = ?");
                    $stmt->bind_param("i", $_POST['class_id']);
                    
                    if ($stmt->execute()) {
                        redirectWithMessage($_SERVER['PHP_SELF'], "Class deleted successfully!", "success");
                    } else {
                        throw new Exception("Failed to delete class");
                    }
                    break;
            }
        }
    } catch (Exception $e) {
        redirectWithMessage($_SERVER['PHP_SELF'], "Error: " . $e->getMessage(), "error");
    } finally {
        if (isset($stmt)) $stmt->close();
        if (isset($checkStmt)) $checkStmt->close();
        closeDatabaseConnection($conn);
    }
}

// Get classes with teacher info and student count
$conn = getDatabaseConnection();
$search = $_GET['search'] ?? '';
$academic_year = $_GET['academic_year'] ?? date('Y');

$sql = "SELECT c.*, 
        t.first_name as teacher_first_name, t.last_name as teacher_last_name,
        (SELECT COUNT(*) FROM students s WHERE s.class = c.class_name AND s.section = c.section AND s.status = 'active') as student_count
        FROM classes c 
        LEFT JOIN teachers t ON c.teacher_id = t.id 
        WHERE c.academic_year = ?";
$params = [$academic_year];
$types = "s";

if (!empty($search)) {
    $sql .= " AND (c.class_name LIKE ? OR c.section LIKE ? OR t.first_name LIKE ? OR t.last_name LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $types .= "ssss";
}

$sql .= " ORDER BY c.class_name, c.section";

$stmt = prepareStatement($conn, $sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$classes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get teachers for assignment
$teacherStmt = prepareStatement($conn, "SELECT id, first_name, last_name, teacher_id FROM teachers WHERE status = 'active' ORDER BY first_name, last_name");
$teacherStmt->execute();
$teachers = $teacherStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt->close();
$teacherStmt->close();
closeDatabaseConnection($conn);

require_once '../includes/header.php';
?>

<!-- Filters -->
<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <input type="text" class="form-control" name="search" placeholder="Search classes..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-3">
                <select class="form-control" name="academic_year">
                    <?php for ($year = date('Y') - 2; $year <= date('Y') + 1; $year++): ?>
                        <option value="<?php echo $year; ?>" <?php echo $academic_year == $year ? 'selected' : ''; ?>>
                            Academic Year <?php echo $year; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary">Filter</button>
            </div>
            <div class="col-md-3">
                <button type="button" class="btn btn-success" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> Add Class
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Classes Table -->
<div class="card">
    <div class="card-header">
        <h5><i class="fas fa-school"></i> Classes List (Academic Year <?php echo $academic_year; ?>)</h5>
    </div>
    <div class="card-body">
        <?php if (!empty($classes)): ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Class</th>
                            <th>Section</th>
                            <th>Class Teacher</th>
                            <th>Students</th>
                            <th>Academic Year</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($classes as $class): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($class['class_name']); ?></strong></td>
                                <td><span class="badge bg-info"><?php echo htmlspecialchars($class['section']); ?></span></td>
                                <td>
                                    <?php if ($class['teacher_first_name']): ?>
                                        <span class="text-success">
                                            <i class="fas fa-chalkboard-teacher"></i> 
                                            <?php echo htmlspecialchars($class['teacher_first_name'] . ' ' . $class['teacher_last_name']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-warning">
                                            <i class="fas fa-exclamation-triangle"></i> Not Assigned
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?php echo $class['student_count']; ?> students</span>
                                </td>
                                <td><?php echo $class['academic_year']; ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-primary" onclick="editClass(<?php echo htmlspecialchars(json_encode($class)); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="confirmDelete(<?php echo $class['id']; ?>, '<?php echo $class['class_name']; ?>', '<?php echo $class['section']; ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted text-center">No classes found for academic year <?php echo $academic_year; ?>.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Add/Edit Class Modal -->
<div class="modal" id="classModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add Class</h5>
                <button type="button" class="btn-close" onclick="closeModal()"></button>
            </div>
            <form id="classForm" method="POST">
                <div class="modal-body">
                    <input type="hidden" id="action" name="action" value="add">
                    <input type="hidden" id="class_id" name="class_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Class Name *</label>
                        <select class="form-control" id="class_name" name="class_name" required>
                            <option value="">Select Class</option>
                            <option value="9">Class 9</option>
                            <option value="10">Class 10</option>
                            <option value="11">Class 11</option>
                            <option value="12">Class 12</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Section *</label>
                        <select class="form-control" id="section" name="section" required>
                            <option value="">Select Section</option>
                            <option value="A">Section A</option>
                            <option value="B">Section B</option>
                            <option value="C">Section C</option>
                            <option value="D">Section D</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Class Teacher</label>
                        <select class="form-control" id="teacher_id" name="teacher_id">
                            <option value="">Select Teacher (Optional)</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>">
                                    <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name'] . ' (' . $teacher['teacher_id'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Academic Year *</label>
                        <select class="form-control" id="academic_year" name="academic_year" required>
                            <?php for ($year = date('Y') - 1; $year <= date('Y') + 2; $year++): ?>
                                <option value="<?php echo $year; ?>" <?php echo $year == date('Y') ? 'selected' : ''; ?>>
                                    <?php echo $year; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Class</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add Class';
    document.getElementById('action').value = 'add';
    document.getElementById('classForm').reset();
    document.getElementById('academic_year').value = '<?php echo date('Y'); ?>';
    document.getElementById('classModal').style.display = 'block';
}

function editClass(classData) {
    document.getElementById('modalTitle').textContent = 'Edit Class';
    document.getElementById('action').value = 'edit';
    document.getElementById('class_id').value = classData.id;
    document.getElementById('class_name').value = classData.class_name;
    document.getElementById('section').value = classData.section;
    document.getElementById('teacher_id').value = classData.teacher_id || '';
    document.getElementById('academic_year').value = classData.academic_year;
    document.getElementById('classModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('classModal').style.display = 'none';
}

function confirmDelete(classId, className, section) {
    if (confirm(`Are you sure you want to delete Class ${className}-${section}? This action cannot be undone.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="class_id" value="${classId}">
            <input type="hidden" name="class_name" value="${className}">
            <input type="hidden" name="section" value="${section}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('classModal');
    if (event.target === modal) {
        closeModal();
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>