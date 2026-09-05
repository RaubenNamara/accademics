<?php
require_once 'backend/config/Database.php';

$db = (new Database())->getConnection();

// Create class_teacher_assignments table
$sql = "
CREATE TABLE IF NOT EXISTS class_teacher_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    class_id INT NOT NULL,
    stream VARCHAR(50) DEFAULT NULL,
    academic_year INT NOT NULL,
    term INT DEFAULT NULL,
    start_date DATE DEFAULT NULL,
    end_date DATE DEFAULT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_class_teacher_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    CONSTRAINT fk_class_teacher_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
)
";

try {
    $db->exec($sql);
    echo "class_teacher_assignments table created successfully\n";
    
    // Verify table exists
    $stmt = $db->query("SHOW TABLES LIKE 'class_teacher_assignments'");
    $result = $stmt->fetchAll();
    echo "Table exists: " . (count($result) > 0 ? 'YES' : 'NO') . "\n";
    
    // Show structure
    $stmt = $db->query("DESCRIBE class_teacher_assignments");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "\nTable structure:\n";
    foreach ($columns as $col) {
        echo "  - " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
    
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage() . "\n";
}
