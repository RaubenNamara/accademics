-- Student Section Database Migration
-- This migration adds tables for student management, parents, subjects, and academic results

USE accademics_db;

-- Students Table
CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admission_number VARCHAR(50) UNIQUE NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    gender ENUM('male', 'female', 'other') NOT NULL,
    date_of_birth DATE NOT NULL,
    class VARCHAR(50) NOT NULL,
    stream VARCHAR(50) NOT NULL,
    level ENUM('O_LEVEL', 'A_LEVEL') NOT NULL,
    enrollment_date DATE DEFAULT (CURRENT_DATE),
    behaviour_notes TEXT,
    medical_notes TEXT,
    special_needs TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_admission (admission_number),
    INDEX idx_class_stream (class, stream),
    INDEX idx_level (level)
);

-- Parents/Guardians Table
CREATE TABLE IF NOT EXISTS parents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    relationship ENUM('father', 'mother', 'guardian', 'other') NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(255),
    address TEXT,
    is_primary_contact BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    INDEX idx_student (student_id)
);

-- Subjects Table
CREATE TABLE IF NOT EXISTS subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_code VARCHAR(20) UNIQUE NOT NULL,
    subject_name VARCHAR(255) NOT NULL,
    level ENUM('O_LEVEL', 'A_LEVEL', 'BOTH') NOT NULL DEFAULT 'BOTH',
    is_compulsory BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_level (level),
    INDEX idx_code (subject_code)
);

-- Student Academic Results Table
CREATE TABLE IF NOT EXISTS student_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    subject_id INT NOT NULL,
    year YEAR NOT NULL,
    term INT NOT NULL CHECK (term IN (1, 2, 3)),
    exam_type ENUM('BOT1', 'EOT1', 'BOT2', 'EOT2', 'BOT3', 'EOT3', 'FINAL') NOT NULL,
    marks DECIMAL(5,2) NOT NULL,
    grade VARCHAR(5),
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_subject_exam (student_id, subject_id, year, term, exam_type),
    INDEX idx_student (student_id),
    INDEX idx_subject (subject_id),
    INDEX idx_year_term (year, term)
);

-- Insert default subjects for O Level
INSERT INTO subjects (subject_code, subject_name, level, is_compulsory) VALUES
('MATH', 'Mathematics', 'O_LEVEL', TRUE),
('ENG', 'English Language', 'O_LEVEL', TRUE),
('PHY', 'Physics', 'O_LEVEL', FALSE),
('CHE', 'Chemistry', 'O_LEVEL', FALSE),
('BIO', 'Biology', 'O_LEVEL', FALSE),
('GEO', 'Geography', 'O_LEVEL', FALSE),
('HIST', 'History', 'O_LEVEL', FALSE),
('CRE', 'Christian Religious Education', 'O_LEVEL', FALSE),
('IRE', 'Islamic Religious Education', 'O_LEVEL', FALSE),
('LIT', 'Literature in English', 'O_LEVEL', FALSE),
('KIS', 'Kiswahili', 'O_LEVEL', FALSE),
('FREN', 'French', 'O_LEVEL', FALSE),
('GER', 'German', 'O_LEVEL', FALSE),
('AGRI', 'Agriculture', 'O_LEVEL', FALSE),
('COM', 'Commerce', 'O_LEVEL', FALSE),
('ACC', 'Accounting', 'O_LEVEL', FALSE),
('ENT', 'Entrepreneurship', 'O_LEVEL', FALSE),
('ART', 'Fine Art', 'O_LEVEL', FALSE),
('MUS', 'Music', 'O_LEVEL', FALSE),
('PE', 'Physical Education', 'O_LEVEL', FALSE),
('ICT', 'Information and Communication Technology', 'O_LEVEL', FALSE);

-- Insert default subjects for A Level
INSERT INTO subjects (subject_code, subject_name, level, is_compulsory) VALUES
('SUB_MATH', 'Mathematics', 'A_LEVEL', FALSE),
('SUB_PHY', 'Physics', 'A_LEVEL', FALSE),
('SUB_CHE', 'Chemistry', 'A_LEVEL', FALSE),
('SUB_BIO', 'Biology', 'A_LEVEL', FALSE),
('SUB_GEO', 'Geography', 'A_LEVEL', FALSE),
('SUB_HIST', 'History', 'A_LEVEL', FALSE),
('SUB_ECON', 'Economics', 'A_LEVEL', FALSE),
('SUB_ENT', 'Entrepreneurship', 'A_LEVEL', FALSE),
('SUB_LIT', 'Literature', 'A_LEVEL', FALSE),
('SUB_KIS', 'Kiswahili', 'A_LEVEL', FALSE),
('SUB_FREN', 'French', 'A_LEVEL', FALSE),
('SUB_ART', 'Fine Art', 'A_LEVEL', FALSE),
('SUB_DIV', 'Divinity', 'A_LEVEL', FALSE),
('SUB_IRE', 'Islamic Religious Education', 'A_LEVEL', FALSE),
('SUB_COM', 'Commerce', 'A_LEVEL', FALSE),
('SUB_ACC', 'Principles of Accounts', 'A_LEVEL', FALSE),
('SUB_GOV', 'Government', 'A_LEVEL', FALSE),
('SUB_PSO', 'Political Science', 'A_LEVEL', FALSE);
