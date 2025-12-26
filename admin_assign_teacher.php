<?php

// Query to show teacher-subject assignments with ability to add/remove

require_once 'config.php';
checkRole('admin');

$message = '';
$message_type = '';

// Handle assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'assign') {
        $teacher_id = intval($_POST['teacher_id']);
        $subject_ids = $_POST['subjects'] ?? [];
        
        if (empty($subject_ids)) {
            $message = 'Please select at least one subject';
            $message_type = 'error';
        } else {
            // Remove existing assignments for this teacher
            $delete = $conn->prepare("DELETE FROM teacher_subjects WHERE teacher_id = ?");
            $delete->bind_param("i", $teacher_id);
            $delete->execute();
            
            // Add new assignments
            $insert = $conn->prepare("INSERT INTO teacher_subjects (teacher_id, subject_id) VALUES (?, ?)");
            foreach ($subject_ids as $subject_id) {
                $insert->bind_param("ii", $teacher_id, $subject_id);
                $insert->execute();
            }
            
            $message = 'Teacher assigned successfully!';
            $message_type = 'success';
        }
    } elseif ($action === 'remove') {
        $teacher_id = intval($_POST['teacher_id']);
        $subject_id = intval($_POST['subject_id']);
        
        $delete = $conn->prepare("DELETE FROM teacher_subjects WHERE teacher_id = ? AND subject_id = ?");
        $delete->bind_param("ii", $teacher_id, $subject_id);
        
        if ($delete->execute()) {
            $message = 'Assignment removed!';
            $message_type = 'success';
        }
    }
}

// Get all teachers
$teachers_query = "SELECT * FROM users WHERE role = 'teacher' AND is_active = 1 ORDER BY full_name";
$teachers = $conn->query($teachers_query);

// Get all subjects
$subjects_query = "SELECT * FROM subjects WHERE is_active = 1 ORDER BY grade_level, subject_name";
$subjects = $conn->query($subjects_query);

// Get current assignments
$assignments_query = "SELECT u.user_id, u.full_name, u.email,
                      GROUP_CONCAT(CONCAT(s.subject_name, ' (', s.subject_code, ')') SEPARATOR ', ') as subjects
                      FROM users u
                      LEFT JOIN teacher_subjects ts ON u.user_id = ts.teacher_id
                      LEFT JOIN subjects s ON ts.subject_id = s.subject_id
                      WHERE u.role = 'teacher' AND u.is_active = 1
                      GROUP BY u.user_id
                      ORDER BY u.full_name";
$assignments = $conn->query($assignments_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Assign Teachers - SmartOLevel LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <header class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white p-4 shadow-lg">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold">SmartOLevel LMS</h1>
                <p class="text-sm text-indigo-100">Assign Teachers to Subjects</p>
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

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Assign Teacher Form -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">Assign Teacher to Subjects</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="assign">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Select Teacher *</label>
                        <select name="teacher_id" required class="w-full px-4 py-2 border rounded-lg" id="teacherSelect" onchange="loadTeacherSubjects()">
                            <option value="">Choose a teacher</option>
                            <?php 
                            $teachers->data_seek(0);
                            while ($teacher = $teachers->fetch_assoc()): 
                            ?>
                            <option value="<?php echo $teacher['user_id']; ?>">
                                <?php echo htmlspecialchars($teacher['full_name']); ?> (<?php echo htmlspecialchars($teacher['email']); ?>)
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Select Subjects *</label>
                        <div class="max-h-96 overflow-y-auto border rounded-lg p-3 space-y-2">
                            <?php 
                            $subjects->data_seek(0);
                            $current_grade = '';
                            while ($subject = $subjects->fetch_assoc()): 
                                if ($current_grade != $subject['grade_level']):
                                    if ($current_grade != '') echo '</div>';
                                    $current_grade = $subject['grade_level'];
                            ?>
                            <div class="font-bold text-sm text-indigo-700 mt-3 mb-2">Grade <?php echo $current_grade; ?></div>
                            <div class="space-y-1">
                            <?php endif; ?>
                                <label class="flex items-center p-2 hover:bg-gray-50 rounded cursor-pointer">
                                    <input type="checkbox" name="subjects[]" value="<?php echo $subject['subject_id']; ?>" 
                                           class="mr-3 rounded text-indigo-600 subject-checkbox">
                                    <span class="text-sm">
                                        <?php echo htmlspecialchars($subject['subject_name']); ?>
                                        <span class="text-xs text-gray-500">(<?php echo htmlspecialchars($subject['subject_code']); ?>)</span>
                                    </span>
                                </label>
                            <?php endwhile; ?>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="w-full bg-indigo-600 text-white py-3 rounded-lg hover:bg-indigo-700 font-semibold">
                        🎯 Assign Teacher
                    </button>
                </form>
            </div>

            <!-- Current Assignments -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">Current Assignments</h2>
                <div class="space-y-4 max-h-[600px] overflow-y-auto">
                    <?php 
                    $assignments->data_seek(0);
                    while ($assignment = $assignments->fetch_assoc()): 
                    ?>
                    <div class="border rounded-lg p-4 hover:shadow-md transition">
                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <h3 class="font-bold text-gray-800"><?php echo htmlspecialchars($assignment['full_name']); ?></h3>
                                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($assignment['email']); ?></p>
                            </div>
                            <button onclick="editTeacher(<?php echo $assignment['user_id']; ?>)" 
                                    class="px-3 py-1 bg-blue-100 text-blue-700 rounded hover:bg-blue-200 text-sm">
                                ✏️ Edit
                            </button>
                        </div>
                        <div class="mt-3">
                            <p class="text-xs font-medium text-gray-500 mb-2">Assigned Subjects:</p>
                            <?php if ($assignment['subjects']): ?>
                                <p class="text-sm text-gray-700"><?php echo htmlspecialchars($assignment['subjects']); ?></p>
                            <?php else: ?>
                                <p class="text-sm text-gray-400 italic">No subjects assigned</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function editTeacher(teacherId) {
            document.getElementById('teacherSelect').value = teacherId;
            loadTeacherSubjects();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function loadTeacherSubjects() {
            const teacherId = document.getElementById('teacherSelect').value;
            if (!teacherId) return;
            
            // Uncheck all checkboxes first
            document.querySelectorAll('.subject-checkbox').forEach(cb => cb.checked = false);
            
            // Fetch and check assigned subjects
            fetch('get_teacher_subjects.php?teacher_id=' + teacherId)
                .then(response => response.json())
                .then(data => {
                    data.forEach(subjectId => {
                        const checkbox = document.querySelector(`input[value="${subjectId}"]`);
                        if (checkbox) checkbox.checked = true;
                    });
                })
                .catch(error => console.error('Error:', error));
        }
    </script>
</body>
</html>