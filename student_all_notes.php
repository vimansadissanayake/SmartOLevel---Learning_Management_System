<?php
require_once 'config.php';
checkRole('student');

$student_id = $_SESSION['user_id'];
$student_name = $_SESSION['full_name'];
$grade = $_SESSION['grade'] ?? '10';

$query = "SELECT ln.note_id, ln.title, ln.file_path, ln.uploaded_at,
                 s.subject_name, s.subject_code
          FROM lecture_notes ln
          INNER JOIN subjects s ON ln.subject_id = s.subject_id
          INNER JOIN student_subjects ss ON s.subject_id = ss.subject_id
          WHERE ss.student_id = ? AND ln.is_active = 1 AND s.grade_level = ?
          ORDER BY ln.uploaded_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("is", $student_id, $grade);
$stmt->execute();
$notes = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Download Notes - SmartOLevel LMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <header class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white p-4 shadow-lg">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold">SmartOLevel LMS</h1>
                <p class="text-sm text-blue-100">Download Notes - Grade <?php echo htmlspecialchars($grade); ?></p>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-sm">Welcome, <?php echo htmlspecialchars($student_name); ?></span>
                <a href="logout.php" class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition">Logout</a>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto p-6">
        <div class="flex items-center mb-6">
            <a href="student_dashboard.php" class="text-blue-600 hover:underline mr-4">← Back to Dashboard</a>
            <h2 class="text-2xl font-bold text-gray-800">All Lecture Notes</h2>
        </div>

        <?php if ($notes->num_rows > 0): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php while ($note = $notes->fetch_assoc()): ?>
                    <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition">
                        <div class="text-6xl mb-4 text-center text-blue-600">📄</div>
                        <h3 class="font-bold text-lg text-gray-800 mb-2 text-center"><?php echo htmlspecialchars($note['title']); ?></h3>
                        <p class="text-sm text-gray-600 text-center mb-4">
                            <?php echo htmlspecialchars($note['subject_name']); ?> (<?php echo htmlspecialchars($note['subject_code']); ?>)
                        </p>
                        <p class="text-xs text-gray-400 text-center mb-6">Uploaded: <?php echo formatDate($note['uploaded_at']); ?></p>
                        <a href="<?php echo htmlspecialchars($note['file_path']); ?>" download
                           class="block text-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                            Download PDF
                        </a>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-12 bg-white rounded-lg shadow-md">
                <div class="text-6xl mb-4">📄</div>
                <p class="text-xl text-gray-500">No notes available yet</p>
                <p class="text-gray-400 mt-2">Your teachers will upload notes soon.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>