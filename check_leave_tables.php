<?php
require_once 'backend/config/Database.php';

$db = (new Database())->getConnection();

// Check if leave_requests table exists
$stmt = $db->query("SHOW TABLES LIKE 'leave_requests'");
$result = $stmt->fetchAll();
echo "leave_requests table exists: " . (count($result) > 0 ? 'YES' : 'NO') . "\n";

// Check if leave_balances table exists
$stmt = $db->query("SHOW TABLES LIKE 'leave_balances'");
$result = $stmt->fetchAll();
echo "leave_balances table exists: " . (count($result) > 0 ? 'YES' : 'NO') . "\n";

// Check if employees table exists
$stmt = $db->query("SHOW TABLES LIKE 'employees'");
$result = $stmt->fetchAll();
echo "employees table exists: " . (count($result) > 0 ? 'YES' : 'NO') . "\n";

// Show structure of leave_requests if it exists
if (count($result) > 0) {
    echo "\nleave_requests structure:\n";
    $stmt = $db->query("DESCRIBE leave_requests");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo "  - " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
}

// Count existing leave requests
$stmt = $db->query("SELECT COUNT(*) as count FROM leave_requests");
$count = $stmt->fetch(PDO::FETCH_ASSOC);
echo "\nTotal leave requests: " . $count['count'] . "\n";

// Show recent leave requests
$stmt = $db->query("SELECT * FROM leave_requests ORDER BY created_at DESC LIMIT 5");
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "\nRecent leave requests:\n";
foreach ($requests as $req) {
    echo "  ID: " . $req['id'] . ", Employee: " . $req['employee_id'] . ", Type: " . $req['leave_type'] . ", Status: " . $req['status'] . "\n";
}
