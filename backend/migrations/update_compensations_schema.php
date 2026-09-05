<?php
require_once __DIR__ . '/../config/Database.php';

$database = new Database();
$db = $database->getConnection();

try {
    // Add minutes_compensated column if it doesn't exist
    $stmt = $db->query("SHOW COLUMNS FROM lesson_compensations LIKE 'minutes_compensated'");
    $columnExists = $stmt->fetch();

    if (!$columnExists) {
        $db->exec("ALTER TABLE lesson_compensations ADD COLUMN minutes_compensated INT DEFAULT 0 AFTER compensation_day");
        echo "minutes_compensated column added successfully\n";
    } else {
        echo "minutes_compensated column already exists\n";
    }

    // Update status enum to include new values
    $db->exec("ALTER TABLE lesson_compensations MODIFY COLUMN status ENUM('Pending', 'Completed', 'Partially Compensated', 'Fully Compensated') DEFAULT 'Partially Compensated'");
    echo "Status enum updated successfully\n";

    // Remove old columns if they exist
    $stmt = $db->query("SHOW COLUMNS FROM lesson_compensations LIKE 'compensation_time'");
    if ($stmt->fetch()) {
        $db->exec("ALTER TABLE lesson_compensations DROP COLUMN compensation_time");
        echo "Dropped compensation_time column\n";
    }

    $stmt = $db->query("SHOW COLUMNS FROM lesson_compensations LIKE 'periods_regained'");
    if ($stmt->fetch()) {
        $db->exec("ALTER TABLE lesson_compensations DROP COLUMN periods_regained");
        echo "Dropped periods_regained column\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
