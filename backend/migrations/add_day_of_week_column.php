<?php
require_once __DIR__ . '/../config/Database.php';

$database = new Database();
$db = $database->getConnection();

try {
    // Check if column exists
    $stmt = $db->query("SHOW COLUMNS FROM teacher_lesson_attendance LIKE 'day_of_week'");
    $columnExists = $stmt->fetch();

    if (!$columnExists) {
        $db->exec("ALTER TABLE teacher_lesson_attendance ADD COLUMN day_of_week VARCHAR(3) DEFAULT NULL AFTER attendance_date");
        echo "day_of_week column added successfully\n";
        
        // Update existing records
        $db->exec("UPDATE teacher_lesson_attendance SET day_of_week = CASE DAYOFWEEK(attendance_date) WHEN 1 THEN 'Sun' WHEN 2 THEN 'Mon' WHEN 3 THEN 'Tue' WHEN 4 THEN 'Wed' WHEN 5 THEN 'Thu' WHEN 6 THEN 'Fri' WHEN 7 THEN 'Sat' END WHERE day_of_week IS NULL");
        echo "Updated existing records with day_of_week\n";
    } else {
        echo "day_of_week column already exists\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
