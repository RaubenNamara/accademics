-- Enhanced Timetable Management System Schema
-- This migration creates all tables needed for a professional timetable platform

-- Academic Sessions Table
CREATE TABLE IF NOT EXISTS academic_sessions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  session_name VARCHAR(100) NOT NULL,
  academic_year INT NOT NULL,
  term TINYINT NOT NULL CHECK (term IN (1, 2, 3)),
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  is_active BOOLEAN DEFAULT FALSE,
  is_archived BOOLEAN DEFAULT FALSE,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_session_year_term (session_name, academic_year, term),
  INDEX idx_active (is_active),
  INDEX idx_year (academic_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bell Schedules Table
CREATE TABLE IF NOT EXISTS bell_schedules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  schedule_name VARCHAR(100) NOT NULL,
  schedule_type ENUM('weekly', 'fortnightly', 'custom', 'rotation') DEFAULT 'weekly',
  day_pattern ENUM('uniform', 'custom') DEFAULT 'uniform',
  academic_session_id INT,
  is_active BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (academic_session_id) REFERENCES academic_sessions(id) ON DELETE SET NULL,
  INDEX idx_active (is_active),
  INDEX idx_session (academic_session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bell Schedule Periods Table
CREATE TABLE IF NOT EXISTS bell_schedule_periods (
  id INT AUTO_INCREMENT PRIMARY KEY,
  bell_schedule_id INT NOT NULL,
  period_number TINYINT NOT NULL,
  period_name VARCHAR(50) NOT NULL,
  day_of_week VARCHAR(20) DEFAULT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  period_type ENUM('lesson', 'devotion', 'breakfast', 'break', 'lunch', 'mentorship', 'games', 'prep', 'supper', 'assembly', 'other') DEFAULT 'lesson',
  is_active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (bell_schedule_id) REFERENCES bell_schedules(id) ON DELETE CASCADE,
  UNIQUE KEY unique_schedule_period_day (bell_schedule_id, period_number, day_of_week),
  INDEX idx_schedule (bell_schedule_id),
  INDEX idx_day (day_of_week)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Lesson Requirements Table (Enhanced)
CREATE TABLE IF NOT EXISTS lesson_requirements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  academic_session_id INT NOT NULL,
  class_id INT NOT NULL,
  stream VARCHAR(50),
  subject_id INT NOT NULL,
  teacher_id INT NOT NULL,
  room_id INT,
  periods_per_week INT NOT NULL DEFAULT 1,
  double_lesson_allowed BOOLEAN DEFAULT FALSE,
  double_lesson_required BOOLEAN DEFAULT FALSE,
  preferred_days JSON,
  preferred_periods JSON,
  avoid_days JSON,
  avoid_periods JSON,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (academic_session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE,
  FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
  FOREIGN KEY (subject_id) REFERENCES subjects_new(id) ON DELETE CASCADE,
  FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
  FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE SET NULL,
  UNIQUE KEY unique_requirement (academic_session_id, class_id, stream, subject_id, teacher_id),
  INDEX idx_session (academic_session_id),
  INDEX idx_class (class_id),
  INDEX idx_teacher (teacher_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Teacher Availability Table
CREATE TABLE IF NOT EXISTS teacher_availability (
  id INT AUTO_INCREMENT PRIMARY KEY,
  teacher_id INT NOT NULL,
  academic_session_id INT NOT NULL,
  day_of_week VARCHAR(20) NOT NULL,
  period_number TINYINT NOT NULL,
  is_available BOOLEAN DEFAULT TRUE,
  reason VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
  FOREIGN KEY (academic_session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE,
  UNIQUE KEY unique_availability (teacher_id, academic_session_id, day_of_week, period_number),
  INDEX idx_teacher (teacher_id),
  INDEX idx_session (academic_session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Timetable Constraints Table
CREATE TABLE IF NOT EXISTS timetable_constraints (
  id INT AUTO_INCREMENT PRIMARY KEY,
  constraint_type ENUM(
    'no_double_booking',
    'max_lessons_per_day',
    'min_free_periods',
    'max_consecutive_lessons',
    'preferred_teaching_periods',
    'room_restriction',
    'teacher_preference',
    'subject_sequencing',
    'class_balance',
    'double_lessons_allowed'
  ) NOT NULL,
  academic_session_id INT NOT NULL,
  constraint_value JSON,
  is_active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (academic_session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE,
  UNIQUE KEY unique_constraint (constraint_type, academic_session_id),
  INDEX idx_session (academic_session_id),
  INDEX idx_type (constraint_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Timetable Versions Table
CREATE TABLE IF NOT EXISTS timetable_versions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  academic_session_id INT NOT NULL,
  version_name VARCHAR(100) NOT NULL,
  version_number INT NOT NULL,
  status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
  generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  published_at TIMESTAMP NULL,
  archived_at TIMESTAMP NULL,
  generated_by INT,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (academic_session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE,
  FOREIGN KEY (generated_by) REFERENCES teachers(id) ON DELETE SET NULL,
  UNIQUE KEY unique_version (academic_session_id, version_number),
  INDEX idx_session (academic_session_id),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Update timetable table to support events and versioning
ALTER TABLE timetable 
ADD COLUMN IF NOT EXISTS entry_type ENUM('lesson', 'event') DEFAULT 'lesson',
ADD COLUMN IF NOT EXISTS event_id INT NULL,
ADD COLUMN IF NOT EXISTS event_name VARCHAR(100) NULL,
ADD COLUMN IF NOT EXISTS event_color VARCHAR(20) NULL,
ADD COLUMN IF NOT EXISTS event_type VARCHAR(50) NULL,
ADD COLUMN IF NOT EXISTS event_description TEXT NULL,
ADD COLUMN IF NOT EXISTS duration_minutes INT DEFAULT 40,
ADD COLUMN IF NOT EXISTS spans_periods TINYINT DEFAULT 1,
ADD COLUMN IF NOT EXISTS timetable_version_id INT NULL,
ADD COLUMN IF NOT EXISTS is_locked BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS room_id INT NULL AFTER teacher_id,
ADD INDEX IF NOT EXISTS idx_event (event_id),
ADD INDEX IF NOT EXISTS idx_version (timetable_version_id),
ADD INDEX IF NOT EXISTS idx_room (room_id);

ALTER TABLE timetable
ADD FOREIGN KEY IF NOT EXISTS (event_id) REFERENCES school_events(id) ON DELETE SET NULL,
ADD FOREIGN KEY IF NOT EXISTS (timetable_version_id) REFERENCES timetable_versions(id) ON DELETE SET NULL,
ADD FOREIGN KEY IF NOT EXISTS (room_id) REFERENCES rooms(id) ON DELETE SET NULL;

-- Drop old unique constraints to allow events
ALTER TABLE timetable DROP INDEX IF EXISTS unique_teacher_period;
ALTER TABLE timetable DROP INDEX IF EXISTS unique_class_period;

-- Add new constraints that allow events
ALTER TABLE timetable 
ADD CONSTRAINT unique_teacher_lesson_period UNIQUE (academic_year, term, day_of_week, period_number, teacher_id, entry_type),
ADD CONSTRAINT unique_class_lesson_period UNIQUE (academic_year, term, day_of_week, period_number, class_id, stream, entry_type);

-- Insert default academic session
INSERT INTO academic_sessions (session_name, academic_year, term, start_date, end_date, is_active)
VALUES ('2025 Term 1', 2025, 1, '2025-01-06', '2025-04-04', TRUE)
ON DUPLICATE KEY UPDATE session_name=VALUES(session_name);

-- Insert default bell schedule
INSERT INTO bell_schedules (schedule_name, schedule_type, day_pattern, is_active)
VALUES ('Standard Weekly Schedule', 'weekly', 'uniform', TRUE)
ON DUPLICATE KEY UPDATE schedule_name=VALUES(schedule_name);

-- Get the bell schedule ID
SET @schedule_id = LAST_INSERT_ID();

-- Insert default periods for all days
INSERT INTO bell_schedule_periods (bell_schedule_id, period_number, period_name, day_of_week, start_time, end_time, period_type) VALUES
(@schedule_id, 1, 'Devotion', 'Monday', '07:00:00', '07:30:00', 'devotion'),
(@schedule_id, 2, 'Period 1', 'Monday', '07:30:00', '08:20:00', 'lesson'),
(@schedule_id, 3, 'Period 2', 'Monday', '08:20:00', '09:10:00', 'lesson'),
(@schedule_id, 4, 'Break', 'Monday', '09:10:00', '09:30:00', 'break'),
(@schedule_id, 5, 'Period 3', 'Monday', '09:30:00', '10:20:00', 'lesson'),
(@schedule_id, 6, 'Period 4', 'Monday', '10:20:00', '11:10:00', 'lesson'),
(@schedule_id, 7, 'Period 5', 'Monday', '11:10:00', '12:00:00', 'lesson'),
(@schedule_id, 8, 'Lunch', 'Monday', '12:00:00', '13:00:00', 'lunch'),
(@schedule_id, 9, 'Period 6', 'Monday', '13:00:00', '13:50:00', 'lesson'),
(@schedule_id, 10, 'Period 7', 'Monday', '13:50:00', '14:40:00', 'lesson'),
(@schedule_id, 11, 'Period 8', 'Monday', '14:40:00', '15:30:00', 'lesson'),
(@schedule_id, 12, 'Games', 'Monday', '15:30:00', '16:30:00', 'games')
ON DUPLICATE KEY UPDATE start_time=VALUES(start_time), end_time=VALUES(end_time);

-- Copy for other days (uniform schedule)
INSERT INTO bell_schedule_periods (bell_schedule_id, period_number, period_name, day_of_week, start_time, end_time, period_type)
SELECT bell_schedule_id, period_number, period_name, 'Tuesday', start_time, end_time, period_type
FROM bell_schedule_periods WHERE day_of_week = 'Monday'
ON DUPLICATE KEY UPDATE start_time=VALUES(start_time), end_time=VALUES(end_time);

INSERT INTO bell_schedule_periods (bell_schedule_id, period_number, period_name, day_of_week, start_time, end_time, period_type)
SELECT bell_schedule_id, period_number, period_name, 'Wednesday', start_time, end_time, period_type
FROM bell_schedule_periods WHERE day_of_week = 'Monday'
ON DUPLICATE KEY UPDATE start_time=VALUES(start_time), end_time=VALUES(end_time);

INSERT INTO bell_schedule_periods (bell_schedule_id, period_number, period_name, day_of_week, start_time, end_time, period_type)
SELECT bell_schedule_id, period_number, period_name, 'Thursday', start_time, end_time, period_type
FROM bell_schedule_periods WHERE day_of_week = 'Monday'
ON DUPLICATE KEY UPDATE start_time=VALUES(start_time), end_time=VALUES(end_time);

INSERT INTO bell_schedule_periods (bell_schedule_id, period_number, period_name, day_of_week, start_time, end_time, period_type)
SELECT bell_schedule_id, period_number, period_name, 'Friday', start_time, end_time, period_type
FROM bell_schedule_periods WHERE day_of_week = 'Monday'
ON DUPLICATE KEY UPDATE start_time=VALUES(start_time), end_time=VALUES(end_time);

-- Insert default constraints
INSERT INTO timetable_constraints (constraint_type, academic_session_id, constraint_value, is_active)
SELECT 'no_double_booking', id, '{"enabled": true}', TRUE
FROM academic_sessions WHERE is_active = TRUE
ON DUPLICATE KEY UPDATE constraint_value=VALUES(constraint_value);

INSERT INTO timetable_constraints (constraint_type, academic_session_id, constraint_value, is_active)
SELECT 'max_lessons_per_day', id, '{"max": 6}', TRUE
FROM academic_sessions WHERE is_active = TRUE
ON DUPLICATE KEY UPDATE constraint_value=VALUES(constraint_value);

INSERT INTO timetable_constraints (constraint_type, academic_session_id, constraint_value, is_active)
SELECT 'min_free_periods', id, '{"min": 1}', TRUE
FROM academic_sessions WHERE is_active = TRUE
ON DUPLICATE KEY UPDATE constraint_value=VALUES(constraint_value);
