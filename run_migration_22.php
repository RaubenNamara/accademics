<?php
require_once 'backend/config/Database.php';

$database = new Database();
$db = $database->getConnection();

try {
    // Create lesson_observations table
    $sql = "
        CREATE TABLE IF NOT EXISTS lesson_observations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            teacher_id INT NOT NULL,
            subject_id INT NOT NULL,
            class_id INT NOT NULL,
            stream_id INT NOT NULL,
            term VARCHAR(50) NOT NULL,
            year INT NOT NULL,
            round VARCHAR(50) NOT NULL,
            total_score DECIMAL(5,2) NOT NULL,
            calculated_rating DECIMAL(3,2) NOT NULL,
            performance_category VARCHAR(50) NOT NULL,
            strengths_observed TEXT,
            general_comment TEXT,
            areas_for_improvement TEXT,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_teacher (teacher_id),
            INDEX idx_subject (subject_id),
            INDEX idx_class (class_id),
            INDEX idx_stream (stream_id),
            INDEX idx_year (year),
            INDEX idx_round (round),
            INDEX idx_term (term),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $db->exec($sql);
    echo "Lesson observations table created successfully.\n";
} catch (PDOException $e) {
    echo "Error creating lesson observations table: " . $e->getMessage() . "\n";
}

echo "Migration completed.\n";
