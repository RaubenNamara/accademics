<?php
require_once 'backend/config/Database.php';

$database = new Database();
$db = $database->getConnection();

// Check total teachers and active teachers
$stmt = $db->query("SELECT COUNT(*) as total, SUM(is_active) as active FROM teachers");
$result = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Total teachers: " . $result['total'] . "\n";
echo "Active teachers: " . $result['active'] . "\n";

// Check a sample of teachers
$stmt = $db->query("SELECT id, full_name, email, is_active FROM teachers LIMIT 5");
$teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "\nSample teachers:\n";
foreach ($teachers as $teacher) {
    echo "ID: {$teacher['id']}, Name: {$teacher['full_name']}, Email: {$teacher['email']}, Active: {$teacher['is_active']}\n";
}
