<?php
require_once 'backend/config/Database.php';

$database = new Database();
$db = $database->getConnection();

// Check if specialization column exists
$query = "SHOW COLUMNS FROM teachers LIKE 'specialization'";
$stmt = $db->prepare($query);
$stmt->execute();
$columnExists = $stmt->fetch();

if ($columnExists) {
    echo "specialization column EXISTS - renaming to other_duties...\n";
    
    // Rename the column
    $alterQuery = "ALTER TABLE teachers CHANGE COLUMN specialization other_duties TEXT DEFAULT NULL";
    $stmt = $db->prepare($alterQuery);
    
    if ($stmt->execute()) {
        echo "Column renamed from specialization to other_duties successfully\n";
    } else {
        echo "Failed to rename column: " . $stmt->errorInfo()[2] . "\n";
    }
} else {
    // Check if other_duties column already exists
    $query = "SHOW COLUMNS FROM teachers LIKE 'other_duties'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $otherDutiesExists = $stmt->fetch();
    
    if ($otherDutiesExists) {
        echo "other_duties column already EXISTS\n";
    } else {
        echo "Neither specialization nor other_duties column exists - adding other_duties column...\n";
        
        // Add the column
        $alterQuery = "ALTER TABLE teachers ADD COLUMN other_duties TEXT DEFAULT NULL AFTER teaching_license_number";
        $stmt = $db->prepare($alterQuery);
        
        if ($stmt->execute()) {
            echo "other_duties column added successfully\n";
        } else {
            echo "Failed to add other_duties column: " . $stmt->errorInfo()[2] . "\n";
        }
    }
}

// Show table structure
echo "\nTable structure:\n";
$query = "DESCRIBE teachers";
$stmt = $db->prepare($query);
$stmt->execute();
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($columns as $column) {
    echo "  - {$column['Field']} ({$column['Type']})\n";
}
?>
