-- Migration to support multiple former school support documents per student
USE accademics_db;

-- Create student_former_school_documents table to support multiple former school support documents
CREATE TABLE IF NOT EXISTS student_former_school_documents (
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

-- Migrate existing single former_school_support_doc to new structure if it exists
-- This will copy existing single documents to the new table structure
INSERT INTO student_former_school_documents (student_id, filename, file_path, file_size, uploaded_at)
SELECT 
    id,
    COALESCE(former_school_support_doc, 'legacy_document') as filename,
    former_school_support_doc as file_path,
    NULL as file_size,
    updated_at as uploaded_at
FROM students 
WHERE former_school_support_doc IS NOT NULL 
AND former_school_support_doc != '';
