<?php
require_once __DIR__ . '/backend/config/Database.php';

$db = (new Database())->getConnection();
$sql = file_get_contents(__DIR__ . '/backend/migrations/20_update_users_roles.sql');

try {
    $db->exec($sql);
    echo "Migration 20 applied successfully.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
