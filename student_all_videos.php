<?php
require_once 'config.php';
checkRole('student');

$student_id = $_SESSION['user_id'];
$student_name = $_SESSION['full_name'];
$grade = $_SESSION['grade'] ?? '10';

// Fetch all active videos from enrolled subjects
$query = "SELECT lv.video_id, lv.title, lv.description, lv.video_url, lv.uploaded_at,
                 s.subject_name, s.subject_code
          FROM lecture_videos lv
          INNER JOIN subjects s ON lv.subject_id = s.subject_id
          INNER JOIN student_subjects ss ON s.subject_id = ss.subject_id
          WHERE ss.student_id = ? AND lv.is_active = 1 AND s.grade_level = ?
          ORDER BY lv.uploaded_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("is", $student_id, $grade);
$stmt->execute();
$videos = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Watch Videos - SmartOLevel LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white p-4 shadow-lg">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold">SmartOLevel LMS</h1>
                <p class="text-sm text-blue-100">Watch Videos - Grade <?php echo htmlspecialchars($grade); ?></p>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-sm">Welcome, <?php echo htmlspecialchars($student_name); ?></span>
                <a href="logout.php" class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition">Logout</a>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto p-6">
        <div class="flex items-center mb-6">
            <a href="student_dashboard.php" class="text-blue-600 hover:underline mr-4">← Back to Dashboard</a>
            <h2 class="text-2xl font-bold text-gray-800">All Lecture Videos</h2>
        </div>

        <?php if ($videos->num_rows > 0): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php while ($video = $videos->fetch_assoc()): ?>
                    <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition">
                        <div class="aspect-w-16 aspect-h-9 bg-gray-200">
                            <!-- YouTube/Vimeo thumbnail fallback or iframe preview -->
                            <?php if (strpos($video['video_url'], 'youtube.com') !== false || strpos($video['video_url'], 'youtu.be') !== false): ?>
                                <?php 
                                    preg_match('/(?:youtube\.com\/.*v=|youtu\.be\/)([^&\n?#]+)/', $video['video_url'], $matches);
                                    $youtube_id = $matches[1] ?? '';
                                ?>
                                <img src="https://img.youtube.com/vi/<?php echo $youtube_id; ?>/hqdefault.jpg" alt="Thumbnail" class="w-full h-48 object-cover">
                            <?php else: ?>
                                <div class="w-full h-48 bg-gray-300 flex items-center justify-center text-gray-600">Video</div>
                            <?php endif; ?>
                        </div>
                        <div class="p-4">
                            <h3 class="font-bold text-lg text-gray-800 mb-1"><?php echo htmlspecialchars($video['title']); ?></h3>
                            <p class="text-sm text-gray-600 mb-2">
                                <?php echo htmlspecialchars($video['subject_name']); ?> (<?php echo htmlspecialchars($video['subject_code']); ?>)
                            </p>
                            <?php if ($video['description']): ?>
                                <p class="text-sm text-gray-500 mb-3 line-clamp-2"><?php echo htmlspecialchars($video['description']); ?></p>
                            <?php endif; ?>
                            <p class="text-xs text-gray-400 mb-4">Uploaded: <?php echo formatDate($video['uploaded_at']); ?></p>
                            <a href="<?php echo htmlspecialchars($video['video_url']); ?>" target="_blank"
                               class="block text-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
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
                <p class="text-gray-400 mt-2">Your teachers will upload videos soon.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>