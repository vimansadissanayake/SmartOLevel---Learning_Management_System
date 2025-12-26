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

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_note'])) {
    $subject_id = intval($_POST['subject_id']);
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    
    if ($subject_id && $title && isset($_FILES['note_file'])) {
        $file = $_FILES['note_file'];
        
        // Validate file
        $validation = validateFileUpload($file, ALLOWED_NOTES_TYPES, MAX_FILE_SIZE_NOTES);
        
        if ($validation['success']) {
            $file_ext = getFileExtension($file['name']);
            $file_name = time() . '_' . uniqid() . '.' . $file_ext;
            $file_path = UPLOAD_DIR_NOTES . $file_name;
            
            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                $file_size = filesize($file_path);
                
                $stmt = $conn->prepare("INSERT INTO lecture_notes (subject_id, teacher_id, title, description, file_path, file_type, file_size) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("iissssi", $subject_id, $teacher_id, $title, $description, $file_path, $file_ext, $file_size);
                
                if ($stmt->execute()) {
                    $success = "Note uploaded successfully!";
                    $conn->query("INSERT INTO activity_log (user_id, action_type, description) VALUES ($teacher_id, 'note_uploaded', 'Uploaded note: $title')");
                } else {
                    $error = "Failed to save note information";
                    unlink($file_path);
                }
            } else {
                $error = "Failed to upload file";
            }
        } else {
            $error = $validation['message'];
        }
    } else {
        $error = "Please fill all fields and select a file";
    }
}

// Get uploaded notes
$notes_query = "SELECT ln.*, s.subject_name, s.subject_code
                FROM lecture_notes ln
                INNER JOIN subjects s ON ln.subject_id = s.subject_id
                WHERE ln.teacher_id = ? AND ln.is_active = 1
                ORDER BY ln.uploaded_at DESC";
$stmt = $conn->prepare($notes_query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$notes = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Upload Notes - Teacher</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <header class="bg-gradient-to-r from-green-600 to-teal-600 text-white p-4 shadow-lg">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold">Upload Lecture Notes</h1>
                <p class="text-sm text-green-100">Share study materials with students</p>
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
                <h2 class="text-2xl font-bold text-gray-800 mb-4">Upload New Note</h2>
                <form method="POST" enctype="multipart/form-data">
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
                        <label class="block text-sm font-medium text-gray-700 mb-2">Title *</label>
                        <input type="text" name="title" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500" placeholder="e.g., Chapter 1: Introduction to Algebra">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea name="description" rows="3" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500" placeholder="Brief description of the note..."></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Upload File *</label>
                        <input type="file" name="note_file" required accept=".pdf,.doc,.docx,.ppt,.pptx" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                        <p class="text-xs text-gray-500 mt-1">Allowed: PDF, DOC, DOCX, PPT, PPTX (Max: 10MB)</p>
                    </div>

                    <button type="submit" name="upload_note" class="w-full px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 font-semibold">
                        📄 Upload Note
                    </button>
                </form>
            </div>

            <!-- Uploaded Notes -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-4">My Uploaded Notes</h2>
                <div class="max-h-[600px] overflow-y-auto space-y-3">
                    <?php if ($notes->num_rows > 0): ?>
                        <?php while ($note = $notes->fetch_assoc()): ?>
                        <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition">
                            <div class="flex items-start justify-between mb-2">
                                <div class="flex-1">
                                    <h3 class="font-bold text-gray-800"><?php echo htmlspecialchars($note['title']); ?></h3>
                                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($note['subject_name']); ?></p>
                                </div>
                                <span class="text-2xl">📄</span>
                            </div>
                            
                            <?php if ($note['description']): ?>
                            <p class="text-sm text-gray-600 mb-3"><?php echo nl2br(htmlspecialchars($note['description'])); ?></p>
                            <?php endif; ?>
                            
                            <div class="flex items-center justify-between text-xs text-gray-500">
                                <span>Uploaded: <?php echo formatDate($note['uploaded_at']); ?></span>
                                <span>Downloads: <?php echo $note['download_count']; ?></span>
                            </div>
                            
                            <div class="mt-3 flex gap-2">
                                <a href="<?php echo htmlspecialchars($note['file_path']); ?>" target="_blank" class="flex-1 text-center px-3 py-2 bg-blue-100 text-blue-700 rounded hover:bg-blue-200 text-sm">
                                    View
                                </a>
                                <a href="teacher_manage_content.php?edit_note=<?php echo $note['note_id']; ?>" class="flex-1 text-center px-3 py-2 bg-yellow-100 text-yellow-700 rounded hover:bg-yellow-200 text-sm">
                                    Edit
                                </a>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="text-gray-500 text-center py-8">No notes uploaded yet</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>