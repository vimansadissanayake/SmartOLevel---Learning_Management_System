<?php
require_once 'config.php';
checkRole('student');

$student_id = $_SESSION['user_id'];
$student_name = $_SESSION['full_name'];
$grade = $_SESSION['grade'] ?? '10';

$query = "SELECT a.assignment_id, a.assignment_title, a.description, a.file_path, a.due_date, a.total_marks,
                 s.subject_name, s.subject_code,
                 asub.submission_id, asub.submitted_at
          FROM assignments a
          INNER JOIN subjects s ON a.subject_id = s.subject_id
          INNER JOIN student_subjects ss ON s.subject_id = ss.subject_id
          LEFT JOIN assignment_submissions asub ON a.assignment_id = asub.assignment_id AND asub.student_id = ?
          WHERE ss.student_id = ? AND a.is_active = 1 AND s.grade_level = ?
          ORDER BY a.due_date ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param("iis", $student_id, $student_id, $grade);
$stmt->execute();
$assignments = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Assignments - SmartOLevel LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <header class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white p-4 shadow-lg">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold">SmartOLevel LMS</h1>
                <p class="text-sm text-blue-100">Assignments - Grade <?php echo htmlspecialchars($grade); ?></p>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-sm">Welcome, <?php echo htmlspecialchars($student_name); ?></span>
                <a href="logout.php" class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition">Logout</a>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto p-6">
        <div class="flex items-center mb-6">
            <a href="student_dashboard.php" class="text-blue-600 hover:underline mr-4">← Back to Dashboard</a>
            <h2 class="text-2xl font-bold text-gray-800">All Assignments</h2>
        </div>

        <?php if ($assignments->num_rows > 0): ?>
            <div class="space-y-6">
                <?php while ($assign = $assignments->fetch_assoc()): 
                    $is_submitted = !empty($assign['submission_id']);
                    $is_overdue = strtotime($assign['due_date']) < time() && !$is_submitted;
                ?>
                    <div class="bg-white rounded-lg shadow-md p-6 <?php echo $is_overdue ? 'border-l-4 border-red-500' : ''; ?>">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <h3 class="font-bold text-xl text-gray-800 mb-2"><?php echo htmlspecialchars($assign['assignment_title']); ?></h3>
                                <p class="text-sm text-gray-600 mb-3">
                                    <?php echo htmlspecialchars($assign['subject_name']); ?> (<?php echo htmlspecialchars($assign['subject_code']); ?>)
                                </p>
                                <?php if ($assign['description']): ?>
                                    <p class="text-gray-700 mb-4"><?php echo nl2br(htmlspecialchars($assign['description'])); ?></p>
                                <?php endif; ?>
                                <p class="text-sm font-medium <?php echo $is_overdue ? 'text-red-600' : 'text-gray-600'; ?>">
                                    Due: <?php echo formatDate($assign['due_date'], true); ?>
                                    <?php if ($is_overdue) echo ' (Overdue)'; ?>
                                </p>
                            </div>
                            <div class="ml-6 text-right">
                                <?php if ($is_submitted): ?>
                                    <span class="inline-block px-4 py-2 bg-green-100 text-green-800 rounded-lg font-bold">Submitted</span>
                                    <p class="text-xs text-gray-500 mt-1"><?php echo formatDate($assign['submitted_at']); ?></p>
                                <?php else: ?>
                                    <span class="inline-block px-4 py-2 bg-yellow-100 text-yellow-800 rounded-lg font-bold">Pending</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="mt-6 flex gap-4">
                            <?php if ($assign['file_path']): ?>
                                <a href="<?php echo htmlspecialchars($assign['file_path']); ?>" target="_blank"
                                   class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                                    View Assignment
                                </a>
                            <?php endif; ?>
                            <a href="student_submit_assignment.php?id=<?php echo $assign['assignment_id']; ?>"
                               class="px-4 py-2 <?php echo $is_submitted ? 'bg-gray-400' : 'bg-green-600 hover:bg-green-700'; ?> text-white rounded-lg transition <?php echo $is_submitted ? 'cursor-not-allowed' : ''; ?>"
                               <?php echo $is_submitted ? 'onclick="return false;"' : ''; ?>>
                                <?php echo $is_submitted ? 'Already Submitted' : 'Submit Work'; ?>
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-12 bg-white rounded-lg shadow-md">
                <div class="text-6xl mb-4">📝</div>
                <p class="text-xl text-gray-500">No assignments yet</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>