-- O/Level Learning Management System Database - SmartOLevel LMS

CREATE DATABASE IF NOT EXISTS olevel_lms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE olevel_lms;

-- Users Table
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('student', 'admin', 'teacher') DEFAULT 'student',
    grade ENUM('10','11') NULL,
    phone VARCHAR(20) NULL,
    address TEXT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    INDEX idx_email (email),
    INDEX idx_role (role)
);

-- Subjects Table (Enhanced with all O/L subjects)
CREATE TABLE subjects (
    subject_id INT AUTO_INCREMENT PRIMARY KEY,
    subject_name VARCHAR(255) NOT NULL,
    subject_code VARCHAR(20) NOT NULL UNIQUE,
    grade_level ENUM('10','11') NOT NULL,
    subject_category ENUM('Language', 'Mathematics', 'Science', 'Social Studies', 'Religion', 'Other') NOT NULL,
    description TEXT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Teacher-Subject Assignments
CREATE TABLE teacher_subjects (
    teacher_id INT NOT NULL,
    subject_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (teacher_id, subject_id),
    FOREIGN KEY (teacher_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(subject_id) ON DELETE CASCADE
);

-- Student-Subject Enrollments
CREATE TABLE student_subjects (
    student_id INT NOT NULL,
    subject_id INT NOT NULL,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (student_id, subject_id),
    FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(subject_id) ON DELETE CASCADE
);

-- Lecture Notes (PDFs/Books)
CREATE TABLE lecture_notes (
    note_id INT AUTO_INCREMENT PRIMARY KEY,
    subject_id INT NOT NULL,
    teacher_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,  
    description TEXT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_type VARCHAR(10) NOT NULL,
    file_size INT NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    download_count INT DEFAULT 0,
    FOREIGN KEY (subject_id) REFERENCES subjects(subject_id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Lecture Videos (YouTube or uploaded videos)
CREATE TABLE lecture_videos (
    video_id INT AUTO_INCREMENT PRIMARY KEY,
    subject_id INT NOT NULL,
    teacher_id INT NOT NULL,
    title VARCHAR(255) NOT NULL, 
    description TEXT NULL,
    video_url VARCHAR(500) NOT NULL,
    video_type ENUM('youtube', 'upload') DEFAULT 'youtube',
    duration VARCHAR(50) NULL,
    thumbnail_url VARCHAR(500) NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    view_count INT DEFAULT 0,
    FOREIGN KEY (subject_id) REFERENCES subjects(subject_id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Assignments 
CREATE TABLE assignments (
    assignment_id INT AUTO_INCREMENT PRIMARY KEY,
    subject_id INT NOT NULL,
    teacher_id INT NOT NULL,
    assignment_title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    instructions TEXT NULL,
    file_path VARCHAR(255) NULL,
    total_marks INT NOT NULL,
    due_date DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    allow_late_submission BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (subject_id) REFERENCES subjects(subject_id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Assignment Submissions
CREATE TABLE assignment_submissions (
    submission_id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    student_id INT NOT NULL,
    submitted_file_path VARCHAR(255) NULL,
    submission_text TEXT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('submitted', 'late', 'graded', 'pending') DEFAULT 'submitted',
    UNIQUE KEY unique_submission (assignment_id, student_id),
    FOREIGN KEY (assignment_id) REFERENCES assignments(assignment_id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Assignment Grades
CREATE TABLE assignment_grades (
    grade_id INT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT NOT NULL UNIQUE,
    teacher_id INT NOT NULL,
    marks_obtained DECIMAL(5,2) NOT NULL,
    grade VARCHAR(5) NULL,
    feedback TEXT NULL,
    graded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (submission_id) REFERENCES assignment_submissions(submission_id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Quizzes
CREATE TABLE quizzes (
    quiz_id INT AUTO_INCREMENT PRIMARY KEY,
    subject_id INT NOT NULL,
    teacher_id INT NOT NULL,
    title VARCHAR(255) NOT NULL, 
    description TEXT NULL,
    total_marks INT NOT NULL,
    duration_minutes INT NOT NULL,
    passing_marks INT NOT NULL,
    start_date DATETIME NULL,
    end_date DATETIME NULL,
    attempts_allowed INT DEFAULT 1,
    show_correct_answers BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (subject_id) REFERENCES subjects(subject_id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Quiz Questions
CREATE TABLE quiz_questions (
    question_id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    question_text TEXT NOT NULL,
    question_type ENUM('mcq', 'true_false', 'short_answer') NOT NULL,
    option_a VARCHAR(500) NULL,
    option_b VARCHAR(500) NULL,
    option_c VARCHAR(500) NULL,
    option_d VARCHAR(500) NULL,
    correct_answer VARCHAR(500) NOT NULL,
    marks INT NOT NULL,
    question_order INT NOT NULL,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(quiz_id) ON DELETE CASCADE
);

-- Quiz Attempts 
CREATE TABLE quiz_attempts (
    attempt_id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    student_id INT NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NULL,
    score DECIMAL(5,2) NULL,
    percentage DECIMAL(5,2) NULL,
    total_marks INT NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('in_progress', 'completed', 'timeout') DEFAULT 'in_progress',
    FOREIGN KEY (quiz_id) REFERENCES quizzes(quiz_id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Quiz Answers
CREATE TABLE quiz_answers (
    answer_id INT AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT NOT NULL,
    question_id INT NOT NULL,
    student_answer TEXT NULL,
    is_correct BOOLEAN DEFAULT FALSE,
    marks_awarded DECIMAL(5,2) DEFAULT 0,
    FOREIGN KEY (attempt_id) REFERENCES quiz_attempts(attempt_id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES quiz_questions(question_id) ON DELETE CASCADE
);

-- System Activity Log (Admin monitoring)
CREATE TABLE activity_log (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action_type VARCHAR(100) NOT NULL,
    description TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Insert All O/Level Subjects in school Sri Lanka in here we can choose subjects
INSERT INTO subjects (subject_name, subject_code, grade_level, subject_category, description) VALUES
-- Language Subjects
('Sinhala Language', 'SIN-10', '10', 'Language', 'Grade 10 Sinhala Language'),
('Sinhala Language', 'SIN-11', '11', 'Language', 'Grade 11 Sinhala Language'),
('Tamil Language', 'TAM-10', '10', 'Language', 'Grade 10 Tamil Language'),
('Tamil Language', 'TAM-11', '11', 'Language', 'Grade 11 Tamil Language'),
('English Language', 'ENG-10', '10', 'Language', 'Grade 10 English Language'),
('English Language', 'ENG-11', '11', 'Language', 'Grade 11 English Language'),

-- Mathematics
('Mathematics', 'MATH-10', '10', 'Mathematics', 'Grade 10 Mathematics'),
('Mathematics', 'MATH-11', '11', 'Mathematics', 'Grade 11 Mathematics'),

-- Science
('Science', 'SCI-10', '10', 'Science', 'Grade 10 Science'),
('Science', 'SCI-11', '11', 'Science', 'Grade 11 Science'),

-- Social Studies
('History', 'HIST-10', '10', 'Social Studies', 'Grade 10 History'),
('History', 'HIST-11', '11', 'Social Studies', 'Grade 11 History'),
('Geography', 'GEO-10', '10', 'Social Studies', 'Grade 10 Geography'),
('Geography', 'GEO-11', '11', 'Social Studies', 'Grade 11 Geography'),

-- Religion
('Buddhism', 'BUD-10', '10', 'Religion', 'Grade 10 Buddhism'),
('Buddhism', 'BUD-11', '11', 'Religion', 'Grade 11 Buddhism'),
('Hinduism', 'HIN-10', '10', 'Religion', 'Grade 10 Hinduism'),
('Hinduism', 'HIN-11', '11', 'Religion', 'Grade 11 Hinduism'),
('Christianity', 'CHR-10', '10', 'Religion', 'Grade 10 Christianity'),
('Christianity', 'CHR-11', '11', 'Religion', 'Grade 11 Christianity'),
('Islam', 'ISL-10', '10', 'Religion', 'Grade 10 Islam'),
('Islam', 'ISL-11', '11', 'Religion', 'Grade 11 Islam'),

-- Other Subjects
('Business Studies', 'BUS-10', '10', 'Other', 'Grade 10 Business Studies'),
('Business Studies', 'BUS-11', '11', 'Other', 'Grade 11 Business Studies'),
('ICT', 'ICT-10', '10', 'Other', 'Grade 10 Information & Communication Technology'),
('ICT', 'ICT-11', '11', 'Other', 'Grade 11 Information & Communication Technology'),
('Health & Physical Education', 'HPE-10', '10', 'Other', 'Grade 10 Health & Physical Education'),
('Health & Physical Education', 'HPE-11', '11', 'Other', 'Grade 11 Health & Physical Education'),
('Art', 'ART-10', '10', 'Other', 'Grade 10 Art'),
('Art', 'ART-11', '11', 'Other', 'Grade 11 Art'),
('Music', 'MUS-10', '10', 'Other', 'Grade 10 Music'),
('Music', 'MUS-11', '11', 'Other', 'Grade 11 Music'),
('Dancing', 'DAN-10', '10', 'Other', 'Grade 10 Dancing'),
('Dancing', 'DAN-11', '11', 'Other', 'Grade 11 Dancing'),
('Drama & Theatre', 'DRA-10', '10', 'Other', 'Grade 10 Drama & Theatre'),
('Drama & Theatre', 'DRA-11', '11', 'Other', 'Grade 11 Drama & Theatre');

-- Insert Demo Users (Password: password for all)
INSERT INTO users (full_name, email, password_hash, role, grade, phone) VALUES
('System Administrator', 'admin@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', NULL, '0771234567'),
('Teacher1', 'teacher1@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher', NULL, '0771234568'),
('Student1', 'student1@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', '10', '0771234569'),
('Teacher', 'teacher2@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher', NULL, '0771234570'),
('Student2', 'student2@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', '11', '0771234571');

-- Sample Data: Assign teachers to subjects
INSERT INTO teacher_subjects (teacher_id, subject_id) VALUES
(2, 7),  -- Teacher1 -> Math Grade 10
(2, 9),  -- Teacher1 -> Science Grade 10
(4, 8),  -- Teacher2 -> Math Grade 11
(4, 10); -- Teacher2 -> Science Grade 11

-- Sample Data: Enroll students in subjects
INSERT INTO student_subjects (student_id, subject_id) VALUES
-- Student1(Grade 10)
(3, 1), (3, 5), (3, 7), (3, 9), (3, 11), (3, 13), (3, 15), (3, 21),
-- Student2(Grade 11)
(5, 2), (5, 6), (5, 8), (5, 10), (5, 12), (5, 14), (5, 16), (5, 22);

-- i added some Sample lecture notes
INSERT INTO lecture_notes (subject_id, teacher_id, title, description, file_path, file_type, file_size) VALUES
(7, 2, 'maths', 'Introduction to basic maths expressions', 'uploads/notes/maths.pdf', 'pdf', 1024000),
(9, 2, 'science', 'Understanding about animals', 'uploads/notes/sceience.pdf', 'pdf', 2048000);

-- i added some Sample lecture videos
INSERT INTO lecture_videos (subject_id, teacher_id, title, description, video_url, video_type) VALUES
(7, 2, 'squre root', 'Learn how to solve squre root equations', 'https://youtu.be/63WYn-sdVM0', 'youtube'),
(9, 2, 'animals', 'lern about animals', 'https://www.youtube.com/watch?v=KEJf-cJfolY&pp=ygUQc2NpZW5jZSBncmFkZSAxMA%3D%3D', 'youtube');

-- i added some Sample assignments
INSERT INTO assignments (subject_id, teacher_id, assignment_title, description, instructions, total_marks, due_date) VALUES
(7, 2, 'Assignment 1', 'Complete all problems', 'Solve problems 1-20 from the textbook', 100, DATE_ADD(NOW(), INTERVAL 7 DAY)),
(9, 2, 'Diagram', 'Draw and label a plant cell', 'Use the provided template and color coding', 50, DATE_ADD(NOW(), INTERVAL 10 DAY));

-- i added someSample quizzes
INSERT INTO quizzes (subject_id, teacher_id, title, description, total_marks, duration_minutes, passing_marks) VALUES
(7, 2, 'Quiz 1', 'Test your skills', 20, 30, 10),
(9, 2, 'Chapter 1 Quiz', ' structure and function', 25, 25, 13);

ALTER TABLE quiz_questions 
ADD COLUMN correct_option ENUM('A','B','C','D') NOT NULL AFTER option_d;