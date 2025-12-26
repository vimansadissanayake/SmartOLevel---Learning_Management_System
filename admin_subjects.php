<?php
require_once 'config.php';
checkRole('admin');

$success = '';
$error = '';

// Handle Add Subject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_subject'])) {
    $subject_name = trim($_POST['subject_name']);
    $subject_code = trim($_POST['subject_code']);
    $grade_level = $_POST['grade_level'];
    $subject_category = $_POST['subject_category'];
    $description = trim($_POST['description']);
    
    if ($subject_name && $subject_code && $grade_level && $subject_category) {
        $check = $conn->prepare("SELECT subject_id FROM subjects WHERE subject_code = ?");
        $check->bind_param("s", $subject_code);
        $check->execute();
        
        if ($check->get_result()->num_rows > 0) {
            $error = "Subject code already exists";
        } else {
            $stmt = $conn->prepare("INSERT INTO subjects (subject_name, subject_code, grade_level, subject_category, description) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $subject_name, $subject_code, $grade_level, $subject_category, $description);
            
            if ($stmt->execute()) {
                $success = "Subject added successfully";
                $conn->query("INSERT INTO activity_log (user_id, action_type, description) VALUES ({$_SESSION['user_id']}, 'subject_added', 'Added subject: $subject_name')");
            } else {
                $error = "Failed to add subject";
            }
        }
    } else {
        $error = "Please fill all required fields";
    }
}

// Handle Edit Subject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_subject'])) {
    $subject_id = intval($_POST['subject_id']);
    $subject_name = trim($_POST['subject_name']);
    $description = trim($_POST['description']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    $stmt = $conn->prepare("UPDATE subjects SET subject_name = ?, description = ?, is_active = ? WHERE subject_id = ?");
    $stmt->bind_param("ssii", $subject_name, $description, $is_active, $subject_id);
    
    if ($stmt->execute()) {
        $success = "Subject updated successfully";
    } else {
        $error = "Failed to update subject";
    }
}

// Handle Delete Subject
if (isset($_GET['delete'])) {
    $subject_id = intval($_GET['delete']);
    $stmt = $conn->prepare("UPDATE subjects SET is_active = 0 WHERE subject_id = ?");
    $stmt->bind_param("i", $subject_id);
    
    if ($stmt->execute()) {
        $success = "Subject deactivated successfully";
    } else {
        $error = "Failed to deactivate subject";
    }
}

// Get all subjects with counts
$query = "SELECT s.*, 
          (SELECT COUNT(*) FROM teacher_subjects WHERE subject_id = s.subject_id) as teacher_count,
          (SELECT COUNT(*) FROM student_subjects WHERE subject_id = s.subject_id) as student_count,
          (SELECT COUNT(*) FROM lecture_notes WHERE subject_id = s.subject_id AND is_active = 1) as notes_count,
          (SELECT COUNT(*) FROM lecture_videos WHERE subject_id = s.subject_id AND is_active = 1) as videos_count
          FROM subjects s
          ORDER BY s.grade_level, s.subject_category, s.subject_name";
$subjects = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Subjects - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <header class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white p-4 shadow-lg">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold">Manage Subjects</h1>
                <p class="text-sm text-indigo-100">Add, Edit & Monitor Subjects</p>
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

        <!-- Add Subject Form -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">Add New Subject</h2>
            <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Subject Name *</label>
                    <input type="text" name="subject_name" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Subject Code *</label>
                    <input type="text" name="subject_code" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500" placeholder="e.g., MATH-10">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Grade Level *</label>
                    <select name="grade_level" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500">
                        <option value="">Select Grade</option>
                        <option value="10">Grade 10</option>
                        <option value="11">Grade 11</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Category *</label>
                    <select name="subject_category" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500">
                        <option value="">Select Category</option>
                        <option value="Language">Language</option>
                        <option value="Mathematics">Mathematics</option>
                        <option value="Science">Science</option>
                        <option value="Social Studies">Social Studies</option>
                        <option value="Religion">Religion</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                    <textarea name="description" rows="2" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500"></textarea>
                </div>
                <div class="md:col-span-2">
                    <button type="submit" name="add_subject" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Add Subject</button>
                </div>
            </form>
        </div>

        <!-- Subjects List -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">All Subjects</h2>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Subject</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Code</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Grade</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Category</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Teachers</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Students</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Content</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Status</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php while ($subject = $subjects->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900"><?php echo htmlspecialchars($subject['subject_name']); ?></div>
                                <?php if ($subject['description']): ?>
                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars(substr($subject['description'], 0, 50)); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($subject['subject_code']); ?></td>
                            <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($subject['grade_level']); ?></td>
                            <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($subject['subject_category']); ?></td>
                            <td class="px-4 py-3 text-sm text-center"><?php echo $subject['teacher_count']; ?></td>
                            <td class="px-4 py-3 text-sm text-center"><?php echo $subject['student_count']; ?></td>
                            <td class="px-4 py-3 text-sm text-center">
                                <span class="text-blue-600"><?php echo $subject['notes_count']; ?>N</span> / 
                                <span class="text-green-600"><?php echo $subject['videos_count']; ?>V</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 text-xs rounded-full <?php echo $subject['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo $subject['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <button onclick="editSubject(<?php echo htmlspecialchars(json_encode($subject)); ?>)" class="text-blue-600 hover:underline text-sm mr-2">Edit</button>
                                <?php if ($subject['is_active']): ?>
                                <a href="?delete=<?php echo $subject['subject_id']; ?>" onclick="return confirm('Deactivate this subject?')" class="text-red-600 hover:underline text-sm">Deactivate</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full p-6">
            <h3 class="text-2xl font-bold mb-4">Edit Subject</h3>
            <form method="POST">
                <input type="hidden" name="subject_id" id="edit_subject_id">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Subject Name</label>
                        <input type="text" name="subject_name" id="edit_subject_name" required class="w-full px-4 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea name="description" id="edit_description" rows="3" class="w-full px-4 py-2 border rounded-lg"></textarea>
                    </div>
                    <div>
                        <label class="flex items-center">
                            <input type="checkbox" name="is_active" id="edit_is_active" class="mr-2">
                            <span class="text-sm font-medium">Active</span>
                        </label>
                    </div>
                    <div class="flex gap-4">
                        <button type="submit" name="edit_subject" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Update</button>
                        <button type="button" onclick="closeModal()" class="px-6 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400">Cancel</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        function editSubject(subject) {
            document.getElementById('edit_subject_id').value = subject.subject_id;
            document.getElementById('edit_subject_name').value = subject.subject_name;
            document.getElementById('edit_description').value = subject.description || '';
            document.getElementById('edit_is_active').checked = subject.is_active == 1;
            document.getElementById('editModal').classList.remove('hidden');
        }
        
        function closeModal() {
            document.getElementById('editModal').classList.add('hidden');
        }
    </script>
</body>
</html>