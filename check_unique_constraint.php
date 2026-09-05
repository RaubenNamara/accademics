<?php
require_once 'backend/config/Database.php';

$database = new Database();
$db = $database->getConnection();

// Check unique constraints
$query = "SHOW INDEX FROM class_teacher_performance WHERE Key_name = 'unique_class_teacher_term'";
$stmt = $db->prepare($query);
$stmt->execute();
$indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Unique constraint 'unique_class_teacher_term':\n";
print_r($indexes);

// Show all indexes
echo "\n\nAll indexes on class_teacher_performance:\n";
$query = "SHOW INDEX FROM class_teacher_performance";
$stmt = $db->prepare($query);
$stmt->execute();
$allIndexes = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($allIndexes as $index) {
    echo "  - {$index['Key_name']}: Column {$index['Column_name']}\n";
}
?>
