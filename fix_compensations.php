<?php
require_once 'backend/config/Database.php';

$database = new Database();
$db = $database->getConnection();

try {
    // Get all compensations with minutes_compensated
    $stmt = $db->query("SELECT id, lesson_monitoring_id, minutes_compensated FROM lesson_compensations WHERE minutes_compensated > 0");
    $compensations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Found " . count($compensations) . " compensations to process\n";

    foreach ($compensations as $comp) {
        $compId = $comp['id'];
        $lessonId = $comp['lesson_monitoring_id'];
        $minutesCompensated = $comp['minutes_compensated'];

        // Update the lesson attendance record
        $updateQuery = "UPDATE teacher_lesson_attendance
                       SET minutes_lost = GREATEST(0, minutes_lost - :minutes_compensated)
                       WHERE id = :lesson_monitoring_id";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bindValue(':minutes_compensated', $minutesCompensated);
        $updateStmt->bindValue(':lesson_monitoring_id', $lessonId);
        $updateStmt->execute();

        echo "Updated lesson $lessonId: subtracted $minutesCompensated minutes\n";
    }

    echo "\nDone! All compensations have been applied to the original lessons.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
