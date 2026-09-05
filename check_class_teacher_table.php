<?php
require_once 'backend/config/Database.php';

$db = (new Database())->getConnection();

// Check if class_teacher_assignments table exists
$stmt = $db->query("SHOW TABLES LIKE 'class_teacher_assignments'");
$result = $stmt->fetchAll();
echo "class_teacher_assignments table exists: " . (count($result) > 0 ? 'YES' : 'NO') . "\n";

if (count($result) > 0) {
    echo "\nTable structure:\n";
    $stmt = $db->query("DESCRIBE class_teacher_assignments");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo "  - " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
    
    // Check current data
    $stmt = $db->query("SELECT COUNT(*) as count FROM class_teacher_assignments");
    $count = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "\nTotal assignments: " . $count['count'] . "\n";
    
    // Show recent assignments
    $stmt = $db->query("SELECT * FROM class_teacher_assignments ORDER BY created_at DESC LIMIT 5");
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "\nRecent assignments:\n";
    foreach ($assignments as $assign) {
        echo "  ID: " . $assign['id'] . ", Teacher: " . $assign['teacher_id'] . ", Class: " . $assign['class_id'] . ", Year: " . $assign['academic_year'] . ", Active: " . ($assign['is_active'] ? 'YES' : 'NO') . "\n";
    }
}

// Check if teachers table exists
$stmt = $db->query("SHOW TABLES LIKE 'teachers'");
$result = $stmt->fetchAll();
echo "\nteachers table exists: " . (count($result) > 0 ? 'YES' : 'NO') . "\n";

// Check if classes table exists
$stmt = $db->query("SHOW TABLES LIKE 'classes'");
$result = $stmt->fetchAll();
echo "classes table exists: " . (count($result) > 0 ? 'YES' : 'NO') . "\n";
