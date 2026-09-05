<?php
require_once 'backend/config/Database.php';

$database = new Database();
$db = $database->getConnection();

try {
    // Add LIN column to students table
    $db->exec("ALTER TABLE students ADD COLUMN lin VARCHAR(255) DEFAULT '' AFTER enrollment_date");
    echo "LIN column added to students table successfully.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "LIN column already exists in students table.\n";
    } else {
        echo "Error adding LIN column: " . $e->getMessage() . "\n";
    }
}

try {
    // Add NIN column to parents table
    $db->exec("ALTER TABLE parents ADD COLUMN nin VARCHAR(255) DEFAULT '' AFTER email");
    echo "NIN column added to parents table successfully.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "NIN column already exists in parents table.\n";
    } else {
        echo "Error adding NIN column: " . $e->getMessage() . "\n";
    }
}

echo "Migration completed.\n";
