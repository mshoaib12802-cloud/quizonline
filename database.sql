CREATE DATABASE IF NOT EXISTS quizonline CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE quizonline;

-- Drop tables if needed in reverse dependency order for clean re-install
DROP TABLE IF EXISTS attempt_answers;
DROP TABLE IF EXISTS attempts;
DROP TABLE IF EXISTS questions;
DROP TABLE IF EXISTS quizzes;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS app_settings;
DROP TABLE IF EXISTS users;

-- 1. Users Table (Includes academic profile fields, role, and active status)
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('student','teacher','admin') NOT NULL DEFAULT 'student',
  roll_number VARCHAR(50) NULL UNIQUE,
  semester TINYINT NULL,
  department VARCHAR(120) NULL,
  phone VARCHAR(30) NULL,
  gender VARCHAR(20) NULL,
  profile_image VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Categories Table
CREATE TABLE categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- 3. Quizzes Table (Includes semester, marks, publish workflow, and creator)
CREATE TABLE quizzes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(180) NOT NULL,
  description TEXT,
  category_id INT NOT NULL,
  semester TINYINT NOT NULL DEFAULT 1,
  time_limit INT NOT NULL DEFAULT 10,
  total_marks INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) DEFAULT 1,
  publish_status ENUM('draft','published') NOT NULL DEFAULT 'draft',
  published_at DATETIME NULL,
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 4. Questions Table (Supports MCQs and Short answer questions)
CREATE TABLE questions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  quiz_id INT NOT NULL,
  question_text TEXT NOT NULL,
  question_type ENUM('mcq','short') NOT NULL DEFAULT 'mcq',
  option_a VARCHAR(500) NOT NULL DEFAULT '',
  option_b VARCHAR(500) NOT NULL DEFAULT '',
  option_c VARCHAR(500) NOT NULL DEFAULT '',
  option_d VARCHAR(500) NOT NULL DEFAULT '',
  correct_option ENUM('A','B','C','D') NOT NULL DEFAULT 'A',
  marks INT NOT NULL DEFAULT 1,
  explanation TEXT,
  FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 5. Attempts Table (Student submissions and teacher reviews)
CREATE TABLE attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  quiz_id INT NOT NULL,
  score DECIMAL(6,2) NOT NULL DEFAULT 0,
  total INT NOT NULL DEFAULT 0,
  percentage DECIMAL(5,2) NOT NULL DEFAULT 0,
  status ENUM('submitted','graded','published') NOT NULL DEFAULT 'submitted',
  teacher_feedback TEXT NULL,
  graded_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 6. Attempt Answers Table (Individual answers and assigned grades)
CREATE TABLE attempt_answers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  attempt_id INT NOT NULL,
  question_id INT NOT NULL,
  selected_option ENUM('A','B','C','D') NULL,
  text_answer TEXT NULL,
  is_correct TINYINT(1) NOT NULL DEFAULT 0,
  awarded_marks DECIMAL(6,2) NOT NULL DEFAULT 0,
  feedback TEXT NULL,
  FOREIGN KEY (attempt_id) REFERENCES attempts(id) ON DELETE CASCADE,
  FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 7. App Settings Table (Encrypted API keys and model configuration)
CREATE TABLE app_settings (
  setting_key VARCHAR(80) PRIMARY KEY,
  setting_value TEXT NOT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -------------------------------------------------------------
-- Default Seed Data
-- -------------------------------------------------------------

-- Seeded Demo Users (Matching credentials in README.md)
-- Admin: admin@gmail.com / admin
-- Teacher: teacher@gmail.com / teacher@
-- Student: student@gmail.com / student@
INSERT INTO users (id, name, email, password, role, roll_number, semester, department, is_active) VALUES
(1, 'System Administrator', 'admin@gmail.com', '$2y$10$vI8aWBnW3fID.ZQ4/4Y4bO8mN71w2vS5L9o10pDk6Wk2l0k8ePymy', 'admin', 'ADM-001', NULL, 'Administration', 1),
(2, 'Demo Teacher', 'teacher@gmail.com', '$2y$10$wTlnrN4dJp1pD0x0P3N1pef0q0F.n9Fz1OaK3f1hBwE3P8SgUfGmy', 'teacher', 'TCH-001', NULL, 'Computer Science', 1),
(3, 'Demo Student', 'student@gmail.com', '$2y$10$s7b9s9y1k2p3d4f5g6h7j8k9l0z1x2c3v4b5n6m7q8w9e0r1t2y3u', 'student', 'STU-2026-01', 1, 'Computer Science', 1);

-- Default Subjects / Categories
INSERT INTO categories (id, name) VALUES
(1, 'Programming'),
(2, 'Mathematics'),
(3, 'Science'),
(4, 'General Knowledge');

-- Initial Published Quiz (Semester 1, 5 MCQs, 5 Marks Total)
INSERT INTO quizzes (id, title, description, category_id, semester, time_limit, total_marks, is_active, publish_status, published_at, created_by)
VALUES (1, 'Programming Basics', 'Test your understanding of basic programming concepts.', 1, 1, 10, 5, 1, 'published', NOW(), 2);

INSERT INTO questions (quiz_id, question_text, question_type, option_a, option_b, option_c, option_d, correct_option, marks, explanation) VALUES
(1, 'Which symbol is commonly used to end a PHP statement?', 'mcq', ':', ';', '.', ',', 'B', 1, 'Most PHP statements end with a semicolon.'),
(1, 'Which data type stores true or false?', 'mcq', 'String', 'Integer', 'Boolean', 'Array', 'C', 1, 'A Boolean value is either true or false.'),
(1, 'What does HTML describe?', 'mcq', 'Page structure', 'Database tables', 'Server hardware', 'Internet speed', 'A', 1, 'HTML defines the structure and content of a web page.'),
(1, 'Which language is mainly used to style web pages?', 'mcq', 'SQL', 'CSS', 'PHP', 'Python', 'B', 1, 'CSS controls presentation, color, spacing and layout.'),
(1, 'What is an array used for?', 'mcq', 'Sending email only', 'Storing multiple values', 'Turning off a computer', 'Creating passwords only', 'B', 1, 'An array groups multiple values in one variable.');
