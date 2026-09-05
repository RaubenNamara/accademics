-- Create timetable table matching backend implementation
-- This table stores teacher and class schedules with conflict detection

CREATE TABLE IF NOT EXISTS timetable (
  id INT AUTO_INCREMENT PRIMARY KEY,
  
  academic_year INT NOT NULL,
  term TINYINT UNSIGNED NOT NULL,
  
  day_of_week VARCHAR(20) NOT NULL, -- Monday, Tuesday, etc.
  period_number TINYINT UNSIGNED NOT NULL, -- 1, 2, 3, etc.
  start_time TIME NULL,
  end_time TIME NULL,
  
  class_id INT NOT NULL,
  stream VARCHAR(50) NULL,
  subject_id INT NOT NULL,
  teacher_id INT NOT NULL,
  
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
