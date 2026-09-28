<?php
require_once 'backend/config/Database.php';

$database = new Database();
$db = $database->getConnection();

$columns = [
    'aggregate' => "ALTER TABLE students ADD COLUMN aggregate VARCHAR(20) DEFAULT NULL AFTER special_needs",
    'division' => "ALTER TABLE students ADD COLUMN division VARCHAR(20) DEFAULT NULL AFTER aggregate",
    'aggregate_remarks' => "ALTER TABLE students ADD COLUMN aggregate_remarks TEXT DEFAULT NULL AFTER division",
];

foreach ($columns as $column => $alterQuery) {
    $query = "SHOW COLUMNS FROM students LIKE '$column'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $exists = $stmt->fetch();

    if (!$exists) {
        echo "$column column does not exist - adding it...\n";
        $stmt = $db->prepare($alterQuery);
        if ($stmt->execute()) {
            echo "$column column added successfully\n";
        } else {
            echo "Failed to add $column column: " . $stmt->errorInfo()[2] . "\n";
        }
    } else {
        echo "$column column already EXISTS\n";
    }
}

echo "\nTable structure:\n";
$query = "DESCRIBE students";
$stmt = $db->prepare($query);
$stmt->execute();
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($columns as $column) {
    echo "  - {$column['Field']} ({$column['Type']})\n";
}
?>
