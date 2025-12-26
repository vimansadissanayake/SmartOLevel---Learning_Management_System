<?php
// admin_reports.php
require_once 'config.php';
checkRole('admin');

// Get date range
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// User Registration Report
$user_reg_query = "SELECT 
    DATE(created_at) as date,
    role,
    COUNT(*) as count
FROM users
WHERE created_at BETWEEN '$start_date' AND '$end_date'
GROUP BY DATE(created_at), role
ORDER BY date DESC";
$user_registrations = $conn->query($user_reg_query);

// Content Upload Report
$content_query = "SELECT 
    'Notes' as type, COUNT(*) as count, DATE(uploaded_at) as latest
FROM lecture_notes WHERE uploaded_at BETWEEN '$start_date' AND '$end_date'
UNION ALL
SELECT 
    'Videos' as type, COUNT(*) as count, DATE(uploaded_at) as latest
FROM lecture_videos WHERE uploaded_at BETWEEN '$start_date' AND '$end_date'
UNION ALL
SELECT 
    'Assignments' as type, COUNT(*) as count, DATE(created_at) as latest
FROM assignments WHERE created_at BETWEEN '$start_date' AND '$end_date'
UNION ALL
SELECT 
    'Quizzes' as type, COUNT(*) as count, DATE(created_at) as latest
FROM quizzes WHERE created_at BETWEEN '$start_date' AND '$end_date'";
$content_stats = $conn->query($content_query);

// Subject Enrollment Report
$enrollment_query = "SELECT 
    s.subject_name,
    s.grade_level,
    COUNT(ss.student_id) as student_count
FROM subjects s
LEFT JOIN student_subjects ss ON s.subject_id = ss.subject_id
GROUP BY s.subject_id
ORDER BY student_count DESC
LIMIT 10";
$enrollments = $conn->query($enrollment_query);

// Active Users Report
$active_users_query = "SELECT 
    role,
    COUNT(*) as total,
    SUM(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as active_7days,
    SUM(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as active_30days
FROM users
WHERE is_active = 1
GROUP BY role";
$active_users = $conn->query($active_users_query);

// Performance Summary
$performance_query = "SELECT 
    ROUND(AVG((ag.marks_obtained / a.total_marks) * 100), 2) as avg_assignment_score,
    ROUND(AVG(qa.percentage), 2) as avg_quiz_score,
    COUNT(DISTINCT ag.submission_id) as total_graded,
    COUNT(DISTINCT qa.attempt_id) as total_quiz_attempts
FROM assignment_grades ag
CROSS JOIN quiz_attempts qa
INNER JOIN assignments a ON ag.submission_id IN (
    SELECT submission_id FROM assignment_submissions WHERE assignment_id = a.assignment_id
)
WHERE ag.graded_at BETWEEN '$start_date' AND '$end_date'
AND qa.attempted_at BETWEEN '$start_date' AND '$end_date'";
$performance = $conn->query($performance_query)->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reports - SmartOLevel LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <header class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white p-4 shadow-lg">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold">SmartOLevel LMS</h1>
                <p class="text-sm text-indigo-100">System Reports & Analytics</p>
            </div>
            <div class="flex items-center gap-4">
                <a href="admin_dashboard.php" class="px-4 py-2 bg-blue-500 rounded-lg hover:bg-blue-600">← Dashboard</a>
                <a href="logout.php" class="px-4 py-2 bg-red-500 rounded-lg hover:bg-red-600">Logout</a>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto p-6">
        <!-- Date Range Filter -->
        <div class="bg-white p-4 rounded-lg shadow-md mb-6">
            <form method="GET" class="flex gap-4 items-end">
                <div>
                    <label class="block text-sm font-medium mb-1">Start Date</label>
                    <input type="date" name="start_date" value="<?php echo $start_date; ?>" 
                           class="px-4 py-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">End Date</label>
                    <input type="date" name="end_date" value="<?php echo $end_date; ?>" 
                           class="px-4 py-2 border rounded-lg">
                </div>
                <!-- "Generate Report" button removed as requested -->
                <button type="button" onclick="window.print()" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                    🖨️ Print Report
                </button>
            </form>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Performance Summary -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-xl font-bold mb-4">Performance Summary</h3>
                <div class="space-y-4">
                    <div class="flex justify-between items-center p-3 bg-blue-50 rounded">
                        <span class="font-medium">Avg Assignment Score</span>
                        <span class="text-2xl font-bold text-blue-600"><?php echo $performance['avg_assignment_score'] ?? 0; ?>%</span>
                    </div>
                    <div class="flex justify-between items-center p-3 bg-green-50 rounded">
                        <span class="font-medium">Avg Quiz Score</span>
                        <span class="text-2xl font-bold text-green-600"><?php echo $performance['avg_quiz_score'] ?? 0; ?>%</span>
                    </div>
                    <div class="flex justify-between items-center p-3 bg-purple-50 rounded">
                        <span class="font-medium">Graded Assignments</span>
                        <span class="text-2xl font-bold text-purple-600"><?php echo $performance['total_graded'] ?? 0; ?></span>
                    </div>
                    <div class="flex justify-between items-center p-3 bg-yellow-50 rounded">
                        <span class="font-medium">Quiz Attempts</span>
                        <span class="text-2xl font-bold text-yellow-600"><?php echo $performance['total_quiz_attempts'] ?? 0; ?></span>
                    </div>
                </div>
            </div>

            <!-- Active Users -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-xl font-bold mb-4">Active Users</h3>
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">7 Days</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">30 Days</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <?php while ($active = $active_users->fetch_assoc()): ?>
                        <tr>
                            <td class="px-4 py-3 font-medium"><?php echo ucfirst($active['role']); ?></td>
                            <td class="px-4 py-3"><?php echo $active['total']; ?></td>
                            <td class="px-4 py-3 text-green-600 font-bold"><?php echo $active['active_7days']; ?></td>
                            <td class="px-4 py-3 text-blue-600 font-bold"><?php echo $active['active_30days']; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- Content Upload Stats -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-xl font-bold mb-4">Content Uploaded</h3>
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Count</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Latest</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <?php while ($content = $content_stats->fetch_assoc()): ?>
                        <tr>
                            <td class="px-4 py-3 font-medium"><?php echo $content['type']; ?></td>
                            <td class="px-4 py-3 text-indigo-600 font-bold"><?php echo $content['count']; ?></td>
                            <td class="px-4 py-3 text-sm text-gray-600"><?php echo $content['latest'] ?? 'N/A'; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- Top Enrolled Subjects -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-xl font-bold mb-4">Top Enrolled Subjects</h3>
                <div class="space-y-2">
                    <?php while ($enrollment = $enrollments->fetch_assoc()): ?>
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                        <div>
                            <div class="font-medium"><?php echo htmlspecialchars($enrollment['subject_name']); ?></div>
                            <div class="text-xs text-gray-500">Grade <?php echo $enrollment['grade_level']; ?></div>
                        </div>
                        <span class="px-3 py-1 bg-indigo-100 text-indigo-800 rounded-full font-bold">
                            <?php echo $enrollment['student_count']; ?> students
                        </span>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>