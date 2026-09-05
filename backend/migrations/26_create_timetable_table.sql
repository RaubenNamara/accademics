-- Create timetable table matching backend implementation
-- This table stores teacher and class schedules with conflict detection

CREATE TABLE IF NOT EXISTS timetable (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  
  academic_year INT NOT NULL,
  term TINYINT UNSIGNED NOT NULL,
  
  day_of_week VARCHAR(20) NOT NULL, -- Monday, Tuesday, etc.
  period_number TINYINT UNSIGNED NOT NULL, -- 1, 2, 3, etc.
  start_time TIME NULL,
  end_time TIME NULL,
  
  class_id INT UNSIGNED NOT NULL,
  stream VARCHAR(50) NULL,
  subject_id INT UNSIGNED NOT NULL,
  teacher_id INT UNSIGNED NOT NULL,
  
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  CONSTRAINT fk_timetable_class
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
  CONSTRAINT fk_timetable_subject
    FOREIGN KEY (subject_id) REFERENCES subjects_new(id) ON DELETE CASCADE,
  CONSTRAINT fk_timetable_teacher
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
  
  -- Prevent duplicate slots for same teacher in same period
  UNIQUE KEY unique_teacher_period (academic_year, term, day_of_week, period_number, teacher_id),
  -- Prevent duplicate slots for same class in same period
  UNIQUE KEY unique_class_period (academic_year, term, day_of_week, period_number, class_id, stream),
  
  INDEX idx_timetable_year_term (academic_year, term),
  INDEX idx_timetable_teacher (teacher_id, academic_year, term),
  INDEX idx_timetable_class (class_id, academic_year, term)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Optional: Create timetable_periods table for configurable period times
CREATE TABLE IF NOT EXISTS timetable_periods (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  period_number TINYINT UNSIGNED NOT NULL UNIQUE,
  period_name VARCHAR(50) NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default periods (8 periods per day)
INSERT INTO timetable_periods (period_number, period_name, start_time, end_time) VALUES
(1, 'Period 1', '08:00:00', '08:40:00'),
(2, 'Period 2', '08:40:00', '09:20:00'),
(3, 'Period 3', '09:20:00', '10:00:00'),
(4, 'Period 4', '10:00:00', '10:40:00'),
(5, 'Period 5', '10:40:00', '11:20:00'),
(6, 'Period 6', '11:20:00', '12:00:00'),
(7, 'Period 7', '12:00:00', '12:40:00'),
(8, 'Period 8', '12:40:00', '13:20:00')
ON DUPLICATE KEY UPDATE period_name=VALUES(period_name), start_time=VALUES(start_time), end_time=VALUES(end_time);
