<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../auth/session.php';

// Require admin role
requireRole(ROLE_ADMIN);

$page_title = "Manage Teachers";

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDatabaseConnection();
    
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add':
                    // Generate teacher ID
                    $teacher_id = generateTeacherId();
                    
                    // Insert teacher
                    $stmt = prepareStatement($conn, 
                        "INSERT INTO teachers (teacher_id, first_name, last_name, date_of_birth, gender, phone, address, qualification, subject_specialization, joining_date) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    );
                    $stmt->bind_param("ssssssssss", 
                        $teacher_id, $_POST['first_name'], $_POST['last_name'], $_POST['date_of_birth'], 
                        $_POST['gender'], $_POST['phone'], $_POST['address'], $_POST['qualification'], 
                        $_POST['subject_specialization'], $_POST['joining_date']
                    );
                    
                    if ($stmt->execute()) {
                        redirectWithMessage($_SERVER['PHP_SELF'], "Teacher added successfully!", "success");
                    } else {
                        throw new Exception("Failed to add teacher");
                    }
                    break;
                    
                case 'edit':
                    $stmt = prepareStatement($conn, 
                        "UPDATE teachers SET first_name=?, last_name=?, date_of_birth=?, gender=?, phone=?, address=?, qualification=?, subject_specialization=?, joining_date=?, status=? 
                         WHERE id=?"
                    );
                    $stmt->bind_param("ssssssssssi", 
                        $_POST['first_name'], $_POST['last_name'], $_POST['date_of_birth'], 
                        $_POST['gender'], $_POST['phone'], $_POST['address'], $_POST['qualification'], 
                        $_POST['subject_specialization'], $_POST['joining_date'], $_POST['status'], $_POST['teacher_id']
                    );
                    
                    if ($stmt->execute()) {
                        redirectWithMessage($_SERVER['PHP_SELF'], "Teacher updated successfully!", "success");
                    } else {
                        throw new Exception("Failed to update teacher");
                    }
                    break;
                    
                case 'delete':
                    $stmt = prepareStatement($conn, "UPDATE teachers SET status = 'inactive' WHERE id = ?");
                    $stmt->bind_param("i", $_POST['teacher_id']);
                    
                    if ($stmt->execute()) {
                        redirectWithMessage($_SERVER['PHP_SELF'], "Teacher deactivated successfully!", "success");
                    } else {
                        throw new Exception("Failed to deactivate teacher");
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

// Get teachers for display
$conn = getDatabaseConnection();
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? 'all';

$sql = "SELECT * FROM teachers WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $sql .= " AND (first_name LIKE ? OR last_name LIKE ? OR teacher_id LIKE ? OR phone LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $types .= "ssss";
}

if ($status !== 'all') {
    $sql .= " AND status = ?";
    $params[] = $status;
    $types .= "s";
}

$sql .= " ORDER BY created_at DESC";

$stmt = prepareStatement($conn, $sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$teachers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt->close();
closeDatabaseConnection($conn);

require_once '../includes/header.php';
?>

<!-- Filters -->
<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <input type="text" class="form-control" name="search" placeholder="Search teachers..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-3">
                <select class="form-control" name="status">
                    <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary">Filter</button>
            </div>
            <div class="col-md-3">
                <button type="button" class="btn btn-success" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> Add Teacher
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Teachers Table -->
<div class="card">
    <div class="card-header">
        <h5><i class="fas fa-chalkboard-teacher"></i> Teachers List</h5>
    </div>
    <div class="card-body">
        <?php if (!empty($teachers)): ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Teacher ID</th>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Qualification</th>
                            <th>Specialization</th>
                            <th>Joining Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($teachers as $teacher): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($teacher['teacher_id']); ?></td>
                                <td><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($teacher['phone']); ?></td>
                                <td><?php echo htmlspecialchars($teacher['qualification']); ?></td>
                                <td><?php echo htmlspecialchars($teacher['subject_specialization']); ?></td>
                                <td><?php echo formatDate($teacher['joining_date']); ?></td>
                                <td>
                                    <span class="badge <?php echo $teacher['status'] === 'active' ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo ucfirst($teacher['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-primary" onclick="editTeacher(<?php echo htmlspecialchars(json_encode($teacher)); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($teacher['status'] === 'active'): ?>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="confirmDelete(<?php echo $teacher['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted text-center">No teachers found.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Add/Edit Teacher Modal -->
<div class="modal" id="teacherModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add Teacher</h5>
                <button type="button" class="btn-close" onclick="closeModal()"></button>
            </div>
            <form id="teacherForm" method="POST">
                <div class="modal-body">
                    <input type="hidden" id="action" name="action" value="add">
                    <input type="hidden" id="teacher_id" name="teacher_id">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">First Name *</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Last Name *</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" class="form-control" id="date_of_birth" name="date_of_birth">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Gender</label>
                            <select class="form-control" id="gender" name="gender">
                                <option value="">Select Gender</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="tel" class="form-control" id="phone" name="phone">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Joining Date *</label>
                            <input type="date" class="form-control" id="joining_date" name="joining_date" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="2"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Qualification *</label>
                            <input type="text" class="form-control" id="qualification" name="qualification" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Subject Specialization</label>
                            <input type="text" class="form-control" id="subject_specialization" name="subject_specialization">
                        </div>
                    </div>
                    
                    <div class="mb-3" id="statusField" style="display: none;">
                        <label class="form-label">Status</label>
                        <select class="form-control" id="status" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Teacher</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add Teacher';
    document.getElementById('action').value = 'add';
    document.getElementById('teacherForm').reset();
    document.getElementById('statusField').style.display = 'none';
    document.getElementById('teacherModal').style.display = 'block';
}

function editTeacher(teacher) {
    document.getElementById('modalTitle').textContent = 'Edit Teacher';
    document.getElementById('action').value = 'edit';
    document.getElementById('teacher_id').value = teacher.id;
    document.getElementById('first_name').value = teacher.first_name;
    document.getElementById('last_name').value = teacher.last_name;
    document.getElementById('date_of_birth').value = teacher.date_of_birth;
    document.getElementById('gender').value = teacher.gender;
    document.getElementById('phone').value = teacher.phone;
    document.getElementById('address').value = teacher.address;
    document.getElementById('qualification').value = teacher.qualification;
    document.getElementById('subject_specialization').value = teacher.subject_specialization;
    document.getElementById('joining_date').value = teacher.joining_date;
    document.getElementById('status').value = teacher.status;
    document.getElementById('statusField').style.display = 'block';
    document.getElementById('teacherModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('teacherModal').style.display = 'none';
}

function confirmDelete(teacherId) {
    if (confirm('Are you sure you want to deactivate this teacher?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="teacher_id" value="${teacherId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('teacherModal');
    if (event.target === modal) {
        closeModal();
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>