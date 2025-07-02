<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../auth/session.php';

// Require admin role
requireRole(ROLE_ADMIN);

$page_title = "Manage Users";

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDatabaseConnection();
    
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add':
                    // Hash password
                    $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    
                    // Insert user
                    $stmt = prepareStatement($conn, 
                        "INSERT INTO users (username, email, password, user_type) VALUES (?, ?, ?, ?)"
                    );
                    $stmt->bind_param("ssss", $_POST['username'], $_POST['email'], $hashedPassword, $_POST['user_type']);
                    
                    if ($stmt->execute()) {
                        redirectWithMessage($_SERVER['PHP_SELF'], "User created successfully!", "success");
                    } else {
                        throw new Exception("Failed to create user");
                    }
                    break;
                    
                case 'edit':
                    if (!empty($_POST['password'])) {
                        // Update with new password
                        $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
                        $stmt = prepareStatement($conn, 
                            "UPDATE users SET username=?, email=?, password=?, user_type=? WHERE id=?"
                        );
                        $stmt->bind_param("ssssi", $_POST['username'], $_POST['email'], $hashedPassword, $_POST['user_type'], $_POST['user_id']);
                    } else {
                        // Update without password change
                        $stmt = prepareStatement($conn, 
                            "UPDATE users SET username=?, email=?, user_type=? WHERE id=?"
                        );
                        $stmt->bind_param("sssi", $_POST['username'], $_POST['email'], $_POST['user_type'], $_POST['user_id']);
                    }
                    
                    if ($stmt->execute()) {
                        redirectWithMessage($_SERVER['PHP_SELF'], "User updated successfully!", "success");
                    } else {
                        throw new Exception("Failed to update user");
                    }
                    break;
                    
                case 'delete':
                    // Check if user has linked profiles
                    $checkStmt = prepareStatement($conn, 
                        "SELECT 
                            (SELECT COUNT(*) FROM students WHERE user_id = ?) as student_count,
                            (SELECT COUNT(*) FROM teachers WHERE user_id = ?) as teacher_count"
                    );
                    $checkStmt->bind_param("ii", $_POST['user_id'], $_POST['user_id']);
                    $checkStmt->execute();
                    $result = $checkStmt->get_result()->fetch_assoc();
                    
                    if ($result['student_count'] > 0 || $result['teacher_count'] > 0) {
                        throw new Exception("Cannot delete user with linked student or teacher profile");
                    }
                    
                    $stmt = prepareStatement($conn, "DELETE FROM users WHERE id = ? AND id != ?");
                    $stmt->bind_param("ii", $_POST['user_id'], $_SESSION['user_id']); // Prevent self-deletion
                    
                    if ($stmt->execute()) {
                        redirectWithMessage($_SERVER['PHP_SELF'], "User deleted successfully!", "success");
                    } else {
                        throw new Exception("Failed to delete user");
                    }
                    break;
                    
                case 'link_student':
                    $stmt = prepareStatement($conn, "UPDATE students SET user_id = ? WHERE id = ?");
                    $stmt->bind_param("ii", $_POST['user_id'], $_POST['student_id']);
                    
                    if ($stmt->execute()) {
                        redirectWithMessage($_SERVER['PHP_SELF'], "Student linked successfully!", "success");
                    } else {
                        throw new Exception("Failed to link student");
                    }
                    break;
                    
                case 'link_teacher':
                    $stmt = prepareStatement($conn, "UPDATE teachers SET user_id = ? WHERE id = ?");
                    $stmt->bind_param("ii", $_POST['user_id'], $_POST['teacher_id']);
                    
                    if ($stmt->execute()) {
                        redirectWithMessage($_SERVER['PHP_SELF'], "Teacher linked successfully!", "success");
                    } else {
                        throw new Exception("Failed to link teacher");
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

// Get users with linked profiles
$conn = getDatabaseConnection();
$search = $_GET['search'] ?? '';
$user_type = $_GET['user_type'] ?? 'all';

$sql = "SELECT u.*, 
        s.first_name as student_first_name, s.last_name as student_last_name, s.student_id,
        t.first_name as teacher_first_name, t.last_name as teacher_last_name, t.teacher_id
        FROM users u 
        LEFT JOIN students s ON u.id = s.user_id 
        LEFT JOIN teachers t ON u.id = t.user_id 
        WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $sql .= " AND (u.username LIKE ? OR u.email LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm]);
    $types .= "ss";
}

if ($user_type !== 'all') {
    $sql .= " AND u.user_type = ?";
    $params[] = $user_type;
    $types .= "s";
}

$sql .= " ORDER BY u.created_at DESC";

$stmt = prepareStatement($conn, $sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get unlinked students and teachers for linking
$unlinkStmt = prepareStatement($conn, "SELECT id, first_name, last_name, student_id FROM students WHERE user_id IS NULL AND status = 'active'");
$unlinkStmt->execute();
$unlinkedStudents = $unlinkStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$unlinkTeacherStmt = prepareStatement($conn, "SELECT id, first_name, last_name, teacher_id FROM teachers WHERE user_id IS NULL AND status = 'active'");
$unlinkTeacherStmt->execute();
$unlinkedTeachers = $unlinkTeacherStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt->close();
$unlinkStmt->close();
$unlinkTeacherStmt->close();
closeDatabaseConnection($conn);

require_once '../includes/header.php';
?>

<!-- Filters -->
<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <input type="text" class="form-control" name="search" placeholder="Search users..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-3">
                <select class="form-control" name="user_type">
                    <option value="all" <?php echo $user_type === 'all' ? 'selected' : ''; ?>>All Types</option>
                    <option value="admin" <?php echo $user_type === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="teacher" <?php echo $user_type === 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                    <option value="student" <?php echo $user_type === 'student' ? 'selected' : ''; ?>>Student</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary">Filter</button>
            </div>
            <div class="col-md-3">
                <button type="button" class="btn btn-success" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> Add User
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Users Table -->
<div class="card">
    <div class="card-header">
        <h5><i class="fas fa-users"></i> Users List</h5>
    </div>
    <div class="card-body">
        <?php if (!empty($users)): ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Email</th>
                            <th>User Type</th>
                            <th>Linked Profile</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="badge <?php 
                                        echo match($user['user_type']) {
                                            'admin' => 'bg-danger',
                                            'teacher' => 'bg-success',
                                            'student' => 'bg-primary',
                                            default => 'bg-secondary'
                                        };
                                    ?>">
                                        <?php echo ucfirst($user['user_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($user['user_type'] === 'student' && $user['student_first_name']): ?>
                                        <span class="text-success">
                                            <i class="fas fa-user-graduate"></i> 
                                            <?php echo htmlspecialchars($user['student_first_name'] . ' ' . $user['student_last_name'] . ' (' . $user['student_id'] . ')'); ?>
                                        </span>
                                    <?php elseif ($user['user_type'] === 'teacher' && $user['teacher_first_name']): ?>
                                        <span class="text-info">
                                            <i class="fas fa-chalkboard-teacher"></i> 
                                            <?php echo htmlspecialchars($user['teacher_first_name'] . ' ' . $user['teacher_last_name'] . ' (' . $user['teacher_id'] . ')'); ?>
                                        </span>
                                    <?php elseif ($user['user_type'] !== 'admin'): ?>
                                        <span class="text-warning">
                                            <i class="fas fa-unlink"></i> Not linked
                                            <button type="button" class="btn btn-sm btn-warning ms-1" onclick="openLinkModal(<?php echo $user['id']; ?>, '<?php echo $user['user_type']; ?>')">
                                                Link
                                            </button>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">Admin User</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo formatDate($user['created_at']); ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-primary" onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="confirmDelete(<?php echo $user['id']; ?>)">
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
            <p class="text-muted text-center">No users found.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Add/Edit User Modal -->
<div class="modal" id="userModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add User</h5>
                <button type="button" class="btn-close" onclick="closeModal()"></button>
            </div>
            <form id="userForm" method="POST">
                <div class="modal-body">
                    <input type="hidden" id="action" name="action" value="add">
                    <input type="hidden" id="user_id" name="user_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Username *</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Password <span id="passwordNote">(leave blank to keep current)</span></label>
                        <input type="password" class="form-control" id="password" name="password">
                        <div class="form-text">Minimum 6 characters</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">User Type *</label>
                        <select class="form-control" id="user_type" name="user_type" required>
                            <option value="">Select Type</option>
                            <option value="admin">Admin</option>
                            <option value="teacher">Teacher</option>
                            <option value="student">Student</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Link Profile Modal -->
<div class="modal" id="linkModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Link Profile</h5>
                <button type="button" class="btn-close" onclick="closeLinkModal()"></button>
            </div>
            <form id="linkForm" method="POST">
                <div class="modal-body">
                    <input type="hidden" id="link_action" name="action">
                    <input type="hidden" id="link_user_id" name="user_id">
                    
                    <div id="studentLink" style="display: none;">
                        <label class="form-label">Select Student</label>
                        <select class="form-control" name="student_id">
                            <option value="">Choose Student</option>
                            <?php foreach ($unlinkedStudents as $student): ?>
                                <option value="<?php echo $student['id']; ?>">
                                    <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name'] . ' (' . $student['student_id'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div id="teacherLink" style="display: none;">
                        <label class="form-label">Select Teacher</label>
                        <select class="form-control" name="teacher_id">
                            <option value="">Choose Teacher</option>
                            <?php foreach ($unlinkedTeachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>">
                                    <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name'] . ' (' . $teacher['teacher_id'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeLinkModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Link Profile</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add User';
    document.getElementById('action').value = 'add';
    document.getElementById('userForm').reset();
    document.getElementById('passwordNote').style.display = 'none';
    document.getElementById('password').required = true;
    document.getElementById('userModal').style.display = 'block';
}

function editUser(user) {
    document.getElementById('modalTitle').textContent = 'Edit User';
    document.getElementById('action').value = 'edit';
    document.getElementById('user_id').value = user.id;
    document.getElementById('username').value = user.username;
    document.getElementById('email').value = user.email;
    document.getElementById('user_type').value = user.user_type;
    document.getElementById('passwordNote').style.display = 'inline';
    document.getElementById('password').required = false;
    document.getElementById('userModal').style.display = 'block';
}

function openLinkModal(userId, userType) {
    document.getElementById('link_user_id').value = userId;
    document.getElementById('studentLink').style.display = 'none';
    document.getElementById('teacherLink').style.display = 'none';
    
    if (userType === 'student') {
        document.getElementById('link_action').value = 'link_student';
        document.getElementById('studentLink').style.display = 'block';
    } else if (userType === 'teacher') {
        document.getElementById('link_action').value = 'link_teacher';
        document.getElementById('teacherLink').style.display = 'block';
    }
    
    document.getElementById('linkModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('userModal').style.display = 'none';
}

function closeLinkModal() {
    document.getElementById('linkModal').style.display = 'none';
}

function confirmDelete(userId) {
    if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="user_id" value="${userId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    const userModal = document.getElementById('userModal');
    const linkModal = document.getElementById('linkModal');
    if (event.target === userModal) {
        closeModal();
    } else if (event.target === linkModal) {
        closeLinkModal();
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>