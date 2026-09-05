-- Add profile_picture column to students table
USE accademics_db;

ALTER TABLE students ADD COLUMN profile_picture VARCHAR(255) DEFAULT NULL AFTER special_needs;
