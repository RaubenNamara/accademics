<?php
require_once 'backend/config/Database.php';

$db = (new Database())->getConnection();

// Get a teacher and class for testing
$teacher = $db->query("SELECT id, full_name FROM teachers LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$class = $db->query("SELECT id, class_name FROM classes LIMIT 1")->fetch(PDO::FETCH_ASSOC);

if (!$teacher || !$class) {
    echo "No teachers or classes found in database\n";
    exit;
}

echo "Testing with:\n";
echo "  Teacher: " . $teacher['full_name'] . " (ID: " . $teacher['id'] . ")\n";
echo "  Class: " . $class['class_name'] . " (ID: " . $class['id'] . ")\n\n";

// Test inserting a class teacher assignment
try {
    $year = 2026;
    $stmt = $db->prepare("
        INSERT INTO class_teacher_assignments
        (teacher_id, class_id, stream, academic_year, term, start_date, is_active)
        VALUES (:teacher_id, :class_id, :stream, :year, :term, CURDATE(), 1)
    ");
    $stmt->execute([
        ':teacher_id' => $teacher['id'],
        ':class_id' => $class['id'],
        ':stream' => null,
        ':year' => $year,
        ':term' => null,
    ]);
    
    $newId = $db->lastInsertId();
    echo "✓ Assignment created with ID: " . $newId . "\n";
    
    // Verify the insert
    $stmt = $db->prepare("SELECT * FROM class_teacher_assignments WHERE id = :id");
    $stmt->execute([':id' => $newId]);
    $inserted = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Inserted record:\n";
    echo "  Teacher ID: " . $inserted['teacher_id'] . "\n";
    echo "  Class ID: " . $inserted['class_id'] . "\n";
    echo "  Academic Year: " . $inserted['academic_year'] . "\n";
    echo "  Is Active: " . ($inserted['is_active'] ? 'YES' : 'NO') . "\n";
    
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

// Check total assignments
$stmt = $db->query("SELECT COUNT(*) as count FROM class_teacher_assignments");
$count = $stmt->fetch(PDO::FETCH_ASSOC);
echo "\nTotal assignments in database: " . $count['count'] . "\n";
