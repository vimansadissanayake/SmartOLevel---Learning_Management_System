<?php
require_once 'config.php';
checkRole('admin');

$admin_name = $_SESSION['full_name'];

// Get statistics
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM users WHERE role='student' AND is_active=1) as total_students,
    (SELECT COUNT(*) FROM users WHERE role='teacher' AND is_active=1) as total_teachers,
    (SELECT COUNT(*) FROM subjects WHERE is_active=1) as total_subjects,
    (SELECT COUNT(*) FROM lecture_notes WHERE is_active=1) as total_notes,
    (SELECT COUNT(*) FROM lecture_videos WHERE is_active=1) as total_videos,
    (SELECT COUNT(*) FROM assignments WHERE is_active=1) as total_assignments,
    (SELECT COUNT(*) FROM quizzes WHERE is_active=1) as total_quizzes";
$stats = $conn->query($stats_query)->fetch_assoc();

// Get recent activity
$activity_query = "SELECT al.*, u.full_name, u.role 
                   FROM activity_log al 
                   LEFT JOIN users u ON al.user_id = u.user_id 
                   ORDER BY al.created_at DESC LIMIT 10";
$activities = $conn->query($activity_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - SmartOLevel LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white p-4 shadow-lg">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold">SmartOLevel LMS</h1>
                <p class="text-sm text-indigo-100">Admin Control Panel</p>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-sm">Welcome, <?php echo htmlspecialchars($admin_name); ?></span>
                <a href="logout.php" class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition">Logout</a>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto p-6">
        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <div class="bg-white p-6 rounded-lg shadow-md border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">Total Students</p>
                        <p class="text-3xl font-bold text-blue-600"><?php echo $stats['total_students']; ?></p>
                    </div>
                    <div class="text-4xl">👨‍🎓</div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-md border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">Total Teachers</p>
                        <p class="text-3xl font-bold text-green-600"><?php echo $stats['total_teachers']; ?></p>
                    </div>
                    <div class="text-4xl">👨‍🏫</div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-md border-l-4 border-yellow-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">Active Subjects</p>
                        <p class="text-3xl font-bold text-yellow-600"><?php echo $stats['total_subjects']; ?></p>
                    </div>
                    <div class="text-4xl">📚</div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-md border-l-4 border-purple-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">Total Content</p>
                        <p class="text-3xl font-bold text-purple-600">
                            <?php echo $stats['total_notes'] + $stats['total_videos'] + $stats['total_assignments'] + $stats['total_quizzes']; ?>
                        </p>
                    </div>
                    <div class="text-4xl">📊</div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white p-6 rounded-lg shadow-md mb-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">Quick Actions</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <a href="admin_users.php" class="flex flex-col items-center p-4 bg-blue-50 rounded-lg hover:bg-blue-100 transition">
                    <span class="text-4xl mb-2">👥</span>
                    <span class="font-semibold text-blue-700">Manage Users</span>
                </a>
                <a href="admin_subjects.php" class="flex flex-col items-center p-4 bg-green-50 rounded-lg hover:bg-green-100 transition">
                    <span class="text-4xl mb-2">📖</span>
                    <span class="font-semibold text-green-700">Manage Subjects</span>
                </a>
                <a href="admin_assign_teacher.php" class="flex flex-col items-center p-4 bg-yellow-50 rounded-lg hover:bg-yellow-100 transition">
                    <span class="text-4xl mb-2">🎯</span>
                    <span class="font-semibold text-yellow-700">Assign Teachers</span>
                </a>
                <a href="admin_enroll_student.php" class="flex flex-col items-center p-4 bg-purple-50 rounded-lg hover:bg-purple-100 transition">
                    <span class="text-4xl mb-2">✍️</span>
                    <span class="font-semibold text-purple-700">Enroll Students</span>
                </a>
                <a href="admin_content_monitor.php" class="flex flex-col items-center p-4 bg-red-50 rounded-lg hover:bg-red-100 transition">
                    <span class="text-4xl mb-2">📹</span>
                    <span class="font-semibold text-red-700">Monitor Content</span>
                </a>
                <a href="admin_results.php" class="flex flex-col items-center p-4 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition">
                    <span class="text-4xl mb-2">🏆</span>
                    <span class="font-semibold text-indigo-700">View Results</span>
                </a>
                <a href="admin_reports.php" class="flex flex-col items-center p-4 bg-pink-50 rounded-lg hover:bg-pink-100 transition">
                    <span class="text-4xl mb-2">📊</span>
                    <span class="font-semibold text-pink-700">Reports</span>
                </a>
                <a href="admin_settings.php" class="flex flex-col items-center p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                    <span class="text-4xl mb-2">⚙️</span>
                    <span class="font-semibold text-gray-700">Settings</span>
                </a>
            </div>
        </div>

        <!-- Content Overview -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-xl font-bold text-gray-800 mb-4">Content Statistics</h3>
                <div class="space-y-3">
                    <div class="flex justify-between items-center p-3 bg-blue-50 rounded">
                        <span class="font-medium">📄 Lecture Notes</span>
                        <span class="text-2xl font-bold text-blue-600"><?php echo $stats['total_notes']; ?></span>
                    </div>
                    <div class="flex justify-between items-center p-3 bg-green-50 rounded">
                        <span class="font-medium">🎥 Videos</span>
                        <span class="text-2xl font-bold text-green-600"><?php echo $stats['total_videos']; ?></span>
                    </div>
                    <div class="flex justify-between items-center p-3 bg-yellow-50 rounded">
                        <span class="font-medium">📝 Assignments</span>
                        <span class="text-2xl font-bold text-yellow-600"><?php echo $stats['total_assignments']; ?></span>
                    </div>
                    <div class="flex justify-between items-center p-3 bg-purple-50 rounded">
                        <span class="font-medium">❓ Quizzes</span>
                        <span class="text-2xl font-bold text-purple-600"><?php echo $stats['total_quizzes']; ?></span>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-xl font-bold text-gray-800 mb-4">Recent Activity</h3>
                <div class="space-y-2 max-h-80 overflow-y-auto">
                    <?php if ($activities->num_rows > 0): ?>
                        <?php while($activity = $activities->fetch_assoc()): ?>
                        <div class="p-3 bg-gray-50 rounded text-sm">
                            <p class="font-medium text-gray-800">
                                <?php echo htmlspecialchars($activity['full_name'] ?? 'System'); ?>
                                <span class="text-xs text-gray-500">(<?php echo htmlspecialchars($activity['role'] ?? 'system'); ?>)</span>
                            </p>
                            <p class="text-gray-600"><?php echo htmlspecialchars($activity['description']); ?></p>
                            <p class="text-xs text-gray-400 mt-1"><?php echo formatDateTime($activity['created_at']); ?></p>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="text-gray-500 text-center py-4">No recent activity</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>