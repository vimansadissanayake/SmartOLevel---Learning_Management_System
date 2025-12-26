<?php
// teacher_add_quiz_questions.php
require_once 'config.php';
checkRole('teacher');

$teacher_id = $_SESSION['user_id'];
$teacher_name = $_SESSION['full_name'];

$quiz_id = intval($_GET['quiz_id'] ?? 0);

if ($quiz_id <= 0) {
    header("Location: teacher_dashboard.php");
    exit();
}

// Verify quiz ownership
$quiz_query = "SELECT q.*, s.subject_name 
               FROM quizzes q 
               INNER JOIN subjects s ON q.subject_id = s.subject_id 
               WHERE q.quiz_id = ? AND q.teacher_id = ? AND q.is_active = 1";
$stmt = $conn->prepare($quiz_query);
$stmt->bind_param("ii", $quiz_id, $teacher_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: teacher_dashboard.php");
    exit();
}

$quiz = $result->fetch_assoc();

$error = '';
$success = '';

// Helper function to get fresh questions
function getQuestions($conn, $quiz_id) {
    $q = "SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY question_order ASC";
    $stmt = $conn->prepare($q);
    $stmt->bind_param("i", $quiz_id);
    $stmt->execute();
    return $stmt->get_result();
}

$questions = getQuestions($conn, $quiz_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_question') {
        $question_text = trim($_POST['question_text'] ?? '');
        $option_a = trim($_POST['option_a'] ?? '');
        $option_b = trim($_POST['option_b'] ?? '');
        $option_c = trim($_POST['option_c'] ?? '');
        $option_d = trim($_POST['option_d'] ?? '');
        $correct_option = $_POST['correct_option'] ?? '';

        if (empty($question_text) || empty($option_a) || empty($option_b) || empty($option_c) || empty($option_d) || empty($correct_option)) {
            $error = "Please fill in all fields for the question.";
        } elseif (!in_array($correct_option, ['A', 'B', 'C', 'D'])) {
            $error = "Please select a valid correct option.";
        } else {
            // Get next order
            $order_q = "SELECT COALESCE(MAX(question_order), 0) + 1 AS next_order FROM quiz_questions WHERE quiz_id = ?";
            $stmt = $conn->prepare($order_q);
            $stmt->bind_param("i", $quiz_id);
            $stmt->execute();
            $next_order = $stmt->get_result()->fetch_assoc()['next_order'];

            // Insert question
            $insert = "INSERT INTO quiz_questions 
                       (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_option, question_order)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insert);
            $stmt->bind_param("issssssi", $quiz_id, $question_text, $option_a, $option_b, $option_c, $option_d, $correct_option, $next_order);

            if ($stmt->execute()) {
                $success = "Question added successfully!";
                $questions = getQuestions($conn, $quiz_id); // Refresh list
            } else {
                $error = "Database error: Could not add question.";
            }
        }
    }

    elseif ($action === 'delete_question') {
        $question_id = intval($_POST['question_id'] ?? 0);

        if ($question_id > 0) {
            $delete = "DELETE FROM quiz_questions WHERE question_id = ? AND quiz_id = ?";
            $stmt = $conn->prepare($delete);
            $stmt->bind_param("ii", $question_id, $quiz_id);

            if ($stmt->execute() && $stmt->affected_rows > 0) {
                // Reorder remaining questions
                $conn->query("SET @pos := 0");
                $reorder = "UPDATE quiz_questions SET question_order = (@pos := @pos + 1) WHERE quiz_id = ? ORDER BY question_order";
                $stmt = $conn->prepare($reorder);
                $stmt->bind_param("i", $quiz_id);
                $stmt->execute();

                $success = "Question deleted and reordered.";
                $questions = getQuestions($conn, $quiz_id);
            } else {
                $error = "Could not delete question (not found or permission issue).";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Quiz Questions - SmartOLevel LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        function confirmDelete() {
            return confirm('Delete this question permanently?\nThis cannot be undone.');
        }
    </script>
</head>
<body class="bg-gray-100 min-h-screen">
    <header class="bg-gradient-to-r from-green-600 to-teal-600 text-white p-4 shadow-lg">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold">SmartOLevel LMS</h1>
                <p class="text-sm text-green-100">Add Questions to Quiz</p>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-sm">Welcome, <?php echo htmlspecialchars($teacher_name); ?></span>
                <a href="logout.php" class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600">Logout</a>
                <a href="teacher_dashboard.php" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">Dashboard</a>
            </div>
        </div>
    </header>

    <div class="max-w-5xl mx-auto p-6">
        <div class="bg-white rounded-xl shadow-lg p-8 mb-8">
            <h2 class="text-3xl font-bold text-gray-800 mb-4"><?php echo htmlspecialchars($quiz['title']); ?></h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-lg">
                <div><strong>Subject:</strong> <?php echo htmlspecialchars($quiz['subject_name']); ?></div>
                <div><strong>Marks:</strong> <?php echo $quiz['total_marks']; ?></div>
                <div><strong>Time:</strong> <?php echo $quiz['duration_minutes']; ?> min</div>
            </div>
            <?php if ($quiz['description']): ?>
                <p class="mt-6 text-gray-700 italic"><?php echo nl2br(htmlspecialchars($quiz['description'])); ?></p>
            <?php endif; ?>
            <div class="mt-6">
                <span class="text-xl font-bold text-green-600">Questions: <?php echo $questions->num_rows; ?></span>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <!-- Add Question Form -->
        <div class="bg-white rounded-xl shadow-lg p-8 mb-8">
            <h3 class="text-2xl font-bold text-gray-800 mb-6">Add New Question</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add_question">
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Question Text *</label>
                    <textarea name="question_text" rows="4" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-green-500" placeholder="Enter question..."></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <div><label class="block text-sm font-medium text-gray-700 mb-2">Option A *</label><input type="text" name="option_a" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-green-500"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-2">Option B *</label><input type="text" name="option_b" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-green-500"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-2">Option C *</label><input type="text" name="option_c" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-green-500"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-2">Option D *</label><input type="text" name="option_d" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-green-500"></div>
                </div>

                <div class="mb-8">
                    <label class="block text-sm font-medium text-gray-700 mb-4">Correct Answer *</label>
                    <div class="grid grid-cols-4 gap-4">
                        <?php foreach(['A','B','C','D'] as $opt): ?>
                        <label class="flex items-center p-4 bg-gray-50 rounded-lg hover:bg-green-50 cursor-pointer">
                            <input type="radio" name="correct_option" value="<?php echo $opt; ?>" required class="mr-3 text-green-600">
                            <span class="font-bold text-xl"><?php echo $opt; ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <button type="submit" class="px-8 py-4 bg-gradient-to-r from-green-600 to-teal-600 text-white font-bold text-lg rounded-lg shadow-lg hover:from-green-700 hover:to-teal-700 transition">
                    Add Question
                </button>
            </form>
        </div>

        <!-- Existing Questions -->
        <?php if ($questions->num_rows > 0): ?>
        <div class="bg-white rounded-xl shadow-lg p-8">
            <h3 class="text-2xl font-bold text-gray-800 mb-6">Current Questions (<?php echo $questions->num_rows; ?>)</h3>
            <div class="space-y-8">
                <?php $no = 1; while ($q = $questions->fetch_assoc()): ?>
                <div class="border-2 border-gray-200 rounded-xl p-6 relative hover:border-green-500">
                    <div class="absolute top-4 right-6">
                        <form method="POST" onsubmit="return confirmDelete();" class="inline">
                            <input type="hidden" name="action" value="delete_question">
                            <input type="hidden" name="question_id" value="<?php echo $q['question_id']; ?>">
                            <button type="submit" class="text-red-600 hover:text-red-800 font-semibold">✕ Delete</button>
                        </form>
                    </div>
                    <p class="text-xl font-medium mb-6">
                        <span class="text-green-600 font-bold text-2xl"><?php echo $no++; ?>.</span>
                        <?php echo htmlspecialchars($q['question_text']); ?>
                    </p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 ml-10">
                        <?php 
                        $opts = ['A' => $q['option_a'], 'B' => $q['option_b'], 'C' => $q['option_c'], 'D' => $q['option_d']];
                        foreach ($opts as $key => $text):
                            $correct = $q['correct_option'] === $key;
                        ?>
                        <div class="p-5 rounded-xl border-2 <?php echo $correct ? 'bg-green-100 border-green-500' : 'bg-gray-50 border-gray-300'; ?>">
                            <span class="font-bold text-xl"><?php echo $key; ?>.</span>
                            <span class="ml-4"><?php echo htmlspecialchars($text); ?></span>
                            <?php if ($correct): ?>
                                <span class="ml-6 px-4 py-1 bg-green-700 text-white rounded-full text-sm font-bold">✓ Correct</span>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>

            <div class="mt-10 text-center space-x-4">
                <a href="teacher_quiz_results.php?quiz_id=<?php echo $quiz_id; ?>" class="px-8 py-4 bg-purple-600 text-white font-bold rounded-lg hover:bg-purple-700">
                    View Results
                </a>
                <a href="teacher_manage_content.php" class="px-8 py-4 bg-blue-600 text-white font-bold rounded-lg hover:bg-blue-700">
                    Back to Content
                </a>
            </div>
        </div>
        <?php else: ?>
        <div class="bg-white rounded-xl shadow-lg p-16 text-center">
            <div class="text-8xl text-gray-300 mb-6">❓</div>
            <p class="text-3xl font-bold text-gray-600">No questions yet</p>
            <p class="text-xl text-gray-500 mt-4">Add your first question using the form above!</p>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>