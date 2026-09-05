<?php
require_once 'config/Database.php';
require_once 'config/HrHelpers.php';

$database = new Database();
$db = $database->getConnection();

// Test if school_events table exists
try {
    $stmt = $db->query("SHOW TABLES LIKE 'school_events'");
    $result = $stmt->fetchAll();
    
    if (empty($result)) {
        echo "ERROR: school_events table does not exist\n";
    } else {
        echo "SUCCESS: school_events table exists\n";
        
        // Test inserting a record
        $stmt = $db->prepare("INSERT INTO school_events (event_name, event_type, event_color, description, duration_minutes, is_active) VALUES (?, ?, ?, ?, ?, 1)");
        $stmt->execute(['Test Event', 'custom', '#FF6B6B', 'Test description', 30]);
        
        echo "SUCCESS: Test record inserted\n";
        echo "Last insert ID: " . $db->lastInsertId() . "\n";
        
        // Clean up
        $db->exec("DELETE FROM school_events WHERE event_name = 'Test Event'");
        echo "SUCCESS: Test record deleted\n";
    }
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
