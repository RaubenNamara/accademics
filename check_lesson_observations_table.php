<?php
require_once 'backend/config/Database.php';

$database = new Database();
$db = $database->getConnection();

// Check if overall_comment column exists
$query = "SHOW COLUMNS FROM lesson_observations LIKE 'overall_comment'";
$stmt = $db->prepare($query);
$stmt->execute();
$columnExists = $stmt->fetch();

if ($columnExists) {
    echo "overall_comment column EXISTS\n";
} else {
    echo "overall_comment column DOES NOT EXIST - adding it now...\n";
    
    // Add the column
    $alterQuery = "ALTER TABLE lesson_observations ADD COLUMN overall_comment TEXT DEFAULT '' AFTER average_score";
    $stmt = $db->prepare($alterQuery);
    
    if ($stmt->execute()) {
        echo "overall_comment column added successfully\n";
    } else {
        echo "Failed to add overall_comment column: " . $stmt->errorInfo()[2] . "\n";
    }
}

// Show table structure
echo "\nTable structure:\n";
$query = "DESCRIBE lesson_observations";
$stmt = $db->prepare($query);
$stmt->execute();
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($columns as $column) {
    echo "  - {$column['Field']} ({$column['Type']})\n";
}
?>
