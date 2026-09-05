<?php
require_once 'backend/config/Database.php';

$database = new Database();
$db = $database->getConnection();

// Check if academic_score column exists
$query = "SHOW COLUMNS FROM class_teacher_performance LIKE 'academic_score'";
$stmt = $db->prepare($query);
$stmt->execute();
$academicScoreExists = $stmt->fetch();

if (!$academicScoreExists) {
    echo "academic_score column does not exist - adding it...\n";
    
    // Add the column
    $alterQuery = "ALTER TABLE class_teacher_performance ADD COLUMN academic_score INT DEFAULT 0 AFTER average_comment";
    $stmt = $db->prepare($alterQuery);
    
    if ($stmt->execute()) {
        echo "academic_score column added successfully\n";
    } else {
        echo "Failed to add academic_score column: " . $stmt->errorInfo()[2] . "\n";
    }
} else {
    echo "academic_score column already EXISTS\n";
}

// Check if weekly_average_score column exists
$query = "SHOW COLUMNS FROM class_teacher_performance LIKE 'weekly_average_score'";
$stmt = $db->prepare($query);
$stmt->execute();
$weeklyAverageExists = $stmt->fetch();

if (!$weeklyAverageExists) {
    echo "weekly_average_score column does not exist - adding it...\n";
    
    // Add the column
    $alterQuery = "ALTER TABLE class_teacher_performance ADD COLUMN weekly_average_score DECIMAL(10,2) DEFAULT NULL AFTER academic_score";
    $stmt = $db->prepare($alterQuery);
    
    if ($stmt->execute()) {
        echo "weekly_average_score column added successfully\n";
    } else {
        echo "Failed to add weekly_average_score column: " . $stmt->errorInfo()[2] . "\n";
    }
} else {
    echo "weekly_average_score column already EXISTS\n";
}

// Show table structure
echo "\nTable structure:\n";
$query = "DESCRIBE class_teacher_performance";
$stmt = $db->prepare($query);
$stmt->execute();
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($columns as $column) {
    echo "  - {$column['Field']} ({$column['Type']})\n";
}
?>
