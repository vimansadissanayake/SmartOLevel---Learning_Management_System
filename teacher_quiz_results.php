<?php
// teacher_quiz_results.php
require_once 'config.php';
checkRole('teacher');

$teacher_id = $_SESSION['user_id'];
$teacher_name = $_SESSION['full_name'];

// Optional: filter by specific quiz
$quiz_id_filter = intval($_GET['quiz_id'] ?? 0);

// Get all quizzes created by this teacher
$quizzes_query = "SELECT quiz_id, title, subject_id, total_marks 
                  FROM quizzes 
                  WHERE teacher_id = ? AND is_active = 1 
                  ORDER BY created_at DESC";
$stmt = $conn->prepare($quizzes_query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$quizzes = $stmt->get_result();

// Build the main results query
$results_query = "SELECT qa.attempt_id, qa.score, qa.percentage, qa.attempted_at,
                        q.title AS quiz_title, q.total_marks,
                        s.subject_name,
                        u.full_name AS student_name, u.user_id AS student_id
                 FROM quiz_attempts qa
                 INNER JOIN quizzes q ON qa.quiz_id = q.quiz_id
                 INNER JOIN subjects s ON q.subject_id = s.subject_id
                 INNER JOIN users u ON qa.student_id = u.user_id
                 WHERE q.teacher_id = ?";

$params = [$teacher_id];
$types = "i";

if ($quiz_id_filter > 0) {
    $results_query .= " AND qa.quiz_id = ?";
    $params[] = $quiz_id_filter;
    $types .= "i";
}

$results_query .= " ORDER BY qa.attempted_at DESC";

$stmt = $conn->prepare($results_query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$results = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Quiz Results - SmartOLevel LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-green-600 to-teal-600 text-white p-4 shadow-lg">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold">SmartOLevel LMS</h1>
                <p class="text-sm text-green-100">Quiz Results Overview</p>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-sm">Welcome, <?php echo htmlspecialchars($teacher_name); ?></span>
                <a href="logout.php" class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition">Logout</a>
                <a href="teacher_dashboard.php" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition">Back to Dashboard</a>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto p-6">
        <!-- Quiz Filter -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Filter by Quiz</h2>
            <div class="flex flex-wrap gap-3">
                <a href="teacher_quiz_results.php" 
                   class="px-5 py-2 <?php echo $quiz_id_filter == 0 ? 'bg-green-600 text-white' : 'bg-gray-200 text-gray-700'; ?> rounded-lg hover:shadow transition">
                    All Quizzes
                </a>
                <?php while ($quiz = $quizzes->fetch_assoc()): ?>
                <a href="teacher_quiz_results.php?quiz_id=<?php echo $quiz['quiz_id']; ?>"
                   class="px-5 py-2 <?php echo $quiz_id_filter == $quiz['quiz_id'] ? 'bg-green-600 text-white' : 'bg-gray-200 text-gray-700'; ?> rounded-lg hover:shadow transition">
                    <?php echo htmlspecialchars($quiz['title']); ?>
                </a>
                <?php endwhile; ?>
            </div>
        </div>

        <!-- Results Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 bg-green-50 border-b border-green-200">
                <h2 class="text-2xl font-bold text-gray-800">Student Quiz Results</h2>
                <p class="text-sm text-gray-600 mt-1">
                    <?php echo $quiz_id_filter > 0 ? 'Showing results for selected quiz' : 'All quiz attempts by your students'; ?>
                </p>
            </div>

            <?php if ($results->num_rows > 0): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quiz</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Score</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Percentage</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grade</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Attempted On</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php 
                            // Reset pointer after filter buttons
                            $results->data_seek(0);
                            while ($row = $results->fetch_assoc()): 
                                $letter_grade = calculateGrade($row['percentage']);
                                $grade_color = getGradeColor($letter_grade);
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($row['student_name']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($row['quiz_title']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    <?php echo htmlspecialchars($row['subject_name']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">
                                    <?php echo number_format($row['score'], 1); ?> / <?php echo $row['total_marks']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold" style="color: <?php echo $grade_color; ?>">
                                    <?php echo number_format($row['percentage'], 1); ?>%
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-lg font-bold" style="color: <?php echo $grade_color; ?>">
                                    <?php echo $letter_grade; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo formatDateTime($row['attempted_at']); ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-12">
                    <div class="text-6xl mb-4">❓</div>
                    <p class="text-xl text-gray-600">No quiz results yet</p>
                    <p class="text-gray-500 mt-2">
                        <?php echo $quiz_id_filter > 0 ? 'No students have attempted this quiz yet.' : 'Your students haven\'t taken any quizzes yet.'; ?>
                    </p>
                    <a href="teacher_dashboard.php" class="mt-6 inline-block px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                        Back to Dashboard
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Summary Stats (if no filter) -->
        <?php if ($quiz_id_filter == 0 && $results->num_rows > 0): ?>
        <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white p-6 rounded-lg shadow-md text-center">
                <p class="text-sm text-gray-600">Total Attempts</p>
                <p class="text-3xl font-bold text-green-600"><?php echo $results->num_rows; ?></p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-md text-center">
                <p class="text-sm text-gray-600">Average Score</p>
                <?php
                $results->data_seek(0);
                $total_percentage = 0;
                $count = 0;
                while ($row = $results->fetch_assoc()) {
                    $total_percentage += $row['percentage'];
                    $count++;
                }
                $avg_percentage = $count > 0 ? round($total_percentage / $count, 1) : 0;
                $avg_grade = calculateGrade($avg_percentage);
                $avg_color = getGradeColor($avg_grade);
                ?>
                <p class="text-3xl font-bold" style="color: <?php echo $avg_color; ?>">
                    <?php echo $avg_percentage; ?>%
                </p>
                <p class="text-lg font-semibold mt-1" style="color: <?php echo $avg_color; ?>">
                    <?php echo $avg_grade; ?>
                </p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-md text-center">
                <p class="text-sm text-gray-600">Passing Rate</p>
                <?php
                $results->data_seek(0);
                $passing = 0;
                while ($row = $results->fetch_assoc()) {
                    if ($row['percentage'] >= 50) $passing++; // Assuming 50% is passing
                }
                $passing_rate = $count > 0 ? round(($passing / $count) * 100) : 0;
                ?>
                <p class="text-3xl font-bold text-blue-600"><?php echo $passing_rate; ?>%</p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>