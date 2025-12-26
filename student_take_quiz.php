<?php
// student_take_quiz.php
require_once 'config.php';
checkRole('student');

$student_id = $_SESSION['user_id'];
$student_name = $_SESSION['full_name'];

// Accept both 'quiz_id' and 'id' parameter
$quiz_id = intval($_GET['quiz_id'] ?? $_GET['id'] ?? 0);
$error = '';
$quiz = null;
$attempts_made = 0;
$attempts_allowed = 1;

if ($quiz_id <= 0) {
    $error = "Invalid quiz selected. Please choose from the quiz list.";
} else {
    // Fetch quiz with strict enrollment and availability checking part
    $quiz_query = "SELECT q.*, s.subject_name,
                   (SELECT COUNT(*) FROM quiz_attempts qa 
                    WHERE qa.quiz_id = q.quiz_id AND qa.student_id = ?) AS attempts_made
                   FROM quizzes q
                   INNER JOIN subjects s ON q.subject_id = s.subject_id
                   INNER JOIN student_subjects ss ON s.subject_id = ss.subject_id
                   WHERE q.quiz_id = ? 
                     AND q.is_active = 1
                     AND ss.student_id = ?
                     AND (q.start_date IS NULL OR q.start_date <= NOW())
                     AND (q.end_date IS NULL OR q.end_date >= NOW())";

    $stmt = $conn->prepare($quiz_query);
    $stmt->bind_param("iii", $student_id, $quiz_id, $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        // Find out why
        $active_check = $conn->query("SELECT is_active FROM quizzes WHERE quiz_id = " . (int)$quiz_id)->fetch_assoc();
        $enroll_check = $conn->query("SELECT 1 FROM student_subjects ss 
                                      INNER JOIN quizzes q ON q.subject_id = ss.subject_id 
                                      WHERE ss.student_id = $student_id AND q.quiz_id = $quiz_id")->num_rows;

        if (!$active_check || $active_check['is_active'] == 0) {
            $error = "This quiz is no longer available (deleted or deactivated).";
        } elseif ($enroll_check == 0) {
            $error = "You are not enrolled in the subject for this quiz. Contact your teacher.";
        } else {
            $error = "This quiz is not currently open (check start/end dates).";
        }
    } else {
        $quiz = $result->fetch_assoc();
        $attempts_made = (int)$quiz['attempts_made'];
        $attempts_allowed = (int)$quiz['attempts_allowed'];

        if ($attempts_made >= $attempts_allowed) {
            $error = "You have already used all {$attempts_allowed} attempt(s) for this quiz.";
        }
    }
}

// Load questions only if quiz is valid and no error
$questions = null;
if ($quiz !== null && empty($error)) {
    $q_query = "SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY question_order ASC";
    $stmt = $conn->prepare($q_query);
    $stmt->bind_param("i", $quiz_id);
    $stmt->execute();
    $questions = $stmt->get_result();

    if ($questions->num_rows === 0) {
        $error = "This quiz has no questions yet. Please wait for your teacher to add them.";
    }
}

$success = false;
$answers = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $quiz !== null && empty($error)) {
    $answers = $_POST['answer'] ?? [];
    $score = 0;
    $total_questions = $questions->num_rows;

    $questions->data_seek(0);
    while ($q = $questions->fetch_assoc()) {
        if (isset($answers[$q['question_id']]) && $answers[$q['question_id']] === $q['correct_option']) {
            $score++;
        }
    }

    $percentage = $total_questions > 0 ? round(($score / $total_questions) * 100, 2) : 0;
    $final_score = round(($score / $total_questions) * $quiz['total_marks'], 2);

    $insert = "INSERT INTO quiz_attempts (quiz_id, student_id, score, percentage, attempted_at)
               VALUES (?, ?, ?, ?, NOW())
               ON DUPLICATE KEY UPDATE score = VALUES(score), percentage = VALUES(percentage), attempted_at = NOW()";
    $stmt = $conn->prepare($insert);
    $stmt->bind_param("iidd", $quiz_id, $student_id, $final_score, $percentage);
    $stmt->execute();

    $letter_grade = calculateGrade($percentage);
    $grade_color = getGradeColor($letter_grade);
    $success = true;

    echo "<script>localStorage.removeItem('quiz_answers_$quiz_id');</script>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Take Quiz - SmartOLevel LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php if (!$success && empty($error) && $quiz !== null): ?>
    <script>
        const quizId = <?php echo $quiz_id; ?>;
        let timeLeft = <?php echo $quiz['duration_minutes'] * 60; ?>;

        function saveAnswer(qid, val) {
            let ans = JSON.parse(localStorage.getItem('quiz_answers_' + quizId) || '{}');
            if (val) ans[qid] = val;
            else delete ans[qid];
            localStorage.setItem('quiz_answers_' + quizId, JSON.stringify(ans));
        }

        function loadAnswers() {
            const saved = localStorage.getItem('quiz_answers_' + quizId);
            if (saved) {
                const ans = JSON.parse(saved);
                Object.keys(ans).forEach(id => {
                    const radio = document.querySelector(`input[name="answer[${id}]"][value="${ans[id]}"]`);
                    if (radio) radio.checked = true;
                });
            }
        }

        function startTimer() {
            const timer = document.getElementById('timer');
            const form = document.getElementById('quizForm');
            const interval = setInterval(() => {
                if (timeLeft <= 0) {
                    clearInterval(interval);
                    alert("Time's up! Your answers are being submitted...");
                    form.submit();
                } else {
                    timeLeft--;
                    const m = String(Math.floor(timeLeft / 60)).padStart(2, '0');
                    const s = String(timeLeft % 60).padStart(2, '0');
                    timer.textContent = m + ':' + s;
                    if (timeLeft <= 60) {
                        timer.classList.add('text-red-600', 'font-bold', 'animate-pulse');
                    }
                }
            }, 1000);
        }

        window.onload = () => {
            loadAnswers();
            startTimer();
        };
    </script>
    <?php endif; ?>
</head>
<body class="bg-gray-100 min-h-screen">
    <header class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white p-4 shadow-lg fixed top-0 w-full z-50">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold">SmartOLevel LMS</h1>
                <p class="text-sm">Quiz: <?php echo htmlspecialchars($quiz['title'] ?? 'Not Available'); ?></p>
            </div>
            <div class="flex items-center gap-6">
                <div class="text-right">
                    <p class="text-sm"><?php echo htmlspecialchars($student_name); ?></p>
                    <p class="text-xs">Attempt <?php echo $attempts_made + ($success ? 0 : 1); ?> of <?php echo $attempts_allowed; ?></p>
                </div>
                <?php if (!$success && empty($error) && $quiz !== null): ?>
                <div id="timer" class="text-3xl font-mono font-bold">
                    <?php echo sprintf("%02d:00", $quiz['duration_minutes']); ?>
                </div>
                <?php endif; ?>
                <a href="student_all_quizzes.php" class="px-5 py-2 bg-white/20 rounded-lg hover:bg-white/30 transition">
                    ← Back to Quizzes
                </a>
            </div>
        </div>
    </header>

    <div class="pt-24 px-4 max-w-5xl mx-auto pb-20">
        <?php if (!empty($error)): ?>
            <div class="bg-red-100 border-l-8 border-red-600 text-red-800 p-10 rounded-2xl text-center text-2xl font-bold shadow-xl">
                ⚠️ <?php echo htmlspecialchars($error); ?>
                <div class="mt-8">
                    <a href="student_all_quizzes.php" class="px-8 py-4 bg-blue-600 text-white rounded-xl hover:bg-blue-700 text-lg mr-4">
                        ← All Quizzes
                    </a>
                    <a href="student_dashboard.php" class="px-8 py-4 bg-gray-600 text-white rounded-xl hover:bg-gray-700 text-lg">
                        Dashboard
                    </a>
                </div>
            </div>

        <?php elseif ($success): ?>
            <!-- Results Screen -->
            <div class="bg-white rounded-3xl shadow-2xl p-12 text-center max-w-4xl mx-auto">
                <div class="text-9xl mb-8"><?php echo in_array($letter_grade, ['A','B']) ? '🎉' : '📝'; ?></div>
                <h2 class="text-5xl font-bold text-gray-800 mb-10">Quiz Completed!</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-10 mb-12">
                    <div class="bg-blue-50 p-8 rounded-2xl">
                        <p class="text-lg text-gray-600">Your Score</p>
                        <p class="text-5xl font-bold text-blue-600 mt-2"><?php echo $final_score; ?> / <?php echo $quiz['total_marks']; ?></p>
                    </div>
                    <div class="bg-green-50 p-8 rounded-2xl">
                        <p class="text-lg text-gray-600">Percentage</p>
                        <p class="text-5xl font-bold mt-2" style="color: <?php echo $grade_color; ?>">
                            <?php echo $percentage; ?>%
                        </p>
                    </div>
                    <div class="bg-purple-50 p-8 rounded-2xl">
                        <p class="text-lg text-gray-600">Grade</p>
                        <p class="text-6xl font-bold mt-2" style="color: <?php echo $grade_color; ?>">
                            <?php echo $letter_grade; ?>
                        </p>
                    </div>
                </div>
                <a href="student_all_quizzes.php" class="px-10 py-5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white text-xl font-bold rounded-xl shadow-lg hover:shadow-2xl transition">
                    Back to Quizzes
                </a>
            </div>

        <?php else: ?>
            <!-- Take Quiz Form -->
            <div class="bg-white rounded-3xl shadow-2xl overflow-hidden">
                <div class="bg-gradient-to-r from-blue-600 to-indigo-700 text-white p-10">
                    <h2 class="text-4xl font-bold mb-4"><?php echo htmlspecialchars($quiz['title']); ?></h2>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-6 text-lg">
                        <div><strong>Subject:</strong> <?php echo htmlspecialchars($quiz['subject_name']); ?></div>
                        <div><strong>Questions:</strong> <?php echo $questions->num_rows; ?></div>
                        <div><strong>Marks:</strong> <?php echo $quiz['total_marks']; ?></div>
                        <div><strong>Time:</strong> <?php echo $quiz['duration_minutes']; ?> minutes</div>
                    </div>
                    <?php if (!empty($quiz['description'])): ?>
                        <p class="mt-6 italic opacity-90"><?php echo nl2br(htmlspecialchars($quiz['description'])); ?></p>
                    <?php endif; ?>
                </div>

                <form id="quizForm" method="POST" class="p-10">
                    <div class="space-y-16">
                        <?php $no = 1; while ($q = $questions->fetch_assoc()): ?>
                        <div class="border-b-2 border-gray-200 pb-12 last:border-0">
                            <p class="text-2xl font-semibold mb-8">
                                <span class="text-indigo-600 font-bold"><?php echo $no++; ?>.</span>
                                <?php echo htmlspecialchars($q['question_text']); ?>
                            </p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 ml-12">
                                <?php foreach (['A','B','C','D'] as $opt): 
                                    $text = $q["option_" . strtolower($opt)];
                                ?>
                                <label class="flex items-center p-6 bg-gray-50 rounded-2xl cursor-pointer hover:bg-indigo-50 transition border-4 border-transparent hover:border-indigo-400">
                                    <input type="radio" 
                                           name="answer[<?php echo $q['question_id']; ?>]" 
                                           value="<?php echo $opt; ?>" 
                                           required
                                           onchange="saveAnswer(<?php echo $q['question_id']; ?>, this.value)"
                                           class="w-6 h-6 text-indigo-600 focus:ring-indigo-500">
                                    <span class="ml-6 text-xl font-medium"><?php echo $opt; ?>.</span>
                                    <span class="ml-4 text-lg"><?php echo htmlspecialchars($text); ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>

                    <div class="mt-16 text-center">
                        <button type="submit" 
                                class="px-12 py-6 bg-gradient-to-r from-indigo-600 to-blue-700 text-white text-2xl font-bold rounded-2xl shadow-2xl hover:shadow-3xl transition transform hover:scale-110">
                            Submit Quiz
                        </button>
                        <p class="mt-6 text-gray-600 text-lg">Your answers are automatically saved as you go</p>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>