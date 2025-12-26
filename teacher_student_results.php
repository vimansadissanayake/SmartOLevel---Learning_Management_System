<?php
// teacher_student_results.php
require_once 'config.php';
checkRole('teacher');

$teacher_id = $_SESSION['user_id'];
$teacher_name = $_SESSION['full_name'];

// Get assignment grades for teacher's students
$assignment_grades_query = "SELECT ag.marks_obtained, ag.grade, ag.feedback, ag.graded_at,
                            a.assignment_title, s.subject_name, u.full_name AS student_name
                            FROM assignment_grades ag
                            INNER JOIN assignment_submissions asub ON ag.submission_id = asub.submission_id
                            INNER JOIN assignments a ON asub.assignment_id = a.assignment_id
                            INNER JOIN subjects s ON a.subject_id = s.subject_id
                            INNER JOIN users u ON asub.student_id = u.user_id
                            WHERE a.teacher_id = ?
                            ORDER BY ag.graded_at DESC";
$stmt = $conn->prepare($assignment_grades_query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$assignment_grades = $stmt->get_result();

// Get quiz attempts for teacher's students
$quiz_attempts_query = "SELECT qa.score, qa.percentage, qa.attempted_at,
                        q.title AS quiz_title, s.subject_name, u.full_name AS student_name
                        FROM quiz_attempts qa
                        INNER JOIN quizzes q ON qa.quiz_id = q.quiz_id
                        INNER JOIN subjects s ON q.subject_id = s.subject_id
                        INNER JOIN users u ON qa.student_id = u.user_id
                        WHERE q.teacher_id = ?
                        ORDER BY qa.attempted_at DESC";
$stmt = $conn->prepare($quiz_attempts_query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$quiz_attempts = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Results - SmartOLevel LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-green-600 to-teal-600 text-white p-4 shadow-lg">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold">SmartOLevel LMS</h1>
                <p class="text-sm text-green-100">Student Results</p>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-sm">Welcome, <?php echo htmlspecialchars($teacher_name); ?></span>
                <a href="logout.php" class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition">Logout</a>
                <a href="teacher_dashboard.php" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition">Back to Dashboard</a>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto p-6">
        <!-- Assignment Grades Section -->
        <div class="bg-white p-6 rounded-lg shadow-md mb-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">Assignment Grades</h2>
            <?php if ($assignment_grades->num_rows > 0): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assignment</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Marks</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grade</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($grade = $assignment_grades->fetch_assoc()): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($grade['student_name']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($grade['assignment_title']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($grade['subject_name']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo number_format($grade['marks_obtained'], 1); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($grade['grade']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo formatDate($grade['graded_at']); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-gray-500 text-center py-4">No assignment grades available yet.</p>
            <?php endif; ?>
        </div>

        <!-- Quiz Attempts Section -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">Quiz Attempts</h2>
            <?php if ($quiz_attempts->num_rows > 0): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quiz</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Score</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Percentage</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($attempt = $quiz_attempts->fetch_assoc()): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($attempt['student_name']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($attempt['quiz_title']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($attempt['subject_name']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo number_format($attempt['score'], 1); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo number_format($attempt['percentage'], 1); ?>%</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo formatDate($attempt['attempted_at']); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-gray-500 text-center py-4">No quiz attempts available yet.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>