<?php
require_once 'config.php';
checkRole('student');

$student_id = $_SESSION['user_id'];
$student_name = $_SESSION['full_name'];
$grade = $_SESSION['grade'] ?? '10';

if (!$student_id) {
    header("Location: login.php");
    exit();
}

// Get enrolled subjects with content counts
$query = "SELECT s.subject_id, s.subject_name, s.subject_code, s.subject_category,
          (SELECT COUNT(*) FROM lecture_notes ln WHERE ln.subject_id = s.subject_id AND ln.is_active = 1) AS notes_count,
          (SELECT COUNT(*) FROM lecture_videos lv WHERE lv.subject_id = s.subject_id AND lv.is_active = 1) AS videos_count,
          (SELECT COUNT(*) FROM assignments a WHERE a.subject_id = s.subject_id AND a.is_active = 1) AS assignments_count,
          (SELECT COUNT(*) FROM quizzes q WHERE q.subject_id = s.subject_id AND q.is_active = 1) AS quizzes_count
          FROM subjects s
          INNER JOIN student_subjects ss ON s.subject_id = ss.subject_id
          WHERE ss.student_id = ? AND s.grade_level = ? AND s.is_active = 1
          ORDER BY s.subject_category, s.subject_name";
$stmt = $conn->prepare($query);
$stmt->bind_param("is", $student_id, $grade);
$stmt->execute();
$subjects = $stmt->get_result();

// Get pending assignments
$pending_query = "SELECT COUNT(*) as pending_count 
                  FROM assignments a
                  INNER JOIN student_subjects ss ON a.subject_id = ss.subject_id
                  LEFT JOIN assignment_submissions asub ON a.assignment_id = asub.assignment_id AND asub.student_id = ?
                  WHERE ss.student_id = ? AND a.is_active = 1 AND asub.submission_id IS NULL AND a.due_date > NOW()";
$stmt = $conn->prepare($pending_query);
$stmt->bind_param("ii", $student_id, $student_id);
$stmt->execute();
$pending_assignments = $stmt->get_result()->fetch_assoc()['pending_count'];

// Get recent grades
$grades_query = "SELECT ag.marks_obtained, a.total_marks, a.assignment_title, s.subject_name, ag.graded_at
                 FROM assignment_grades ag
                 INNER JOIN assignment_submissions asub ON ag.submission_id = asub.submission_id
                 INNER JOIN assignments a ON asub.assignment_id = a.assignment_id
                 INNER JOIN subjects s ON a.subject_id = s.subject_id
                 WHERE asub.student_id = ?
                 ORDER BY ag.graded_at DESC LIMIT 5";
$stmt = $conn->prepare($grades_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$recent_grades = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Dashboard - SmartOLevel LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white p-4 shadow-lg">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold">SmartOLevel LMS</h1>
                <p class="text-sm text-blue-100">Student Portal - Grade <?php echo htmlspecialchars($grade); ?></p>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-sm">Welcome, <?php echo htmlspecialchars($student_name); ?></span>
                <a href="logout.php" class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition">Logout</a>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto p-6">
        <!-- Pending Assignments Alert -->
        <?php if ($pending_assignments > 0): ?>
        <div class="bg-yellow-100 border-l-4 border-yellow-500 p-4 mb-6 rounded">
            <div class="flex items-center">
                <span class="text-2xl mr-3">⚠️</span>
                <div>
                    <p class="font-bold text-yellow-700">Pending Assignments</p>
                    <p class="text-sm text-yellow-600">You have <?php echo $pending_assignments; ?> assignments due. Don't forget to submit!</p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Quick Access -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <a href="student_all_videos.php" class="bg-white p-4 rounded-lg shadow hover:shadow-lg transition text-center">
                <div class="text-4xl mb-2">🎥</div>
                <p class="font-bold text-gray-800">Watch Videos</p>
            </a>
            <a href="student_all_notes.php" class="bg-white p-4 rounded-lg shadow hover:shadow-lg transition text-center">
                <div class="text-4xl mb-2">📄</div>
                <p class="font-bold text-gray-800">Download Notes</p>
            </a>
            <a href="student_all_assignments.php" class="bg-white p-4 rounded-lg shadow hover:shadow-lg transition text-center">
                <div class="text-4xl mb-2">📝</div>
                <p class="font-bold text-gray-800">Assignments</p>
            </a>
            <a href="student_all_quizzes.php" class="bg-white p-4 rounded-lg shadow hover:shadow-lg transition text-center">
                <div class="text-4xl mb-2">❓</div>
                <p class="font-bold text-gray-800">Take Quizzes</p>
            </a>
        </div>

        <!-- Recent Grades -->
        <?php if ($recent_grades->num_rows > 0): ?>
        <div class="bg-white p-6 rounded-lg shadow-md mb-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">Recent Grades</h2>
            <div class="space-y-2">
                <?php while ($grade_item = $recent_grades->fetch_assoc()): 
                    $percentage = ($grade_item['marks_obtained'] / $grade_item['total_marks']) * 100;
                    $grade_letter = calculateGrade($percentage);
                    $grade_color = getGradeColor($grade_letter);
                ?>
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                    <div class="flex-1">
                        <p class="font-medium text-gray-800"><?php echo htmlspecialchars($grade_item['assignment_title']); ?></p>
                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($grade_item['subject_name']); ?> • <?php echo formatDate($grade_item['graded_at']); ?></p>
                    </div>
                    <div class="text-right">
                        <p class="text-2xl font-bold" style="color: <?php echo $grade_color; ?>">
                            <?php echo number_format($grade_item['marks_obtained'], 1); ?>/<?php echo $grade_item['total_marks']; ?>
                        </p>
                        <p class="text-sm font-bold" style="color: <?php echo $grade_color; ?>"><?php echo $grade_letter; ?></p>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <a href="student_view_grades.php" class="block mt-4 text-center text-blue-600 hover:underline">View All Grades →</a>
        </div>
        <?php endif; ?>

        <!-- My Subjects -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">My Subjects</h2>
            
            <?php if ($subjects->num_rows > 0): 
                $current_category = '';
                while ($subject = $subjects->fetch_assoc()): 
                    if ($current_category != $subject['subject_category']):
                        if ($current_category != '') echo '</div>';
                        $current_category = $subject['subject_category'];
            ?>
                <h3 class="text-lg font-bold text-gray-700 mt-4 mb-3 border-b pb-2"><?php echo htmlspecialchars($current_category); ?></h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php endif; ?>
                
                <div class="border border-gray-200 rounded-lg p-4 hover:border-blue-500 hover:shadow-md transition">
                    <h4 class="font-bold text-lg text-blue-700 mb-2"><?php echo htmlspecialchars($subject['subject_name']); ?></h4>
                    <p class="text-sm text-gray-600 mb-3">Code: <?php echo htmlspecialchars($subject['subject_code']); ?></p>
                    
                    <div class="grid grid-cols-4 gap-2 text-center mb-4">
                        <div class="bg-blue-50 p-2 rounded">
                            <p class="text-xs text-blue-700">Notes</p>
                            <p class="font-bold text-blue-600"><?php echo $subject['notes_count']; ?></p>
                        </div>
                        <div class="bg-green-50 p-2 rounded">
                            <p class="text-xs text-green-700">Videos</p>
                            <p class="font-bold text-green-600"><?php echo $subject['videos_count']; ?></p>
                        </div>
                        <div class="bg-yellow-50 p-2 rounded">
                            <p class="text-xs text-yellow-700">Tasks</p>
                            <p class="font-bold text-yellow-600"><?php echo $subject['assignments_count']; ?></p>
                        </div>
                        <div class="bg-purple-50 p-2 rounded">
                            <p class="text-xs text-purple-700">Quiz</p>
                            <p class="font-bold text-purple-600"><?php echo $subject['quizzes_count']; ?></p>
                        </div>
                    </div>
                    
                    <a href="student_subject_view.php?id=<?php echo $subject['subject_id']; ?>" 
                       class="block text-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                        Open Subject
                    </a>
                </div>
                
            <?php 
                endwhile;
                echo '</div>';
            else: 
            ?>
                <div class="text-center py-12">
                    <div class="text-6xl mb-4">📚</div>
                    <p class="text-xl text-gray-500">No subjects enrolled yet</p>
                    <p class="text-gray-400 mt-2">Contact your teacher or admin to enroll in subjects</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>