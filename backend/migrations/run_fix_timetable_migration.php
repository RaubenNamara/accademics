<?php
// Run timetable schema fix migration
require_once __DIR__ . '/../config/Database.php';

try {
    $db = (new Database())->getConnection();
    
    // Read SQL file
    $sql = file_get_contents(__DIR__ . '/27_fix_timetable_schema.sql');
    
    // Split by semicolon to get individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $statement) {
        if (!empty($statement) && !preg_match('/^--/', $statement)) {
            $db->exec($statement);
        }
    }
    
    echo "Migration completed successfully!\n";
    echo "Room column dropped from timetable table.\n";
    echo "Unique constraints fixed to prevent class duplication.\n";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
