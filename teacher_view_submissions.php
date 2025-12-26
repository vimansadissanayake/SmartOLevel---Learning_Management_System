<?php
require_once 'config.php';
checkRole('teacher');

$teacher_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Handle grading
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['grade_submission'])) {
    $submission_id = intval($_POST['submission_id']);
    $marks = floatval($_POST['marks_obtained']);
    $total_marks = floatval($_POST['total_marks']);
    $feedback = trim($_POST['feedback']);
    
    $percentage = ($marks / $total_marks) * 100;
    $grade = calculateGrade($percentage);
    
    // Check if already graded
    $check = $conn->prepare("SELECT grade_id FROM assignment_grades WHERE submission_id = ?");
    $check->bind_param("i", $submission_id);
    $check->execute();
    
    if ($check->get_result()->num_rows > 0) {
        $stmt = $conn->prepare("UPDATE assignment_grades SET marks_obtained = ?, grade = ?, feedback = ?, graded_at = NOW() WHERE submission_id = ?");
        $stmt->bind_param("dssi", $marks, $grade, $feedback, $submission_id);
    } else {
        $stmt = $conn->prepare("INSERT INTO assignment_grades (submission_id, teacher_id, marks_obtained, grade, feedback) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iidss", $submission_id, $teacher_id, $marks, $grade, $feedback);
    }
    
    if ($stmt->execute()) {
        $conn->query("UPDATE assignment_submissions SET status = 'graded' WHERE submission_id = $submission_id");
        $success = "Submission graded successfully!";
    } else {
        $error = "Failed to save grade";
    }
}

// Get submissions
$assignment_filter = isset($_GET['assignment_id']) ? intval($_GET['assignment_id']) : 0;

$query = "SELECT asub.*, a.assignment_title, a.total_marks, a.due_date,
          u.full_name as student_name, u.email as student_email,
          s.subject_name, s.subject_code,
          ag.grade_id, ag.marks_obtained, ag.grade as letter_grade, ag.feedback, ag.graded_at
          FROM assignment_submissions asub
          INNER JOIN assignments a ON asub.assignment_id = a.assignment_id
          INNER JOIN users u ON asub.student_id = u.user_id
          INNER JOIN subjects s ON a.subject_id = s.subject_id
          LEFT JOIN assignment_grades ag ON asub.submission_id = ag.submission_id
          WHERE a.teacher_id = ?";

if ($assignment_filter) {
    $query .= " AND a.assignment_id = $assignment_filter";
}

$query .= " ORDER BY asub.submitted_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$submissions = $stmt->get_result();

// Get assignments for filter
$assignments_query = "SELECT assignment_id, assignment_title, subject_id FROM assignments WHERE teacher_id = ? AND is_active = 1 ORDER BY due_date DESC";
$stmt = $conn->prepare($assignments_query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$assignments = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Submissions - Teacher</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <header class="bg-gradient-to-r from-green-600 to-teal-600 text-white p-4 shadow-lg">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold">Assignment Submissions</h1>
                <p class="text-sm text-green-100">Grade student work</p>
            </div>
            <div class="flex items-center gap-4">
                <a href="teacher_dashboard.php" class="px-4 py-2 bg-white text-green-600 rounded-lg hover:bg-green-50">← Dashboard</a>
                <a href="logout.php" class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600">Logout</a>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto p-6">
        <?php if ($success): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Filter -->
        <div class="bg-white rounded-lg shadow-md p-4 mb-6">
            <form method="GET" class="flex gap-4 items-end">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Filter by Assignment</label>
                    <select name="assignment_id" class="w-full px-4 py-2 border rounded-lg" onchange="this.form.submit()">
                        <option value="">All Assignments</option>
                        <?php while ($assign = $assignments->fetch_assoc()): ?>
                            <option value="<?php echo $assign['assignment_id']; ?>" <?php echo $assignment_filter == $assign['assignment_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($assign['assignment_title']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <?php if ($assignment_filter): ?>
                <a href="?" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400">Clear Filter</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Submissions List -->
        <div class="space-y-4">
            <?php if ($submissions->num_rows > 0): ?>
                <?php while ($sub = $submissions->fetch_assoc()): 
                    $is_graded = !empty($sub['grade_id']);
                    $is_late = strtotime($sub['submitted_at']) > strtotime($sub['due_date']);
                ?>
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex justify-between items-start mb-4">
                        <div class="flex-1">
                            <h3 class="font-bold text-xl text-gray-800"><?php echo htmlspecialchars($sub['assignment_title']); ?></h3>
                            <p class="text-sm text-gray-600"><?php echo htmlspecialchars($sub['subject_name']); ?> (<?php echo htmlspecialchars($sub['subject_code']); ?>)</p>
                            <div class="mt-2">
                                <p class="text-sm"><span class="font-medium">Student:</span> <?php echo htmlspecialchars($sub['student_name']); ?></p>
                                <p class="text-sm"><span class="font-medium">Email:</span> <?php echo htmlspecialchars($sub['student_email']); ?></p>
                            </div>
                        </div>
                        <div class="text-right">
                            <?php if ($is_graded): ?>
                                <span class="inline-block px-4 py-2 bg-green-100 text-green-800 rounded-lg font-bold">Graded</span>
                                <p class="text-2xl font-bold mt-2" style="color: <?php echo getGradeColor($sub['letter_grade']); ?>">
                                    <?php echo number_format($sub['marks_obtained'], 1); ?>/<?php echo $sub['total_marks']; ?>
                                </p>
                                <p class="text-sm font-bold" style="color: <?php echo getGradeColor($sub['letter_grade']); ?>"><?php echo $sub['letter_grade']; ?></p>
                            <?php else: ?>
                                <span class="inline-block px-4 py-2 bg-yellow-100 text-yellow-800 rounded-lg font-bold">Pending</span>
                            <?php endif; ?>
                            <?php if ($is_late): ?>
                                <p class="text-xs text-red-600 mt-2">⚠️ Late Submission</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="border-t pt-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <p class="text-sm font-medium text-gray-700">Submitted:</p>
                                <p class="text-sm text-gray-600"><?php echo formatDateTime($sub['submitted_at']); ?></p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-700">Due Date:</p>
                                <p class="text-sm text-gray-600"><?php echo formatDateTime($sub['due_date']); ?></p>
                            </div>
                        </div>

                        <?php if ($sub['submitted_file_path']): ?>
                        <div class="mb-4">
                            <a href="<?php echo htmlspecialchars($sub['submitted_file_path']); ?>" target="_blank" class="inline-block px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                📄 Download Submission
                            </a>
                        </div>
                        <?php endif; ?>

                        <?php if ($sub['submission_text']): ?>
                        <div class="mb-4 p-4 bg-gray-50 rounded">
                            <p class="text-sm font-medium text-gray-700 mb-2">Submission Text:</p>
                            <p class="text-sm text-gray-800"><?php echo nl2br(htmlspecialchars($sub['submission_text'])); ?></p>
                        </div>
                        <?php endif; ?>

                        <!-- Grading Form -->
                        <button onclick="toggleGradeForm(<?php echo $sub['submission_id']; ?>)" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                            <?php echo $is_graded ? '✏️ Edit Grade' : '✓ Grade Now'; ?>
                        </button>

                        <div id="gradeForm<?php echo $sub['submission_id']; ?>" class="hidden mt-4 p-4 bg-gray-50 rounded-lg">
                            <form method="POST">
                                <input type="hidden" name="submission_id" value="<?php echo $sub['submission_id']; ?>">
                                <input type="hidden" name="total_marks" value="<?php echo $sub['total_marks']; ?>">
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Marks Obtained *</label>
                                        <input type="number" name="marks_obtained" step="0.5" min="0" max="<?php echo $sub['total_marks']; ?>" 
                                               value="<?php echo $is_graded ? $sub['marks_obtained'] : ''; ?>" required
                                               class="w-full px-4 py-2 border rounded-lg">
                                        <p class="text-xs text-gray-500 mt-1">Out of <?php echo $sub['total_marks']; ?> marks</p>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Feedback</label>
                                    <textarea name="feedback" rows="3" class="w-full px-4 py-2 border rounded-lg"><?php echo $is_graded ? htmlspecialchars($sub['feedback']) : ''; ?></textarea>
                                </div>

                                <div class="flex gap-2">
                                    <button type="submit" name="grade_submission" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                                        Save Grade
                                    </button>
                                    <button type="button" onclick="toggleGradeForm(<?php echo $sub['submission_id']; ?>)" class="px-6 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400">
                                        Cancel
                                    </button>
                                </div>
                            </form>
                        </div>

                        <?php if ($is_graded && $sub['feedback']): ?>
                        <div class="mt-4 p-4 bg-blue-50 rounded">
                            <p class="text-sm font-medium text-blue-800 mb-1">Your Feedback:</p>
                            <p class="text-sm text-blue-700"><?php echo nl2br(htmlspecialchars($sub['feedback'])); ?></p>
                            <p class="text-xs text-blue-600 mt-2">Graded on: <?php echo formatDateTime($sub['graded_at']); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="bg-white rounded-lg shadow-md p-12 text-center">
                    <div class="text-6xl mb-4">📝</div>
                    <p class="text-xl text-gray-500">No submissions yet</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function toggleGradeForm(id) {
            const form = document.getElementById('gradeForm' + id);
            form.classList.toggle('hidden');
        }
    </script>
</body>
</html>