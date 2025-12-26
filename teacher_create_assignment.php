<?php
require_once 'config.php';
checkRole('teacher');

$teacher_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Get teacher's subjects
$subjects_query = "SELECT s.* FROM subjects s
                   INNER JOIN teacher_subjects ts ON s.subject_id = ts.subject_id
                   WHERE ts.teacher_id = ?";
$stmt = $conn->prepare($subjects_query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$subjects_result = $stmt->get_result();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject_id = intval($_POST['subject_id']);
    $assignment_title = sanitize($_POST['assignment_title']);
    $description = sanitize($_POST['description']);
    $instructions = sanitize($_POST['instructions']);
    $total_marks = intval($_POST['total_marks']);
    $due_date = $_POST['due_date']; // datetime-local sends YYYY-MM-DDTHH:MM
    
    $file_path = null;
    
    // Handle optional file upload
    if (isset($_FILES['assignment_file']) && $_FILES['assignment_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['assignment_file'];
        $validation = validateFileUpload($file, ALLOWED_ASSIGNMENT_TYPES, MAX_FILE_SIZE_ASSIGNMENTS);
        
        if ($validation['success']) {
            $ext = getFileExtension($file['name']);
            $new_filename = uniqid() . '_' . time() . '.' . $ext;
            $upload_path = UPLOAD_DIR_ASSIGNMENTS . $new_filename;
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                $file_path = $upload_path;
            } else {
                $message = 'Failed to upload file.';
                $message_type = 'error';
            }
        } else {
            $message = $validation['message'];
            $message_type = 'error';
        }
    }
    
    if (empty($message)) {
        $insert_query = "INSERT INTO assignments 
                         (subject_id, teacher_id, assignment_title, description, instructions, file_path, total_marks, due_date, is_active) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("iisssisi", $subject_id, $teacher_id, $assignment_title, $description, $instructions, $file_path, $total_marks, $due_date);
        
        if ($stmt->execute()) {
            $message = 'Assignment created successfully!';
            $message_type = 'success';
            
            // Log activity
            $log_stmt = $conn->prepare("INSERT INTO activity_log (user_id, action_type, description) VALUES (?, 'assignment_upload', ?)");
            $log_desc = "Created assignment: $assignment_title";
            $log_stmt->bind_param("is", $teacher_id, $log_desc);
            $log_stmt->execute();
        } else {
            $message = 'Error creating assignment: ' . $conn->error;
            $message_type = 'error';
        }
    }
}

// Get all assignments for teacher with stats
$assignments_query = "SELECT a.*, s.subject_name,
                      (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.assignment_id) as submission_count,
                      (SELECT COUNT(*) FROM student_subjects WHERE subject_id = a.subject_id) as total_students
                      FROM assignments a
                      INNER JOIN subjects s ON a.subject_id = s.subject_id
                      WHERE a.teacher_id = ?
                      ORDER BY a.created_at DESC";
$stmt = $conn->prepare($assignments_query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$assignments_result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Assignment - SmartOLevel LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen p-6">
    <header class="bg-gradient-to-r from-green-600 to-teal-600 text-white p-4 mb-6 rounded-lg shadow-lg">
        <div class="max-w-6xl mx-auto flex justify-between items-center">
            <h1 class="text-3xl font-bold">Create Assignment</h1>
            <a href="teacher_dashboard.php" class="px-4 py-2 bg-white text-green-600 rounded-lg hover:bg-gray-100 transition">← Back to Dashboard</a>
        </div>
    </header>

    <div class="max-w-6xl mx-auto">
        <!-- Create Form -->
        <div class="bg-white p-8 rounded-lg shadow-md mb-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">Create New Assignment</h2>

            <?php if ($message): ?>
                <div class="<?php echo $message_type === 'success' ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700'; ?> px-4 py-3 rounded mb-6 border-l-4">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" enctype="multipart/form-data" class="space-y-6">
                <div>
                    <label for="subject_id" class="block text-sm font-medium text-gray-700 mb-2">Subject *</label>
                    <select id="subject_id" name="subject_id" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                        <option value="">Select Subject</option>
                        <?php while ($subject = $subjects_result->fetch_assoc()): ?>
                            <option value="<?php echo $subject['subject_id']; ?>">
                                <?php echo htmlspecialchars($subject['subject_name']) . ' (' . $subject['subject_code'] . ')'; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div>
                    <label for="assignment_title" class="block text-sm font-medium text-gray-700 mb-2">Assignment Title *</label>
                    <input type="text" id="assignment_title" name="assignment_title" required 
                           placeholder="e.g., Term Test - Unit 3"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Short Description</label>
                    <textarea id="description" name="description" rows="3" 
                              placeholder="Brief overview of the assignment..."
                              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"></textarea>
                </div>

                <div>
                    <label for="instructions" class="block text-sm font-medium text-gray-700 mb-2">Detailed Instructions</label>
                    <textarea id="instructions" name="instructions" rows="5" 
                              placeholder="Provide clear instructions for students..."
                              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="total_marks" class="block text-sm font-medium text-gray-700 mb-2">Total Marks *</label>
                        <input type="number" id="total_marks" name="total_marks" required min="1" placeholder="e.g., 100"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                    </div>

                    <div>
                        <label for="due_date" class="block text-sm font-medium text-gray-700 mb-2">Due Date & Time *</label>
                        <input type="datetime-local" id="due_date" name="due_date" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                    </div>
                </div>

                <div>
                    <label for="assignment_file" class="block text-sm font-medium text-gray-700 mb-2">Attachment File (Optional)</label>
                    <input type="file" id="assignment_file" name="assignment_file" accept=".pdf,.doc,.docx,.ppt,.pptx"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                    <p class="text-xs text-gray-500 mt-1">Supported: PDF, Word, PowerPoint (Max size as per config)</p>
                </div>

                <button type="submit" class="w-full py-4 px-6 bg-gradient-to-r from-green-600 to-teal-600 text-white font-bold rounded-lg shadow-lg hover:from-green-700 hover:to-teal-700 transition">
                    📝 Create Assignment
                </button>
            </form>
        </div>

        <!-- Assignments List -->
        <div class="bg-white p-8 rounded-lg shadow-md">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">My Assignments</h2>

            <?php if ($assignments_result->num_rows > 0): ?>
                <div class="space-y-4">
                    <?php while ($assignment = $assignments_result->fetch_assoc()): 
                        $is_overdue = strtotime($assignment['due_date']) < time();
                        $submission_rate = $assignment['total_students'] > 0 
                            ? round(($assignment['submission_count'] / $assignment['total_students']) * 100) 
                            : 0;
                        $icon = $assignment['file_path'] ? '📎' : '📝';
                    ?>
                    <div class="border border-gray-200 rounded-lg p-4 hover:border-green-500 transition">
                        <div class="flex gap-4">
                            <div class="text-6xl"><?php echo $icon; ?></div>
                            <div class="flex-1">
                                <h3 class="font-bold text-lg text-gray-800"><?php echo htmlspecialchars($assignment['assignment_title']); ?></h3>
                                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($assignment['subject_name']); ?></p>
                                <p class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars($assignment['description']); ?></p>
                                <div class="flex flex-wrap items-center gap-4 mt-3 text-xs text-gray-500">
                                    <span>🎯 <?php echo $assignment['total_marks']; ?> marks</span>
                                    <span>📅 Due: <?php echo date('M d, Y - h:i A', strtotime($assignment['due_date'])); ?></span>
                                    <span>📊 <?php echo $assignment['submission_count']; ?>/<?php echo $assignment['total_students']; ?> submitted (<?php echo $submission_rate; ?>%)</span>
                                    <span class="px-2 py-1 <?php echo $is_overdue ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'; ?> rounded-full">
                                        <?php echo $is_overdue ? 'Overdue' : 'Active'; ?>
                                    </span>
                                </div>
                            </div>
                            <div class="flex flex-col gap-2">
                                <a href="teacher_view_submissions.php?id=<?php echo $assignment['assignment_id']; ?>"
                                   class="px-4 py-2 bg-indigo-600 text-white text-sm rounded hover:bg-indigo-700 text-center">View Submissions</a>
                                <a href="teacher_edit_assignment.php?id=<?php echo $assignment['assignment_id']; ?>"
                                   class="px-4 py-2 bg-yellow-600 text-white text-sm rounded hover:bg-yellow-700 text-center">Edit</a>
                                <a href="teacher_delete_assignment.php?id=<?php echo $assignment['assignment_id']; ?>"
                                   class="px-4 py-2 bg-red-600 text-white text-sm rounded hover:bg-red-700 text-center"
                                   onclick="return confirm('Delete this assignment? All submissions will be lost.')">Delete</a>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-12">
                    <div class="text-6xl mb-4">📝</div>
                    <p class="text-xl text-gray-500">No assignments created yet</p>
                    <p class="text-gray-400 mt-2">Create your first assignment using the form above</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>