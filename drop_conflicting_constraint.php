<?php
require_once 'backend/config/Database.php';

$database = new Database();
$db = $database->getConnection();

// Drop the conflicting unique constraint that doesn't include week
$query = "ALTER TABLE class_teacher_performance DROP INDEX unique_class_teacher_term";
$stmt = $db->prepare($query);

if ($stmt->execute()) {
    echo "Successfully dropped unique_class_teacher_term constraint\n";
} else {
    echo "Failed to drop constraint: " . $stmt->errorInfo()[2] . "\n";
}

// Verify the constraint was dropped
$query = "SHOW INDEX FROM class_teacher_performance WHERE Key_name = 'unique_class_teacher_term'";
$stmt = $db->prepare($query);
$stmt->execute();
$indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($indexes)) {
    echo "Verified: unique_class_teacher_term constraint has been removed\n";
} else {
    echo "Error: Constraint still exists\n";
}

// Show remaining indexes
echo "\nRemaining indexes:\n";
$query = "SHOW INDEX FROM class_teacher_performance";
$stmt = $db->prepare($query);
$stmt->execute();
$allIndexes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$grouped = [];
foreach ($allIndexes as $index) {
    $keyName = $index['Key_name'];
    if (!isset($grouped[$keyName])) {
        $grouped[$keyName] = [];
    }
    $grouped[$keyName][] = $index['Column_name'];
}

foreach ($grouped as $keyName => $columns) {
    echo "  - $keyName: " . implode(', ', $columns) . "\n";
}
?>
