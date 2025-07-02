-- Student Management System Database Schema

CREATE DATABASE IF NOT EXISTS student_management;
USE student_management;

-- Users table (for authentication)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    user_type ENUM('admin', 'teacher', 'student') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Students table
CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    student_id VARCHAR(20) UNIQUE NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    date_of_birth DATE,
    gender ENUM('male', 'female', 'other'),
    phone VARCHAR(15),
    address TEXT,
    parent_name VARCHAR(100),
    parent_phone VARCHAR(15),
    parent_email VARCHAR(100),
    admission_date DATE,
    class VARCHAR(20),
    section VARCHAR(5),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Teachers table
CREATE TABLE teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    teacher_id VARCHAR(20) UNIQUE NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    date_of_birth DATE,
    gender ENUM('male', 'female', 'other'),
    phone VARCHAR(15),
    address TEXT,
    qualification VARCHAR(100),
    subject_specialization VARCHAR(100),
    joining_date DATE,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Subjects table
CREATE TABLE subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_code VARCHAR(10) UNIQUE NOT NULL,
    subject_name VARCHAR(100) NOT NULL,
    description TEXT,
    credits INT DEFAULT 1,
    class VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Classes table
CREATE TABLE classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_name VARCHAR(20) NOT NULL,
    section VARCHAR(5) NOT NULL,
    teacher_id INT,
    academic_year VARCHAR(10),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id),
    UNIQUE KEY unique_class_section (class_name, section, academic_year)
);

-- Grades table
CREATE TABLE grades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT,
    subject_id INT,
    teacher_id INT,
    exam_type ENUM('quiz', 'midterm', 'final', 'assignment') NOT NULL,
    marks_obtained DECIMAL(5,2),
    total_marks DECIMAL(5,2),
    grade VARCHAR(2),
    remarks TEXT,
    exam_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id),
    FOREIGN KEY (teacher_id) REFERENCES teachers(id)
);

-- Attendance table
CREATE TABLE attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT,
    subject_id INT,
    teacher_id INT,
    attendance_date DATE NOT NULL,
    status ENUM('present', 'absent', 'late') NOT NULL,
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id),
    FOREIGN KEY (teacher_id) REFERENCES teachers(id),
    UNIQUE KEY unique_attendance (student_id, subject_id, attendance_date)
);

-- Extracurricular Activities table
CREATE TABLE extracurricular_activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    activity_name VARCHAR(100) NOT NULL,
    description TEXT,
    activity_date DATE,
    teacher_incharge INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_incharge) REFERENCES teachers(id)
);

-- Student Activities table (many-to-many relationship)
CREATE TABLE student_activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT,
    activity_id INT,
    participation_date DATE,
    performance_rating ENUM('excellent', 'good', 'average', 'poor'),
    remarks TEXT,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (activity_id) REFERENCES extracurricular_activities(id)
);

-- Insert default admin user
INSERT INTO users (username, email, password, user_type) VALUES 
('admin', 'admin@school.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Insert sample subjects
INSERT INTO subjects (subject_code, subject_name, description, credits, class) VALUES
('MATH101', 'Mathematics', 'Basic Mathematics', 3, '10'),
('ENG101', 'English', 'English Language and Literature', 3, '10'),
('SCI101', 'Science', 'General Science', 4, '10'),
('HIST101', 'History', 'World History', 2, '10'),
('GEO101', 'Geography', 'Physical and Human Geography', 2, '10');

-- Insert sample classes
INSERT INTO classes (class_name, section, academic_year) VALUES
('10', 'A', '2024'),
('10', 'B', '2024'),
('11', 'A', '2024'),
('11', 'B', '2024'),
('12', 'A', '2024'),
('12', 'B', '2024');