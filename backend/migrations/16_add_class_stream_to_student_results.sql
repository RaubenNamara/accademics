-- Add class and stream columns to student_results table
-- This allows storing the class and stream at the result level, which may differ from the student's current class/stream

USE accademics_db;

ALTER TABLE student_results 
ADD COLUMN class VARCHAR(50) AFTER subject_id,
ADD COLUMN stream VARCHAR(50) AFTER class,
ADD INDEX idx_class_stream (class, stream);
