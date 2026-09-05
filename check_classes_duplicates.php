<?php
require_once 'backend/config/Database.php';

$db = (new Database())->getConnection();

// Check for duplicate classes
$stmt = $db->query("
    SELECT class_name, stream_name, COUNT(*) as count 
    FROM classes 
    GROUP BY class_name, stream_name 
    HAVING COUNT(*) > 1
");
$duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($duplicates) > 0) {
    echo "Duplicate classes found:\n";
    foreach ($duplicates as $dup) {
        echo "  " . $dup['class_name'] . " - " . $dup['stream_name'] . " (count: " . $dup['count'] . ")\n";
    }
} else {
    echo "No duplicate classes found in database\n";
}

// Show all classes
$stmt = $db->query("SELECT * FROM classes ORDER BY class_name, stream_name");
$allClasses = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "\nAll classes (" . count($allClasses) . "):\n";
foreach ($allClasses as $class) {
    echo "  ID: " . $class['id'] . " - " . $class['class_name'] . " - " . $class['stream_name'] . "\n";
}
