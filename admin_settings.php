<?php
require_once 'config.php';
checkRole('admin');

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $admin_id = $_SESSION['user_id'];
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone'] ?? '');
        
        $update = $conn->prepare("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE user_id = ?");
        $update->bind_param("sssi", $full_name, $email, $phone, $admin_id);
        
        if ($update->execute()) {
            $_SESSION['full_name'] = $full_name;
            $_SESSION['email'] = $email;
            $message = 'Profile updated successfully!';
            $message_type = 'success';
        } else {
            $message = 'Failed to update profile.';
            $message_type = 'error';
        }
    } 
    
    elseif ($action === 'change_password') {
        $admin_id = $_SESSION['user_id'];
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Verify current password
        $check = $conn->prepare("SELECT password_hash FROM users WHERE user_id = ?");
        $check->bind_param("i", $admin_id);
        $check->execute();
        $result = $check->get_result()->fetch_assoc();
        
        if (password_verify($current_password, $result['password_hash'])) {
            if ($new_password === $confirm_password) {
                if (strlen($new_password) >= 6) {
                    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $update = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
                    $update->bind_param("si", $new_hash, $admin_id);
                    
                    if ($update->execute()) {
                        $message = 'Password changed successfully!';
                        $message_type = 'success';
                    } else {
                        $message = 'Failed to change password.';
                        $message_type = 'error';
                    }
                } else {
                    $message = 'New password must be at least 6 characters.';
                    $message_type = 'error';
                }
            } else {
                $message = 'New passwords do not match.';
                $message_type = 'error';
            }
        } else {
            $message = 'Current password is incorrect.';
            $message_type = 'error';
        }
    }
}

// Get current admin info
$admin_id = $_SESSION['user_id'];
$admin_query = "SELECT full_name, email, phone FROM users WHERE user_id = ?";
$stmt = $conn->prepare($admin_query);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Settings - SmartOLevel LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <header class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white p-4 shadow-lg">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold">SmartOLevel LMS</h1>
                <p class="text-sm text-indigo-100">Admin Settings</p>
            </div>
            <div class="flex items-center gap-4">
                <a href="admin_dashboard.php" class="px-4 py-2 bg-blue-500 rounded-lg hover:bg-blue-600">← Dashboard</a>
                <a href="logout.php" class="px-4 py-2 bg-red-500 rounded-lg hover:bg-red-600">Logout</a>
            </div>
        </div>
    </header>

    <div class="max-w-4xl mx-auto p-6">
        <?php if ($message): ?>
            <div class="<?php 
                echo $message_type === 'success' ? 'bg-green-100 border-green-500 text-green-700' : 'bg-red-100 border-red-500 text-red-700'; 
            ?> border-l-4 p-4 rounded mb-6">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- Profile Settings -->
            <div class="bg-white rounded-lg shadow-md p-8">
                <h3 class="text-2xl font-bold text-gray-800 mb-6">Update Profile</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="space-y-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Full Name</label>
                            <input type="text" name="full_name" value="<?php echo htmlspecialchars($admin['full_name']); ?>" 
                                   required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($admin['email']); ?>" 
                                   required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Phone (Optional)</label>
                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($admin['phone'] ?? ''); ?>" 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <button type="submit" 
                                class="w-full py-3 bg-gradient-to-r from-indigo-600 to-purple-600 text-white font-semibold rounded-lg shadow-lg hover:from-indigo-700 hover:to-purple-700 transition">
                            Update Profile
                        </button>
                    </div>
                </form>
            </div>

            <!-- Change Password -->
            <div class="bg-white rounded-lg shadow-md p-8">
                <h3 class="text-2xl font-bold text-gray-800 mb-6">Change Password</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="change_password">
                    <div class="space-y-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Current Password</label>
                            <input type="password" name="current_password" required 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
                            <input type="password" name="new_password" required minlength="6" 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password</label>
                            <input type="password" name="confirm_password" required minlength="6" 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <button type="submit" 
                                class="w-full py-3 bg-gradient-to-r from-indigo-600 to-purple-600 text-white font-semibold rounded-lg shadow-lg hover:from-indigo-700 hover:to-purple-700 transition">
                            Change Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>