<?php
// Script to run migration 25: Add Non-Teaching Staff Positions and Departments

require_once 'backend/config/Database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        die("Database connection failed\n");
    }
    
    echo "Starting Migration 25: Add Non-Teaching Staff Positions and Departments\n";
    echo "========================================================================\n\n";
    
    // Read the migration SQL file
    $migrationFile = 'backend/migrations/25_add_non_teaching_positions_and_departments.sql';
    if (!file_exists($migrationFile)) {
        die("Migration file not found: $migrationFile\n");
    }
    
    $sql = file_get_contents($migrationFile);
    
    // Remove SELECT statements (verification queries) - we'll run them separately
    $sql = preg_replace('/^SELECT.*?;$/ms', '', $sql);
    $sql = preg_replace('/^-- .*$/m', '', $sql);
    
    // Split the SQL into individual statements
    $statements = explode(';', $sql);
    
    $executed = 0;
    $errors = 0;
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (empty($statement) || strpos($statement, 'SET') === 0) {
            continue;
        }
        
        try {
            $db->exec($statement);
            $executed++;
            echo "✓ Executed: " . substr($statement, 0, 60) . "...\n";
        } catch (PDOException $e) {
            // Ignore duplicate entry errors (INSERT IGNORE should handle this, but just in case)
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                echo "⊘ Skipped (duplicate): " . substr($statement, 0, 60) . "...\n";
            } else {
                $errors++;
                echo "✗ Error: " . $e->getMessage() . "\n";
                echo "  Statement: " . substr($statement, 0, 100) . "...\n";
            }
        }
    }
    
    echo "\n========================================================================\n";
    echo "Migration completed\n";
    echo "Statements executed: $executed\n";
    echo "Errors encountered: $errors\n";
    
    if ($errors === 0) {
        echo "\n✓ Migration successful!\n";
    } else {
        echo "\n⚠ Migration completed with $errors error(s)\n";
    }
    
    // Verify the migration
    echo "\nVerifying migration...\n";
    
    $deptCount = $db->query("SELECT COUNT(*) FROM departments WHERE name IN (
        'ICT Department', 'Science Department', 'Administration Department', 'Finance Department',
        'Chaplaincy Department', 'Guidance & Counselling Department', 'Procurement Department',
        'Transport Department', 'Catering Department', 'Academics Department',
        'Public Relations Department', 'Human Resource Department', 'Maintenance Department',
        'Media & Communications Department', 'Co-Curricular Activities Department', 'Sports Department'
    )")->fetchColumn();
    
    $roleCount = $db->query("SELECT COUNT(*) FROM roles WHERE name IN (
        'ICT Lab Technician', 'Science Lab Technician', 'Data Entrant', 'Systems Developer',
        'Assistant Finance Officer', 'Finance Officer', 'School Chaplain',
        'Guidance and Counselling Officer', 'Procurement Officer', 'School Driver',
        'Cook', 'Exam Master', 'Exam Secretary', 'Administrative Secretary',
        'Public Relations Officer (P.R.O)', 'HR Secretary', 'Electrician',
        'Content Manager', 'Music Director', 'Coach'
    )")->fetchColumn();
    
    echo "Departments added: $deptCount/16\n";
    echo "Positions added: $roleCount/20\n";
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage() . "\n");
}
