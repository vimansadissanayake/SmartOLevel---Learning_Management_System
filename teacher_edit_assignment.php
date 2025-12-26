<?php
// teacher_edit_assignment.php
require_once 'config.php';
checkRole('teacher');

$teacher_id = $_SESSION['user_id'];
$teacher_name = $_SESSION['full_name'];

$assignment_id = intval($_GET['id'] ?? 0);

if ($assignment_id <= 0) {
    header("Location: teacher_dashboard.php");
    exit();
}

// Fetch assignment details
$query = "SELECT a.*, s.subject_name 
          FROM assignments a 
          INNER JOIN subjects s ON a.subject_id = s.subject_id 
          WHERE a.assignment_id = ? AND a.teacher_id = ? AND a.is_active = 1";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $assignment_id, $teacher_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Assignment not found or doesn't belong to this teacher
    header("Location: teacher_dashboard.php");
    exit();
}

$assignment = $result->fetch_assoc();

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
    $assignment_title = trim($_POST['assignment_title']);
    $description = trim($_POST['description'] ?? '');
    $instructions = trim($_POST['instructions'] ?? '');
    $total_marks = intval($_POST['total_marks']);
    $due_date = $_POST['due_date'];
    $allow_late_submission = isset($_POST['allow_late_submission']) ? 1 : 0;

    if (empty($assignment_title) || $total_marks <= 0 || empty($due_date)) {
        $error = "Please fill in all required fields correctly.";
    } elseif (strtotime($due_date) === false) {
        $error = "Invalid due date format.";
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
            $error = "You can only edit assignments for subjects you teach.";
        } else {
            $update_query = "UPDATE assignments 
                            SET subject_id = ?, 
                                assignment_title = ?, 
                                description = ?, 
                                instructions = ?, 
                                total_marks = ?, 
                                due_date = ?, 
                                allow_late_submission = ? 
                            WHERE assignment_id = ? AND teacher_id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("issisiiii", $subject_id, $assignment_title, $description, $instructions, $total_marks, $due_date, $allow_late_submission, $assignment_id, $teacher_id);

            if ($stmt->execute()) {
                $success = "Assignment updated successfully!";
                // Refresh assignment data
                $assignment['subject_id'] = $subject_id;
                $assignment['assignment_title'] = $assignment_title;
                $assignment['description'] = $description;
                $assignment['instructions'] = $instructions;
                $assignment['total_marks'] = $total_marks;
                $assignment['due_date'] = $due_date;
                $assignment['allow_late_submission'] = $allow_late_submission;
            } else {
                $error = "Failed to update assignment. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Assignment - SmartOLevel LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-green-600 to-teal-600 text-white p-4 shadow-lg">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold">SmartOLevel LMS</h1>
                <p class="text-sm text-green-100">Edit Assignment</p>
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
            <h2 class="text-2xl font-bold text-gray-800 mb-6">Edit Assignment</h2>

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

            <form method="POST" enctype="multipart/form-data">
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
                                    <?php echo ($subject['subject_id'] == $assignment['subject_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($subject['subject_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- Assignment Title -->
                <div class="mb-6">
                    <label for="assignment_title" class="block text-sm font-medium text-gray-700 mb-2">
                        Assignment Title <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="assignment_title" name="assignment_title" required 
                           value="<?php echo htmlspecialchars($assignment['assignment_title']); ?>"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>

                <!-- Description -->
                <div class="mb-6">
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                        Description (Optional)
                    </label>
                    <textarea id="description" name="description" rows="4"
                              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"><?php echo htmlspecialchars($assignment['description'] ?? ''); ?></textarea>
                </div>

                <!-- Instructions -->
                <div class="mb-6">
                    <label for="instructions" class="block text-sm font-medium text-gray-700 mb-2">
                        Instructions (Optional)
                    </label>
                    <textarea id="instructions" name="instructions" rows="5"
                              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"><?php echo htmlspecialchars($assignment['instructions'] ?? ''); ?></textarea>
                </div>

                <!-- Total Marks -->
                <div class="mb-6">
                    <label for="total_marks" class="block text-sm font-medium text-gray-700 mb-2">
                        Total Marks <span class="text-red-500">*</span>
                    </label>
                    <input type="number" id="total_marks" name="total_marks" required min="1" 
                           value="<?php echo $assignment['total_marks']; ?>"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>

                <!-- Due Date -->
                <div class="mb-6">
                    <label for="due_date" class="block text-sm font-medium text-gray-700 mb-2">
                        Due Date & Time <span class="text-red-500">*</span>
                    </label>
                    <input type="datetime-local" id="due_date" name="due_date" required 
                           value="<?php echo str_replace(' ', 'T', $assignment['due_date']); ?>"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>

                <!-- Allow Late Submission -->
                <div class="mb-8">
                    <label class="flex items-center">
                        <input type="checkbox" name="allow_late_submission" 
                               <?php echo $assignment['allow_late_submission'] ? 'checked' : ''; ?>
                               class="mr-3 rounded text-green-600 focus:ring-green-500">
                        <span class="text-sm font-medium text-gray-700">Allow late submissions</span>
                    </label>
                </div>

                <!-- Submit Button -->
                <div class="flex gap-4">
                    <button type="submit" 
                            class="px-6 py-3 bg-gradient-to-r from-green-600 to-teal-600 text-white font-semibold rounded-lg shadow-lg hover:from-green-700 hover:to-teal-700 transition">
                        Update Assignment
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