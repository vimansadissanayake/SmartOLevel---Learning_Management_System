<?php
require_once 'config.php';
checkRole('admin');

$admin_name = $_SESSION['full_name'];
$message = '';
$message_type = '';

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_user') {
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $role = $_POST['role'];
        $grade = $_POST['grade'] ?? null;
        $phone = trim($_POST['phone'] ?? '');
        
        // Check if email exists
        $check = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $message = 'Email already exists!';
            $message_type = 'error';
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $insert = $conn->prepare("INSERT INTO users (full_name, email, password_hash, role, grade, phone) VALUES (?, ?, ?, ?, ?, ?)");
            $insert->bind_param("ssssss", $full_name, $email, $password_hash, $role, $grade, $phone);
            
            if ($insert->execute()) {
                $message = 'User added successfully!';
                $message_type = 'success';
            } else {
                $message = 'Failed to add user.';
                $message_type = 'error';
            }
        }
    } elseif ($action === 'toggle_status') {
        $user_id = intval($_POST['user_id']);
        $new_status = intval($_POST['is_active']);
        
        $update = $conn->prepare("UPDATE users SET is_active = ? WHERE user_id = ?");
        $update->bind_param("ii", $new_status, $user_id);
        
        if ($update->execute()) {
            $message = 'User status updated!';
            $message_type = 'success';
        }
    } elseif ($action === 'delete_user') {
        $user_id = intval($_POST['user_id']);
        
        $delete = $conn->prepare("DELETE FROM users WHERE user_id = ? AND role != 'admin'");
        $delete->bind_param("i", $user_id);
        
        if ($delete->execute()) {
            $message = 'User deleted successfully!';
            $message_type = 'success';
        }
    }
}

// Get all users
$filter_role = $_GET['role'] ?? 'all';
$search = $_GET['search'] ?? '';

$query = "SELECT * FROM users WHERE 1=1";
if ($filter_role !== 'all') {
    $query .= " AND role = '$filter_role'";
}
if ($search) {
    $query .= " AND (full_name LIKE '%$search%' OR email LIKE '%$search%')";
}
$query .= " ORDER BY created_at DESC";

$users = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users - SmartOLevel LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white p-4 shadow-lg">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold">SmartOLevel LMS</h1>
                <p class="text-sm text-indigo-100">Manage Users</p>
            </div>
            <div class="flex items-center gap-4">
                <a href="admin_dashboard.php" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition">← Dashboard</a>
                <a href="logout.php" class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition">Logout</a>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto p-6">
        <?php if ($message): ?>
            <div class="<?php echo $message_type === 'success' ? 'bg-green-100 border-green-500 text-green-700' : 'bg-red-100 border-red-500 text-red-700'; ?> border-l-4 p-4 rounded mb-6">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Add User Button -->
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-800">All Users</h2>
            <button onclick="document.getElementById('addUserModal').classList.remove('hidden')" 
                    class="px-6 py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition shadow">
                ➕ Add New User
            </button>
        </div>

        <!-- Filters -->
        <div class="bg-white p-4 rounded-lg shadow-md mb-6">
            <form method="GET" class="flex gap-4">
                <select name="role" class="px-4 py-2 border rounded-lg">
                    <option value="all" <?php echo $filter_role === 'all' ? 'selected' : ''; ?>>All Roles</option>
                    <option value="student" <?php echo $filter_role === 'student' ? 'selected' : ''; ?>>Students</option>
                    <option value="teacher" <?php echo $filter_role === 'teacher' ? 'selected' : ''; ?>>Teachers</option>
                    <option value="admin" <?php echo $filter_role === 'admin' ? 'selected' : ''; ?>>Admins</option>
                </select>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Search by name or email" class="flex-1 px-4 py-2 border rounded-lg">
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    🔍 Search
                </button>
                <a href="admin_users.php" class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600">
                    Clear
                </a>
            </form>
        </div>

        <!-- Users Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Grade</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php while ($user = $users->fetch_assoc()): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm"><?php echo $user['user_id']; ?></td>
                        <td class="px-6 py-4 text-sm font-medium"><?php echo htmlspecialchars($user['full_name']); ?></td>
                        <td class="px-6 py-4 text-sm"><?php echo htmlspecialchars($user['email']); ?></td>
                        <td class="px-6 py-4">
                            <span class="px-3 py-1 text-xs font-semibold rounded-full 
                                <?php echo $user['role'] === 'admin' ? 'bg-red-100 text-red-800' : 
                                          ($user['role'] === 'teacher' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'); ?>">
                                <?php echo ucfirst($user['role']); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm"><?php echo $user['grade'] ?? '-'; ?></td>
                        <td class="px-6 py-4">
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                <input type="hidden" name="is_active" value="<?php echo $user['is_active'] ? 0 : 1; ?>">
                                <button type="submit" class="px-3 py-1 text-xs font-semibold rounded-full 
                                    <?php echo $user['is_active'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                    <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                </button>
                            </form>
                        </td>
                        <td class="px-6 py-4 text-sm space-x-2">
                            <?php if ($user['role'] !== 'admin'): ?>
                            <form method="POST" class="inline" onsubmit="return confirm('Delete this user?')">
                                <input type="hidden" name="action" value="delete_user">
                                <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                <button type="submit" class="text-red-600 hover:text-red-800">🗑️ Delete</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add User Modal -->
    <div id="addUserModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-lg p-6 max-w-md w-full">
            <h3 class="text-xl font-bold mb-4">Add New User</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add_user">
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium mb-1">Full Name *</label>
                        <input type="text" name="full_name" required class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Email *</label>
                        <input type="email" name="email" required class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Password *</label>
                        <input type="password" name="password" required minlength="6" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Role *</label>
                        <select name="role" required class="w-full px-3 py-2 border rounded-lg" onchange="toggleGradeField(this)">
                            <option value="">Select Role</option>
                            <option value="student">Student</option>
                            <option value="teacher">Teacher</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div id="gradeFieldModal" class="hidden">
                        <label class="block text-sm font-medium mb-1">Grade</label>
                        <select name="grade" class="w-full px-3 py-2 border rounded-lg">
                            <option value="">Select Grade</option>
                            <option value="10">Grade 10</option>
                            <option value="11">Grade 11</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Phone</label>
                        <input type="tel" name="phone" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                </div>
                <div class="flex gap-3 mt-6">
                    <button type="submit" class="flex-1 bg-indigo-600 text-white py-2 rounded-lg hover:bg-indigo-700">
                        Add User
                    </button>
                    <button type="button" onclick="document.getElementById('addUserModal').classList.add('hidden')" 
                            class="flex-1 bg-gray-300 text-gray-700 py-2 rounded-lg hover:bg-gray-400">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleGradeField(select) {
            const gradeField = document.getElementById('gradeFieldModal');
            if (select.value === 'student') {
                gradeField.classList.remove('hidden');
            } else {
                gradeField.classList.add('hidden');
            }
        }
    </script>
</body>
</html>