<?php
// Test departments API directly
require_once 'backend/config/Database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Test GET request
    echo "Testing GET request...\n";
    $stmt = $db->query("SELECT id, name, position, is_active FROM departments ORDER BY name ASC");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Found " . count($departments) . " departments\n";
    print_r($departments);
    
    // Test POST request
    echo "\nTesting POST request...\n";
    $sql = "INSERT INTO departments (name, position, is_active) VALUES (:name, :position, :is_active)";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':name', 'Test Department', PDO::PARAM_STR);
    $stmt->bindValue(':position', 'Test Position', PDO::PARAM_STR);
    $stmt->bindValue(':is_active', true, PDO::PARAM_BOOL);
    
    if ($stmt->execute()) {
        echo "Test department created successfully. ID: " . $db->lastInsertId() . "\n";
    } else {
        echo "Failed to create test department\n";
    }
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
