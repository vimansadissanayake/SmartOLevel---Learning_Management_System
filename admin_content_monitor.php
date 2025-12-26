<?php

// Display all content uploaded by teachers

require_once 'config.php';
checkRole('admin');

$message = '';
$message_type = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $content_type = $_POST['content_type'] ?? '';
    $content_id = intval($_POST['content_id']);
    
    if ($action === 'toggle_status') {
        $new_status = intval($_POST['is_active']);
        $table = '';
        $id_field = '';
        
        switch ($content_type) {
            case 'note':
                $table = 'lecture_notes';
                $id_field = 'note_id';
                break;
            case 'video':
                $table = 'lecture_videos';
                $id_field = 'video_id';
                break;
            case 'assignment':
                $table = 'assignments';
                $id_field = 'assignment_id';
                break;
            case 'quiz':
                $table = 'quizzes';
                $id_field = 'quiz_id';
                break;
        }
        
        if ($table) {
            $update = $conn->prepare("UPDATE $table SET is_active = ? WHERE $id_field = ?");
            $update->bind_param("ii", $new_status, $content_id);
            if ($update->execute()) {
                $message = 'Content status updated!';
                $message_type = 'success';
            }
        }
    } elseif ($action === 'delete') {
        $table = '';
        $id_field = '';
        
        switch ($content_type) {
            case 'note':
                $table = 'lecture_notes';
                $id_field = 'note_id';
                break;
            case 'video':
                $table = 'lecture_videos';
                $id_field = 'video_id';
                break;
            case 'assignment':
                $table = 'assignments';
                $id_field = 'assignment_id';
                break;
            case 'quiz':
                $table = 'quizzes';
                $id_field = 'quiz_id';
                break;
        }
        
        if ($table) {
            $delete = $conn->prepare("DELETE FROM $table WHERE $id_field = ?");
            $delete->bind_param("i", $content_id);
            if ($delete->execute()) {
                $message = 'Content deleted successfully!';
                $message_type = 'success';
            }
        }
    }
}

// Get content statistics
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM lecture_notes WHERE is_active = 1) as active_notes,
    (SELECT COUNT(*) FROM lecture_notes WHERE is_active = 0) as inactive_notes,
    (SELECT COUNT(*) FROM lecture_videos WHERE is_active = 1) as active_videos,
    (SELECT COUNT(*) FROM lecture_videos WHERE is_active = 0) as inactive_videos,
    (SELECT COUNT(*) FROM assignments WHERE is_active = 1) as active_assignments,
    (SELECT COUNT(*) FROM assignments WHERE is_active = 0) as inactive_assignments,
    (SELECT COUNT(*) FROM quizzes WHERE is_active = 1) as active_quizzes,
    (SELECT COUNT(*) FROM quizzes WHERE is_active = 0) as inactive_quizzes";
$stats = $conn->query($stats_query)->fetch_assoc();

// Get all content
$notes_query = "SELECT ln.*, u.full_name as teacher_name, s.subject_name, s.subject_code
                FROM lecture_notes ln
                INNER JOIN users u ON ln.teacher_id = u.user_id
                INNER JOIN subjects s ON ln.subject_id = s.subject_id
                ORDER BY ln.uploaded_at DESC";
$notes = $conn->query($notes_query);

$videos_query = "SELECT lv.*, u.full_name as teacher_name, s.subject_name, s.subject_code
                 FROM lecture_videos lv
                 INNER JOIN users u ON lv.teacher_id = u.user_id
                 INNER JOIN subjects s ON lv.subject_id = s.subject_id
                 ORDER BY lv.uploaded_at DESC";
$videos = $conn->query($videos_query);

$assignments_query = "SELECT a.*, u.full_name as teacher_name, s.subject_name, s.subject_code,
                      (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.assignment_id) as submission_count
                      FROM assignments a
                      INNER JOIN users u ON a.teacher_id = u.user_id
                      INNER JOIN subjects s ON a.subject_id = s.subject_id
                      ORDER BY a.created_at DESC";
$assignments = $conn->query($assignments_query);

$quizzes_query = "SELECT q.*, u.full_name as teacher_name, s.subject_name, s.subject_code,
                  (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = q.quiz_id) as attempt_count
                  FROM quizzes q
                  INNER JOIN users u ON q.teacher_id = u.user_id
                  INNER JOIN subjects s ON q.subject_id = s.subject_id
                  ORDER BY q.created_at DESC";
$quizzes = $conn->query($quizzes_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Content Monitor - SmartOLevel LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <header class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white p-4 shadow-lg">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold">SmartOLevel LMS</h1>
                <p class="text-sm text-indigo-100">Monitor Content</p>
            </div>
            <div class="flex items-center gap-4">
                <a href="admin_dashboard.php" class="px-4 py-2 bg-blue-500 rounded-lg hover:bg-blue-600">← Dashboard</a>
                <a href="logout.php" class="px-4 py-2 bg-red-500 rounded-lg hover:bg-red-600">Logout</a>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto p-6">
        <?php if ($message): ?>
            <div class="<?php echo $message_type === 'success' ? 'bg-green-100 border-green-500 text-green-700' : 'bg-red-100 border-red-500 text-red-700'; ?> border-l-4 p-4 rounded mb-6">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white p-4 rounded-lg shadow">
                <div class="text-2xl mb-1">📄</div>
                <div class="text-sm text-gray-600">Lecture Notes</div>
                <div class="text-2xl font-bold text-blue-600"><?php echo $stats['active_notes']; ?></div>
                <div class="text-xs text-gray-500"><?php echo $stats['inactive_notes']; ?> inactive</div>
            </div>
            <div class="bg-white p-4 rounded-lg shadow">
                <div class="text-2xl mb-1">🎥</div>
                <div class="text-sm text-gray-600">Videos</div>
                <div class="text-2xl font-bold text-green-600"><?php echo $stats['active_videos']; ?></div>
                <div class="text-xs text-gray-500"><?php echo $stats['inactive_videos']; ?> inactive</div>
            </div>
            <div class="bg-white p-4 rounded-lg shadow">
                <div class="text-2xl mb-1">📝</div>
                <div class="text-sm text-gray-600">Assignments</div>
                <div class="text-2xl font-bold text-yellow-600"><?php echo $stats['active_assignments']; ?></div>
                <div class="text-xs text-gray-500"><?php echo $stats['inactive_assignments']; ?> inactive</div>
            </div>
            <div class="bg-white p-4 rounded-lg shadow">
                <div class="text-2xl mb-1">❓</div>
                <div class="text-sm text-gray-600">Quizzes</div>
                <div class="text-2xl font-bold text-purple-600"><?php echo $stats['active_quizzes']; ?></div>
                <div class="text-xs text-gray-500"><?php echo $stats['inactive_quizzes']; ?> inactive</div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="flex gap-2 mb-6 overflow-x-auto">
            <button onclick="showTab('notes')" id="tab-notes" class="px-6 py-3 bg-indigo-600 text-white rounded-lg font-semibold whitespace-nowrap">
                📄 Notes
            </button>
            <button onclick="showTab('videos')" id="tab-videos" class="px-6 py-3 bg-gray-300 text-gray-700 rounded-lg font-semibold whitespace-nowrap">
                🎥 Videos
            </button>
            <button onclick="showTab('assignments')" id="tab-assignments" class="px-6 py-3 bg-gray-300 text-gray-700 rounded-lg font-semibold whitespace-nowrap">
                📝 Assignments
            </button>
            <button onclick="showTab('quizzes')" id="tab-quizzes" class="px-6 py-3 bg-gray-300 text-gray-700 rounded-lg font-semibold whitespace-nowrap">
                ❓ Quizzes
            </button>
        </div>

        <!-- Notes Tab -->
        <div id="content-notes" class="content-tab">
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Title</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subject</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Teacher</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Uploaded</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <?php while ($note = $notes->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm font-medium"><?php echo htmlspecialchars($note['title']); ?></td>
                            <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($note['subject_name']); ?></td>
                            <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($note['teacher_name']); ?></td>
                            <td class="px-4 py-3 text-sm"><?php echo formatDate($note['uploaded_at']); ?></td>
                            <td class="px-4 py-3">
                                <form method="POST" class="inline">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="content_type" value="note">
                                    <input type="hidden" name="content_id" value="<?php echo $note['note_id']; ?>">
                                    <input type="hidden" name="is_active" value="<?php echo $note['is_active'] ? 0 : 1; ?>">
                                    <button type="submit" class="px-2 py-1 text-xs rounded <?php echo $note['is_active'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                        <?php echo $note['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </button>
                                </form>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <a href="<?php echo htmlspecialchars($note['file_path']); ?>" target="_blank" class="text-blue-600 hover:underline mr-2">View</a>
                                <form method="POST" class="inline" onsubmit="return confirm('Delete this note?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="content_type" value="note">
                                    <input type="hidden" name="content_id" value="<?php echo $note['note_id']; ?>">
                                    <button type="submit" class="text-red-600 hover:underline">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Videos Tab -->
        <div id="content-videos" class="content-tab hidden">
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Title</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subject</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Teacher</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Uploaded</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <?php while ($video = $videos->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm font-medium"><?php echo htmlspecialchars($video['title']); ?></td>
                            <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($video['subject_name']); ?></td>
                            <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($video['teacher_name']); ?></td>
                            <td class="px-4 py-3 text-sm"><?php echo formatDate($video['uploaded_at']); ?></td>
                            <td class="px-4 py-3">
                                <form method="POST" class="inline">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="content_type" value="video">
                                    <input type="hidden" name="content_id" value="<?php echo $video['video_id']; ?>">
                                    <input type="hidden" name="is_active" value="<?php echo $video['is_active'] ? 0 : 1; ?>">
                                    <button type="submit" class="px-2 py-1 text-xs rounded <?php echo $video['is_active'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                        <?php echo $video['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </button>
                                </form>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <a href="<?php echo htmlspecialchars($video['video_url']); ?>" target="_blank" class="text-blue-600 hover:underline mr-2">Watch</a>
                                <form method="POST" class="inline" onsubmit="return confirm('Delete this video?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="content_type" value="video">
                                    <input type="hidden" name="content_id" value="<?php echo $video['video_id']; ?>">
                                    <button type="submit" class="text-red-600 hover:underline">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Assignments Tab -->
        <div id="content-assignments" class="content-tab hidden">
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Title</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subject</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Teacher</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Due Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Submissions</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <?php while ($assignment = $assignments->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm font-medium"><?php echo htmlspecialchars($assignment['assignment_title']); ?></td>
                            <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($assignment['subject_name']); ?></td>
                            <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($assignment['teacher_name']); ?></td>
                            <td class="px-4 py-3 text-sm"><?php echo formatDate($assignment['due_date']); ?></td>
                            <td class="px-4 py-3"><span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs"><?php echo $assignment['submission_count']; ?></span></td>
                            <td class="px-4 py-3">
                                <form method="POST" class="inline">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="content_type" value="assignment">
                                    <input type="hidden" name="content_id" value="<?php echo $assignment['assignment_id']; ?>">
                                    <input type="hidden" name="is_active" value="<?php echo $assignment['is_active'] ? 0 : 1; ?>">
                                    <button type="submit" class="px-2 py-1 text-xs rounded <?php echo $assignment['is_active'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                        <?php echo $assignment['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </button>
                                </form>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <form method="POST" class="inline" onsubmit="return confirm('Delete this assignment?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="content_type" value="assignment">
                                    <input type="hidden" name="content_id" value="<?php echo $assignment['assignment_id']; ?>">
                                    <button type="submit" class="text-red-600 hover:underline">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Quizzes Tab -->
        <div id="content-quizzes" class="content-tab hidden">
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Title</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subject</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Teacher</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Duration</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Attempts</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <?php while ($quiz = $quizzes->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm font-medium"><?php echo htmlspecialchars($quiz['title']); ?></td>
                            <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($quiz['subject_name']); ?></td>
                            <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($quiz['teacher_name']); ?></td>
                            <td class="px-4 py-3 text-sm"><?php echo $quiz['duration_minutes']; ?> min</td>
                            <td class="px-4 py-3"><span class="px-2 py-1 bg-purple-100 text-purple-800 rounded text-xs"><?php echo $quiz['attempt_count']; ?></span></td>
                            <td class="px-4 py-3">
                                <form method="POST" class="inline">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="content_type" value="quiz">
                                    <input type="hidden" name="content_id" value="<?php echo $quiz['quiz_id']; ?>">
                                    <input type="hidden" name="is_active" value="<?php echo $quiz['is_active'] ? 0 : 1; ?>">
                                    <button type="submit" class="px-2 py-1 text-xs rounded <?php echo $quiz['is_active'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                        <?php echo $quiz['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </button>
                                </form>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <form method="POST" class="inline" onsubmit="return confirm('Delete this quiz?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="content_type" value="quiz">
                                    <input type="hidden" name="content_id" value="<?php echo $quiz['quiz_id']; ?>">
                                    <button type="submit" class="text-red-600 hover:underline">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function showTab(tab) {
            document.querySelectorAll('.content-tab').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('[id^="tab-"]').forEach(el => {
                el.classList.remove('bg-indigo-600', 'text-white');
                el.classList.add('bg-gray-300', 'text-gray-700');
            });
            
            document.getElementById('content-' + tab).classList.remove('hidden');
            document.getElementById('tab-' + tab).classList.add('bg-indigo-600', 'text-white');
            document.getElementById('tab-' + tab).classList.remove('bg-gray-300', 'text-gray-700');
        }
    </script>
</body>
</html>