<?php
require_once __DIR__ . '/../config/Database.php';

$database = new Database();
$db = $database->getConnection();

try {
    $sql = "CREATE TABLE IF NOT EXISTS lesson_compensations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        lesson_monitoring_id INT NOT NULL COMMENT 'Reference to teacher_lesson_attendance record',
        teacher_id INT NOT NULL,
        class_id INT DEFAULT NULL COMMENT 'Optional reference to classes table if available',
        subject VARCHAR(100) NOT NULL,
        class VARCHAR(50) NOT NULL,
        stream VARCHAR(50) DEFAULT NULL,
        original_date DATE NOT NULL,
        original_day VARCHAR(3) NOT NULL,
        compensation_date DATE NOT NULL,
        compensation_day VARCHAR(3) NOT NULL,
        compensation_time TIME DEFAULT NULL,
        periods_regained INT DEFAULT 1,
        remarks TEXT DEFAULT NULL,
        status ENUM('Pending', 'Completed') DEFAULT 'Pending',
        created_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        FOREIGN KEY (lesson_monitoring_id) REFERENCES teacher_lesson_attendance(id) ON DELETE CASCADE,
        FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
        
        INDEX idx_lesson_monitoring_id (lesson_monitoring_id),
        INDEX idx_teacher_id (teacher_id),
        INDEX idx_original_date (original_date),
        INDEX idx_compensation_date (compensation_date),
        INDEX idx_status (status),
        INDEX idx_compensation_day (compensation_day),
        
        CONSTRAINT chk_original_day CHECK (original_day IN ('Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun')),
        CONSTRAINT chk_compensation_day CHECK (compensation_day IN ('Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun')),
        CONSTRAINT chk_compensation_date CHECK (compensation_date >= original_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $db->exec($sql);
    echo "lesson_compensations table created successfully\n";
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage() . "\n";
}
?>
