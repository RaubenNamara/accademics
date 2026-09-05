-- School HR and Academic Performance Management System Database Schema

CREATE DATABASE IF NOT EXISTS accademics_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE accademics_db;

-- Users Table (Authentication)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('super_admin', 'admin', 'teacher', 'staff', 'academic_office', 'hr_manager') NOT NULL DEFAULT 'admin',
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Teachers Table
CREATE TABLE teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    contact VARCHAR(50),
    subject VARCHAR(100),
    class VARCHAR(50),
    stream VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Subject Teacher Performance Table
CREATE TABLE subject_teacher_performance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    year YEAR NOT NULL,
    term INT NOT NULL CHECK (term IN (1, 2, 3)),
    subject VARCHAR(100) NOT NULL,
    class VARCHAR(50) NOT NULL,
    stream VARCHAR(50) NOT NULL,
    bot1 DECIMAL(5,2) DEFAULT 0,
    eot1 DECIMAL(5,2) DEFAULT 0,
    tc1 DECIMAL(5,2) DEFAULT 0,
    eot2 DECIMAL(5,2) DEFAULT 0,
    tc2 DECIMAL(5,2) DEFAULT 0,
    eot3 DECIMAL(5,2) DEFAULT 0,
    tc3 DECIMAL(5,2) DEFAULT 0,
    agp DECIMAL(5,2) DEFAULT 0,
    tc1_comment VARCHAR(255) DEFAULT '',
    tc2_comment VARCHAR(255) DEFAULT '',
    tc3_comment VARCHAR(255) DEFAULT '',
    agp_comment VARCHAR(255) DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_teacher_subject_term (teacher_id, year, term, subject, class, stream)
);

-- Lesson Monitoring Table (Weekly tracking Week 1-13)
CREATE TABLE lesson_monitoring (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    year YEAR NOT NULL,
    term INT NOT NULL CHECK (term IN (1, 2, 3)),
    subject VARCHAR(100),
    class VARCHAR(50),
    stream VARCHAR(50),
    week_1 INT DEFAULT 0,
    week_2 INT DEFAULT 0,
    week_3 INT DEFAULT 0,
    week_4 INT DEFAULT 0,
    week_5 INT DEFAULT 0,
    week_6 INT DEFAULT 0,
    week_7 INT DEFAULT 0,
    week_8 INT DEFAULT 0,
    week_9 INT DEFAULT 0,
    week_10 INT DEFAULT 0,
    week_11 INT DEFAULT 0,
    week_12 INT DEFAULT 0,
    week_13 INT DEFAULT 0,
    total_minutes_lost INT DEFAULT 0,
    equivalent_single_lessons DECIMAL(5,2) DEFAULT 0,
    equivalent_double_lessons DECIMAL(5,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_teacher_term (teacher_id, year, term)
);

-- Lesson Observation Table
CREATE TABLE lesson_observations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    year YEAR NOT NULL,
    term INT NOT NULL CHECK (term IN (1, 2, 3)),
    subject VARCHAR(100),
    class VARCHAR(50),
    stream VARCHAR(50),
    -- Round 1
    round_1_score DECIMAL(5,2) DEFAULT 0,
    round_1_area_of_improvement TEXT,
    round_1_agreed_action_points TEXT,
    -- Round 2
    round_2_score DECIMAL(5,2) DEFAULT 0,
    round_2_area_of_improvement TEXT,
    round_2_agreed_action_points TEXT,
    -- Average
    average_score DECIMAL(5,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_teacher_term_obs (teacher_id, year, term, subject, class, stream)
);

-- Teachers Duty Performance Table
CREATE TABLE duty_performance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    year YEAR NOT NULL,
    term INT NOT NULL CHECK (term IN (1, 2, 3)),
    week_number INT DEFAULT 1,
    punctuality DECIMAL(5,2) DEFAULT 0,
    supervision DECIMAL(5,2) DEFAULT 0,
    cleanliness DECIMAL(5,2) DEFAULT 0,
    time_keeping DECIMAL(5,2) DEFAULT 0,
    participation DECIMAL(5,2) DEFAULT 0,
    total_score DECIMAL(5,2) DEFAULT 0,
    percentage DECIMAL(5,2) DEFAULT 0,
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_teacher_term_week_duty (teacher_id, year, term, week_number)
);

-- Teacher of the Week/Month/Year Tracking
CREATE TABLE teacher_awards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    award_type ENUM('week', 'month', 'year') NOT NULL,
    year YEAR NOT NULL,
    term INT,
    week_number INT,
    month_name VARCHAR(20),
    reason TEXT,
    awarded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE
);

-- Class Teacher Performance Table
CREATE TABLE class_teacher_performance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    class VARCHAR(50) NOT NULL,
    stream VARCHAR(50) NOT NULL,
    year YEAR NOT NULL,
    term INT NOT NULL CHECK (term IN (1, 2, 3)),
    -- Weekly tracking
    week_1_roll_call BOOLEAN DEFAULT FALSE,
    week_1_mentorship BOOLEAN DEFAULT FALSE,
    week_1_devotion BOOLEAN DEFAULT FALSE,
    week_1_cleanliness BOOLEAN DEFAULT FALSE,
    week_2_roll_call BOOLEAN DEFAULT FALSE,
    week_2_mentorship BOOLEAN DEFAULT FALSE,
    week_2_devotion BOOLEAN DEFAULT FALSE,
    week_2_cleanliness BOOLEAN DEFAULT FALSE,
    week_3_roll_call BOOLEAN DEFAULT FALSE,
    week_3_mentorship BOOLEAN DEFAULT FALSE,
    week_3_devotion BOOLEAN DEFAULT FALSE,
    week_3_cleanliness BOOLEAN DEFAULT FALSE,
    week_4_roll_call BOOLEAN DEFAULT FALSE,
    week_4_mentorship BOOLEAN DEFAULT FALSE,
    week_4_devotion BOOLEAN DEFAULT FALSE,
    week_4_cleanliness BOOLEAN DEFAULT FALSE,
    week_5_roll_call BOOLEAN DEFAULT FALSE,
    week_5_mentorship BOOLEAN DEFAULT FALSE,
    week_5_devotion BOOLEAN DEFAULT FALSE,
    week_5_cleanliness BOOLEAN DEFAULT FALSE,
    week_6_roll_call BOOLEAN DEFAULT FALSE,
    week_6_mentorship BOOLEAN DEFAULT FALSE,
    week_6_devotion BOOLEAN DEFAULT FALSE,
    week_6_cleanliness BOOLEAN DEFAULT FALSE,
    week_7_roll_call BOOLEAN DEFAULT FALSE,
    week_7_mentorship BOOLEAN DEFAULT FALSE,
    week_7_devotion BOOLEAN DEFAULT FALSE,
    week_7_cleanliness BOOLEAN DEFAULT FALSE,
    week_8_roll_call BOOLEAN DEFAULT FALSE,
    week_8_mentorship BOOLEAN DEFAULT FALSE,
    week_8_devotion BOOLEAN DEFAULT FALSE,
    week_8_cleanliness BOOLEAN DEFAULT FALSE,
    week_9_roll_call BOOLEAN DEFAULT FALSE,
    week_9_mentorship BOOLEAN DEFAULT FALSE,
    week_9_devotion BOOLEAN DEFAULT FALSE,
    week_9_cleanliness BOOLEAN DEFAULT FALSE,
    week_10_roll_call BOOLEAN DEFAULT FALSE,
    week_10_mentorship BOOLEAN DEFAULT FALSE,
    week_10_devotion BOOLEAN DEFAULT FALSE,
    week_10_cleanliness BOOLEAN DEFAULT FALSE,
    week_11_roll_call BOOLEAN DEFAULT FALSE,
    week_11_mentorship BOOLEAN DEFAULT FALSE,
    week_11_devotion BOOLEAN DEFAULT FALSE,
    week_11_cleanliness BOOLEAN DEFAULT FALSE,
    week_12_roll_call BOOLEAN DEFAULT FALSE,
    week_12_mentorship BOOLEAN DEFAULT FALSE,
    week_12_devotion BOOLEAN DEFAULT FALSE,
    week_12_cleanliness BOOLEAN DEFAULT FALSE,
    week_13_roll_call BOOLEAN DEFAULT FALSE,
    week_13_mentorship BOOLEAN DEFAULT FALSE,
    week_13_devotion BOOLEAN DEFAULT FALSE,
    week_13_cleanliness BOOLEAN DEFAULT FALSE,
    parents_contacted BOOLEAN DEFAULT FALSE,
    -- Academic performance
    bt1 DECIMAL(5,2) DEFAULT 0,
    t1 DECIMAL(5,2) DEFAULT 0,
    c1 DECIMAL(5,2) GENERATED ALWAYS AS (t1 - bt1) STORED,
    t2 DECIMAL(5,2) DEFAULT 0,
    c2 DECIMAL(5,2) GENERATED ALWAYS AS (t2 - t1) STORED,
    t3 DECIMAL(5,2) DEFAULT 0,
    c3 DECIMAL(5,2) GENERATED ALWAYS AS (t3 - t2) STORED,
    average_change DECIMAL(5,2) GENERATED ALWAYS AS ((c1 + c2 + c3) / 3) STORED,
    c1_comment VARCHAR(100) GENERATED ALWAYS AS (CASE WHEN c1 < 0 THEN 'Urgent follow-up is required' ELSE 'Teacher is placed on a growth development plan' END) STORED,
    c2_comment VARCHAR(100) GENERATED ALWAYS AS (CASE WHEN c2 < 0 THEN 'Urgent follow-up is required' ELSE 'Teacher is placed on a growth development plan' END) STORED,
    c3_comment VARCHAR(100) GENERATED ALWAYS AS (CASE WHEN c3 < 0 THEN 'Urgent follow-up is required' ELSE 'Teacher is placed on a growth development plan' END) STORED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_class_teacher_term (teacher_id, year, term, class, stream)
);

-- Departments Table
CREATE TABLE departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_name (name)
);

-- Insert default admin user (password: admin123)
INSERT INTO users (full_name, email, password_hash, role) VALUES 
('System Administrator', 'admin@school.com', '$2y$10$QGbfI6pdVccHfEFC0ChexO6tkmy.PN9/MfGc4Is/yQRZzVUmcXreW', 'admin');

-- Insert sample teachers
INSERT INTO teachers (full_name, email, contact, subject, class, stream) VALUES
('John Smith', 'john.smith@school.com', '0712345678', 'Mathematics', 'Form 1', 'A'),
('Sarah Johnson', 'sarah.johnson@school.com', '0723456789', 'English', 'Form 2', 'B'),
('Michael Brown', 'michael.brown@school.com', '0734567890', 'Science', 'Form 3', 'A'),
('Emily Davis', 'emily.davis@school.com', '0745678901', 'History', 'Form 4', 'C');
