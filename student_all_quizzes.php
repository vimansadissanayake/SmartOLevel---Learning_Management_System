<?php
require_once 'config.php';
checkRole('student');

$student_id = $_SESSION['user_id'];
$student_name = $_SESSION['full_name'];
$grade = $_SESSION['grade'] ?? '10';

$query = "SELECT q.quiz_id, q.title, q.description, q.duration_minutes, q.total_marks,
                 s.subject_name, s.subject_code,
                 qa.attempt_id, qa.score, qa.attempted_at
          FROM quizzes q
          INNER JOIN subjects s ON q.subject_id = s.subject_id
          INNER JOIN student_subjects ss ON s.subject_id = ss.subject_id
          LEFT JOIN quiz_attempts qa ON q.quiz_id = qa.quiz_id AND qa.student_id = ?
          WHERE ss.student_id = ? AND q.is_active = 1 AND s.grade_level = ?
          ORDER BY q.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("iis", $student_id, $student_id, $grade);
$stmt->execute();
$quizzes = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Take Quizzes - SmartOLevel LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <header class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white p-4 shadow-lg">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold">SmartOLevel LMS</h1>
                <p class="text-sm text-blue-100">Quizzes - Grade <?php echo htmlspecialchars($grade); ?></p>
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
            <h2 class="text-2xl font-bold text-gray-800">Available Quizzes</h2>
        </div>

        <?php if ($quizzes->num_rows > 0): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php while ($quiz = $quizzes->fetch_assoc()): 
                    $attempted = !empty($quiz['attempt_id']);
                ?>
                    <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition">
                        <div class="text-6xl mb-4 text-center text-purple-600">❓</div>
                        <h3 class="font-bold text-lg text-gray-800 mb-2 text-center"><?php echo htmlspecialchars($quiz['title']); ?></h3>
                        <p class="text-sm text-gray-600 text-center mb-4">
                            <?php echo htmlspecialchars($quiz['subject_name']); ?> (<?php echo htmlspecialchars($quiz['subject_code']); ?>)
                        </p>
                        <?php if ($quiz['description']): ?>
                            <p class="text-sm text-gray-500 text-center mb-4"><?php echo htmlspecialchars($quiz['description']); ?></p>
                        <?php endif; ?>
                        <p class="text-sm text-gray-700 text-center mb-6">
                            Duration: <?php echo $quiz['duration_minutes']; ?> min | Marks: <?php echo $quiz['total_marks']; ?>
                        </p>

                        <?php if ($attempted): ?>
                            <div class="text-center mb-4">
                                <span class="inline-block px-4 py-2 bg-green-100 text-green-800 rounded-lg font-bold">
                                    Score: <?php echo $quiz['score']; ?>/<?php echo $quiz['total_marks']; ?>
                                </span>
                            </div>
                            <p class="text-xs text-gray-500 text-center mb-4">Attempted on <?php echo formatDate($quiz['attempted_at']); ?></p>
                            <div class="text-center">
                                <span class="px-4 py-2 bg-gray-400 text-white rounded-lg cursor-not-allowed">Already Attempted</span>
                            </div>
                        <?php else: ?>
                            <a href="student_take_quiz.php?id=<?php echo $quiz['quiz_id']; ?>"
                               class="block text-center px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition">
                                Start Quiz
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-12 bg-white rounded-lg shadow-md">
                <div class="text-6xl mb-4">❓</div>
                <p class="text-xl text-gray-500">No quizzes available yet</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>