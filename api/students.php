<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../auth/session.php';

// Check if request is AJAX
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(400);
    exit('Bad Request');
}

// Ensure user is authenticated
requireAuth();

// Set JSON response header
header('Content-Type: application/json');

$conn = getDatabaseConnection();
$method = $_SERVER['REQUEST_METHOD'];
$response = ['success' => false, 'message' => '', 'data' => null];

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                // Get single student
                $student = getStudent($_GET['id']);
                if ($student) {
                    $response['success'] = true;
                    $response['data'] = $student;
                } else {
                    $response['message'] = 'Student not found';
                }
            } else {
                // Get all students with optional filters
                $filters = [
                    'search' => $_GET['search'] ?? '',
                    'class' => $_GET['class'] ?? '',
                    'section' => $_GET['section'] ?? '',
                    'status' => $_GET['status'] ?? ''
                ];
                
                $students = getStudents($filters);
                $response['success'] = true;
                $response['data'] = $students;
            }
            break;
            
        case 'POST':
            // Create new student
            $data = [
                'student_id' => generateStudentId(),
                'first_name' => sanitizeInput($_POST['first_name'] ?? ''),
                'last_name' => sanitizeInput($_POST['last_name'] ?? ''),
                'date_of_birth' => $_POST['date_of_birth'] ?? null,
                'gender' => $_POST['gender'] ?? null,
                'phone' => sanitizeInput($_POST['phone'] ?? ''),
                'address' => sanitizeInput($_POST['address'] ?? ''),
                'parent_name' => sanitizeInput($_POST['parent_name'] ?? ''),
                'parent_phone' => sanitizeInput($_POST['parent_phone'] ?? ''),
                'parent_email' => sanitizeInput($_POST['parent_email'] ?? ''),
                'admission_date' => $_POST['admission_date'] ?? date('Y-m-d'),
                'class' => sanitizeInput($_POST['class'] ?? ''),
                'section' => sanitizeInput($_POST['section'] ?? ''),
                'status' => $_POST['status'] ?? 'active'
            ];
            
            if (createStudent($data)) {
                $response['success'] = true;
                $response['message'] = 'Student created successfully';
            } else {
                $response['message'] = 'Failed to create student';
            }
            break;
            
        case 'PUT':
            // Update student
            $input = json_decode(file_get_contents('php://input'), true);
            $id = $_GET['id'] ?? null;
            
            if (!$id) {
                $response['message'] = 'Student ID is required';
                break;
            }
            
            $data = [
                'first_name' => sanitizeInput($input['first_name'] ?? ''),
                'last_name' => sanitizeInput($input['last_name'] ?? ''),
                'date_of_birth' => $input['date_of_birth'] ?? null,
                'gender' => $input['gender'] ?? null,
                'phone' => sanitizeInput($input['phone'] ?? ''),
                'address' => sanitizeInput($input['address'] ?? ''),
                'parent_name' => sanitizeInput($input['parent_name'] ?? ''),
                'parent_phone' => sanitizeInput($input['parent_phone'] ?? ''),
                'parent_email' => sanitizeInput($input['parent_email'] ?? ''),
                'class' => sanitizeInput($input['class'] ?? ''),
                'section' => sanitizeInput($input['section'] ?? ''),
                'status' => $input['status'] ?? 'active'
            ];
            
            if (updateStudent($id, $data)) {
                $response['success'] = true;
                $response['message'] = 'Student updated successfully';
            } else {
                $response['message'] = 'Failed to update student';
            }
            break;
            
        case 'DELETE':
            // Delete student
            $id = $_GET['id'] ?? null;
            
            if (!$id) {
                $response['message'] = 'Student ID is required';
                break;
            }
            
            if (deleteStudent($id)) {
                $response['success'] = true;
                $response['message'] = 'Student deleted successfully';
            } else {
                $response['message'] = 'Failed to delete student';
            }
            break;
            
        default:
            $response['message'] = 'Method not allowed';
            break;
    }
    
} catch (Exception $e) {
    error_log("Students API Error: " . $e->getMessage());
    $response['message'] = 'An error occurred while processing your request';
} finally {
    closeDatabaseConnection($conn);
}

echo json_encode($response);

// Helper functions
function getStudents($filters = []) {
    global $conn;
    
    $sql = "SELECT s.*, u.username, u.email 
            FROM students s 
            LEFT JOIN users u ON s.user_id = u.id 
            WHERE 1=1";
    
    $params = [];
    $types = "";
    
    if (!empty($filters['search'])) {
        $sql .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_id LIKE ?)";
        $searchTerm = '%' . $filters['search'] . '%';
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
        $types .= "sss";
    }
    
    if (!empty($filters['class'])) {
        $sql .= " AND s.class = ?";
        $params[] = $filters['class'];
        $types .= "s";
    }
    
    if (!empty($filters['section'])) {
        $sql .= " AND s.section = ?";
        $params[] = $filters['section'];
        $types .= "s";
    }
    
    if (!empty($filters['status'])) {
        $sql .= " AND s.status = ?";
        $params[] = $filters['status'];
        $types .= "s";
    }
    
    $sql .= " ORDER BY s.first_name, s.last_name";
    
    $stmt = prepareStatement($conn, $sql);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    
    $stmt->close();
    return $students;
}

function getStudent($id) {
    global $conn;
    
    $stmt = prepareStatement($conn, "SELECT s.*, u.username, u.email FROM students s LEFT JOIN users u ON s.user_id = u.id WHERE s.id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $student = $result->fetch_assoc();
    $stmt->close();
    
    return $student;
}

function createStudent($data) {
    global $conn;
    
    try {
        $conn->begin_transaction();
        
        // Create user account first
        $username = strtolower($data['first_name'] . '.' . $data['last_name']);
        $email = $username . '@student.edu';
        $password = password_hash('password', PASSWORD_DEFAULT); // Default password
        
        $userStmt = prepareStatement($conn, "INSERT INTO users (username, email, password, user_type) VALUES (?, ?, ?, 'student')");
        $userStmt->bind_param("sss", $username, $email, $password);
        
        if (!$userStmt->execute()) {
            throw new Exception("Failed to create user account");
        }
        
        $userId = $conn->insert_id;
        $userStmt->close();
        
        // Create student record
        $studentStmt = prepareStatement($conn, 
            "INSERT INTO students (user_id, student_id, first_name, last_name, date_of_birth, gender, phone, address, parent_name, parent_phone, parent_email, admission_date, class, section, status) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        
        $studentStmt->bind_param("issssssssssssss", 
            $userId, $data['student_id'], $data['first_name'], $data['last_name'], 
            $data['date_of_birth'], $data['gender'], $data['phone'], $data['address'],
            $data['parent_name'], $data['parent_phone'], $data['parent_email'],
            $data['admission_date'], $data['class'], $data['section'], $data['status']
        );
        
        if (!$studentStmt->execute()) {
            throw new Exception("Failed to create student record");
        }
        
        $studentStmt->close();
        $conn->commit();
        return true;
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Create student error: " . $e->getMessage());
        return false;
    }
}

function updateStudent($id, $data) {
    global $conn;
    
    $stmt = prepareStatement($conn, 
        "UPDATE students SET first_name = ?, last_name = ?, date_of_birth = ?, gender = ?, phone = ?, address = ?, parent_name = ?, parent_phone = ?, parent_email = ?, class = ?, section = ?, status = ? WHERE id = ?"
    );
    
    $stmt->bind_param("ssssssssssssi", 
        $data['first_name'], $data['last_name'], $data['date_of_birth'], $data['gender'],
        $data['phone'], $data['address'], $data['parent_name'], $data['parent_phone'],
        $data['parent_email'], $data['class'], $data['section'], $data['status'], $id
    );
    
    $success = $stmt->execute();
    $stmt->close();
    
    return $success;
}

function deleteStudent($id) {
    global $conn;
    
    try {
        $conn->begin_transaction();
        
        // Get user_id first
        $stmt = prepareStatement($conn, "SELECT user_id FROM students WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $student = $result->fetch_assoc();
        $stmt->close();
        
        if (!$student) {
            throw new Exception("Student not found");
        }
        
        // Delete student record (this will cascade delete grades, attendance, etc.)
        $deleteStudentStmt = prepareStatement($conn, "DELETE FROM students WHERE id = ?");
        $deleteStudentStmt->bind_param("i", $id);
        
        if (!$deleteStudentStmt->execute()) {
            throw new Exception("Failed to delete student record");
        }
        $deleteStudentStmt->close();
        
        // Delete user account
        if ($student['user_id']) {
            $deleteUserStmt = prepareStatement($conn, "DELETE FROM users WHERE id = ?");
            $deleteUserStmt->bind_param("i", $student['user_id']);
            $deleteUserStmt->execute();
            $deleteUserStmt->close();
        }
        
        $conn->commit();
        return true;
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Delete student error: " . $e->getMessage());
        return false;
    }
}
?>