<?php
/**
 * Run HR migrations 18 and 19. Execute: php run_hr_migration.php
 */
require_once __DIR__ . '/backend/config/Database.php';

$database = new Database();
$db = $database->getConnection();

$migrations = [
    __DIR__ . '/backend/migrations/18_add_hr_staff_tables.sql',
    __DIR__ . '/backend/migrations/19_hr_enhancements.sql',
    __DIR__ . '/backend/migrations/20_update_users_roles.sql',
];

foreach ($migrations as $file) {
    if (!file_exists($file)) {
        echo "Skip missing: $file\n";
        continue;
    }
    echo "Running: " . basename($file) . "\n";
    $sql = file_get_contents($file);
    try {
        $db->exec($sql);
        echo "  OK\n";
    } catch (PDOException $e) {
        echo "  Note: " . $e->getMessage() . "\n";
    }
}

echo "Done.\n";
