<?php
require_once 'backend/config/Database.php';

$db = (new Database())->getConnection();

// Check leave_balances table structure
$stmt = $db->query("DESCRIBE leave_balances");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "leave_balances structure:\n";
foreach ($columns as $col) {
    echo "  - " . $col['Field'] . " (" . $col['Type'] . ")\n";
}

// Check current data in leave_balances
$stmt = $db->query("SELECT lb.*, e.hr_code, e.first_name, e.last_name FROM leave_balances lb INNER JOIN employees e ON e.id = lb.employee_id");
$balances = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "\nCurrent balances (" . count($balances) . " records):\n";
foreach ($balances as $bal) {
    echo "  Employee: " . $bal['first_name'] . " " . $bal['last_name'] . " (" . $bal['hr_code'] . ")\n";
    echo "    Type: " . $bal['leave_type'] . ", Year: " . $bal['year'] . "\n";
    echo "    Entitled: " . $bal['entitled_days'] . ", Used: " . $bal['used_days'] . ", Remaining: " . $bal['remaining_days'] . "\n";
}

// Check if there are approved leave requests
$stmt = $db->query("SELECT lr.*, e.first_name, e.last_name FROM leave_requests lr INNER JOIN employees e ON e.id = lr.employee_id WHERE lr.status = 'approved'");
$approved = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "\nApproved leave requests (" . count($approved) . " records):\n";
foreach ($approved as $req) {
    echo "  ID: " . $req['id'] . ", Employee: " . $req['first_name'] . " " . $req['last_name'] . "\n";
    echo "    Type: " . $req['leave_type'] . ", Days: " . $req['days'] . ", Dates: " . $req['start_date'] . " to " . $req['end_date'] . "\n";
}
