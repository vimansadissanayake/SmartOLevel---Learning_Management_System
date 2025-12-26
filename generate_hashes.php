<?php

$admin_hash = password_hash('123', PASSWORD_DEFAULT);
$teacher_hash = password_hash('123', PASSWORD_DEFAULT);
$student_hash = password_hash('123', PASSWORD_DEFAULT);

echo "<h2>Password Hashes for '123' (Unique for each role)</h2>";
echo "<p><strong>Admin Hash:</strong><br>" . $admin_hash . "</p>";
echo "<p><strong>Teacher Hash:</strong><br>" . $teacher_hash . "</p>";
echo "<p><strong>Student Hash:</strong><br>" . $student_hash . "</p>";

echo "<hr>";
echo "<h3>Sample UPDATE queries (if you already have users in database):</h3>";
echo "<pre>";
echo "UPDATE users SET password_hash = '$admin_hash' WHERE email = 'admin@gmail.com';\n";
echo "UPDATE users SET password_hash = '$teacher_hash' WHERE email = 'teacher@gmail.com';\n";
echo "UPDATE users SET password_hash = '$student_hash' WHERE email = 'student@gmail.com';\n";
echo "</pre>";

echo "<hr>";
echo "<h3>Verification Test:</h3>";
$test_password = '123';
$test_hash = password_hash($test_password, PASSWORD_DEFAULT);
$verify_result = password_verify($test_password, $test_hash);
echo "<p>Password: '$test_password'</p>";
echo "<p>Generated Hash: $test_hash</p>";
echo "<p>Verification Result: " . ($verify_result ? "✅ SUCCESS" : "❌ FAILED") . "</p>";
?>