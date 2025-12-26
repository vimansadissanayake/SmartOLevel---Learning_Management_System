<?php
require_once 'config.php';
checkRole('teacher');

$teacher_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Get teacher's subjects
$subjects_query = "SELECT s.* FROM subjects s
                   INNER JOIN teacher_subjects ts ON s.subject_id = ts.subject_id
                   WHERE ts.teacher_id = ? AND s.is_active = 1";
$stmt = $conn->prepare($subjects_query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$subjects = $stmt->get_result();

// Handle video upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_video'])) {
    $subject_id = intval($_POST['subject_id']);
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $video_url = trim($_POST['video_url']);
    $duration = trim($_POST['duration']);
    
    if ($subject_id && $title && $video_url) {
        // Extract YouTube video ID if it's a YouTube link
        $youtube_id = '';
        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $video_url, $matches)) {
            $youtube_id = $matches[1];
            $thumbnail_url = "https://img.youtube.com/vi/$youtube_id/hqdefault.jpg";
        } else {
            $thumbnail_url = null;
        }
        
        $video_type = 'youtube';
        
        $stmt = $conn->prepare("INSERT INTO lecture_videos (subject_id, teacher_id, title, description, video_url, video_type, duration, thumbnail_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissssss", $subject_id, $teacher_id, $title, $description, $video_url, $video_type, $duration, $thumbnail_url);
        
        if ($stmt->execute()) {
            $success = "Video added successfully!";
            $conn->query("INSERT INTO activity_log (user_id, action_type, description) VALUES ($teacher_id, 'video_uploaded', 'Added video: $title')");
        } else {
            $error = "Failed to add video";
        }
    } else {
        $error = "Please fill all required fields";
    }
}

// Get uploaded videos
$videos_query = "SELECT lv.*, s.subject_name, s.subject_code
                 FROM lecture_videos lv
                 INNER JOIN subjects s ON lv.subject_id = s.subject_id
                 WHERE lv.teacher_id = ? AND lv.is_active = 1
                 ORDER BY lv.uploaded_at DESC";
$stmt = $conn->prepare($videos_query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$videos = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Upload Videos - Teacher</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <header class="bg-gradient-to-r from-green-600 to-teal-600 text-white p-4 shadow-lg">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold">Upload Lecture Videos</h1>
                <p class="text-sm text-green-100">Share educational videos with students</p>
            </div>
            <div class="flex items-center gap-4">
                <a href="teacher_dashboard.php" class="px-4 py-2 bg-white text-green-600 rounded-lg hover:bg-green-50">← Dashboard</a>
                <a href="logout.php" class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600">Logout</a>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto p-6">
        <?php if ($success): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Upload Form -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-4">Add New Video</h2>
                <form method="POST">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Subject *</label>
                        <select name="subject_id" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                            <option value="">Select Subject</option>
                            <?php 
                            $subjects->data_seek(0);
                            while ($subject = $subjects->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $subject['subject_id']; ?>">
                                    <?php echo htmlspecialchars($subject['subject_name']); ?> (<?php echo htmlspecialchars($subject['subject_code']); ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Video Title *</label>
                        <input type="text" name="title" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500" placeholder="e.g., Introduction to Quadratic Equations">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">YouTube Video URL *</label>
                        <input type="url" name="video_url" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500" placeholder="https://www.youtube.com/watch?v=...">
                        <p class="text-xs text-gray-500 mt-1">Paste the full YouTube video URL</p>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Duration (optional)</label>
                        <input type="text" name="duration" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500" placeholder="e.g., 15:30">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea name="description" rows="3" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500" placeholder="Brief description of the video content..."></textarea>
                    </div>

                    <button type="submit" name="add_video" class="w-full px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 font-semibold">
                        🎥 Add Video
                    </button>
                </form>

                <div class="mt-6 p-4 bg-blue-50 rounded-lg">
                    <h4 class="font-bold text-blue-800 mb-2">💡 Tips:</h4>
                    <ul class="text-sm text-blue-700 space-y-1">
                        <li>• Use clear, descriptive titles</li>
                        <li>• Ensure videos are public or unlisted on YouTube</li>
                        <li>• Add timestamps in description for key topics</li>
                        <li>• Keep videos focused on specific topics</li>
                    </ul>
                </div>
            </div>

            <!-- Uploaded Videos -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-4">My Uploaded Videos</h2>
                <div class="max-h-[600px] overflow-y-auto space-y-3">
                    <?php if ($videos->num_rows > 0): ?>
                        <?php while ($video = $videos->fetch_assoc()): ?>
                        <div class="border border-gray-200 rounded-lg overflow-hidden hover:shadow-md transition">
                            <?php if ($video['thumbnail_url']): ?>
                            <div class="relative">
                                <img src="<?php echo htmlspecialchars($video['thumbnail_url']); ?>" alt="Thumbnail" class="w-full h-40 object-cover">
                                <div class="absolute inset-0 bg-black bg-opacity-30 flex items-center justify-center">
                                    <div class="w-16 h-16 bg-red-600 rounded-full flex items-center justify-center">
                                        <span class="text-white text-3xl">▶</span>
                                    </div>
                                </div>
                                <?php if ($video['duration']): ?>
                                <span class="absolute bottom-2 right-2 bg-black bg-opacity-75 text-white text-xs px-2 py-1 rounded">
                                    <?php echo htmlspecialchars($video['duration']); ?>
                                </span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <div class="p-4">
                                <h3 class="font-bold text-gray-800 mb-1"><?php echo htmlspecialchars($video['title']); ?></h3>
                                <p class="text-sm text-gray-600 mb-2"><?php echo htmlspecialchars($video['subject_name']); ?></p>
                                
                                <?php if ($video['description']): ?>
                                <p class="text-sm text-gray-600 mb-3"><?php echo nl2br(htmlspecialchars(substr($video['description'], 0, 100))); ?><?php echo strlen($video['description']) > 100 ? '...' : ''; ?></p>
                                <?php endif; ?>
                                
                                <div class="flex items-center justify-between text-xs text-gray-500 mb-3">
                                    <span>Uploaded: <?php echo formatDate($video['uploaded_at']); ?></span>
                                    <span>Views: <?php echo $video['view_count']; ?></span>
                                </div>
                                
                                <div class="flex gap-2">
                                    <a href="<?php echo htmlspecialchars($video['video_url']); ?>" target="_blank" class="flex-1 text-center px-3 py-2 bg-red-100 text-red-700 rounded hover:bg-red-200 text-sm">
                                        Watch
                                    </a>
                                    <a href="teacher_manage_content.php?edit_video=<?php echo $video['video_id']; ?>" class="flex-1 text-center px-3 py-2 bg-yellow-100 text-yellow-700 rounded hover:bg-yellow-200 text-sm">
                                        Edit
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="text-gray-500 text-center py-8">No videos uploaded yet</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>