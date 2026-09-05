<?php
require_once 'backend/config/Database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check if classes table already exists
    $checkClasses = $db->query("SHOW TABLES LIKE 'classes'");
    if ($checkClasses->rowCount() > 0) {
        echo "Table 'classes' already exists\n";
    } else {
        $db->exec("
            CREATE TABLE IF NOT EXISTS classes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                class_name VARCHAR(50) NOT NULL,
                stream_name VARCHAR(50) NOT NULL,
                full_class_name VARCHAR(100) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_class_stream (class_name, stream_name),
                INDEX idx_class_name (class_name),
                INDEX idx_stream_name (stream_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "Table 'classes' created successfully\n";
    }
    
    // Check if subjects_new table already exists
    $checkSubjects = $db->query("SHOW TABLES LIKE 'subjects_new'");
    if ($checkSubjects->rowCount() > 0) {
        echo "Table 'subjects_new' already exists\n";
    } else {
        $db->exec("
            CREATE TABLE IF NOT EXISTS subjects_new (
                id INT AUTO_INCREMENT PRIMARY KEY,
                subject_name VARCHAR(255) NOT NULL,
                subject_code VARCHAR(20) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_subject_code (subject_code),
                INDEX idx_subject_name (subject_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "Table 'subjects_new' created successfully\n";
    }
    
    // Check if class_subjects table already exists
    $checkClassSubjects = $db->query("SHOW TABLES LIKE 'class_subjects'");
    if ($checkClassSubjects->rowCount() > 0) {
        echo "Table 'class_subjects' already exists\n";
    } else {
        $db->exec("
            CREATE TABLE IF NOT EXISTS class_subjects (
                id INT AUTO_INCREMENT PRIMARY KEY,
                class_id INT NOT NULL,
                subject_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
                FOREIGN KEY (subject_id) REFERENCES subjects_new(id) ON DELETE CASCADE,
                UNIQUE KEY unique_class_subject (class_id, subject_id),
                INDEX idx_class_id (class_id),
                INDEX idx_subject_id (subject_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "Table 'class_subjects' created successfully\n";
    }
    
    // Insert default classes if table is empty
    $classCount = $db->query("SELECT COUNT(*) FROM classes")->fetchColumn();
    if ($classCount == 0) {
        $defaultClasses = [
            ['S.1', 'East', 'S.1 East'],
            ['S.1', 'West', 'S.1 West'],
            ['S.1', 'North', 'S.1 North'],
            ['S.1', 'South', 'S.1 South'],
            ['S.2', 'East', 'S.2 East'],
            ['S.2', 'West', 'S.2 West'],
            ['S.2', 'North', 'S.2 North'],
            ['S.2', 'South', 'S.2 South'],
            ['S.3', 'East', 'S.3 East'],
            ['S.3', 'West', 'S.3 West'],
            ['S.3', 'North', 'S.3 North'],
            ['S.3', 'South', 'S.3 South'],
            ['S.4', 'East', 'S.4 East'],
            ['S.4', 'West', 'S.4 West'],
            ['S.4', 'North', 'S.4 North'],
            ['S.4', 'South', 'S.4 South'],
            ['S.5', 'East', 'S.5 East'],
            ['S.5', 'West', 'S.5 West'],
            ['S.5', 'North', 'S.5 North'],
            ['S.5', 'South', 'S.5 South'],
            ['S.6', 'East', 'S.6 East'],
            ['S.6', 'West', 'S.6 West'],
            ['S.6', 'North', 'S.6 North'],
            ['S.6', 'South', 'S.6 South'],
        ];
        
        $stmt = $db->prepare("INSERT INTO classes (class_name, stream_name, full_class_name) VALUES (?, ?, ?)");
        foreach ($defaultClasses as $class) {
            $stmt->execute($class);
        }
        echo "Default classes inserted successfully\n";
    } else {
        echo "Classes table already has data\n";
    }
    
    // Insert default subjects if table is empty
    $subjectCount = $db->query("SELECT COUNT(*) FROM subjects_new")->fetchColumn();
    if ($subjectCount == 0) {
        $defaultSubjects = [
            ['Mathematics', 'MATH'],
            ['English Language', 'ENG'],
            ['Physics', 'PHY'],
            ['Chemistry', 'CHE'],
            ['Biology', 'BIO'],
            ['Geography', 'GEO'],
            ['History', 'HIST'],
            ['Christian Religious Education', 'CRE'],
            ['Islamic Religious Education', 'IRE'],
            ['Literature in English', 'LIT'],
            ['Kiswahili', 'KIS'],
            ['French', 'FREN'],
            ['German', 'GER'],
            ['Agriculture', 'AGRI'],
            ['Commerce', 'COM'],
            ['Accounting', 'ACC'],
            ['Entrepreneurship', 'ENT'],
            ['Fine Art', 'ART'],
            ['Music', 'MUS'],
            ['Physical Education', 'PE'],
            ['Information and Communication Technology', 'ICT'],
        ];
        
        $stmt = $db->prepare("INSERT INTO subjects_new (subject_name, subject_code) VALUES (?, ?)");
        foreach ($defaultSubjects as $subject) {
            $stmt->execute($subject);
        }
        echo "Default subjects inserted successfully\n";
    } else {
        echo "Subjects table already has data\n";
    }
    
    echo "\nMigration completed successfully\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
