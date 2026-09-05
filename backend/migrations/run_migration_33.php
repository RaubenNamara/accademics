<?php
require_once __DIR__ . '/../config/Database.php';

$database = new Database();
$db = $database->getConnection();

try {
    // Rename lesson_date to attendance_date
    $db->exec("ALTER TABLE teacher_lesson_attendance CHANGE COLUMN lesson_date attendance_date DATE NOT NULL");
    echo "Renamed lesson_date to attendance_date\n";
} catch (PDOException $e) {
    echo "Error renaming lesson_date: " . $e->getMessage() . "\n";
}

try {
    // Rename class_name to class
    $db->exec("ALTER TABLE teacher_lesson_attendance CHANGE COLUMN class_name class VARCHAR(100) NULL");
    echo "Renamed class_name to class\n";
} catch (PDOException $e) {
    echo "Error renaming class_name: " . $e->getMessage() . "\n";
}

try {
    // Add missing columns
    $db->exec("ALTER TABLE teacher_lesson_attendance ADD COLUMN day_of_week VARCHAR(3) DEFAULT NULL AFTER attendance_date");
    echo "Added day_of_week column\n";
} catch (PDOException $e) {
    echo "Error adding day_of_week: " . $e->getMessage() . "\n";
}

try {
    $db->exec("ALTER TABLE teacher_lesson_attendance ADD COLUMN expected_minutes INT DEFAULT 0 AFTER time_out");
    echo "Added expected_minutes column\n";
} catch (PDOException $e) {
    echo "Error adding expected_minutes: " . $e->getMessage() . "\n";
}

try {
    $db->exec("ALTER TABLE teacher_lesson_attendance ADD COLUMN actual_minutes INT DEFAULT 0 AFTER expected_minutes");
    echo "Added actual_minutes column\n";
} catch (PDOException $e) {
    echo "Error adding actual_minutes: " . $e->getMessage() . "\n";
}

try {
    $db->exec("ALTER TABLE teacher_lesson_attendance ADD COLUMN minutes_lost INT DEFAULT 0 AFTER actual_minutes");
    echo "Added minutes_lost column\n";
} catch (PDOException $e) {
    echo "Error adding minutes_lost: " . $e->getMessage() . "\n";
}

try {
    // Update existing records to populate day_of_week
    $db->exec("UPDATE teacher_lesson_attendance SET day_of_week = CASE DAYOFWEEK(attendance_date) WHEN 1 THEN 'Sun' WHEN 2 THEN 'Mon' WHEN 3 THEN 'Tue' WHEN 4 THEN 'Wed' WHEN 5 THEN 'Thu' WHEN 6 THEN 'Fri' WHEN 7 THEN 'Sat' END WHERE day_of_week IS NULL");
    echo "Updated day_of_week for existing records\n";
} catch (PDOException $e) {
    echo "Error updating day_of_week: " . $e->getMessage() . "\n";
}

try {
    // Create indexes
    $db->exec("CREATE INDEX idx_day_of_week ON teacher_lesson_attendance(day_of_week)");
    echo "Created idx_day_of_week index\n";
} catch (PDOException $e) {
    echo "Error creating idx_day_of_week: " . $e->getMessage() . "\n";
}

try {
    $db->exec("CREATE INDEX idx_year_term ON teacher_lesson_attendance(year, term)");
    echo "Created idx_year_term index\n";
} catch (PDOException $e) {
    echo "Error creating idx_year_term: " . $e->getMessage() . "\n";
}

try {
    $db->exec("CREATE INDEX idx_week_number ON teacher_lesson_attendance(week_number)");
    echo "Created idx_week_number index\n";
} catch (PDOException $e) {
    echo "Error creating idx_week_number: " . $e->getMessage() . "\n";
}

echo "\nMigration completed successfully!\n";
?>
