<?php
// Run timetable migration
require_once __DIR__ . '/../config/Database.php';

try {
    $db = (new Database())->getConnection();
    
    // Read SQL file
    $sql = file_get_contents(__DIR__ . '/26_create_timetable_table.sql');
    
    // Split by semicolon to get individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $statement) {
        if (!empty($statement) && !preg_match('/^--/', $statement)) {
            $db->exec($statement);
        }
    }
    
    echo "Migration completed successfully!\n";
    echo "Timetable table created.\n";
    echo "Timetable periods table created with default periods.\n";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
