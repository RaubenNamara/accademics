-- Add total_minutes_lost column to lesson_monitoring table
ALTER TABLE lesson_monitoring
ADD COLUMN total_minutes_lost INT DEFAULT 0 AFTER double_lessons_lost;
