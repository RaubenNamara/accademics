-- Fix teacher_lesson_attendance table schema to match API expectations
-- This migration renames columns and adds missing fields

-- Rename lesson_date to attendance_date
ALTER TABLE teacher_lesson_attendance CHANGE COLUMN lesson_date attendance_date DATE NOT NULL;

-- Rename class_name to class
ALTER TABLE teacher_lesson_attendance CHANGE COLUMN class_name class VARCHAR(100) NULL;

-- Add missing columns
ALTER TABLE teacher_lesson_attendance
ADD COLUMN day_of_week VARCHAR(3) DEFAULT NULL AFTER attendance_date,
ADD COLUMN expected_minutes INT DEFAULT 0 AFTER time_out,
ADD COLUMN actual_minutes INT DEFAULT 0 AFTER expected_minutes,
ADD COLUMN minutes_lost INT DEFAULT 0 AFTER actual_minutes;

-- Update existing records to populate day_of_week based on attendance_date
UPDATE teacher_lesson_attendance
SET day_of_week = CASE DAYOFWEEK(attendance_date)
    WHEN 1 THEN 'Sun'
    WHEN 2 THEN 'Mon'
    WHEN 3 THEN 'Tue'
    WHEN 4 THEN 'Wed'
    WHEN 5 THEN 'Thu'
    WHEN 6 THEN 'Fri'
    WHEN 7 THEN 'Sat'
END
WHERE day_of_week IS NULL;

-- Create index on day_of_week for better query performance
CREATE INDEX idx_day_of_week ON teacher_lesson_attendance(day_of_week);

-- Add indexes for better query performance
CREATE INDEX idx_year_term ON teacher_lesson_attendance(year, term);
CREATE INDEX idx_week_number ON teacher_lesson_attendance(week_number);
