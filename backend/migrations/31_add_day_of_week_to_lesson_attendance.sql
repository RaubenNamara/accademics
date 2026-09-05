-- Add day_of_week field to teacher_lesson_attendance table
ALTER TABLE teacher_lesson_attendance
ADD COLUMN day_of_week VARCHAR(3) DEFAULT NULL AFTER attendance_date;

-- Create index on day_of_week for better query performance
CREATE INDEX idx_day_of_week ON teacher_lesson_attendance(day_of_week);

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
