<?php
require_once 'backend/config/Database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Add is_active column if it doesn't exist
    $stmt = $db->query("SHOW COLUMNS FROM departments LIKE 'is_active'");
    if ($stmt->rowCount() == 0) {
        echo "Adding is_active column...\n";
        $db->exec("ALTER TABLE departments ADD COLUMN is_active BOOLEAN DEFAULT TRUE AFTER position");
        echo "is_active column added.\n";
    }
    
    // Update existing rows to have default position value if they don't have one
    echo "Updating existing rows...\n";
    $db->exec("UPDATE departments SET position = 'Staff' WHERE position = '' OR position IS NULL");
    echo "Existing rows updated.\n";
    
    // Show final structure
    echo "\nFinal table structure:\n";
    $stmt = $db->query("DESCRIBE departments");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }
    
    // Show existing data
    echo "\nExisting data:\n";
    $stmt = $db->query("SELECT * FROM departments");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
