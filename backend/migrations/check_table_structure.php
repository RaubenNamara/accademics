<?php
require_once __DIR__ . '/../config/Database.php';

$database = new Database();
$db = $database->getConnection();

$stmt = $db->query("DESCRIBE teacher_lesson_attendance");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "teacher_lesson_attendance table structure:\n";
foreach ($columns as $column) {
    echo "- {$column['Field']} ({$column['Type']})\n";
}
?>
