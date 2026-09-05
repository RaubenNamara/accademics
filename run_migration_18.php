<?php
require_once 'backend/config/Database.php';

$database = new Database();
$db = $database->getConnection();

try {
    $sql = "ALTER TABLE duty_performance
            ADD COLUMN areas_of_improvement TEXT DEFAULT NULL AFTER comment,
            ADD COLUMN general_remarks TEXT DEFAULT NULL AFTER areas_of_improvement,
            ADD COLUMN supervisor VARCHAR(255) DEFAULT NULL AFTER general_remarks";
    
    $db->exec($sql);
    echo "Migration completed successfully! New columns added to duty_performance table.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Columns already exist. Migration skipped.\n";
    } else {
        echo "Migration failed: " . $e->getMessage() . "\n";
    }
}
?>
