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
$success = '';

// Get all subjects for selection
$subjects_query = "SELECT * FROM subjects WHERE is_active = 1 ORDER BY grade_level, subject_category, subject_name";
$all_subjects = $conn->query($subjects_query);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($role) || empty($full_name) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } else {
        // Check if email already exists
        $check_query = "SELECT user_id FROM users WHERE email = ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = 'Email already registered. Please login or use a different email.';
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            if ($role === 'student') {
                $grade = $_POST['grade'] ?? '';
                $selected_subjects = $_POST['subjects'] ?? [];
                
                if (empty($grade)) {
                    $error = 'Please select your grade level';
                } elseif (empty($selected_subjects)) {
                    $error = 'Please select at least one subject';
                } else {
                    // Insert student (no phone/address)
                    $insert_query = "INSERT INTO users (full_name, email, password_hash, role, grade) 
                                    VALUES (?, ?, ?, 'student', ?)";
                    $stmt = $conn->prepare($insert_query);
                    $stmt->bind_param("ssss", $full_name, $email, $password_hash, $grade);
                    
                    if ($stmt->execute()) {
                        $new_user_id = $stmt->insert_id;
                        
                        // Enroll in selected subjects
                        $enroll_stmt = $conn->prepare("INSERT INTO student_subjects (student_id, subject_id) VALUES (?, ?)");
                        foreach ($selected_subjects as $subject_id) {
                            $enroll_stmt->bind_param("ii", $new_user_id, $subject_id);
                            $enroll_stmt->execute();
                        }
                        
                        $success = 'Registration successful! You can now login.';
                    } else {
                        $error = 'Registration failed. Please try again.';
                    }
                }
                
            } elseif ($role === 'teacher') {
                $selected_subjects = $_POST['subjects'] ?? [];
                
                if (empty($selected_subjects)) {
                    $error = 'Please select at least one subject to teach';
                } else {
                    // Insert teacher (no phone/address)
                    $insert_query = "INSERT INTO users (full_name, email, password_hash, role) 
                                    VALUES (?, ?, ?, 'teacher')";
                    $stmt = $conn->prepare($insert_query);
                    $stmt->bind_param("sss", $full_name, $email, $password_hash);
                    
                    if ($stmt->execute()) {
                        $new_user_id = $stmt->insert_id;
                        
                        // Assign to selected subjects
                        $assign_stmt = $conn->prepare("INSERT INTO teacher_subjects (teacher_id, subject_id) VALUES (?, ?)");
                        foreach ($selected_subjects as $subject_id) {
                            $assign_stmt->bind_param("ii", $new_user_id, $subject_id);
                            $assign_stmt->execute();
                        }
                        
                        $success = 'Registration successful! You can now login.';
                    } else {
                        $error = 'Registration failed. Please try again.';
                    }
                }
            } elseif ($role === 'admin') {
                // Insert admin (only name, email, password)
                $insert_query = "INSERT INTO users (full_name, email, password_hash, role) 
                                VALUES (?, ?, ?, 'admin')";
                $stmt = $conn->prepare($insert_query);
                $stmt->bind_param("sss", $full_name, $email, $password_hash);
                
                if ($stmt->execute()) {
                    $success = 'Admin registration successful! You can now login.';
                } else {
                    $error = 'Registration failed. Please try again.';
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - SmartOLevel LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-indigo-500 via-purple-500 to-pink-500 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-2xl">
        <!-- Logo/Header -->
        <div class="text-center mb-6">
            <div class="inline-block p-3 bg-white rounded-full shadow-lg mb-3">
                <svg class="w-12 h-12 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
            </div>
            <h1 class="text-3xl font-bold text-white mb-1">SmartOLevel LMS</h1>
            <p class="text-white text-sm">Create Your Account</p>
        </div>

        <!-- Registration Form -->
        <div class="bg-white rounded-2xl shadow-2xl p-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-4 text-center">Register</h2>
            
            <?php if ($error): ?>
                <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-3 rounded mb-4 text-sm">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-3 rounded mb-4 text-sm">
                    <?php echo htmlspecialchars($success); ?>
                    <a href="login.php" class="font-bold underline ml-2">Login Now</a>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="space-y-4" id="registerForm">
                <!-- Role Selection -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        I am a <span class="text-red-500">*</span>
                    </label>
                    <div class="grid grid-cols-3 gap-3">
                        <label class="cursor-pointer">
                            <input type="radio" name="role" value="student" required class="peer sr-only" onchange="toggleRoleFields()">
                            <div class="p-4 border-2 border-gray-300 rounded-lg peer-checked:border-indigo-600 peer-checked:bg-indigo-50 transition text-center">
                                <div class="text-3xl mb-1">🎓</div>
                                <div class="font-semibold">Student</div>
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="role" value="teacher" required class="peer sr-only" onchange="toggleRoleFields()">
                            <div class="p-4 border-2 border-gray-300 rounded-lg peer-checked:border-green-600 peer-checked:bg-green-50 transition text-center">
                                <div class="text-3xl mb-1">👨‍🏫</div>
                                <div class="font-semibold">Teacher</div>
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="role" value="admin" required class="peer sr-only" onchange="toggleRoleFields()">
                            <div class="p-4 border-2 border-gray-300 rounded-lg peer-checked:border-purple-600 peer-checked:bg-purple-50 transition text-center">
                                <div class="text-3xl mb-1">⚙️</div>
                                <div class="font-semibold">Admin</div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Full Name -->
                <div>
                    <label for="full_name" class="block text-sm font-medium text-gray-700 mb-1">
                        Full Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="full_name" name="full_name" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                           placeholder="Enter your full name">
                </div>

                <!-- Email -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                        Email Address <span class="text-red-500">*</span>
                    </label>
                    <input type="email" id="email" name="email" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                           placeholder="your.email@example.com">
                </div>

                <!-- Grade (Student Only) -->
                <div id="gradeField" class="hidden">
                    <label for="grade" class="block text-sm font-medium text-gray-700 mb-1">
                        Grade Level <span class="text-red-500">*</span>
                    </label>
                    <select id="grade" name="grade" onchange="loadSubjectsByGrade()"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                        <option value="">Select your grade</option>
                        <option value="10">Grade 10</option>
                        <option value="11">Grade 11</option>
                    </select>
                </div>

                <!-- Subjects Selection (Student & Teacher only) -->
                <div id="subjectsField" class="hidden">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        <span id="subjectsLabel">Select Subjects</span> <span class="text-red-500">*</span>
                    </label>
                    <div id="subjectsList" class="max-h-60 overflow-y-auto border border-gray-300 rounded-lg p-3 space-y-2">
                        <?php 
                        $all_subjects->data_seek(0);
                        while ($subject = $all_subjects->fetch_assoc()): 
                        ?>
                        <label class="flex items-center p-2 hover:bg-gray-50 rounded cursor-pointer subject-item" 
                               data-grade="<?php echo $subject['grade_level']; ?>">
                            <input type="checkbox" name="subjects[]" value="<?php echo $subject['subject_id']; ?>"
                                   class="mr-2 rounded text-indigo-600">
                            <span class="text-sm">
                                <?php echo htmlspecialchars($subject['subject_name']); ?>
                                <span class="text-xs text-gray-500">
                                    (Grade <?php echo $subject['grade_level']; ?> - <?php echo $subject['subject_code']; ?>)
                                </span>
                            </span>
                        </label>
                        <?php endwhile; ?>
                    </div>
                </div>

                <!-- Password -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                            Password <span class="text-red-500">*</span>
                        </label>
                        <input type="password" id="password" name="password" required minlength="6"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                               placeholder="Min 6 characters">
                    </div>
                    <div>
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">
                            Confirm Password <span class="text-red-500">*</span>
                        </label>
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="6"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                               placeholder="Re-enter password">
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" 
                        class="w-full bg-gradient-to-r from-indigo-600 to-purple-600 text-white py-3 px-4 rounded-lg font-semibold shadow-lg hover:from-indigo-700 hover:to-purple-700 transform hover:scale-[1.02] transition duration-200">
                    Create Account
                </button>
            </form>

            <!-- Login Link -->
            <div class="mt-4 text-center">
                <p class="text-gray-600 text-sm">
                    Already have an account? 
                    <a href="login.php" class="text-indigo-600 hover:text-indigo-800 font-semibold">
                        Login here
                    </a>
                </p>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-4 text-white text-xs">
            <p>&copy; 2025 SmartOLevel LMS. All rights reserved.</p>
        </div>
    </div>

    <script>
        function toggleRoleFields() {
            const role = document.querySelector('input[name="role"]:checked')?.value;
            const gradeField = document.getElementById('gradeField');
            const subjectsField = document.getElementById('subjectsField');
            const subjectsLabel = document.getElementById('subjectsLabel');
            
            if (role === 'student') {
                gradeField.classList.remove('hidden');
                subjectsField.classList.remove('hidden');
                subjectsLabel.textContent = 'Select Subjects to Study';
                document.getElementById('grade').required = true;
                loadSubjectsByGrade(); // Reset subject visibility
            } else if (role === 'teacher') {
                gradeField.classList.add('hidden');
                subjectsField.classList.remove('hidden');
                subjectsLabel.textContent = 'Select Subjects to Teach';
                document.getElementById('grade').required = false;
                document.getElementById('grade').value = '';
                // Show all subjects for teachers
                document.querySelectorAll('.subject-item').forEach(item => {
                    item.style.display = 'flex';
                });
            } else if (role === 'admin') {
                gradeField.classList.add('hidden');
                subjectsField.classList.add('hidden');
            } else {
                gradeField.classList.add('hidden');
                subjectsField.classList.add('hidden');
            }
        }

        function loadSubjectsByGrade() {
            const grade = document.getElementById('grade').value;
            const role = document.querySelector('input[name="role"]:checked')?.value;
            
            if (role === 'student' && grade) {
                document.querySelectorAll('.subject-item').forEach(item => {
                    if (item.dataset.grade === grade) {
                        item.style.display = 'flex';
                    } else {
                        item.style.display = 'none';
                        item.querySelector('input').checked = false;
                    }
                });
            }
        }

        // Password match validation
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');

        confirmPassword.addEventListener('input', function() {
            if (password.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity('Passwords do not match');
            } else {
                confirmPassword.setCustomValidity('');
            }
        });

        password.addEventListener('input', function() {
            if (confirmPassword.value && password.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity('Passwords do not match');
            } else {
                confirmPassword.setCustomValidity('');
            }
        });

        // Form validation before submit
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const role = document.querySelector('input[name="role"]:checked')?.value;
            const subjects = document.querySelectorAll('input[name="subjects[]"]:checked');
            
            if (!role) {
                e.preventDefault();
                alert('Please select your role');
                return;
            }
            
            if ((role === 'student' || role === 'teacher') && subjects.length === 0) {
                e.preventDefault();
                alert('Please select at least one subject');
                return;
            }
            
            if (role === 'student') {
                const grade = document.getElementById('grade').value;
                if (!grade) {
                    e.preventDefault();
                    alert('Please select your grade level');
                    return;
                }
            }
        });
    </script>
</body>
</html>