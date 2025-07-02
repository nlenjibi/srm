<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../auth/session.php';

// Require student role
requireRole(ROLE_STUDENT);

$page_title = "My Grades";

// Get student profile
$student_profile = getUserProfile();
if (!$student_profile) {
    redirectWithMessage("../index.php", "Student profile not found.", "error");
}

$conn = getDatabaseConnection();

try {
    // Get grades with filters
    $subject_filter = $_GET['subject_id'] ?? 'all';
    $exam_type_filter = $_GET['exam_type'] ?? 'all';
    
    $sql = "SELECT g.*, sub.subject_name, sub.credits, t.first_name as teacher_first_name, t.last_name as teacher_last_name,
            (g.marks_obtained / g.total_marks * 100) as percentage
            FROM grades g 
            JOIN subjects sub ON g.subject_id = sub.id 
            LEFT JOIN teachers t ON g.teacher_id = t.id
            WHERE g.student_id = ?";
    $params = [$student_profile['id']];
    $types = "i";
    
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
    
    $sql .= " ORDER BY g.exam_date DESC, sub.subject_name";
    
    $stmt = prepareStatement($conn, $sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $grades = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Get subjects for filter
    $subjectStmt = prepareStatement($conn, 
        "SELECT DISTINCT s.id, s.subject_name FROM subjects s 
         JOIN grades g ON s.id = g.subject_id 
         WHERE g.student_id = ? 
         ORDER BY s.subject_name"
    );
    $subjectStmt->bind_param("i", $student_profile['id']);
    $subjectStmt->execute();
    $subjects = $subjectStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Get grade statistics
    $statsStmt = prepareStatement($conn, 
        "SELECT 
            COUNT(*) as total_grades,
            AVG(marks_obtained/total_marks*100) as avg_percentage,
            MAX(marks_obtained/total_marks*100) as highest_percentage,
            MIN(marks_obtained/total_marks*100) as lowest_percentage,
            COUNT(CASE WHEN grade = 'A' THEN 1 END) as grade_a,
            COUNT(CASE WHEN grade = 'B' THEN 1 END) as grade_b,
            COUNT(CASE WHEN grade = 'C' THEN 1 END) as grade_c,
            COUNT(CASE WHEN grade = 'D' THEN 1 END) as grade_d,
            COUNT(CASE WHEN grade = 'F' THEN 1 END) as grade_f
         FROM grades WHERE student_id = ?"
    );
    $statsStmt->bind_param("i", $student_profile['id']);
    $statsStmt->execute();
    $grade_stats = $statsStmt->get_result()->fetch_assoc();
    
    // Get subject-wise performance
    $subjectPerformanceStmt = prepareStatement($conn, 
        "SELECT s.subject_name, s.credits,
            COUNT(g.id) as total_grades,
            AVG(g.marks_obtained/g.total_marks*100) as avg_percentage,
            MAX(g.marks_obtained/g.total_marks*100) as highest_percentage,
            MIN(g.marks_obtained/g.total_marks*100) as lowest_percentage
         FROM subjects s 
         JOIN grades g ON s.id = g.subject_id 
         WHERE g.student_id = ?
         GROUP BY s.id, s.subject_name, s.credits
         ORDER BY s.subject_name"
    );
    $subjectPerformanceStmt->bind_param("i", $student_profile['id']);
    $subjectPerformanceStmt->execute();
    $subject_performance = $subjectPerformanceStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    error_log("Student grades error: " . $e->getMessage());
    $grades = [];
    $subjects = [];
    $grade_stats = ['total_grades' => 0, 'avg_percentage' => 0, 'highest_percentage' => 0, 'lowest_percentage' => 0, 'grade_a' => 0, 'grade_b' => 0, 'grade_c' => 0, 'grade_d' => 0, 'grade_f' => 0];
    $subject_performance = [];
} finally {
    if (isset($stmt)) $stmt->close();
    if (isset($subjectStmt)) $subjectStmt->close();
    if (isset($statsStmt)) $statsStmt->close();
    if (isset($subjectPerformanceStmt)) $subjectPerformanceStmt->close();
    closeDatabaseConnection($conn);
}

require_once '../includes/header.php';
?>

<!-- Grade Statistics -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body text-center">
                <h3><?php echo $grade_stats['total_grades']; ?></h3>
                <p>Total Grades</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body text-center">
                <h3><?php echo $grade_stats['avg_percentage'] ? round($grade_stats['avg_percentage'], 1) . '%' : 'N/A'; ?></h3>
                <p>Average Grade</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body text-center">
                <h3><?php echo $grade_stats['highest_percentage'] ? round($grade_stats['highest_percentage'], 1) . '%' : 'N/A'; ?></h3>
                <p>Highest Grade</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-white">
            <div class="card-body text-center">
                <h3><?php echo $grade_stats['lowest_percentage'] ? round($grade_stats['lowest_percentage'], 1) . '%' : 'N/A'; ?></h3>
                <p>Lowest Grade</p>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <select class="form-control" name="subject_id">
                    <option value="all" <?php echo $subject_filter === 'all' ? 'selected' : ''; ?>>All Subjects</option>
                    <?php foreach ($subjects as $subject): ?>
                        <option value="<?php echo $subject['id']; ?>" <?php echo $subject_filter == $subject['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($subject['subject_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <select class="form-control" name="exam_type">
                    <option value="all" <?php echo $exam_type_filter === 'all' ? 'selected' : ''; ?>>All Exam Types</option>
                    <option value="quiz" <?php echo $exam_type_filter === 'quiz' ? 'selected' : ''; ?>>Quiz</option>
                    <option value="midterm" <?php echo $exam_type_filter === 'midterm' ? 'selected' : ''; ?>>Midterm</option>
                    <option value="final" <?php echo $exam_type_filter === 'final' ? 'selected' : ''; ?>>Final</option>
                    <option value="assignment" <?php echo $exam_type_filter === 'assignment' ? 'selected' : ''; ?>>Assignment</option>
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary">Filter Grades</button>
            </div>
        </form>
    </div>
</div>

<!-- Grades Table -->
<div class="card">
    <div class="card-header">
        <h5><i class="fas fa-star"></i> My Grades (<?php echo count($grades); ?> total)</h5>
    </div>
    <div class="card-body">
        <?php if (!empty($grades)): ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Exam Type</th>
                            <th>Score</th>
                            <th>Percentage</th>
                            <th>Grade</th>
                            <th>Teacher</th>
                            <th>Exam Date</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($grades as $grade): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($grade['subject_name']); ?></strong>
                                    <br><small class="text-muted"><?php echo $grade['credits']; ?> credits</small>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo ucfirst($grade['exam_type']); ?></span>
                                </td>
                                <td>
                                    <strong><?php echo $grade['marks_obtained']; ?></strong>/<?php echo $grade['total_marks']; ?>
                                </td>
                                <td>
                                    <span class="badge <?php 
                                        $percentage = round($grade['percentage'], 1);
                                        echo $percentage >= 90 ? 'bg-success' : ($percentage >= 80 ? 'bg-primary' : ($percentage >= 70 ? 'bg-warning' : ($percentage >= 60 ? 'bg-info' : 'bg-danger')));
                                    ?>">
                                        <?php echo $percentage; ?>%
                                    </span>
                                </td>
                                <td>
                                    <span class="badge grade-badge <?php 
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
                                <td>
                                    <?php if ($grade['teacher_first_name']): ?>
                                        <?php echo htmlspecialchars($grade['teacher_first_name'] . ' ' . $grade['teacher_last_name']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Not assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo formatDate($grade['exam_date']); ?></td>
                                <td>
                                    <?php if ($grade['remarks']): ?>
                                        <small class="text-muted"><?php echo htmlspecialchars($grade['remarks']); ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info text-center">
                <h5>No Grades Found</h5>
                <p>You don't have any grades recorded yet, or no grades match your current filters.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Grade Distribution -->
<?php if ($grade_stats['total_grades'] > 0): ?>
<div class="card">
    <div class="card-header">
        <h5><i class="fas fa-chart-pie"></i> Grade Distribution</h5>
    </div>
    <div class="card-body">
        <div class="row text-center">
            <div class="col">
                <div class="grade-stat">
                    <div class="grade-circle grade-a"><?php echo $grade_stats['grade_a']; ?></div>
                    <div>Grade A (90%+)</div>
                    <small class="text-muted"><?php echo $grade_stats['total_grades'] > 0 ? round(($grade_stats['grade_a'] / $grade_stats['total_grades']) * 100, 1) : 0; ?>%</small>
                </div>
            </div>
            <div class="col">
                <div class="grade-stat">
                    <div class="grade-circle grade-b"><?php echo $grade_stats['grade_b']; ?></div>
                    <div>Grade B (80-89%)</div>
                    <small class="text-muted"><?php echo $grade_stats['total_grades'] > 0 ? round(($grade_stats['grade_b'] / $grade_stats['total_grades']) * 100, 1) : 0; ?>%</small>
                </div>
            </div>
            <div class="col">
                <div class="grade-stat">
                    <div class="grade-circle grade-c"><?php echo $grade_stats['grade_c']; ?></div>
                    <div>Grade C (70-79%)</div>
                    <small class="text-muted"><?php echo $grade_stats['total_grades'] > 0 ? round(($grade_stats['grade_c'] / $grade_stats['total_grades']) * 100, 1) : 0; ?>%</small>
                </div>
            </div>
            <div class="col">
                <div class="grade-stat">
                    <div class="grade-circle grade-d"><?php echo $grade_stats['grade_d']; ?></div>
                    <div>Grade D (60-69%)</div>
                    <small class="text-muted"><?php echo $grade_stats['total_grades'] > 0 ? round(($grade_stats['grade_d'] / $grade_stats['total_grades']) * 100, 1) : 0; ?>%</small>
                </div>
            </div>
            <div class="col">
                <div class="grade-stat">
                    <div class="grade-circle grade-f"><?php echo $grade_stats['grade_f']; ?></div>
                    <div>Grade F (Below 60%)</div>
                    <small class="text-muted"><?php echo $grade_stats['total_grades'] > 0 ? round(($grade_stats['grade_f'] / $grade_stats['total_grades']) * 100, 1) : 0; ?>%</small>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Subject-wise Performance -->
<?php if (!empty($subject_performance)): ?>
<div class="card">
    <div class="card-header">
        <h5><i class="fas fa-chart-bar"></i> Subject-wise Performance</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Credits</th>
                        <th>Total Grades</th>
                        <th>Average</th>
                        <th>Highest</th>
                        <th>Lowest</th>
                        <th>Performance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subject_performance as $performance): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($performance['subject_name']); ?></strong></td>
                            <td><?php echo $performance['credits']; ?></td>
                            <td><?php echo $performance['total_grades']; ?></td>
                            <td>
                                <span class="badge <?php 
                                    $avg = round($performance['avg_percentage'], 1);
                                    echo $avg >= 90 ? 'bg-success' : ($avg >= 80 ? 'bg-primary' : ($avg >= 70 ? 'bg-warning' : 'bg-danger'));
                                ?>">
                                    <?php echo $avg; ?>%
                                </span>
                            </td>
                            <td><?php echo round($performance['highest_percentage'], 1); ?>%</td>
                            <td><?php echo round($performance['lowest_percentage'], 1); ?>%</td>
                            <td>
                                <?php 
                                $avg = round($performance['avg_percentage'], 1);
                                if ($avg >= 90) {
                                    echo '<span class="text-success"><i class="fas fa-arrow-up"></i> Excellent</span>';
                                } elseif ($avg >= 80) {
                                    echo '<span class="text-primary"><i class="fas fa-thumbs-up"></i> Good</span>';
                                } elseif ($avg >= 70) {
                                    echo '<span class="text-warning"><i class="fas fa-minus"></i> Average</span>';
                                } else {
                                    echo '<span class="text-danger"><i class="fas fa-arrow-down"></i> Needs Improvement</span>';
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
.grade-stat {
    padding: 1rem;
}

.grade-circle {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 0.5rem;
    font-size: 1.5rem;
    font-weight: bold;
    color: white;
}

.grade-a { background: #28a745; }
.grade-b { background: #007bff; }
.grade-c { background: #ffc107; color: #212529 !important; }
.grade-d { background: #17a2b8; }
.grade-f { background: #dc3545; }

.grade-badge {
    font-size: 1.1rem;
    padding: 0.5rem 0.75rem;
}
</style>

<?php require_once '../includes/footer.php'; ?>