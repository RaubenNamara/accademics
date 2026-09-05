<?php
require_once 'backend/config/Database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check if departments table exists
    $stmt = $db->query("SHOW TABLES LIKE 'departments'");
    if ($stmt->rowCount() == 0) {
        echo "Departments table does not exist. Creating it...\n";
        
        $sql = "CREATE TABLE departments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            position VARCHAR(100) NOT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_name (name)
        )";
        
        $db->exec($sql);
        echo "Departments table created successfully.\n";
    } else {
        echo "Departments table exists. Checking structure...\n";
        
        // Show table structure
        $stmt = $db->query("DESCRIBE departments");
        echo "Current table structure:\n";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            print_r($row);
        }
        
        // Check if position column exists
        $stmt = $db->query("SHOW COLUMNS FROM departments LIKE 'position'");
        if ($stmt->rowCount() == 0) {
            echo "\nPosition column missing. Adding it...\n";
            $db->exec("ALTER TABLE departments ADD COLUMN position VARCHAR(100) NOT NULL DEFAULT '' AFTER name");
            echo "Position column added.\n";
        }
        
        // Check if description column exists and remove it
        $stmt = $db->query("SHOW COLUMNS FROM departments LIKE 'description'");
        if ($stmt->rowCount() > 0) {
            echo "\nDescription column exists. Removing it...\n";
            $db->exec("ALTER TABLE departments DROP COLUMN description");
            echo "Description column removed.\n";
        }
    }
    
    // Show final structure
    echo "\nFinal table structure:\n";
    $stmt = $db->query("DESCRIBE departments");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
