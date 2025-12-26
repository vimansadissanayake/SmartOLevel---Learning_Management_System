<?php
require_once 'config.php';
checkRole('student');

$note_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$query = "SELECT * FROM lecture_notes WHERE note_id = ? AND is_active = 1";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $note_id);
$stmt->execute();
$note = $stmt->get_result()->fetch_assoc();

if ($note && file_exists($note['file_path'])) {
    $conn->query("UPDATE lecture_notes SET download_count = download_count + 1 WHERE note_id = $note_id");

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($note['file_path']) . '"');
    header('Content-Length: ' . filesize($note['file_path']));
    readfile($note['file_path']);
    exit;
}

header("Location: student_dashboard.php");
exit();
?>