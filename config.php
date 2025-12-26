<?php
// config.php - this is my Main Configuration File

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); // Change if you have a password
define('DB_NAME', 'olevel_lms');

// Create database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
} 

// Set character set
$conn->set_charset("utf8mb4");

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

// Check user role and redirect if unauthorized
function checkRole($required_role) {
    if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== $required_role) {
        header("Location: login.php");
        exit();
    }
}

// Sanitize user input 
function sanitize($data) {
    global $conn;
    return mysqli_real_escape_string($conn, trim($data));
}

// Format date
function formatDate($date) {
    return date('d M Y', strtotime($date));
}

// Format datetime
function formatDateTime($datetime) {
    return date('d M Y h:i A', strtotime($datetime));
}

// Validate file upload (added MIME check for security)
function validateFileUpload($file, $allowed_types, $max_size) {
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $mime = mime_content_type($file['tmp_name']); // Requires fileinfo extension
    
    // Define allowed MIME types based on extensions
    $allowed_mimes = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'zip' => 'application/zip',
        // Add more as needed
    ];
    
    if (!in_array($ext, $allowed_types) || !in_array($mime, array_values($allowed_mimes)) || $mime !== ($allowed_mimes[$ext] ?? '')) {
        return ['success' => false, 'message' => 'Invalid file type or MIME. Allowed: ' . implode(', ', $allowed_types)];
    }
    
    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => 'File too large. Max size: ' . round($max_size / 1024 / 1024, 2) . ' MB'];
    }
    
    return ['success' => true];
}

// Get file extension
function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

// Calculate grade based on percentage
function calculateGrade($percentage) {
    if ($percentage >= 90) return 'A+';
    if ($percentage >= 80) return 'A';
    if ($percentage >= 70) return 'B';
    if ($percentage >= 60) return 'C';
    if ($percentage >= 50) return 'D';
    return 'F';
}

// Get color for grade display
function getGradeColor($grade) {
    switch ($grade) {
        case 'A+': 
        case 'A': 
            return '#28a745';
        case 'B': 
            return '#17a2b8';
        case 'C': 
            return '#ffc107';
        case 'D': 
            return '#fd7e14';
        default: 
            return '#dc3545';
    }
}

// FILE UPLOAD CONFIGURATION part

// Upload directories
define('UPLOAD_DIR_NOTES', 'uploads/notes/');
define('UPLOAD_DIR_VIDEOS', 'uploads/videos/'); // Not used (YouTube links)
define('UPLOAD_DIR_ASSIGNMENTS', 'uploads/assignments/');
define('UPLOAD_DIR_SUBMISSIONS', 'uploads/submissions/');

// Create directories if they don't exist (safer permissions: 0755)
$directories = [
    UPLOAD_DIR_NOTES, 
    UPLOAD_DIR_VIDEOS, 
    UPLOAD_DIR_ASSIGNMENTS, 
    UPLOAD_DIR_SUBMISSIONS
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// File size limits (in bytes)
define('MAX_FILE_SIZE_NOTES', 10 * 1024 * 1024); // 10 MB
define('MAX_FILE_SIZE_VIDEOS', 100 * 1024 * 1024); // 100 MB (not used)
define('MAX_FILE_SIZE_ASSIGNMENTS', 10 * 1024 * 1024); // 10 MB
define('MAX_FILE_SIZE_SUBMISSIONS', 10 * 1024 * 1024); // 10 MB

// Allowed file types
define('ALLOWED_NOTES_TYPES', ['pdf', 'doc', 'docx', 'ppt', 'pptx']);
define('ALLOWED_VIDEO_TYPES', ['mp4', 'avi', 'mov', 'wmv', 'flv']); // Not used
define('ALLOWED_ASSIGNMENT_TYPES', ['pdf', 'doc', 'docx']);
define('ALLOWED_SUBMISSION_TYPES', ['pdf', 'doc', 'docx', 'zip']);


// Remove these lines in production
ini_set('display_errors', 1);
error_reporting(E_ALL);
?>