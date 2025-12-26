<?php
require_once 'config.php';
checkRole('teacher');

$teacher_id = $_SESSION['user_id'];
$teacher_name = $_SESSION['full_name'];

// Get teacher's subjects
$subjects_query = "SELECT s.* FROM subjects s
                   INNER JOIN teacher_subjects ts ON s.subject_id = ts.subject_id
                   WHERE ts.teacher_id = ?";
$stmt = $conn->prepare($subjects_query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$subjects = $stmt->get_result();

// Get statistics
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM lecture_notes WHERE teacher_id = ? AND is_active = 1) as total_notes,
    (SELECT COUNT(*) FROM lecture_videos WHERE teacher_id = ? AND is_active = 1) as total_videos,
    (SELECT COUNT(*) FROM assignments WHERE teacher_id = ? AND is_active = 1) as total_assignments,
    (SELECT COUNT(*) FROM quizzes WHERE teacher_id = ? AND is_active = 1) as total_quizzes,
    (SELECT COUNT(DISTINCT ss.student_id) FROM student_subjects ss 
     INNER JOIN teacher_subjects ts ON ss.subject_id = ts.subject_id 
     WHERE ts.teacher_id = ?) as total_students";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param("iiiii", $teacher_id, $teacher_id, $teacher_id, $teacher_id, $teacher_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Get pending submissions count
$pending_query = "SELECT COUNT(*) as pending_count 
                  FROM assignment_submissions asub
                  INNER JOIN assignments a ON asub.assignment_id = a.assignment_id
                  LEFT JOIN assignment_grades ag ON asub.submission_id = ag.submission_id
                  WHERE a.teacher_id = ? AND ag.grade_id IS NULL";
$stmt = $conn->prepare($pending_query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$pending_submissions = $stmt->get_result()->fetch_assoc()['pending_count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Teacher Dashboard - SmartOLevel LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-green-600 to-teal-600 text-white p-4 shadow-lg">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold">SmartOLevel LMS</h1>
                <p class="text-sm text-green-100">Teacher Dashboard</p>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-sm">Welcome, <?php echo htmlspecialchars($teacher_name); ?></span>
                <a href="logout.php" class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition">Logout</a>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto p-6">
        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
            <div class="bg-white p-4 rounded-lg shadow-md border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-600">Students</p>
                        <p class="text-2xl font-bold text-blue-600"><?php echo $stats['total_students']; ?></p>
                    </div>
                    <div class="text-3xl">👨‍🎓</div>
                </div>
            </div>
            
            <div class="bg-white p-4 rounded-lg shadow-md border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-600">Notes</p>
                        <p class="text-2xl font-bold text-green-600"><?php echo $stats['total_notes']; ?></p>
                    </div>
                    <div class="text-3xl">📄</div>
                </div>
            </div>
            
            <div class="bg-white p-4 rounded-lg shadow-md border-l-4 border-purple-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-600">Videos</p>
                        <p class="text-2xl font-bold text-purple-600"><?php echo $stats['total_videos']; ?></p>
                    </div>
                    <div class="text-3xl">🎥</div>
                </div>
            </div>
            
            <div class="bg-white p-4 rounded-lg shadow-md border-l-4 border-yellow-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-600">Assignments</p>
                        <p class="text-2xl font-bold text-yellow-600"><?php echo $stats['total_assignments']; ?></p>
                    </div>
                    <div class="text-3xl">📝</div>
                </div>
            </div>
            
            <div class="bg-white p-4 rounded-lg shadow-md border-l-4 border-red-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-600">Quizzes</p>
                        <p class="text-2xl font-bold text-red-600"><?php echo $stats['total_quizzes']; ?></p>
                    </div>
                    <div class="text-3xl">❓</div>
                </div>
            </div>
        </div>

        <!-- Pending Submissions Alert -->
        <?php if ($pending_submissions > 0): ?>
        <div class="bg-yellow-100 border-l-4 border-yellow-500 p-4 mb-6 rounded">
            <div class="flex items-center">
                <span class="text-2xl mr-3">⚠️</span>
                <div>
                    <p class="font-bold text-yellow-700">Pending Submissions</p>
                    <p class="text-sm text-yellow-600">You have <?php echo $pending_submissions; ?> assignment submissions waiting for grading.</p>
                </div>
                <a href="teacher_view_submissions.php" class="ml-auto px-4 py-2 bg-yellow-600 text-white rounded hover:bg-yellow-700">Grade Now</a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Main Actions -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <a href="teacher_upload_notes.php" class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition text-center">
                <div class="text-5xl mb-3">📄</div>
                <h3 class="text-lg font-bold text-gray-800">Upload Notes/PDFs</h3>
                <p class="text-sm text-gray-600 mt-2">Upload study materials</p>
            </a>
            
            <a href="teacher_upload_video.php" class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition text-center">
                <div class="text-5xl mb-3">🎥</div>
                <h3 class="text-lg font-bold text-gray-800">Upload Videos</h3>
                <p class="text-sm text-gray-600 mt-2">Add YouTube links</p>
            </a>
            
            <a href="teacher_create_assignment.php" class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition text-center">
                <div class="text-5xl mb-3">📝</div>
                <h3 class="text-lg font-bold text-gray-800">Create Assignment</h3>
                <p class="text-sm text-gray-600 mt-2">Set homework & tasks</p>
            </a>
            
            <a href="teacher_create_quiz.php" class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition text-center">
                <div class="text-5xl mb-3">❓</div>
                <h3 class="text-lg font-bold text-gray-800">Create Quiz</h3>
                <p class="text-sm text-gray-600 mt-2">Build assessments</p>
            </a>
        </div>

        <!-- Management Links -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <a href="teacher_view_submissions.php" class="bg-gradient-to-r from-blue-500 to-blue-600 text-white p-4 rounded-lg shadow hover:shadow-lg transition">
                <div class="flex items-center">
                    <span class="text-3xl mr-3">✍️</span>
                    <div>
                        <p class="font-bold">View Submissions</p>
                        <p class="text-sm text-blue-100">Grade student work</p>
                    </div>
                </div>
            </a>
            
            <a href="teacher_manage_content.php" class="bg-gradient-to-r from-green-500 to-green-600 text-white p-4 rounded-lg shadow hover:shadow-lg transition">
                <div class="flex items-center">
                    <span class="text-3xl mr-3">📚</span>
                    <div>
                        <p class="font-bold">Manage Content</p>
                        <p class="text-sm text-green-100">Edit/Delete materials</p>
                    </div>
                </div>
            </a>
            
            <a href="teacher_student_results.php" class="bg-gradient-to-r from-purple-500 to-purple-600 text-white p-4 rounded-lg shadow hover:shadow-lg transition">
                <div class="flex items-center">
                    <span class="text-3xl mr-3">📊</span>
                    <div>
                        <p class="font-bold">Student Results</p>
                        <p class="text-sm text-purple-100">View performance</p>
                    </div>
                </div>
            </a>
        </div>

        <!-- My Subjects -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">My Subjects</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <?php 
                $subjects->data_seek(0); // Reset pointer
                while ($subject = $subjects->fetch_assoc()): 
                ?>
                <div class="border border-gray-200 rounded-lg p-4 hover:border-green-500 transition">
                    <h3 class="font-bold text-lg text-gray-800"><?php echo htmlspecialchars($subject['subject_name']); ?></h3>
                    <p class="text-sm text-gray-600">Code: <?php echo htmlspecialchars($subject['subject_code']); ?></p>
                    <p class="text-sm text-gray-600">Grade: <?php echo htmlspecialchars($subject['grade_level']); ?></p>
                    <p class="text-xs text-gray-500 mt-2"><?php echo htmlspecialchars($subject['description']); ?></p>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
</body>
</html>