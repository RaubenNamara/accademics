<?php
require_once 'backend/config/Database.php';

$database = new Database();
$db = $database->getConnection();

$stmt = $db->query("SELECT COUNT(*) as count FROM teacher_lesson_attendance");
$count = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Total records in teacher_lesson_attendance: {$count['count']}\n";

$stmt = $db->query("SELECT * FROM teacher_lesson_attendance LIMIT 5");
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "\nSample records:\n";
foreach ($records as $record) {
    echo "- ID: {$record['id']}, Teacher: {$record['teacher_id']}, Year: {$record['year']}, Term: {$record['term']}, Week: {$record['week_number']}, Date: {$record['attendance_date']}\n";
}

$stmt = $db->query("SELECT DISTINCT year, term FROM teacher_lesson_attendance");
$years_terms = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "\nAvailable year/term combinations:\n";
foreach ($years_terms as $yt) {
    echo "- Year: {$yt['year']}, Term: {$yt['term']}\n";
}
?>
