<?php
require_once 'backend/config/Database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "Reorganizing departments table...\n";
    
    // Remove position column
    $stmt = $db->query("SHOW COLUMNS FROM departments LIKE 'position'");
    if ($stmt->rowCount() > 0) {
        echo "Removing position column...\n";
        $db->exec("ALTER TABLE departments DROP COLUMN position");
        echo "Position column removed.\n";
    }
    
    // Add description column
    $stmt = $db->query("SHOW COLUMNS FROM departments LIKE 'description'");
    if ($stmt->rowCount() == 0) {
        echo "Adding description column...\n";
        $db->exec("ALTER TABLE departments ADD COLUMN description TEXT AFTER name");
        echo "Description column added.\n";
    }
    
    // Update existing departments with descriptions
    echo "Updating existing departments with descriptions...\n";
    $updates = [
        'Administration' => 'School Administration and Management',
        'Accounts' => 'Financial Management and Accounting',
        'Academics' => 'Academic Affairs and Examinations',
        'Security' => 'School Security and Safety',
        'Health' => 'School Health Services',
        'Transport' => 'Transport and Logistics',
        'IT' => 'Information and Communication Technology',
        'Maintenance' => 'Facilities Maintenance and Repairs',
        'Reception' => 'Front Office and Reception Services',
        'Stores' => 'Inventory and Stores Management'
    ];
    
    foreach ($updates as $name => $description) {
        $stmt = $db->prepare("UPDATE departments SET description = :description WHERE name = :name");
        $stmt->bindValue(':description', $description, PDO::PARAM_STR);
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $stmt->execute();
    }
    
    echo "Departments updated.\n";
    
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
