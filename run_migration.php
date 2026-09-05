<?php
require_once 'backend/config/Database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check if column already exists
    $check = $db->query("SHOW COLUMNS FROM students LIKE 'profile_picture'");
    if ($check->rowCount() > 0) {
        echo "Column profile_picture already exists\n";
        exit;
    }
    
    // Add the column
    $db->exec("ALTER TABLE students ADD COLUMN profile_picture VARCHAR(255) DEFAULT NULL AFTER special_needs");
    echo "Column profile_picture added successfully\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
