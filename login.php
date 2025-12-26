<?php
require_once 'config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    $redirect = match ($_SESSION['role'] ?? '') {
        'student' => 'student_dashboard.php',
        'teacher' => 'teacher_dashboard.php',
        default   => 'admin_dashboard.php'
    };
    header("Location: $redirect");
    exit();
}

$error = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = "Please enter both email and password";
    } else {
        // Query user from database
        $query = "SELECT user_id, full_name, email, password_hash, role, grade 
                  FROM users 
                  WHERE email = ? AND is_active = 1 
                  LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        $user = $result->fetch_assoc();
        $stmt->close();

        // Always run password_verify to prevent timing attacks
        $valid_password = $user && password_verify($password, $user['password_hash']);

        if ($valid_password) {
            session_regenerate_id(true);

            $_SESSION['user_id']   = $user['user_id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email']     = $user['email'];
            $_SESSION['role']      = $user['role'];
            $_SESSION['grade']     = $user['grade'];

            $update_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
            $update_stmt->bind_param("i", $user['user_id']);
            $update_stmt->execute();
            $update_stmt->close();

            $redirect = match ($user['role']) {
                'student' => 'student_dashboard.php',
                'teacher' => 'teacher_dashboard.php',
                default   => 'admin_dashboard.php'
            };

            header("Location: $redirect");
            exit();
        } else {
            $error = "Invalid email or password";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartOLevel LMS - Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-indigo-500 via-purple-500 to-pink-500 min-h-screen flex items-center justify-center">
    <div class="bg-white shadow-2xl rounded-2xl flex max-w-4xl mx-auto overflow-hidden">
        <!-- Left Side - Welcome Message -->
        <div class="w-1/2 bg-gradient-to-br from-indigo-600 to-purple-600 p-12 text-white">
            <h1 class="text-4xl font-bold mb-4">Welcome to <b>SmartOLevel LMS</b></h1>
            <p class="text-lg mb-8">Empowering Students to Master O/L Subjects, Practice Consistently, and Reach Their Goals</p>
            <div class="space-y-4 text-sm">
                <p>📚 Interactive Lesson materials</p>
                <p>📝 Assignments & Quizzes</p>
                <p>🎥 Video Resources</p>
                <p>📊 Progress Tracking</p>
                <p>👥 Teacher Support</p>
            </div>
        </div>

        <!-- Right Side - Login Form -->
        <div class="w-1/2 p-12">
            <h2 class="text-3xl font-bold mb-8 text-gray-800">Login to Your Account</h2>
            
            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-6">
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           required 
                           placeholder="student@gmail.com" 
                           value="<?php echo htmlspecialchars($email ?? ''); ?>"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                </div>

                <div class="mb-6">
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           required 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                </div>

                <div class="flex justify-between items-center mb-6">
                    <a href="register.php" class="text-sm text-indigo-600 hover:underline">Forgot Password?</a>
                    
                    <!-- New: Link to Register Page -->
                    <a href="register.php" class="text-sm text-indigo-600 hover:underline font-medium">
                        Don't have an account? Register
                    </a>
                </div>

                <button type="submit" 
                        class="w-full py-3 px-4 bg-gradient-to-r from-indigo-600 to-purple-600 text-white font-semibold rounded-lg shadow-lg hover:from-indigo-700 hover:to-purple-700 transition duration-300">
                    Login
                </button>
            </form>

            <!-- Note for Demo -->
            <div class="mt-8 p-5 bg-gray-50 rounded-lg border border-gray-200">
                <h4 class="text-sm font-bold mb-3 text-gray-700">Note:</h4>
                <p class="text-xs text-gray-500">Use your password and email to login</p>
            </div>
        </div>
    </div>
</body>
</html>