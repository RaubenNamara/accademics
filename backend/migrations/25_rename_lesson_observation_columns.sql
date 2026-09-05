-- Migration: Rename Lesson Observation Columns
-- This migration renames the old columns to match the new simplified structure

-- Rename subject column to subject_id
ALTER TABLE lesson_observations CHANGE COLUMN subject subject_id INT DEFAULT NULL COMMENT 'Subject ID';

-- Rename class column to class_id  
ALTER TABLE lesson_observations CHANGE COLUMN class class_id INT DEFAULT NULL COMMENT 'Class ID';

-- Rename stream column to stream_id
ALTER TABLE lesson_observations CHANGE COLUMN stream stream_id VARCHAR(50) DEFAULT NULL COMMENT 'Stream ID';

-- Add foreign key constraints if they don't exist
ALTER TABLE lesson_observations ADD CONSTRAINT fk_lesson_observations_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL;
ALTER TABLE lesson_observations ADD CONSTRAINT fk_lesson_observations_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL;
-- Note: streams table might not have an id column, so we might need to handle this differently
