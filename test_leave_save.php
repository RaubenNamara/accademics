<?php
require_once 'backend/config/Database.php';

$db = (new Database())->getConnection();

// Check if there are employees
$stmt = $db->query("SELECT id, hr_code, first_name, last_name FROM employees LIMIT 5");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Employees in database:\n";
foreach ($employees as $emp) {
    echo "  ID: " . $emp['id'] . ", HR Code: " . $emp['hr_code'] . ", Name: " . $emp['first_name'] . " " . $emp['last_name'] . "\n";
}

if (count($employees) === 0) {
    echo "\nNo employees found! Cannot create leave request without employee.\n";
    exit;
}

// Test inserting a leave request directly
$testEmployeeId = $employees[0]['id'];
$testData = [
    'employee_id' => $testEmployeeId,
    'leave_type' => 'annual',
    'start_date' => date('Y-m-d'),
    'end_date' => date('Y-m-d', strtotime('+2 days')),
    'days' => 3,
    'reason' => 'Test leave request',
    'status' => 'pending',
    'requested_by' => null
];

echo "\nAttempting to insert test leave request...\n";
echo "Data: " . json_encode($testData) . "\n";

try {
    $stmt = $db->prepare("
        INSERT INTO leave_requests (employee_id, leave_type, start_date, end_date, days, reason, status, requested_by)
        VALUES (:eid, :type, :start, :end, :days, :reason, :status, :requested_by)
    ");
    $stmt->execute([
        ':eid' => $testData['employee_id'],
        ':type' => $testData['leave_type'],
        ':start' => $testData['start_date'],
        ':end' => $testData['end_date'],
        ':days' => $testData['days'],
        ':reason' => $testData['reason'],
        ':status' => $testData['status'],
        ':requested_by' => $testData['requested_by'],
    ]);
    
    $newId = $db->lastInsertId();
    echo "SUCCESS: Leave request inserted with ID: " . $newId . "\n";
    
    // Verify the insert
    $stmt = $db->prepare("SELECT * FROM leave_requests WHERE id = :id");
    $stmt->execute([':id' => $newId]);
    $inserted = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Inserted record: " . json_encode($inserted) . "\n";
    
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
