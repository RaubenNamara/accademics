<?php
require_once 'backend/config/Database.php';

$database = new Database();
$db = $database->getConnection();

echo "Running migration 24: Simplify Lesson Observation Module\n";
echo "=================================================\n\n";

try {
    // Read the migration file
    $migrationFile = 'backend/migrations/24_simplify_lesson_observation.sql';
    if (!file_exists($migrationFile)) {
        die("Migration file not found: $migrationFile\n");
    }

    $sql = file_get_contents($migrationFile);
    
    // Split by semicolon and execute each statement
    $statements = explode(';', $sql);
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (empty($statement)) continue;
        
        // Skip comments
        if (strpos($statement, '--') === 0) continue;
        
        echo "Executing: " . substr($statement, 0, 50) . "...\n";
        
        try {
            $db->exec($statement);
            echo "✓ Success\n\n";
        } catch (PDOException $e) {
            // Check if it's a "duplicate column" error (column already exists)
            if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                echo "⚠ Column already exists (skipping)\n\n";
            } else {
                echo "✗ Error: " . $e->getMessage() . "\n\n";
            }
        }
    }
    
    echo "\n=================================================\n";
    echo "Migration completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
