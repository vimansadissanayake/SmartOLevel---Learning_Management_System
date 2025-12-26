<?php
require_once 'config.php';
checkRole('teacher');

$teacher_id = $_SESSION['user_id'];
$success = '';
$error = '';

$subjects_query = "SELECT s.* FROM subjects s INNER JOIN teacher_subjects ts ON s.subject_id = ts.subject_id WHERE ts.teacher_id = ? AND s.is_active = 1";
$stmt = $conn->prepare($subjects_query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$subjects = $stmt->get_result();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_quiz'])) {
    $subject_id = intval($_POST['subject_id']);
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $total_marks = intval($_POST['total_marks']);
    $duration = intval($_POST['duration_minutes']);
    $passing_marks = intval($_POST['passing_marks']);
    $attempts = intval($_POST['attempts_allowed']);
    $show_answers = isset($_POST['show_correct_answers']) ? 1 : 0;
    $start_date = $_POST['start_date'] ?: null;
    $end_date = $_POST['end_date'] ?: null;
    
    if ($subject_id && $title && $total_marks && $duration && $passing_marks) {
        $stmt = $conn->prepare("INSERT INTO quizzes (subject_id, teacher_id, title, description, total_marks, duration_minutes, passing_marks, attempts_allowed, show_correct_answers, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissiiiiiss", $subject_id, $teacher_id, $title, $description, $total_marks, $duration, $passing_marks, $attempts, $show_answers, $start_date, $end_date);
        
        if ($stmt->execute()) {
            $quiz_id = $stmt->insert_id;
            $success = "Quiz created! Now add questions.";
            header("Location: teacher_add_quiz_questions.php?quiz_id=$quiz_id");
            exit();
        } else {
            $error = "Failed to create quiz";
        }
    } else {
        $error = "Please fill all required fields";
    }
}

$quizzes_query = "SELECT q.*, s.subject_name, 
                  (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.quiz_id) as question_count,
                  (SELECT COUNT(DISTINCT student_id) FROM quiz_attempts WHERE quiz_id = q.quiz_id) as student_count
                  FROM quizzes q
                  INNER JOIN subjects s ON q.subject_id = s.subject_id
                  WHERE q.teacher_id = ? AND q.is_active = 1
                  ORDER BY q.created_at DESC";
$stmt = $conn->prepare($quizzes_query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$quizzes = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Quiz - Teacher</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <header class="bg-gradient-to-r from-green-600 to-teal-600 text-white p-4 shadow-lg">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold">Create Quiz</h1>
                <p class="text-sm text-green-100">Create assessments for students</p>
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

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 bg-white rounded-lg shadow-md p-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-4">Create New Quiz</h2>
                <form method="POST">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Subject *</label>
                            <select name="subject_id" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                                <option value="">Select Subject</option>
                                <?php $subjects->data_seek(0); while ($subject = $subjects->fetch_assoc()): ?>
                                    <option value="<?php echo $subject['subject_id']; ?>"><?php echo htmlspecialchars($subject['subject_name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Quiz Title *</label>
                            <input type="text" name="title" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                            <textarea name="description" rows="3" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500"></textarea>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Total Marks *</label>
                                <input type="number" name="total_marks" required min="1" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Passing Marks *</label>
                                <input type="number" name="passing_marks" required min="1" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Duration (minutes) *</label>
                                <input type="number" name="duration_minutes" required min="1" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Attempts Allowed *</label>
                                <input type="number" name="attempts_allowed" required min="1" value="1" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Start Date (Optional)</label>
                                <input type="datetime-local" name="start_date" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">End Date (Optional)</label>
                                <input type="datetime-local" name="end_date" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                            </div>
                        </div>

                        <div>
                            <label class="flex items-center">
                                <input type="checkbox" name="show_correct_answers" class="mr-2">
                                <span class="text-sm">Show correct answers after submission</span>
                            </label>
                        </div>
                    </div>

                    <button type="submit" name="create_quiz" class="mt-6 w-full px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 font-semibold">
                        ❓ Create Quiz & Add Questions
                    </button>
                </form>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">My Quizzes</h2>
                <div class="max-h-[600px] overflow-y-auto space-y-3">
                    <?php if ($quizzes->num_rows > 0): ?>
                        <?php while ($quiz = $quizzes->fetch_assoc()): ?>
                        <div class="border border-gray-200 rounded-lg p-3 hover:shadow-md transition">
                            <h4 class="font-bold text-sm text-gray-800 mb-1"><?php echo htmlspecialchars($quiz['title']); ?></h4>
                            <p class="text-xs text-gray-600 mb-2"><?php echo htmlspecialchars($quiz['subject_name']); ?></p>
                            <div class="text-xs text-gray-500 space-y-1">
                                <div>Questions: <?php echo $quiz['question_count']; ?></div>
                                <div>Students: <?php echo $quiz['student_count']; ?></div>
                                <div>Marks: <?php echo $quiz['total_marks']; ?></div>
                            </div>
                            <div class="mt-2 flex gap-1">
                                <a href="teacher_add_quiz_questions.php?quiz_id=<?php echo $quiz['quiz_id']; ?>" class="flex-1 text-center px-2 py-1 bg-blue-100 text-blue-700 rounded hover:bg-blue-200 text-xs">
                                    Questions
                                </a>
                                <a href="teacher_quiz_results.php?quiz_id=<?php echo $quiz['quiz_id']; ?>" class="flex-1 text-center px-2 py-1 bg-green-100 text-green-700 rounded hover:bg-green-200 text-xs">
                                    Results
                                </a>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="text-gray-500 text-center py-4 text-sm">No quizzes yet</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>