<?php

// Query to show student enrollments with ability to add/remove subjects

require_once 'config.php';
checkRole('admin');

$success = '';
$error = '';

// Handle Enrollment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll_student'])) {
    $student_id = intval($_POST['student_id']);
    $subject_ids = $_POST['subject_ids'] ?? [];
    
    if ($student_id && !empty($subject_ids)) {
        $success_count = 0;
        foreach ($subject_ids as $subject_id) {
            $check = $conn->prepare("SELECT * FROM student_subjects WHERE student_id = ? AND subject_id = ?");
            $check->bind_param("ii", $student_id, $subject_id);
            $check->execute();
            
            if ($check->get_result()->num_rows == 0) {
                $stmt = $conn->prepare("INSERT INTO student_subjects (student_id, subject_id) VALUES (?, ?)");
                $stmt->bind_param("ii", $student_id, $subject_id);
                if ($stmt->execute()) $success_count++;
            }
        }
        
        if ($success_count > 0) {
            $success = "$success_count subject(s) enrolled successfully";
            $conn->query("INSERT INTO activity_log (user_id, action_type, description) VALUES ({$_SESSION['user_id']}, 'student_enrolled', 'Enrolled $success_count subjects for student ID: $student_id')");
        } else {
            $error = "Student already enrolled in selected subjects";
        }
    } else {
        $error = "Please select student and at least one subject";
    }
}

// Handle Removal
if (isset($_GET['remove'])) {
    list($student_id, $subject_id) = explode('-', $_GET['remove']);
    $stmt = $conn->prepare("DELETE FROM student_subjects WHERE student_id = ? AND subject_id = ?");
    $stmt->bind_param("ii", $student_id, $subject_id);
    
    if ($stmt->execute()) {
        $success = "Enrollment removed successfully";
    } else {
        $error = "Failed to remove enrollment";
    }
}

// Get all students
$students = $conn->query("SELECT user_id, full_name, email, grade FROM users WHERE role = 'student' AND is_active = 1 ORDER BY grade, full_name");

// Get all subjects
$subjects = $conn->query("SELECT * FROM subjects WHERE is_active = 1 ORDER BY grade_level, subject_category, subject_name");

// Get current enrollments
$enrollments_query = "SELECT ss.student_id, ss.subject_id, ss.enrolled_at,
                      u.full_name as student_name, u.email as student_email, u.grade,
                      s.subject_name, s.subject_code, s.grade_level
                      FROM student_subjects ss
                      INNER JOIN users u ON ss.student_id = u.user_id
                      INNER JOIN subjects s ON ss.subject_id = s.subject_id
                      WHERE u.is_active = 1 AND s.is_active = 1
                      ORDER BY u.grade, u.full_name, s.subject_name";
$enrollments = $conn->query($enrollments_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Enroll Students - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <header class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white p-4 shadow-lg">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold">Enroll Students in Subjects</h1>
                <p class="text-sm text-indigo-100">Manage Student-Subject Enrollments</p>
            </div>
            <div class="flex items-center gap-4">
                <a href="admin_dashboard.php" class="px-4 py-2 bg-white text-indigo-600 rounded-lg hover:bg-indigo-50">← Dashboard</a>
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
            <!-- Enrollment Form -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-4">Enroll Student</h2>
                <form method="POST" id="enrollForm">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Select Student *</label>
                        <select name="student_id" id="studentSelect" required onchange="filterSubjectsByGrade()" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500">
                            <option value="">Choose a student</option>
                            <?php 
                            $students->data_seek(0);
                            while ($student = $students->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $student['user_id']; ?>" data-grade="<?php echo $student['grade']; ?>">
                                    <?php echo htmlspecialchars($student['full_name']); ?> - Grade <?php echo $student['grade']; ?> (<?php echo htmlspecialchars($student['email']); ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Select Subjects *</label>
                        <div class="max-h-96 overflow-y-auto border border-gray-300 rounded-lg p-4">
                            <div id="subjectsContainer" class="space-y-2">
                                <p class="text-gray-500 text-center py-4">Please select a student first</p>
                            </div>
                        </div>
                    </div>

                    <button type="submit" name="enroll_student" class="w-full px-6 py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-semibold">
                        Enroll Student
                    </button>
                </form>
            </div>

            <!-- Current Enrollments -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-4">Current Enrollments</h2>
                <div class="max-h-[600px] overflow-y-auto">
                    <?php 
                    $current_student = '';
                    while ($enroll = $enrollments->fetch_assoc()): 
                        if ($current_student != $enroll['student_name']) {
                            if ($current_student != '') echo '</div></div>';
                            $current_student = $enroll['student_name'];
                    ?>
                        <div class="mb-4">
                            <div class="bg-gradient-to-r from-blue-500 to-indigo-500 text-white p-3 rounded-t-lg">
                                <h3 class="font-bold text-lg"><?php echo htmlspecialchars($enroll['student_name']); ?></h3>
                                <p class="text-sm text-blue-100">Grade <?php echo $enroll['grade']; ?> • <?php echo htmlspecialchars($enroll['student_email']); ?></p>
                            </div>
                            <div class="border border-t-0 rounded-b-lg p-3 space-y-2">
                    <?php } ?>
                                <div class="flex items-center justify-between p-2 bg-gray-50 rounded hover:bg-gray-100">
                                    <div>
                                        <span class="font-medium text-gray-800"><?php echo htmlspecialchars($enroll['subject_name']); ?></span>
                                        <span class="text-xs text-gray-500 ml-2"><?php echo htmlspecialchars($enroll['subject_code']); ?></span>
                                    </div>
                                    <a href="?remove=<?php echo $enroll['student_id']; ?>-<?php echo $enroll['subject_id']; ?>" 
                                       onclick="return confirm('Remove this enrollment?')" 
                                       class="text-red-600 hover:text-red-800 text-sm font-medium">
                                        Remove
                                    </a>
                                </div>
                    <?php 
                    endwhile;
                    if ($current_student != '') echo '</div></div>';
                    ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        const subjectsData = <?php 
            $subjects->data_seek(0);
            $subjects_array = [];
            while ($s = $subjects->fetch_assoc()) {
                $subjects_array[] = $s;
            }
            echo json_encode($subjects_array);
        ?>;

        function filterSubjectsByGrade() {
            const select = document.getElementById('studentSelect');
            const selectedOption = select.options[select.selectedIndex];
            const grade = selectedOption.getAttribute('data-grade');
            const container = document.getElementById('subjectsContainer');
            
            if (!grade) {
                container.innerHTML = '<p class="text-gray-500 text-center py-4">Please select a student first</p>';
                return;
            }
            
            const filteredSubjects = subjectsData.filter(s => s.grade_level === grade);
            
            if (filteredSubjects.length === 0) {
                container.innerHTML = '<p class="text-gray-500 text-center py-4">No subjects available for this grade</p>';
                return;
            }
            
            const categories = {};
            filteredSubjects.forEach(subject => {
                if (!categories[subject.subject_category]) {
                    categories[subject.subject_category] = [];
                }
                categories[subject.subject_category].push(subject);
            });
            
            let html = '';
            for (const [category, subjects] of Object.entries(categories)) {
                html += `<div class="mb-3">
                    <h4 class="font-bold text-gray-700 mb-2 border-b pb-1">${category}</h4>`;
                subjects.forEach(subject => {
                    html += `<label class="flex items-center p-2 hover:bg-gray-50 rounded cursor-pointer">
                        <input type="checkbox" name="subject_ids[]" value="${subject.subject_id}" class="mr-3 rounded text-indigo-600">
                        <div class="flex-1">
                            <span class="font-medium">${subject.subject_name}</span>
                            <span class="text-xs text-gray-500 ml-2">(${subject.subject_code})</span>
                        </div>
                    </label>`;
                });
                html += '</div>';
            }
            
            container.innerHTML = html;
        }
    </script>
</body>
</html>