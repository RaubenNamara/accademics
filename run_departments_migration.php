<?php
// Migration runner for departments table update

require_once 'backend/config/Database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $migrationFile = 'backend/migrations/34_update_departments_table.sql';
    
    if (!file_exists($migrationFile)) {
        die("Migration file not found: $migrationFile\n");
    }
    
    $sql = file_get_contents($migrationFile);
    
    // Split SQL into individual statements
    $statements = explode(';', $sql);
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (empty($statement)) continue;
        
        $db->exec($statement);
    }
    
    echo "Migration completed successfully!\n";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
