<?php
require_once 'config.php';
checkRole('student');

$student_id = $_SESSION['user_id'];
$subject_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get subject details
$subject_query = "SELECT s.* FROM subjects s
                  INNER JOIN student_subjects ss ON s.subject_id = ss.subject_id
                  WHERE ss.student_id = ? AND s.subject_id = ?";
$stmt = $conn->prepare($subject_query);
$stmt->bind_param("ii", $student_id, $subject_id);
$stmt->execute();
$subject = $stmt->get_result()->fetch_assoc();

if (!$subject) {
    header("Location: student_dashboard.php");
    exit();
}

// Get lecture notes - FIXED COLUMN NAMES
$notes_query = "SELECT ln.*, u.full_name as teacher_name 
                FROM lecture_notes ln
                INNER JOIN users u ON ln.teacher_id = u.user_id
                WHERE ln.subject_id = ? AND ln.is_active = 1 
                ORDER BY ln.uploaded_at DESC";
$stmt = $conn->prepare($notes_query);
$stmt->bind_param("i", $subject_id);
$stmt->execute();
$notes = $stmt->get_result();

// Get videos - FIXED COLUMN NAMES
$videos_query = "SELECT lv.*, u.full_name as teacher_name 
                 FROM lecture_videos lv
                 INNER JOIN users u ON lv.teacher_id = u.user_id
                 WHERE lv.subject_id = ? AND lv.is_active = 1 
                 ORDER BY lv.uploaded_at DESC";
$stmt = $conn->prepare($videos_query);
$stmt->bind_param("i", $subject_id);
$stmt->execute();
$videos = $stmt->get_result();

// Get assignments
$assignments_query = "SELECT a.*, 
                      (SELECT submission_id FROM assignment_submissions WHERE assignment_id = a.assignment_id AND student_id = ?) as submitted,
                      (SELECT marks_obtained FROM assignment_grades ag 
                       INNER JOIN assignment_submissions asub ON ag.submission_id = asub.submission_id 
                       WHERE asub.assignment_id = a.assignment_id AND asub.student_id = ?) as marks_obtained
                      FROM assignments a
                      WHERE a.subject_id = ? AND a.is_active = 1 
                      ORDER BY a.due_date DESC";
$stmt = $conn->prepare($assignments_query);
$stmt->bind_param("iii", $student_id, $student_id, $subject_id);
$stmt->execute();
$assignments = $stmt->get_result();

// Get quizzes - FIXED COLUMN NAMES
$quizzes_query = "SELECT q.*,
                  (SELECT attempt_id FROM quiz_attempts WHERE quiz_id = q.quiz_id AND student_id = ? ORDER BY attempt_id DESC LIMIT 1) as attempted,
                  (SELECT score FROM quiz_attempts WHERE quiz_id = q.quiz_id AND student_id = ? ORDER BY attempt_id DESC LIMIT 1) as last_score,
                  (SELECT percentage FROM quiz_attempts WHERE quiz_id = q.quiz_id AND student_id = ? ORDER BY attempt_id DESC LIMIT 1) as last_percentage
                  FROM quizzes q
                  WHERE q.subject_id = ? AND q.is_active = 1 
                  ORDER BY q.created_at DESC";
$stmt = $conn->prepare($quizzes_query);
$stmt->bind_param("iiii", $student_id, $student_id, $student_id, $subject_id);
$stmt->execute();
$quizzes = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($subject['subject_name']); ?> - SmartOLevel LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white p-4 shadow-lg">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold">SmartOLevel LMS</h1>
                <p class="text-sm text-blue-100"><?php echo htmlspecialchars($subject['subject_name']); ?> (<?php echo htmlspecialchars($subject['subject_code']); ?>)</p>
            </div>
            <div class="flex items-center gap-4">
                <a href="student_dashboard.php" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition">← Dashboard</a>
                <a href="logout.php" class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition">Logout</a>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto p-6">
        <!-- Tabs -->
        <div class="flex flex-wrap gap-2 mb-8 bg-white p-2 rounded-lg shadow">
            <button id="tab-notes" onclick="showTab('notes')" class="flex-1 px-6 py-3 bg-indigo-600 text-white rounded-lg transition font-medium">📄 Notes</button>
            <button id="tab-videos" onclick="showTab('videos')" class="flex-1 px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition font-medium">🎥 Videos</button>
            <button id="tab-assignments" onclick="showTab('assignments')" class="flex-1 px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition font-medium">📝 Assignments</button>
            <button id="tab-quizzes" onclick="showTab('quizzes')" class="flex-1 px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition font-medium">🎯 Quizzes</button>
        </div>
        
        <!-- Notes Tab -->
        <div id="content-notes" class="tab-content">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">Lecture Notes</h2>
            <?php if ($notes->num_rows > 0): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php while ($note = $notes->fetch_assoc()): ?>
                    <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition">
                        <div class="text-4xl mb-3 text-center">📄</div>
                        <h3 class="text-lg font-bold text-gray-800 mb-2"><?php echo htmlspecialchars($note['title']); ?></h3>
                        <?php if ($note['description']): ?>
                        <p class="text-sm text-gray-600 mb-3"><?php echo htmlspecialchars($note['description']); ?></p>
                        <?php endif; ?>
                        <p class="text-xs text-gray-500 mb-3">By: <?php echo htmlspecialchars($note['teacher_name']); ?></p>
                        <p class="text-xs text-gray-400 mb-4">Uploaded: <?php echo formatDate($note['uploaded_at']); ?></p>
                        <a href="<?php echo htmlspecialchars($note['file_path']); ?>" target="_blank" download
                           class="block text-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                            Download PDF
                        </a>
                    </div>
                <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-12 bg-white rounded-lg shadow-md">
                    <div class="text-6xl mb-4">📄</div>
                    <p class="text-xl text-gray-500">No notes available yet</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Videos Tab -->
        <div id="content-videos" class="tab-content hidden">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">Lecture Videos</h2>
            <?php if ($videos->num_rows > 0): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php while ($video = $videos->fetch_assoc()): ?>
                    <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition">
                        <?php if (strpos($video['video_url'], 'youtube.com') !== false || strpos($video['video_url'], 'youtu.be') !== false): ?>
                            <?php 
                                preg_match('/(?:youtube\.com\/.*v=|youtu\.be\/)([^&\n?#]+)/', $video['video_url'], $matches);
                                $youtube_id = $matches[1] ?? '';
                            ?>
                            <img src="https://img.youtube.com/vi/<?php echo $youtube_id; ?>/hqdefault.jpg" alt="Thumbnail" class="w-full h-48 object-cover">
                        <?php else: ?>
                            <div class="w-full h-48 bg-gradient-to-br from-blue-400 to-indigo-500 flex items-center justify-center text-white text-4xl">🎥</div>
                        <?php endif; ?>
                        <div class="p-4">
                            <h3 class="text-lg font-bold text-gray-800 mb-2"><?php echo htmlspecialchars($video['title']); ?></h3>
                            <?php if ($video['description']): ?>
                            <p class="text-sm text-gray-600 mb-3"><?php echo htmlspecialchars($video['description']); ?></p>
                            <?php endif; ?>
                            <p class="text-xs text-gray-500 mb-3">By: <?php echo htmlspecialchars($video['teacher_name']); ?></p>
                            <p class="text-xs text-gray-400 mb-4">Uploaded: <?php echo formatDate($video['uploaded_at']); ?></p>
                            <a href="<?php echo htmlspecialchars($video['video_url']); ?>" target="_blank"
                               class="block text-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                                Watch Video ↗
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-12 bg-white rounded-lg shadow-md">
                    <div class="text-6xl mb-4">🎥</div>
                    <p class="text-xl text-gray-500">No videos available yet</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Assignments Tab -->
        <div id="content-assignments" class="tab-content hidden">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">Assignments</h2>
            <?php if ($assignments->num_rows > 0): ?>
                <div class="space-y-4">
                <?php while ($assignment = $assignments->fetch_assoc()): 
                    $is_overdue = strtotime($assignment['due_date']) < time() && !$assignment['submitted'];
                ?>
                    <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition <?php echo $is_overdue ? 'border-l-4 border-red-500' : ''; ?>">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <h3 class="text-lg font-bold text-gray-800 mb-2">📝 <?php echo htmlspecialchars($assignment['assignment_title']); ?></h3>
                                <?php if ($assignment['description']): ?>
                                <p class="text-sm text-gray-600 mb-3"><?php echo htmlspecialchars($assignment['description']); ?></p>
                                <?php endif; ?>
                                <p class="text-sm text-gray-500 mb-2">Due: <?php echo formatDate($assignment['due_date'], true); ?></p>
                                <p class="text-sm text-gray-500">Total Marks: <?php echo $assignment['total_marks']; ?></p>
                            </div>
                            <div class="ml-6 text-right">
                                <?php if ($assignment['submitted']): ?>
                                    <span class="inline-block px-4 py-2 bg-green-100 text-green-800 rounded-lg font-bold mb-2">✓ Submitted</span>
                                    <?php if ($assignment['marks_obtained'] !== null): ?>
                                        <p class="text-sm text-gray-700">Score: <span class="font-bold"><?php echo $assignment['marks_obtained']; ?> / <?php echo $assignment['total_marks']; ?></span></p>
                                    <?php else: ?>
                                        <p class="text-sm text-gray-500">Pending Grade</p>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="inline-block px-4 py-2 bg-yellow-100 text-yellow-800 rounded-lg font-bold mb-2">Pending</span>
                                    <?php if ($is_overdue): ?>
                                        <p class="text-sm text-red-600 font-medium">Overdue!</p>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="mt-4 flex gap-3">
                            <?php if ($assignment['file_path']): ?>
                            <a href="<?php echo htmlspecialchars($assignment['file_path']); ?>" target="_blank"
                               class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                                View Assignment
                            </a>
                            <?php endif; ?>
                            <?php if (!$assignment['submitted']): ?>
                            <a href="student_submit_assignment.php?id=<?php echo $assignment['assignment_id']; ?>"
                               class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                                Submit Work
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-12 bg-white rounded-lg shadow-md">
                    <div class="text-6xl mb-4">📝</div>
                    <p class="text-xl text-gray-500">No assignments available yet</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Quizzes Tab -->
        <div id="content-quizzes" class="tab-content hidden">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">Quizzes</h2>
            <?php if ($quizzes->num_rows > 0): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php while ($quiz = $quizzes->fetch_assoc()): ?>
                    <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition">
                        <div class="text-4xl mb-3 text-center">🎯</div>
                        <h3 class="text-lg font-bold text-gray-800 mb-2"><?php echo htmlspecialchars($quiz['title']); ?></h3>
                        <?php if ($quiz['description']): ?>
                        <p class="text-sm text-gray-600 mb-3"><?php echo htmlspecialchars($quiz['description']); ?></p>
                        <?php endif; ?>
                        <p class="text-sm text-gray-700 mb-3">
                            Duration: <?php echo $quiz['duration_minutes']; ?> mins • Marks: <?php echo $quiz['total_marks']; ?>
                        </p>
                        
                        <?php if($quiz['attempted']): ?>
                            <div class="mb-3">
                                <span class="inline-block px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-bold">✓ Completed</span>
                            </div>
                            <p class="text-sm text-gray-700 mb-3">
                                Score: <span class="font-bold text-lg"><?php echo $quiz['last_score']; ?> / <?php echo $quiz['total_marks']; ?></span>
                                <span class="text-gray-500">(<?php echo number_format($quiz['last_percentage'], 1); ?>%)</span>
                            </p>
                            <a href="student_take_quiz.php?id=<?php echo $quiz['quiz_id']; ?>" 
                               class="block text-center px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition">
                                Retake Quiz
                            </a>
                        <?php else: ?>
                            <a href="student_take_quiz.php?id=<?php echo $quiz['quiz_id']; ?>" 
                               class="block text-center px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition">
                                Start Quiz
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-12 bg-white rounded-lg shadow-md">
                    <div class="text-6xl mb-4">🎯</div>
                    <p class="text-xl text-gray-500">No quizzes available yet</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => content.classList.add('hidden'));
            
            // Remove active styling from all tabs
            document.querySelectorAll('[id^="tab-"]').forEach(tab => {
                tab.classList.remove('bg-indigo-600', 'text-white');
                tab.classList.add('bg-gray-200', 'text-gray-700');
            });
            
            // Show selected tab content
            document.getElementById('content-' + tabName).classList.remove('hidden');
            
            // Add active styling to selected tab
            document.getElementById('tab-' + tabName).classList.remove('bg-gray-200', 'text-gray-700');
            document.getElementById('tab-' + tabName).classList.add('bg-indigo-600', 'text-white');
        }
    </script>
</body>
</html>