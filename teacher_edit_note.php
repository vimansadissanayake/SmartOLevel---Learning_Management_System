<?php
// teacher_edit_note.php
require_once 'config.php';
checkRole('teacher');

$teacher_id = $_SESSION['user_id'];
$teacher_name = $_SESSION['full_name'];

$note_id = intval($_GET['id'] ?? 0);

if ($note_id <= 0) {
    header("Location: teacher_dashboard.php");
    exit();
}

// Fetch lecture note details
$query = "SELECT ln.*, s.subject_name 
          FROM lecture_notes ln 
          INNER JOIN subjects s ON ln.subject_id = s.subject_id 
          WHERE ln.note_id = ? AND ln.teacher_id = ? AND ln.is_active = 1";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $note_id, $teacher_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Note not found or doesn't belong to this teacher
    header("Location: teacher_dashboard.php");
    exit();
}

$note = $result->fetch_assoc();

// Get subjects this teacher is assigned to
$subjects_query = "SELECT s.subject_id, s.subject_name 
                   FROM subjects s 
                   INNER JOIN teacher_subjects ts ON s.subject_id = ts.subject_id 
                   WHERE ts.teacher_id = ? 
                   ORDER BY s.subject_name";
$stmt = $conn->prepare($subjects_query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$teacher_subjects = $stmt->get_result();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject_id = intval($_POST['subject_id']);
    $title = trim($_POST['title']);
    $description = trim($_POST['description'] ?? '');

    if (empty($title)) {
        $error = "Please enter a title for the note.";
    } else {
        // Check if the selected subject belongs to the teacher
        $valid_subject = false;
        $teacher_subjects->data_seek(0);
        while ($sub = $teacher_subjects->fetch_assoc()) {
            if ($sub['subject_id'] == $subject_id) {
                $valid_subject = true;
                break;
            }
        }

        if (!$valid_subject) {
            $error = "You can only edit notes for subjects you teach.";
        } else {
            $update_query = "UPDATE lecture_notes 
                            SET subject_id = ?, 
                                title = ?, 
                                description = ? 
                            WHERE note_id = ? AND teacher_id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("issii", $subject_id, $title, $description, $note_id, $teacher_id);

            if ($stmt->execute()) {
                $success = "Lecture note updated successfully!";
                // Refresh note data
                $note['subject_id'] = $subject_id;
                $note['title'] = $title;
                $note['description'] = $description;
            } else {
                $error = "Failed to update note. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Lecture Note - SmartOLevel LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-green-600 to-teal-600 text-white p-4 shadow-lg">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold">SmartOLevel LMS</h1>
                <p class="text-sm text-green-100">Edit Lecture Note</p>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-sm">Welcome, <?php echo htmlspecialchars($teacher_name); ?></span>
                <a href="logout.php" class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition">Logout</a>
                <a href="teacher_dashboard.php" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition">Back to Dashboard</a>
            </div>
        </div>
    </header>

    <div class="max-w-4xl mx-auto p-6">
        <div class="bg-white rounded-lg shadow-md p-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">Edit Lecture Note</h2>

            <?php if ($error): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <div class="mb-8 p-4 bg-gray-50 rounded-lg">
                <p class="text-sm text-gray-600 mb-2"><strong>Current File:</strong></p>
                <p class="text-lg font-medium text-blue-700">
                    <?php echo htmlspecialchars(basename($note['file_path'])); ?>
                </p>
                <p class="text-xs text-gray-500 mt-1">
                    Uploaded: <?php echo date('d M Y, h:i A', strtotime($note['uploaded_at'])); ?> 
                    • Size: <?php echo number_format($note['file_size'] / 1024, 1); ?> KB
                </p>
                <p class="text-sm text-gray-500 mt-3">
                    <strong>Note:</strong> You cannot change the uploaded file here. To replace it, delete this note and upload a new one.
                </p>
            </div>

            <form method="POST">
                <!-- Subject Selection -->
                <div class="mb-6">
                    <label for="subject_id" class="block text-sm font-medium text-gray-700 mb-2">
                        Subject <span class="text-red-500">*</span>
                    </label>
                    <select id="subject_id" name="subject_id" required 
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                        <?php 
                        $teacher_subjects->data_seek(0);
                        while ($subject = $teacher_subjects->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $subject['subject_id']; ?>" 
                                    <?php echo ($subject['subject_id'] == $note['subject_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($subject['subject_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- Note Title -->
                <div class="mb-6">
                    <label for="title" class="block text-sm font-medium text-gray-700 mb-2">
                        Note Title <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="title" name="title" required 
                           value="<?php echo htmlspecialchars($note['title']); ?>"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                           placeholder="e.g., Chapter 5 - Photosynthesis">
                </div>

                <!-- Description -->
                <div class="mb-8">
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                        Description (Optional)
                    </label>
                    <textarea id="description" name="description" rows="5"
                              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                              placeholder="Brief description of this note..."><?php echo htmlspecialchars($note['description'] ?? ''); ?></textarea>
                </div>

                <!-- Submit Button -->
                <div class="flex gap-4">
                    <button type="submit" 
                            class="px-6 py-3 bg-gradient-to-r from-green-600 to-teal-600 text-white font-semibold rounded-lg shadow-lg hover:from-green-700 hover:to-teal-700 transition">
                        Update Note
                    </button>
                    <a href="teacher_manage_content.php" 
                       class="px-6 py-3 bg-gray-500 text-white font-semibold rounded-lg hover:bg-gray-600 transition">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>