<?php
// student_view_grades.php
require_once 'config.php';
checkRole('student');

$student_id = $_SESSION['user_id'];
$student_name = $_SESSION['full_name'];
$grade_level = $_SESSION['grade'] ?? '10';

// Fetch all graded assignments for this student
$grades_query = "SELECT ag.marks_obtained, ag.grade AS letter_grade, ag.feedback, ag.graded_at,
                        a.assignment_title, a.total_marks, a.due_date,
                        s.subject_name, s.subject_code,
                        t.full_name AS teacher_name
                 FROM assignment_grades ag
                 INNER JOIN assignment_submissions asub ON ag.submission_id = asub.submission_id
                 INNER JOIN assignments a ON asub.assignment_id = a.assignment_id
                 INNER JOIN subjects s ON a.subject_id = s.subject_id
                 INNER JOIN users t ON a.teacher_id = t.user_id
                 WHERE asub.student_id = ?
                 ORDER BY ag.graded_at DESC";

$stmt = $conn->prepare($grades_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$grades = $stmt->get_result();

// Calculate overall statistics
$stats_query = "SELECT 
    COUNT(*) AS total_graded,
    AVG(ag.marks_obtained / a.total_marks * 100) AS average_percentage,
    SUM(ag.marks_obtained) AS total_obtained,
    SUM(a.total_marks) AS total_possible
    FROM assignment_grades ag
    INNER JOIN assignment_submissions asub ON ag.submission_id = asub.submission_id
    INNER JOIN assignments a ON asub.assignment_id = a.assignment_id
    WHERE asub.student_id = ?";

$stmt = $conn->prepare($stats_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$stats_result = $stmt->get_result();
$stats = $stats_result->fetch_assoc();

$overall_percentage = $stats['average_percentage'] ? round($stats['average_percentage'], 2) : 0;
$overall_letter = $overall_percentage ? calculateGrade($overall_percentage) : '-';
$overall_color = getGradeColor($overall_letter);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Grades - SmartOLevel LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white p-4 shadow-lg">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold">SmartOLevel LMS</h1>
                <p class="text-sm text-blue-100">My Grades & Performance</p>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-sm">Welcome, <?php echo htmlspecialchars($student_name); ?></span>
                <a href="logout.php" class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition">Logout</a>
                <a href="student_dashboard.php" class="px-4 py-2 bg-indigo-700 text-white rounded-lg hover:bg-indigo-800 transition">Back to Dashboard</a>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto p-6">
        <!-- Overall Performance Summary -->
        <?php if ($stats['total_graded'] > 0): ?>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white p-6 rounded-lg shadow-md text-center">
                <p class="text-sm text-gray-600">Total Graded Assignments</p>
                <p class="text-3xl font-bold text-blue-600"><?php echo $stats['total_graded']; ?></p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-md text-center">
                <p class="text-sm text-gray-600">Overall Average</p>
                <p class="text-3xl font-bold" style="color: <?php echo $overall_color; ?>">
                    <?php echo $overall_percentage; ?>%
                </p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-md text-center">
                <p class="text-sm text-gray-600">Total Marks</p>
                <p class="text-2xl font-bold text-gray-800">
                    <?php echo $stats['total_obtained'] ?? 0; ?> / <?php echo $stats['total_possible'] ?? 0; ?>
                </p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-md text-center">
                <p class="text-sm text-gray-600">Overall Grade</p>
                <p class="text-4xl font-bold" style="color: <?php echo $overall_color; ?>">
                    <?php echo $overall_letter; ?>
                </p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Grades List -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 bg-blue-50 border-b border-blue-200">
                <h2 class="text-2xl font-bold text-gray-800">Assignment Grades</h2>
                <p class="text-sm text-gray-600 mt-1">All your graded submissions</p>
            </div>

            <?php if ($grades->num_rows > 0): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assignment</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teacher</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Marks</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Percentage</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grade</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due / Graded</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Feedback</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($row = $grades->fetch_assoc()): 
                                $percentage = ($row['marks_obtained'] / $row['total_marks']) * 100;
                                $letter_grade = calculateGrade($percentage);
                                $grade_color = getGradeColor($letter_grade);
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($row['assignment_title']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    <?php echo htmlspecialchars($row['subject_name']); ?>
                                    <span class="text-xs text-gray-500 block"><?php echo $row['subject_code']; ?></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    <?php echo htmlspecialchars($row['teacher_name']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">
                                    <?php echo number_format($row['marks_obtained'], 1); ?> / <?php echo $row['total_marks']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold" style="color: <?php echo $grade_color; ?>">
                                    <?php echo number_format($percentage, 1); ?>%
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-lg font-bold" style="color: <?php echo $grade_color; ?>">
                                    <?php echo $letter_grade; ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    <div>Due: <?php echo formatDate($row['due_date']); ?></div>
                                    <div class="text-xs text-gray-500">Graded: <?php echo formatDate($row['graded_at']); ?></div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700 max-w-xs">
                                    <?php echo $row['feedback'] ? nl2br(htmlspecialchars($row['feedback'])) : '<span class="text-gray-400 italic">No feedback</span>'; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-12">
                    <div class="text-6xl mb-4">📚</div>
                    <p class="text-xl text-gray-600">No grades yet</p>
                    <p class="text-gray-500 mt-2">Your teachers will grade your submissions and they will appear here.</p>
                    <a href="student_dashboard.php" class="mt-6 inline-block px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                        Go to Dashboard
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Note about Quizzes -->
        <div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-6">
            <h3 class="text-lg font-semibold text-blue-900 mb-2">📝 Quiz Results</h3>
            <p class="text-blue-800">Quiz scores are shown instantly after completion. You can review them when taking or retaking quizzes.</p>
        </div>
    </div>
</body>
</html>