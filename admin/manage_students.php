<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../auth/session.php';

// Require admin role
requireRole(ROLE_ADMIN);

$page_title = "Manage Students";

// Get available classes for the filter dropdown
$conn = getDatabaseConnection();
try {
    $stmt = prepareStatement($conn, "SELECT DISTINCT class_name FROM classes ORDER BY class_name");
    $stmt->execute();
    $classes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    $classes = [];
} finally {
    closeDatabaseConnection($conn);
}

require_once '../includes/header.php';
?>

<!-- Page Actions -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-primary" onclick="showAddStudentModal()">
            <i class="fas fa-plus"></i> Add New Student
        </button>
        <button type="button" class="btn btn-secondary" onclick="exportStudents()">
            <i class="fas fa-download"></i> Export
        </button>
    </div>
    
    <div class="d-flex gap-2">
        <div class="form-group" style="margin-bottom: 0;">
            <select class="form-control" id="classFilter" style="width: 150px;">
                <option value="">All Classes</option>
                <?php foreach ($classes as $class): ?>
                    <option value="<?php echo htmlspecialchars($class['class_name']); ?>">
                        Class <?php echo htmlspecialchars($class['class_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin-bottom: 0;">
            <input type="text" class="form-control" id="searchStudents" placeholder="Search students..." style="width: 250px;">
        </div>
    </div>
</div>

<!-- Students Table -->
<div class="card">
    <div class="card-header">
        <h5><i class="fas fa-user-graduate"></i> Students List</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Name</th>
                        <th>Class</th>
                        <th>Section</th>
                        <th>Phone</th>
                        <th>Parent</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="studentsTableBody">
                    <tr>
                        <td colspan="8" class="text-center">
                            <div class="spinner"></div> Loading students...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Student Modal -->
<div class="modal" id="studentModal">
    <div class="modal-dialog" style="max-width: 600px;">
        <div class="modal-header">
            <h5 class="modal-title" id="studentModalTitle">Add New Student</h5>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <form id="studentForm" action="../api/students.php" method="POST">
            <div class="modal-body">
                <input type="hidden" id="studentId" name="id">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="firstName" class="form-label">First Name *</label>
                        <input type="text" class="form-control" id="firstName" name="first_name" required>
                    </div>
                    <div class="form-group">
                        <label for="lastName" class="form-label">Last Name *</label>
                        <input type="text" class="form-control" id="lastName" name="last_name" required>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="dateOfBirth" class="form-label">Date of Birth</label>
                        <input type="date" class="form-control" id="dateOfBirth" name="date_of_birth">
                    </div>
                    <div class="form-group">
                        <label for="gender" class="form-label">Gender</label>
                        <select class="form-control form-select" id="gender" name="gender">
                            <option value="">Select Gender</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="phone" class="form-label">Phone</label>
                        <input type="tel" class="form-control" id="phone" name="phone">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="address" class="form-label">Address</label>
                    <textarea class="form-control" id="address" name="address" rows="2"></textarea>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="parentName" class="form-label">Parent/Guardian Name</label>
                        <input type="text" class="form-control" id="parentName" name="parent_name">
                    </div>
                    <div class="form-group">
                        <label for="parentPhone" class="form-label">Parent Phone</label>
                        <input type="tel" class="form-control" id="parentPhone" name="parent_phone">
                    </div>
                    <div class="form-group">
                        <label for="parentEmail" class="form-label">Parent Email</label>
                        <input type="email" class="form-control" id="parentEmail" name="parent_email">
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="admissionDate" class="form-label">Admission Date</label>
                        <input type="date" class="form-control" id="admissionDate" name="admission_date" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="class" class="form-label">Class *</label>
                        <select class="form-control form-select" id="class" name="class" required>
                            <option value="">Select Class</option>
                            <option value="1">Class 1</option>
                            <option value="2">Class 2</option>
                            <option value="3">Class 3</option>
                            <option value="4">Class 4</option>
                            <option value="5">Class 5</option>
                            <option value="6">Class 6</option>
                            <option value="7">Class 7</option>
                            <option value="8">Class 8</option>
                            <option value="9">Class 9</option>
                            <option value="10">Class 10</option>
                            <option value="11">Class 11</option>
                            <option value="12">Class 12</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="section" class="form-label">Section</label>
                        <select class="form-control form-select" id="section" name="section">
                            <option value="">Select Section</option>
                            <option value="A">Section A</option>
                            <option value="B">Section B</option>
                            <option value="C">Section C</option>
                            <option value="D">Section D</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-control form-select" id="status" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary modal-close">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Student
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Initialize student manager when page loads
document.addEventListener('DOMContentLoaded', function() {
    if (typeof StudentManager !== 'undefined') {
        window.studentManager = new StudentManager();
    }
});

// Function to show add student modal
function showAddStudentModal() {
    document.getElementById('studentModalTitle').textContent = 'Add New Student';
    document.getElementById('studentForm').reset();
    document.getElementById('studentId').value = '';
    document.getElementById('admissionDate').value = '<?php echo date('Y-m-d'); ?>';
    
    if (window.studentManager) {
        window.studentManager.showModal('studentModal');
    }
}

// Function to export students (placeholder)
function exportStudents() {
    Utils.showAlert('Export functionality will be implemented soon.', 'info');
}

// Enhanced student table rendering with better error handling
if (window.StudentManager) {
    const originalRenderTable = StudentManager.prototype.renderStudentTable;
    StudentManager.prototype.renderStudentTable = function(students) {
        const tableBody = document.getElementById('studentsTableBody');
        if (!tableBody) return;

        if (!students) {
            tableBody.innerHTML = '<tr><td colspan="8" class="text-center text-danger">Error loading students</td></tr>';
            return;
        }

        if (students.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No students found</td></tr>';
            return;
        }

        const rows = students.map(student => `
            <tr>
                <td><strong>${student.student_id || 'N/A'}</strong></td>
                <td>${student.first_name} ${student.last_name}</td>
                <td>${student.class || '-'}</td>
                <td>${student.section || '-'}</td>
                <td>${student.phone || '-'}</td>
                <td>${student.parent_name || '-'}</td>
                <td>
                    <span class="badge ${student.status === 'active' ? 'bg-success' : 'bg-danger'}">
                        ${student.status ? student.status.charAt(0).toUpperCase() + student.status.slice(1) : 'Unknown'}
                    </span>
                </td>
                <td>
                    <div class="d-flex gap-1">
                        <button class="btn btn-sm btn-primary" onclick="studentManager.editStudent(${student.id})" title="Edit Student">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-info" onclick="viewStudent(${student.id})" title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="studentManager.deleteStudent(${student.id})" title="Delete Student">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');

        tableBody.innerHTML = rows;
    };
}

// Function to view student details (placeholder)
function viewStudent(id) {
    Utils.showAlert('View student details functionality will be implemented soon.', 'info');
}
</script>

<?php require_once '../includes/footer.php'; ?>