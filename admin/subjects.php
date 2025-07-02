<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../auth/session.php';

// Require admin role
requireRole(ROLE_ADMIN);

$page_title = "Manage Subjects";

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDatabaseConnection();
    
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add':
                    $stmt = prepareStatement($conn, 
                        "INSERT INTO subjects (subject_code, subject_name, description, credits, class) VALUES (?, ?, ?, ?, ?)"
                    );
                    $stmt->bind_param("sssis", 
                        $_POST['subject_code'], $_POST['subject_name'], $_POST['description'], 
                        $_POST['credits'], $_POST['class']
                    );
                    
                    if ($stmt->execute()) {
                        redirectWithMessage($_SERVER['PHP_SELF'], "Subject added successfully!", "success");
                    } else {
                        throw new Exception("Failed to add subject");
                    }
                    break;
                    
                case 'edit':
                    $stmt = prepareStatement($conn, 
                        "UPDATE subjects SET subject_code=?, subject_name=?, description=?, credits=?, class=? WHERE id=?"
                    );
                    $stmt->bind_param("sssisi", 
                        $_POST['subject_code'], $_POST['subject_name'], $_POST['description'], 
                        $_POST['credits'], $_POST['class'], $_POST['subject_id']
                    );
                    
                    if ($stmt->execute()) {
                        redirectWithMessage($_SERVER['PHP_SELF'], "Subject updated successfully!", "success");
                    } else {
                        throw new Exception("Failed to update subject");
                    }
                    break;
                    
                case 'delete':
                    // Check if subject has grades
                    $checkStmt = prepareStatement($conn, "SELECT COUNT(*) as count FROM grades WHERE subject_id = ?");
                    $checkStmt->bind_param("i", $_POST['subject_id']);
                    $checkStmt->execute();
                    $result = $checkStmt->get_result()->fetch_assoc();
                    
                    if ($result['count'] > 0) {
                        throw new Exception("Cannot delete subject with existing grades");
                    }
                    
                    $stmt = prepareStatement($conn, "DELETE FROM subjects WHERE id = ?");
                    $stmt->bind_param("i", $_POST['subject_id']);
                    
                    if ($stmt->execute()) {
                        redirectWithMessage($_SERVER['PHP_SELF'], "Subject deleted successfully!", "success");
                    } else {
                        throw new Exception("Failed to delete subject");
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

// Get subjects for display
$conn = getDatabaseConnection();
$search = $_GET['search'] ?? '';
$class_filter = $_GET['class'] ?? 'all';

$sql = "SELECT * FROM subjects WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $sql .= " AND (subject_name LIKE ? OR subject_code LIKE ? OR description LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    $types .= "sss";
}

if ($class_filter !== 'all') {
    $sql .= " AND class = ?";
    $params[] = $class_filter;
    $types .= "s";
}

$sql .= " ORDER BY class, subject_name";

$stmt = prepareStatement($conn, $sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$subjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get unique classes for filter
$classStmt = prepareStatement($conn, "SELECT DISTINCT class FROM subjects ORDER BY class");
$classStmt->execute();
$classes = $classStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt->close();
$classStmt->close();
closeDatabaseConnection($conn);

require_once '../includes/header.php';
?>

<!-- Filters -->
<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <input type="text" class="form-control" name="search" placeholder="Search subjects..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-3">
                <select class="form-control" name="class">
                    <option value="all" <?php echo $class_filter === 'all' ? 'selected' : ''; ?>>All Classes</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?php echo $class['class']; ?>" <?php echo $class_filter === $class['class'] ? 'selected' : ''; ?>>
                            Class <?php echo $class['class']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary">Filter</button>
            </div>
            <div class="col-md-3">
                <button type="button" class="btn btn-success" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> Add Subject
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Subjects Table -->
<div class="card">
    <div class="card-header">
        <h5><i class="fas fa-book"></i> Subjects List</h5>
    </div>
    <div class="card-body">
        <?php if (!empty($subjects)): ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Subject Code</th>
                            <th>Subject Name</th>
                            <th>Class</th>
                            <th>Credits</th>
                            <th>Description</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subjects as $subject): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($subject['subject_code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                                <td>
                                    <span class="badge bg-primary">Class <?php echo $subject['class']; ?></span>
                                </td>
                                <td><?php echo $subject['credits']; ?></td>
                                <td><?php echo htmlspecialchars($subject['description'] ?: 'N/A'); ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-primary" onclick="editSubject(<?php echo htmlspecialchars(json_encode($subject)); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="confirmDelete(<?php echo $subject['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted text-center">No subjects found.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Add/Edit Subject Modal -->
<div class="modal" id="subjectModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add Subject</h5>
                <button type="button" class="btn-close" onclick="closeModal()"></button>
            </div>
            <form id="subjectForm" method="POST">
                <div class="modal-body">
                    <input type="hidden" id="action" name="action" value="add">
                    <input type="hidden" id="subject_id" name="subject_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Subject Code *</label>
                        <input type="text" class="form-control" id="subject_code" name="subject_code" required>
                        <div class="form-text">e.g., MATH101, ENG101</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Subject Name *</label>
                        <input type="text" class="form-control" id="subject_name" name="subject_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Class *</label>
                        <select class="form-control" id="class" name="class" required>
                            <option value="">Select Class</option>
                            <option value="9">Class 9</option>
                            <option value="10">Class 10</option>
                            <option value="11">Class 11</option>
                            <option value="12">Class 12</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Credits *</label>
                        <input type="number" class="form-control" id="credits" name="credits" min="1" max="10" value="1" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Subject</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add Subject';
    document.getElementById('action').value = 'add';
    document.getElementById('subjectForm').reset();
    document.getElementById('credits').value = '1';
    document.getElementById('subjectModal').style.display = 'block';
}

function editSubject(subject) {
    document.getElementById('modalTitle').textContent = 'Edit Subject';
    document.getElementById('action').value = 'edit';
    document.getElementById('subject_id').value = subject.id;
    document.getElementById('subject_code').value = subject.subject_code;
    document.getElementById('subject_name').value = subject.subject_name;
    document.getElementById('class').value = subject.class;
    document.getElementById('credits').value = subject.credits;
    document.getElementById('description').value = subject.description;
    document.getElementById('subjectModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('subjectModal').style.display = 'none';
}

function confirmDelete(subjectId) {
    if (confirm('Are you sure you want to delete this subject? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="subject_id" value="${subjectId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('subjectModal');
    if (event.target === modal) {
        closeModal();
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>