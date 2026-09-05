-- Timetable Suite Database Migration
-- This migration adds tables for the comprehensive timetable management system

USE accademics_db;

-- Academic Sessions Table
CREATE TABLE IF NOT EXISTS academic_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_name VARCHAR(100) NOT NULL,
    academic_year YEAR NOT NULL,
    term INT NOT NULL CHECK (term IN (1, 2, 3)),
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_active BOOLEAN DEFAULT FALSE,
    is_archived BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_session (academic_year, term)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bell Schedules Table
CREATE TABLE IF NOT EXISTS bell_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    schedule_name VARCHAR(100) NOT NULL,
    schedule_type ENUM('weekly', 'fortnightly', 'custom_cycle', 'day_rotation') DEFAULT 'weekly',
    cycle_days INT DEFAULT 5,
    is_active BOOLEAN DEFAULT FALSE,
    academic_session_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (academic_session_id) REFERENCES academic_sessions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bell Schedule Periods Table
CREATE TABLE IF NOT EXISTS bell_schedule_periods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bell_schedule_id INT NOT NULL,
    day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
    period_number INT NOT NULL,
    period_name VARCHAR(50),
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    period_type ENUM('lesson', 'break', 'lunch', 'devotion', 'breakfast', 'games', 'mentorship', 'prep', 'supper', 'other') DEFAULT 'lesson',
    is_break BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bell_schedule_id) REFERENCES bell_schedules(id) ON DELETE CASCADE,
    UNIQUE KEY unique_period (bell_schedule_id, day_of_week, period_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rooms Table
CREATE TABLE IF NOT EXISTS rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_code VARCHAR(50) NOT NULL UNIQUE,
    room_name VARCHAR(100) NOT NULL,
    room_type ENUM('classroom', 'laboratory', 'library', 'hall', 'office', 'other') DEFAULT 'classroom',
    capacity INT DEFAULT 40,
    has_projector BOOLEAN DEFAULT FALSE,
    has_computers BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lesson Requirements Table
CREATE TABLE IF NOT EXISTS lesson_requirements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    academic_session_id INT NOT NULL,
    class_id INT NOT NULL,
    subject_id INT NOT NULL,
    teacher_id INT NOT NULL,
    room_id INT,
    periods_per_week INT DEFAULT 1,
    prefer_double_lessons BOOLEAN DEFAULT FALSE,
    require_consecutive BOOLEAN DEFAULT FALSE,
    specific_days JSON,
    specific_periods JSON,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (academic_session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects_new(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE SET NULL,
    UNIQUE KEY unique_requirement (academic_session_id, class_id, subject_id, teacher_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Teacher Availability Table
CREATE TABLE IF NOT EXISTS teacher_availability (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    academic_session_id INT NOT NULL,
    day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
    period_number INT NOT NULL,
    is_available BOOLEAN DEFAULT TRUE,
    reason VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (academic_session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_availability (teacher_id, academic_session_id, day_of_week, period_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Timetable Constraints Table
CREATE TABLE IF NOT EXISTS timetable_constraints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    constraint_type ENUM('no_double_booking', 'max_lessons_per_day', 'min_free_periods', 'double_lessons_allowed', 'subject_sequencing', 'class_balance', 'room_restriction', 'teacher_preference') NOT NULL,
    academic_session_id INT NOT NULL,
    constraint_value JSON,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (academic_session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Timetable Generation Log Table
CREATE TABLE IF NOT EXISTS timetable_generation_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    academic_session_id INT NOT NULL,
    generated_by INT,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    total_entries INT DEFAULT 0,
    conflicts_count INT DEFAULT 0,
    status ENUM('success', 'partial', 'failed') DEFAULT 'success',
    notes TEXT,
    FOREIGN KEY (academic_session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Update existing timetable table to include room_id if not exists
ALTER TABLE timetable 
ADD COLUMN IF NOT EXISTS room_id INT NULL AFTER teacher_id,
ADD COLUMN IF NOT EXISTS academic_session_id INT NULL AFTER term,
ADD FOREIGN KEY IF NOT EXISTS (room_id) REFERENCES rooms(id) ON DELETE SET NULL,
ADD FOREIGN KEY IF NOT EXISTS (academic_session_id) REFERENCES academic_sessions(id) ON DELETE SET NULL;

-- Add index for better performance
CREATE INDEX IF NOT EXISTS idx_timetable_session ON timetable(academic_session_id);
CREATE INDEX IF NOT EXISTS idx_timetable_class ON timetable(class_id);
CREATE INDEX IF NOT EXISTS idx_timetable_teacher ON timetable(teacher_id);
CREATE INDEX IF NOT EXISTS idx_timetable_room ON timetable(room_id);
CREATE INDEX IF NOT EXISTS idx_lesson_requirements_session ON lesson_requirements(academic_session_id);
CREATE INDEX IF NOT EXISTS idx_teacher_availability_session ON teacher_availability(academic_session_id);
CREATE INDEX IF NOT EXISTS idx_constraints_session ON timetable_constraints(academic_session_id);

-- Insert default academic session for current year
INSERT IGNORE INTO academic_sessions (session_name, academic_year, term, start_date, end_date, is_active)
VALUES 
('2025 Term 1', 2025, 1, '2025-02-01', '2025-05-15', TRUE),
('2025 Term 2', 2025, 2, '2025-05-20', '2025-08-30', FALSE),
('2025 Term 3', 2025, 3, '2025-09-05', '2025-12-15', FALSE);

-- Insert default bell schedule
INSERT IGNORE INTO bell_schedules (schedule_name, schedule_type, cycle_days, is_active)
VALUES ('Standard Weekly Schedule', 'weekly', 5, TRUE);

-- Get the bell schedule ID for inserting periods
SET @schedule_id = LAST_INSERT_ID();

-- Insert default periods for the bell schedule
INSERT IGNORE INTO bell_schedule_periods (bell_schedule_id, day_of_week, period_number, period_name, start_time, end_time, period_type, is_break)
VALUES 
(@schedule_id, 'Monday', 1, 'Period 1', '08:00:00', '08:40:00', 'lesson', FALSE),
(@schedule_id, 'Monday', 2, 'Period 2', '08:40:00', '09:20:00', 'lesson', FALSE),
(@schedule_id, 'Monday', 3, 'Break', '09:20:00', '09:40:00', 'break', TRUE),
(@schedule_id, 'Monday', 4, 'Period 3', '09:40:00', '10:20:00', 'lesson', FALSE),
(@schedule_id, 'Monday', 5, 'Period 4', '10:20:00', '11:00:00', 'lesson', FALSE),
(@schedule_id, 'Monday', 6, 'Lunch', '11:00:00', '12:00:00', 'lunch', TRUE),
(@schedule_id, 'Monday', 7, 'Period 5', '12:00:00', '12:40:00', 'lesson', FALSE),
(@schedule_id, 'Monday', 8, 'Period 6', '12:40:00', '13:20:00', 'lesson', FALSE);

-- Insert more days properly
INSERT IGNORE INTO bell_schedule_periods (bell_schedule_id, day_of_week, period_number, period_name, start_time, end_time, period_type, is_break)
VALUES 
(@schedule_id, 'Tuesday', 1, 'Period 1', '08:00:00', '08:40:00', 'lesson', FALSE),
(@schedule_id, 'Tuesday', 2, 'Period 2', '08:40:00', '09:20:00', 'lesson', FALSE),
(@schedule_id, 'Tuesday', 3, 'Break', '09:20:00', '09:40:00', 'break', TRUE),
(@schedule_id, 'Tuesday', 4, 'Period 3', '09:40:00', '10:20:00', 'lesson', FALSE),
(@schedule_id, 'Tuesday', 5, 'Period 4', '10:20:00', '11:00:00', 'lesson', FALSE),
(@schedule_id, 'Tuesday', 6, 'Lunch', '11:00:00', '12:00:00', 'lunch', TRUE),
(@schedule_id, 'Tuesday', 7, 'Period 5', '12:00:00', '12:40:00', 'lesson', FALSE),
(@schedule_id, 'Tuesday', 8, 'Period 6', '12:40:00', '13:20:00', 'lesson', FALSE),
(@schedule_id, 'Wednesday', 1, 'Period 1', '08:00:00', '08:40:00', 'lesson', FALSE),
(@schedule_id, 'Wednesday', 2, 'Period 2', '08:40:00', '09:20:00', 'lesson', FALSE),
(@schedule_id, 'Wednesday', 3, 'Break', '09:20:00', '09:40:00', 'break', TRUE),
(@schedule_id, 'Wednesday', 4, 'Period 3', '09:40:00', '10:20:00', 'lesson', FALSE),
(@schedule_id, 'Wednesday', 5, 'Period 4', '10:20:00', '11:00:00', 'lesson', FALSE),
(@schedule_id, 'Wednesday', 6, 'Lunch', '11:00:00', '12:00:00', 'lunch', TRUE),
(@schedule_id, 'Wednesday', 7, 'Period 5', '12:00:00', '12:40:00', 'lesson', FALSE),
(@schedule_id, 'Wednesday', 8, 'Period 6', '12:40:00', '13:20:00', 'lesson', FALSE),
(@schedule_id, 'Thursday', 1, 'Period 1', '08:00:00', '08:40:00', 'lesson', FALSE),
(@schedule_id, 'Thursday', 2, 'Period 2', '08:40:00', '09:20:00', 'lesson', FALSE),
(@schedule_id, 'Thursday', 3, 'Break', '09:20:00', '09:40:00', 'break', TRUE),
(@schedule_id, 'Thursday', 4, 'Period 3', '09:40:00', '10:20:00', 'lesson', FALSE),
(@schedule_id, 'Thursday', 5, 'Period 4', '10:20:00', '11:00:00', 'lesson', FALSE),
(@schedule_id, 'Thursday', 6, 'Lunch', '11:00:00', '12:00:00', 'lunch', TRUE),
(@schedule_id, 'Thursday', 7, 'Period 5', '12:00:00', '12:40:00', 'lesson', FALSE),
(@schedule_id, 'Thursday', 8, 'Period 6', '12:40:00', '13:20:00', 'lesson', FALSE),
(@schedule_id, 'Friday', 1, 'Period 1', '08:00:00', '08:40:00', 'lesson', FALSE),
(@schedule_id, 'Friday', 2, 'Period 2', '08:40:00', '09:20:00', 'lesson', FALSE),
(@schedule_id, 'Friday', 3, 'Break', '09:20:00', '09:40:00', 'break', TRUE),
(@schedule_id, 'Friday', 4, 'Period 3', '09:40:00', '10:20:00', 'lesson', FALSE),
(@schedule_id, 'Friday', 5, 'Period 4', '10:20:00', '11:00:00', 'lesson', FALSE),
(@schedule_id, 'Friday', 6, 'Lunch', '11:00:00', '12:00:00', 'lunch', TRUE),
(@schedule_id, 'Friday', 7, 'Period 5', '12:00:00', '12:40:00', 'lesson', FALSE),
(@schedule_id, 'Friday', 8, 'Period 6', '12:40:00', '13:20:00', 'lesson', FALSE);

-- Insert default rooms
INSERT IGNORE INTO rooms (room_code, room_name, room_type, capacity, has_projector, has_computers)
VALUES 
('R101', 'Room 101', 'classroom', 40, FALSE, FALSE),
('R102', 'Room 102', 'classroom', 40, TRUE, FALSE),
('R103', 'Room 103', 'classroom', 40, FALSE, FALSE),
('R104', 'Room 104', 'classroom', 40, TRUE, TRUE),
('LAB1', 'Science Lab 1', 'laboratory', 30, TRUE, FALSE),
('LAB2', 'Computer Lab', 'laboratory', 25, TRUE, TRUE),
('LIB', 'Library', 'library', 50, FALSE, FALSE),
('HALL', 'Main Hall', 'hall', 200, TRUE, FALSE);

-- Insert default constraints
INSERT IGNORE INTO timetable_constraints (constraint_type, academic_session_id, constraint_value, is_active)
VALUES 
('no_double_booking', 1, '{"enabled": true}', TRUE),
('max_lessons_per_day', 1, '{"max_lessons": 6}', TRUE),
('min_free_periods', 1, '{"min_periods": 1}', TRUE),
('double_lessons_allowed', 1, '{"allowed": true, "max_per_day": 2}', TRUE);

-- School Events Table
CREATE TABLE IF NOT EXISTS school_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_name VARCHAR(100) NOT NULL UNIQUE,
    event_type ENUM('devotion', 'assembly', 'break', 'lunch', 'games', 'clubs', 'mentorship', 'prep', 'other') NOT NULL,
    event_color VARCHAR(7) DEFAULT '#6B7280',
    duration_minutes INT DEFAULT 30,
    is_schedulable BOOLEAN DEFAULT TRUE,
    is_mandatory BOOLEAN DEFAULT FALSE,
    description TEXT,
    default_day ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday') DEFAULT NULL,
    default_period INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add columns if table already exists (for re-running migration)
ALTER TABLE school_events 
ADD COLUMN IF NOT EXISTS is_schedulable BOOLEAN DEFAULT TRUE,
ADD COLUMN IF NOT EXISTS is_mandatory BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS default_day ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday') DEFAULT NULL,
ADD COLUMN IF NOT EXISTS default_period INT DEFAULT NULL;

-- Insert default school events
INSERT IGNORE INTO school_events (event_name, event_type, event_color, duration_minutes, is_schedulable, is_mandatory, default_day, default_period)
VALUES 
('Devotion', 'devotion', '#3B82F6', 30, TRUE, TRUE, 'Monday', 1),
('Assembly', 'assembly', '#8B5CF6', 45, TRUE, TRUE, 'Monday', 2),
('Breakfast', 'break', '#10B981', 30, FALSE, FALSE, NULL, NULL),
('Break', 'break', '#F59E0B', 20, TRUE, FALSE, NULL, NULL),
('Lunch', 'lunch', '#EF4444', 60, TRUE, FALSE, NULL, NULL),
('Mentorship', 'mentorship', '#06B6D4', 40, TRUE, FALSE, NULL, NULL),
('Games', 'games', '#84CC16', 60, TRUE, FALSE, 'Friday', 7),
('Clubs', 'clubs', '#EC4899', 60, TRUE, FALSE, 'Friday', 8),
('Prep', 'prep', '#6366F1', 60, FALSE, FALSE, NULL, NULL),
('Supper', 'break', '#F97316', 30, FALSE, FALSE, NULL, NULL);
