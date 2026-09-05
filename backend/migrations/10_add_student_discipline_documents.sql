-- Migration to support multiple discipline documents per student
USE accademics_db;

-- Add former_school and former_school_support_doc columns to students table if they don't exist
ALTER TABLE students 
ADD COLUMN IF NOT EXISTS former_school VARCHAR(255),
ADD COLUMN IF NOT EXISTS former_school_support_doc VARCHAR(500);

-- Create student_discipline_documents table to support multiple discipline documents
CREATE TABLE IF NOT EXISTS student_discipline_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    INDEX idx_student (student_id)
);

-- Migrate existing single behaviour_document to new structure if it exists
-- This will copy existing single documents to the new table structure
INSERT INTO student_discipline_documents (student_id, filename, file_path, file_size, uploaded_at)
SELECT 
    id,
    COALESCE(behaviour_document, 'legacy_document') as filename,
    behaviour_document as file_path,
    NULL as file_size,
    updated_at as uploaded_at
FROM students 
WHERE behaviour_document IS NOT NULL 
AND behaviour_document != '';
