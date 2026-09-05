<?php
require_once 'backend/config/Database.php';

$db = (new Database())->getConnection();

// Get a test employee and their current balance
$stmt = $db->query("SELECT id, hr_code, first_name, last_name FROM employees LIMIT 1");
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    echo "No employees found\n";
    exit;
}

echo "Testing with employee: " . $employee['first_name'] . " " . $employee['last_name'] . " (ID: " . $employee['id'] . ")\n\n";

// Check current annual balance
$stmt = $db->prepare("SELECT * FROM leave_balances WHERE employee_id = :eid AND leave_type = 'annual' AND year = :year");
$stmt->execute([':eid' => $employee['id'], ':year' => 2026]);
$balanceBefore = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Balance before approval:\n";
echo "  Entitled: " . $balanceBefore['entitled_days'] . "\n";
echo "  Used: " . $balanceBefore['used_days'] . "\n";
echo "  Remaining: " . $balanceBefore['remaining_days'] . "\n\n";

// Create a test leave request
$testData = [
    'employee_id' => $employee['id'],
    'leave_type' => 'annual',
    'start_date' => '2026-06-01',
    'end_date' => '2026-06-03',
    'days' => 3,
    'reason' => 'Test approval workflow',
    'status' => 'pending'
];

$stmt = $db->prepare("
    INSERT INTO leave_requests (employee_id, leave_type, start_date, end_date, days, reason, status)
    VALUES (:eid, :type, :start, :end, :days, :reason, :status)
");
$stmt->execute([
    ':eid' => $testData['employee_id'],
    ':type' => $testData['leave_type'],
    ':start' => $testData['start_date'],
    ':end' => $testData['end_date'],
    ':days' => $testData['days'],
    ':reason' => $testData['reason'],
    ':status' => $testData['status'],
]);

$leaveRequestId = $db->lastInsertId();
echo "Created leave request ID: " . $leaveRequestId . "\n";

// Approve the leave request (simulate the PATCH endpoint logic)
$stmt = $db->prepare("UPDATE leave_requests SET status = 'approved', approved_at = NOW() WHERE id = :id");
$stmt->execute([':id' => $leaveRequestId]);
echo "Approved leave request\n";

// Update balance (simulate the balance update logic)
$stmt = $db->prepare('SELECT employee_id, leave_type, days FROM leave_requests WHERE id = :id');
$stmt->execute([':id' => $leaveRequestId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row) {
    $year = 2026;
    $db->prepare("
        INSERT INTO leave_balances (employee_id, leave_type, year, entitled_days, used_days, remaining_days)
        VALUES (:eid, :type, :year, 21, :used, 21 - :used)
        ON DUPLICATE KEY UPDATE
            used_days = used_days + :used2,
            remaining_days = GREATEST(0, entitled_days - (used_days + :used2))
    ")->execute([
        ':eid' => $row['employee_id'],
        ':type' => $row['leave_type'],
        ':year' => $year,
        ':used' => $row['days'],
        ':used2' => $row['days'],
    ]);
    echo "Updated balance\n";
}

// Check balance after approval
$stmt = $db->prepare("SELECT * FROM leave_balances WHERE employee_id = :eid AND leave_type = 'annual' AND year = :year");
$stmt->execute([':eid' => $employee['id'], ':year' => 2026]);
$balanceAfter = $stmt->fetch(PDO::FETCH_ASSOC);

echo "\nBalance after approval:\n";
echo "  Entitled: " . $balanceAfter['entitled_days'] . "\n";
echo "  Used: " . $balanceAfter['used_days'] . "\n";
echo "  Remaining: " . $balanceAfter['remaining_days'] . "\n";

echo "\nCalculation check:\n";
echo "  Used increased by: " . ($balanceAfter['used_days'] - $balanceBefore['used_days']) . " (expected: 3)\n";
echo "  Remaining decreased by: " . ($balanceBefore['remaining_days'] - $balanceAfter['remaining_days']) . " (expected: 3)\n";

if (($balanceAfter['used_days'] - $balanceBefore['used_days']) == 3 && 
    ($balanceBefore['remaining_days'] - $balanceAfter['remaining_days']) == 3) {
    echo "\n✓ Balance calculation is CORRECT\n";
} else {
    echo "\n✗ Balance calculation is INCORRECT\n";
}
