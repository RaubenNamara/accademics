<?php
require_once 'backend/config/Database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check if week column already exists
    $check = $db->query("SHOW COLUMNS FROM class_teacher_performance LIKE 'week'");
    if ($check->rowCount() > 0) {
        echo "Column week already exists\n";
        exit;
    }
    
    // Apply the migration
    $db->exec("ALTER TABLE class_teacher_performance 
        ADD COLUMN week INT NOT NULL DEFAULT 1 COMMENT 'Week number (1-13)',
        ADD COLUMN roll_call_score INT NOT NULL DEFAULT 12 COMMENT 'Roll call score (0,12,15,20)',
        ADD COLUMN mentorship_score INT NOT NULL DEFAULT 12 COMMENT 'Mentorship score (0,12,15,20)',
        ADD COLUMN devotion_score INT NOT NULL DEFAULT 12 COMMENT 'Devotion score (0,12,15,20)',
        ADD COLUMN cleanliness_score INT NOT NULL DEFAULT 12 COMMENT 'Cleanliness score (0,12,15,20)',
        ADD COLUMN parent_contacted BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Whether parent was contacted',
        ADD COLUMN weekly_score INT NOT NULL DEFAULT 0 COMMENT 'Total weekly score (max 80)'");
    
    // Add unique constraint
    $db->exec("ALTER TABLE class_teacher_performance 
        ADD UNIQUE KEY unique_teacher_week (teacher_id, class, stream, year, term, week)");
    
    echo "Class teacher performance migration applied successfully\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
