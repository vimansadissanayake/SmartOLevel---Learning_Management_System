<?php
require_once 'config.php';
checkRole('student');

$student_id = $_SESSION['user_id'];
$assignment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$message = '';
$message_type = '';

// Get assignment details with subject info
$assignment_query = "SELECT a.*, s.subject_name, s.subject_id FROM assignments a
                     INNER JOIN subjects s ON a.subject_id = s.subject_id
                     INNER JOIN student_subjects ss ON s.subject_id = ss.subject_id
                     WHERE a.assignment_id = ? AND ss.student_id = ? AND a.is_active = 1";
$stmt = $conn->prepare($assignment_query);
$stmt->bind_param("ii", $assignment_id, $student_id);
$stmt->execute();
$assignment = $stmt->get_result()->fetch_assoc();

if (!$assignment) {
    header("Location: student_dashboard.php");
    exit();
}

// Check if already submitted
$submission_query = "SELECT * FROM assignment_submissions WHERE assignment_id = ? AND student_id = ?";
$stmt = $conn->prepare($submission_query);
$stmt->bind_param("ii", $assignment_id, $student_id);
$stmt->execute();
$existing_submission = $stmt->get_result()->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$existing_submission) {
    $submission_text = isset($_POST['submission_text']) ? trim($_POST['submission_text']) : '';
    
    // Check if overdue
    $is_late = strtotime($assignment['due_date']) < time();
    $status = $is_late ? 'late' : 'submitted';
    
    // Handle file upload
    if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['submission_file'];
        
        // Validate file
        $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'];
        $max_size = 10 * 1024 * 1024; // 10MB
        
        if (!in_array($file['type'], $allowed_types)) {
            $message = 'Invalid file type. Only PDF, DOC, DOCX, and ZIP files are allowed.';
            $message_type = 'error';
        } elseif ($file['size'] > $max_size) {
            $message = 'File size exceeds 10MB limit.';
            $message_type = 'error';
        } else {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $upload_dir = 'uploads/submissions/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $new_filename = 'submission_' . $student_id . '_' . $assignment_id . '_' . time() . '.' . $ext;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                // Insert submission
                $insert_query = "INSERT INTO assignment_submissions (assignment_id, student_id, submitted_file_path, submission_text, status) 
                                 VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($insert_query);
                $stmt->bind_param("iisss", $assignment_id, $student_id, $upload_path, $submission_text, $status);
                
                if ($stmt->execute()) {
                    $message = 'Assignment submitted successfully!' . ($is_late ? ' (Note: Submitted late)' : '');
                    $message_type = 'success';
                    
                    // Refresh to show submitted state
                    header("Location: student_submit_assignment.php?id=" . $assignment_id);
                    exit();
                } else {
                    $message = 'Error submitting assignment: ' . $conn->error;
                    $message_type = 'error';
                }
            } else {
                $message = 'Failed to upload file. Please check directory permissions.';
                $message_type = 'error';
            }
        }
    } else {
        $message = 'Please select a file to upload';
        $message_type = 'error';
    }
}

// Get grade if graded
$grade = null;
if ($existing_submission) {
    $grade_query = "SELECT ag.* FROM assignment_grades ag WHERE ag.submission_id = ?";
    $stmt = $conn->prepare($grade_query);
    $stmt->bind_param("i", $existing_submission['submission_id']);
    $stmt->execute();
    $grade = $stmt->get_result()->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Submit Assignment - SmartOLevel LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <header class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white p-4 shadow-lg">
        <div class="max-w-4xl mx-auto flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold">SmartOLevel LMS</h1>
                <p class="text-sm text-blue-100">Submit Assignment</p>
            </div>
            <div class="flex gap-3">
                <a href="student_subject_view.php?id=<?php echo $assignment['subject_id']; ?>" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition">← Back to Subject</a>
                <a href="logout.php" class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition">Logout</a>
            </div>
        </div>
    </header>
    
    <div class="max-w-4xl mx-auto p-6">
        <?php if ($message): ?>
            <div class="<?php echo $message_type === 'success' ? 'bg-green-100 border-l-4 border-green-500 text-green-700' : 'bg-red-100 border-l-4 border-red-500 text-red-700'; ?> px-4 py-3 rounded mb-6">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-lg shadow-md p-8">
            <h1 class="text-3xl font-bold text-indigo-600 mb-6">📝 <?php echo htmlspecialchars($assignment['assignment_title']); ?></h1>
            
            <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                <p class="text-gray-700 mb-2"><strong>Subject:</strong> <?php echo htmlspecialchars($assignment['subject_name']); ?></p>
                <p class="text-gray-700 mb-2"><strong>Due Date:</strong> <?php echo formatDate($assignment['due_date'], true); ?></p>
                <p class="text-gray-700 mb-2"><strong>Total Marks:</strong> <?php echo $assignment['total_marks']; ?></p>
                
                <?php if ($assignment['description']): ?>
                <div class="mt-4">
                    <strong class="text-gray-700">Description:</strong>
                    <p class="text-gray-600 mt-1"><?php echo nl2br(htmlspecialchars($assignment['description'])); ?></p>
                </div>
                <?php endif; ?>
                
                <?php if ($assignment['instructions']): ?>
                <div class="mt-4">
                    <strong class="text-gray-700">Instructions:</strong>
                    <p class="text-gray-600 mt-1"><?php echo nl2br(htmlspecialchars($assignment['instructions'])); ?></p>
                </div>
                <?php endif; ?>
                
                <?php if ($assignment['file_path']): ?>
                <div class="mt-4">
                    <strong class="text-gray-700">Attached File:</strong>
                    <a href="<?php echo htmlspecialchars($assignment['file_path']); ?>" target="_blank" class="ml-2 text-indigo-600 hover:underline">📎 Download Assignment File</a>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($existing_submission): ?>
                <div class="bg-blue-50 border-l-4 border-blue-500 p-6 rounded-lg">
                    <h2 class="text-xl font-bold text-blue-800 mb-4">✓ Assignment Already Submitted</h2>
                    <p class="text-blue-700 mb-2"><strong>Submitted on:</strong> <?php echo formatDate($existing_submission['submitted_at'], true); ?></p>
                    <p class="text-blue-700 mb-2"><strong>Status:</strong> 
                        <span class="px-3 py-1 rounded-full text-sm font-bold <?php echo $existing_submission['status'] === 'late' ? 'bg-yellow-200 text-yellow-800' : 'bg-green-200 text-green-800'; ?>">
                            <?php echo ucfirst($existing_submission['status']); ?>
                        </span>
                    </p>
                    
                    <?php if ($existing_submission['submitted_file_path']): ?>
                    <p class="text-blue-700 mb-2"><strong>Submitted File:</strong> 
                        <a href="<?php echo htmlspecialchars($existing_submission['submitted_file_path']); ?>" target="_blank" class="text-indigo-600 hover:underline">View File</a>
                    </p>
                    <?php endif; ?>
                    
                    <?php if ($existing_submission['submission_text']): ?>
                    <div class="mt-3">
                        <strong class="text-blue-700">Your Comments:</strong>
                        <p class="text-blue-600 mt-1"><?php echo nl2br(htmlspecialchars($existing_submission['submission_text'])); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($grade): ?>
                        <div class="mt-4 p-4 bg-green-50 border border-green-300 rounded">
                            <h3 class="font-bold text-green-800 mb-2">✓ Graded</h3>
                            <p class="text-green-700 mb-1">
                                <strong>Score:</strong> 
                                <span class="text-2xl font-bold"><?php echo $grade['marks_obtained']; ?></span> / <?php echo $assignment['total_marks']; ?>
                                <?php if ($grade['grade']): ?>
                                    <span class="ml-2 px-3 py-1 bg-green-200 text-green-800 rounded-full font-bold"><?php echo htmlspecialchars($grade['grade']); ?></span>
                                <?php endif; ?>
                            </p>
                            <?php if ($grade['feedback']): ?>
                            <div class="mt-2">
                                <strong class="text-green-700">Teacher Feedback:</strong>
                                <p class="text-green-600 mt-1"><?php echo nl2br(htmlspecialchars($grade['feedback'])); ?></p>
                            </div>
                            <?php endif; ?>
                            <p class="text-xs text-green-600 mt-2">Graded on: <?php echo formatDate($grade['graded_at']); ?></p>
                        </div>
                    <?php else: ?>
                        <div class="mt-4 p-4 bg-yellow-50 border border-yellow-300 rounded">
                            <p class="text-yellow-700">⏳ Pending review by teacher</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <h2 class="text-2xl font-bold text-gray-800 mb-6">Submit Your Work</h2>
                <form method="POST" action="" enctype="multipart/form-data" class="space-y-6">
                    <div>
                        <label for="submission_text" class="block text-sm font-medium text-gray-700 mb-2">Comments (Optional)</label>
                        <textarea id="submission_text" name="submission_text" rows="4" 
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" 
                                  placeholder="Add any comments or notes about your submission"></textarea>
                    </div>
                    
                    <div>
                        <label for="submission_file" class="block text-sm font-medium text-gray-700 mb-2">Upload Your Work *</label>
                        <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md hover:border-indigo-400 transition">
                            <div class="space-y-1 text-center">
                                <div class="text-6xl mb-3">📄</div>
                                <div class="flex text-sm text-gray-600">
                                    <label for="submission_file" class="relative cursor-pointer bg-white rounded-md font-medium text-indigo-600 hover:text-indigo-500 focus-within:outline-none px-3 py-2 border border-indigo-300">
                                        <span>Choose a file</span>
                                        <input id="submission_file" name="submission_file" type="file" class="sr-only" required accept=".pdf,.doc,.docx,.zip">
                                    </label>
                                </div>
                                <p class="text-xs text-gray-500">PDF, DOC, DOCX, ZIP up to 10MB</p>
                                <p id="file-name" class="mt-2 text-sm text-indigo-600 font-medium"></p>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (strtotime($assignment['due_date']) < time()): ?>
                    <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded">
                        <p class="text-red-700">⚠️ <strong>Warning:</strong> This assignment is overdue. Your submission will be marked as late.</p>
                    </div>
                    <?php endif; ?>
                    
                    <button type="submit" 
                            class="w-full py-3 px-4 bg-indigo-600 text-white font-semibold rounded-lg shadow hover:bg-indigo-700 transition focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2" 
                            onclick="return confirm('Are you sure you want to submit? You cannot change your submission after submitting.')">
                        Submit Assignment
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.getElementById('submission_file').addEventListener('change', function(e) {
            const fileName = e.target.files[0] ? e.target.files[0].name : '';
            const fileSize = e.target.files[0] ? (e.target.files[0].size / 1024 / 1024).toFixed(2) : 0;
            document.getElementById('file-name').textContent = fileName ? '✓ Selected: ' + fileName + ' (' + fileSize + ' MB)' : '';
        });
    </script>
</body>
</html>