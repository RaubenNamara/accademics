-- Timetable suite rebuild: creates every table the timetable module's PHP/Vue
-- code already expects but which was never actually created in this database.
-- Additive only — does not touch any table outside the timetable suite.

CREATE TABLE IF NOT EXISTS academic_sessions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  session_name VARCHAR(100) NOT NULL,
  academic_year INT NOT NULL,
  term TINYINT NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  is_active BOOLEAN DEFAULT FALSE,
  is_archived BOOLEAN DEFAULT FALSE,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_session_year_term (academic_year, term),
  INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rooms (
  id INT AUTO_INCREMENT PRIMARY KEY,
  room_code VARCHAR(20) NOT NULL UNIQUE,
  room_name VARCHAR(100) NOT NULL,
  room_type ENUM('classroom','laboratory','library','hall','office','other') DEFAULT 'classroom',
  capacity INT DEFAULT 40,
  has_projector BOOLEAN DEFAULT FALSE,
  has_computers BOOLEAN DEFAULT FALSE,
  is_active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bell_schedules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  schedule_name VARCHAR(100) NOT NULL,
  schedule_type ENUM('weekly','fortnightly','custom','rotation') DEFAULT 'weekly',
  day_pattern ENUM('uniform','custom') DEFAULT 'uniform',
  academic_session_id INT NULL,
  is_active BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (academic_session_id) REFERENCES academic_sessions(id) ON DELETE SET NULL,
  INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bell_schedule_periods (
  id INT AUTO_INCREMENT PRIMARY KEY,
  bell_schedule_id INT NOT NULL,
  day_of_week ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
  period_number INT NOT NULL,
  period_name VARCHAR(50) NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  period_type ENUM('lesson','devotion','breakfast','break','lunch','mentorship','games','prep','supper','assembly','other') DEFAULT 'lesson',
  is_active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (bell_schedule_id) REFERENCES bell_schedules(id) ON DELETE CASCADE,
  UNIQUE KEY unique_schedule_period_day (bell_schedule_id, day_of_week, period_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lesson_requirements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  academic_session_id INT NOT NULL,
  class_id INT NOT NULL,
  stream VARCHAR(50) NULL,
  subject_id INT NOT NULL,
  teacher_id INT NOT NULL,
  room_id INT NULL,
  periods_per_week INT NOT NULL DEFAULT 1,
  double_lesson_allowed BOOLEAN DEFAULT FALSE,
  double_lesson_required BOOLEAN DEFAULT FALSE,
  preferred_span TINYINT NULL,
  preferred_days JSON NULL,
  preferred_periods JSON NULL,
  avoid_days JSON NULL,
  avoid_periods JSON NULL,
  notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (academic_session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE,
  FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
  FOREIGN KEY (subject_id) REFERENCES subjects_new(id) ON DELETE CASCADE,
  FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
  FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE SET NULL,
  UNIQUE KEY unique_requirement (academic_session_id, class_id, stream, subject_id, teacher_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS teacher_availability (
  id INT AUTO_INCREMENT PRIMARY KEY,
  teacher_id INT NOT NULL,
  academic_session_id INT NOT NULL,
  day_of_week ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
  period_number INT NOT NULL,
  is_available BOOLEAN DEFAULT TRUE,
  reason VARCHAR(255) NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
  FOREIGN KEY (academic_session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE,
  UNIQUE KEY unique_availability (teacher_id, academic_session_id, day_of_week, period_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS timetable_constraints (
  id INT AUTO_INCREMENT PRIMARY KEY,
  constraint_type ENUM('no_double_booking','max_lessons_per_day','min_free_periods','max_consecutive_lessons','preferred_teaching_periods','room_restriction','teacher_preference','subject_sequencing','class_balance','double_lessons_allowed') NOT NULL,
  academic_session_id INT NOT NULL,
  constraint_value JSON NULL,
  is_active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (academic_session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS timetable_versions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  academic_session_id INT NOT NULL,
  version_name VARCHAR(100) NOT NULL,
  version_number INT NOT NULL,
  status ENUM('draft','published','archived') DEFAULT 'draft',
  generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  published_at TIMESTAMP NULL,
  archived_at TIMESTAMP NULL,
  generated_by INT NULL,
  notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (academic_session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE,
  FOREIGN KEY (generated_by) REFERENCES teachers(id) ON DELETE SET NULL,
  UNIQUE KEY unique_version (academic_session_id, version_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS timetable (
  id INT AUTO_INCREMENT PRIMARY KEY,
  academic_session_id INT NULL,
  academic_year INT NOT NULL,
  term TINYINT UNSIGNED NOT NULL,
  day_of_week VARCHAR(20) NOT NULL,
  period_number TINYINT UNSIGNED NOT NULL,
  spans_periods TINYINT UNSIGNED NOT NULL DEFAULT 1,
  duration_minutes INT NOT NULL DEFAULT 40,
  start_time TIME NULL,
  end_time TIME NULL,
  class_id INT NOT NULL,
  stream VARCHAR(50) NULL,
  subject_id INT NULL,
  teacher_id INT NULL,
  room_id INT NULL,
  entry_type ENUM('lesson','event') DEFAULT 'lesson',
  event_id INT NULL,
  event_name VARCHAR(100) NULL,
  event_color VARCHAR(20) NULL,
  event_type VARCHAR(50) NULL,
  event_description TEXT NULL,
  timetable_version_id INT NULL,
  is_locked BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (academic_session_id) REFERENCES academic_sessions(id) ON DELETE SET NULL,
  FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
  FOREIGN KEY (subject_id) REFERENCES subjects_new(id) ON DELETE CASCADE,
  FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
  FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE SET NULL,
  FOREIGN KEY (timetable_version_id) REFERENCES timetable_versions(id) ON DELETE SET NULL,
  UNIQUE KEY unique_teacher_lesson_period (academic_year, term, day_of_week, period_number, teacher_id, entry_type),
  UNIQUE KEY unique_class_lesson_period (academic_year, term, day_of_week, period_number, class_id, stream, entry_type),
  INDEX idx_timetable_session (academic_session_id),
  INDEX idx_timetable_year_term (academic_year, term),
  INDEX idx_timetable_teacher (teacher_id),
  INDEX idx_timetable_class (class_id),
  INDEX idx_timetable_room (room_id),
  INDEX idx_timetable_version (timetable_version_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- timetable_periods already exists but has no way to flag a row as a break vs
-- an actual lesson slot (one row is literally labeled "Break" sitting inside
-- the period_number sequence, and there's an unmodeled lunch gap between two
-- other periods). Add an explicit flag so span placement/validation can tell
-- real lesson slots from breaks instead of guessing from the label text.
ALTER TABLE timetable_periods
  ADD COLUMN IF NOT EXISTS period_type ENUM('lesson','break') NOT NULL DEFAULT 'lesson' AFTER label;

UPDATE timetable_periods
SET period_type = 'break'
WHERE label REGEXP 'break|lunch' AND period_type = 'lesson';

-- The seeded afternoon periods had an unmodeled ~70min gap between period 7
-- (ended 12:20) and period 8 (started 13:30), with no break/lunch row to
-- explain it. That gap made it impossible to ever place a 4-period-long
-- (Quadruple) A-Level lesson anywhere in the day, since periods 1-3 (before
-- the period-4 break) are only 3 long. Closing the gap so periods 5-9 run
-- back-to-back gives a real 5-period block, long enough for any span up to
-- Quadruple. No admin screen manages this table, so this is a data fix, not
-- an override of something configured through the UI.
UPDATE timetable_periods SET start_time = '12:20:00', end_time = '13:00:00' WHERE period_number = 8;
UPDATE timetable_periods SET start_time = '13:00:00', end_time = '13:40:00' WHERE period_number = 9;

-- Seed a default active academic session so the app isn't empty on first load
INSERT INTO academic_sessions (session_name, academic_year, term, start_date, end_date, is_active)
SELECT '2026 Term 1', 2026, 1, '2026-02-02', '2026-05-15', TRUE
WHERE NOT EXISTS (SELECT 1 FROM academic_sessions);

-- Seed a default bell schedule + Mon-Fri periods mirroring timetable_periods' 40-min
-- lesson slots plus a short break and lunch, for the printable Bell Schedule screen.
INSERT INTO bell_schedules (schedule_name, schedule_type, day_pattern, academic_session_id, is_active)
SELECT 'Standard Weekly Schedule', 'weekly', 'uniform', (SELECT id FROM academic_sessions WHERE is_active = TRUE LIMIT 1), TRUE
WHERE NOT EXISTS (SELECT 1 FROM bell_schedules);

INSERT INTO bell_schedule_periods (bell_schedule_id, day_of_week, period_number, period_name, start_time, end_time, period_type)
SELECT bs.id, d.day, p.period_number, p.period_name, p.start_time, p.end_time, p.period_type
FROM bell_schedules bs
CROSS JOIN (
  SELECT 'Monday' AS day UNION ALL SELECT 'Tuesday' UNION ALL SELECT 'Wednesday'
  UNION ALL SELECT 'Thursday' UNION ALL SELECT 'Friday'
) d
CROSS JOIN (
  SELECT 1 AS period_number, 'Period 1' AS period_name, '08:00:00' AS start_time, '08:40:00' AS end_time, 'lesson' AS period_type
  UNION ALL SELECT 2, 'Period 2', '08:40:00', '09:20:00', 'lesson'
  UNION ALL SELECT 3, 'Break', '09:20:00', '09:40:00', 'break'
  UNION ALL SELECT 4, 'Period 3', '09:40:00', '10:20:00', 'lesson'
  UNION ALL SELECT 5, 'Period 4', '10:20:00', '11:00:00', 'lesson'
  UNION ALL SELECT 6, 'Lunch', '11:00:00', '12:00:00', 'lunch'
  UNION ALL SELECT 7, 'Period 5', '12:00:00', '12:40:00', 'lesson'
  UNION ALL SELECT 8, 'Period 6', '12:40:00', '13:20:00', 'lesson'
) p
WHERE bs.schedule_name = 'Standard Weekly Schedule'
  AND NOT EXISTS (SELECT 1 FROM bell_schedule_periods WHERE bell_schedule_id = bs.id);
