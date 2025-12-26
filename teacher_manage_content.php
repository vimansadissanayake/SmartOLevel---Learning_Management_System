<?php
// teacher_manage_content.php
require_once 'config.php';
checkRole('teacher');

$teacher_id = $_SESSION['user_id'];
$teacher_name = $_SESSION['full_name'];

// Handle delete requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_type']) && isset($_POST['id'])) {
    $delete_type = $_POST['delete_type'];
    $id = intval($_POST['id']);
    
    switch ($delete_type) {
        case 'note':
            $delete_query = "UPDATE lecture_notes SET is_active = 0 WHERE note_id = ? AND teacher_id = ?";
            break;
        case 'video':
            $delete_query = "UPDATE lecture_videos SET is_active = 0 WHERE video_id = ? AND teacher_id = ?";
            break;
        case 'assignment':
            $delete_query = "UPDATE assignments SET is_active = 0 WHERE assignment_id = ? AND teacher_id = ?";
            break;
        case 'quiz':
            $delete_query = "UPDATE quizzes SET is_active = 0 WHERE quiz_id = ? AND teacher_id = ?";
            break;
        default:
            $delete_query = null;
    }
    
    if ($delete_query) {
        $stmt = $conn->prepare($delete_query);
        $stmt->bind_param("ii", $id, $teacher_id);
        $stmt->execute();
    }
    header("Location: teacher_manage_content.php");
    exit();
}

// Get teacher's content
$notes_query = "SELECT ln.*, s.subject_name FROM lecture_notes ln INNER JOIN subjects s ON ln.subject_id = s.subject_id WHERE ln.teacher_id = ? AND ln.is_active = 1 ORDER BY ln.uploaded_at DESC";
$stmt = $conn->prepare($notes_query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$notes = $stmt->get_result();

$videos_query = "SELECT lv.*, s.subject_name FROM lecture_videos lv INNER JOIN subjects s ON lv.subject_id = s.subject_id WHERE lv.teacher_id = ? AND lv.is_active = 1 ORDER BY lv.uploaded_at DESC";
$stmt = $conn->prepare($videos_query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$videos = $stmt->get_result();

$assignments_query = "SELECT a.*, s.subject_name FROM assignments a INNER JOIN subjects s ON a.subject_id = s.subject_id WHERE a.teacher_id = ? AND a.is_active = 1 ORDER BY a.created_at DESC";
$stmt = $conn->prepare($assignments_query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$assignments = $stmt->get_result();

$quizzes_query = "SELECT q.*, s.subject_name FROM quizzes q INNER JOIN subjects s ON q.subject_id = s.subject_id WHERE q.teacher_id = ? AND q.is_active = 1 ORDER BY q.created_at DESC";
$stmt = $conn->prepare($quizzes_query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$quizzes = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Content - SmartOLevel LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-green-600 to-teal-600 text-white p-4 shadow-lg">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold">SmartOLevel LMS</h1>
                <p class="text-sm text-green-100">Manage Content</p>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-sm">Welcome, <?php echo htmlspecialchars($teacher_name); ?></span>
                <a href="logout.php" class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition">Logout</a>
                <a href="teacher_dashboard.php" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition">Back to Dashboard</a>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto p-6">
        <!-- Lecture Notes -->
        <div class="bg-white p-6 rounded-lg shadow-md mb-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">Lecture Notes</h2>
            <?php if ($notes->num_rows > 0): ?>
                <div class="space-y-4">
                    <?php while ($note = $notes->fetch_assoc()): ?>
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded">
                        <div>
                            <p class="font-medium text-gray-800"><?php echo htmlspecialchars($note['title']); ?></p>
                            <p class="text-sm text-gray-600"><?php echo htmlspecialchars($note['subject_name']); ?> • Uploaded: <?php echo formatDate($note['uploaded_at']); ?></p>
                        </div>
                        <div class="flex gap-2">
                            <!-- Edit link - assuming edit page exists, e.g., teacher_edit_note.php -->
                            <a href="teacher_edit_note.php?id=<?php echo $note['note_id']; ?>" class="px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600">Edit</a>
                            <form method="POST">
                                <input type="hidden" name="delete_type" value="note">
                                <input type="hidden" name="id" value="<?php echo $note['note_id']; ?>">
                                <button type="submit" class="px-3 py-1 bg-red-500 text-white rounded hover:bg-red-600" onclick="return confirm('Are you sure you want to delete this note?');">Delete</button>
                            </form>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p class="text-gray-500 text-center py-4">No lecture notes uploaded yet.</p>
            <?php endif; ?>
        </div>

        <!-- Lecture Videos -->
        <div class="bg-white p-6 rounded-lg shadow-md mb-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">Lecture Videos</h2>
            <?php if ($videos->num_rows > 0): ?>
                <div class="space-y-4">
                    <?php while ($video = $videos->fetch_assoc()): ?>
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded">
                        <div>
                            <p class="font-medium text-gray-800"><?php echo htmlspecialchars($video['title']); ?></p>
                            <p class="text-sm text-gray-600"><?php echo htmlspecialchars($video['subject_name']); ?> • Uploaded: <?php echo formatDate($video['uploaded_at']); ?></p>
                        </div>
                        <div class="flex gap-2">
                            <a href="teacher_edit_video.php?id=<?php echo $video['video_id']; ?>" class="px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600">Edit</a>
                            <form method="POST">
                                <input type="hidden" name="delete_type" value="video">
                                <input type="hidden" name="id" value="<?php echo $video['video_id']; ?>">
                                <button type="submit" class="px-3 py-1 bg-red-500 text-white rounded hover:bg-red-600" onclick="return confirm('Are you sure you want to delete this video?');">Delete</button>
                            </form>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p class="text-gray-500 text-center py-4">No lecture videos uploaded yet.</p>
            <?php endif; ?>
        </div>

        <!-- Assignments -->
        <div class="bg-white p-6 rounded-lg shadow-md mb-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">Assignments</h2>
            <?php if ($assignments->num_rows > 0): ?>
                <div class="space-y-4">
                    <?php while ($assignment = $assignments->fetch_assoc()): ?>
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded">
                        <div>
                            <p class="font-medium text-gray-800"><?php echo htmlspecialchars($assignment['assignment_title']); ?></p>
                            <p class="text-sm text-gray-600"><?php echo htmlspecialchars($assignment['subject_name']); ?> • Created: <?php echo formatDate($assignment['created_at']); ?></p>
                        </div>
                        <div class="flex gap-2">
                            <a href="teacher_edit_assignment.php?id=<?php echo $assignment['assignment_id']; ?>" class="px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600">Edit</a>
                            <form method="POST">
                                <input type="hidden" name="delete_type" value="assignment">
                                <input type="hidden" name="id" value="<?php echo $assignment['assignment_id']; ?>">
                                <button type="submit" class="px-3 py-1 bg-red-500 text-white rounded hover:bg-red-600" onclick="return confirm('Are you sure you want to delete this assignment?');">Delete</button>
                            </form>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p class="text-gray-500 text-center py-4">No assignments created yet.</p>
            <?php endif; ?>
        </div>

        <!-- Quizzes -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">Quizzes</h2>
            <?php if ($quizzes->num_rows > 0): ?>
                <div class="space-y-4">
                    <?php while ($quiz = $quizzes->fetch_assoc()): ?>
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded">
                        <div>
                            <p class="font-medium text-gray-800"><?php echo htmlspecialchars($quiz['title']); ?></p>
                            <p class="text-sm text-gray-600"><?php echo htmlspecialchars($quiz['subject_name']); ?> • Created: <?php echo formatDate($quiz['created_at']); ?></p>
                        </div>
                        <div class="flex gap-2">
                            <a href="teacher_edit_quiz.php?id=<?php echo $quiz['quiz_id']; ?>" class="px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600">Edit</a>
                            <form method="POST">
                                <input type="hidden" name="delete_type" value="quiz">
                                <input type="hidden" name="id" value="<?php echo $quiz['quiz_id']; ?>">
                                <button type="submit" class="px-3 py-1 bg-red-500 text-white rounded hover:bg-red-600" onclick="return confirm('Are you sure you want to delete this quiz?');">Delete</button>
                            </form>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p class="text-gray-500 text-center py-4">No quizzes created yet.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>