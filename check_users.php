<?php
require_once 'backend/config/Database.php';

$database = new Database();
$db = $database->getConnection();

$stmt = $db->query("SELECT id, full_name, email, role, is_active FROM users");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Users in database:\n";
foreach ($users as $user) {
    echo "- ID: {$user['id']}, Name: {$user['full_name']}, Email: {$user['email']}, Role: {$user['role']}, Active: {$user['is_active']}\n";
}

if (empty($users)) {
    echo "No users found in database.\n";
}
?>
