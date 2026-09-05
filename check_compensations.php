<?php
require_once 'backend/config/Database.php';

$database = new Database();
$db = $database->getConnection();

// Check existing compensations
$stmt = $db->query("SELECT lc.id, lc.lesson_monitoring_id, lc.minutes_compensated, la.minutes_lost as original_minutes_lost, la.minutes_lost as current_minutes_lost
                   FROM lesson_compensations lc
                   LEFT JOIN teacher_lesson_attendance la ON lc.lesson_monitoring_id = la.id
                   LIMIT 10");
$compensations = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Existing compensations:\n";
foreach ($compensations as $comp) {
    echo "- Compensation ID: {$comp['id']}, Lesson ID: {$comp['lesson_monitoring_id']}, Minutes Compensated: {$comp['minutes_compensated']}, Current Minutes Lost: {$comp['current_minutes_lost']}\n";
}

// Check if there are compensations with minutes_compensated but the original lesson wasn't updated
$stmt = $db->query("SELECT lc.id, lc.lesson_monitoring_id, lc.minutes_compensated, la.minutes_lost
                   FROM lesson_compensations lc
                   LEFT JOIN teacher_lesson_attendance la ON lc.lesson_monitoring_id = la.id
                   WHERE lc.minutes_compensated > 0");
$needsUpdate = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "\nCompensations that may need to update the original lesson:\n";
foreach ($needsUpdate as $comp) {
    echo "- Compensation ID: {$comp['id']}, Lesson ID: {$comp['lesson_monitoring_id']}, Minutes Compensated: {$comp['minutes_compensated']}, Current Minutes Lost: {$comp['minutes_lost']}\n";
}
?>
