<?php
require_once 'backend/config/Database.php';

$db = (new Database())->getConnection();
$year = (int)date('Y');

// Get all employees
$stmt = $db->query("SELECT id, hr_code, first_name, last_name FROM employees");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

$leaveTypes = ['annual', 'sick', 'maternity', 'paternity', 'compassionate', 'unpaid', 'other'];
$entitledDays = [
    'annual' => 21,
    'sick' => 10,
    'maternity' => 60,
    'paternity' => 14,
    'compassionate' => 5,
    'unpaid' => 0,
    'other' => 0
];

echo "Initializing leave balances for year $year...\n\n";

$count = 0;
foreach ($employees as $emp) {
    foreach ($leaveTypes as $type) {
        // Check if balance already exists
        $check = $db->prepare("SELECT id FROM leave_balances WHERE employee_id = :eid AND leave_type = :type AND year = :year");
        $check->execute([':eid' => $emp['id'], ':type' => $type, ':year' => $year]);
        $existing = $check->fetch();
        
        if (!$existing) {
            $entitled = $entitledDays[$type];
            $insert = $db->prepare("
                INSERT INTO leave_balances (employee_id, leave_type, year, entitled_days, used_days, remaining_days)
                VALUES (:eid, :type, :year, :entitled, 0, :entitled)
            ");
            $insert->execute([
                ':eid' => $emp['id'],
                ':type' => $type,
                ':year' => $year,
                ':entitled' => $entitled
            ]);
            $count++;
            echo "Created balance for " . $emp['first_name'] . " " . $emp['last_name'] . " - " . $type . " (" . $entitled . " days)\n";
        }
    }
}

echo "\nTotal balances created: $count\n";

// Verify
$stmt = $db->prepare("SELECT COUNT(*) as count FROM leave_balances WHERE year = :year");
$stmt->execute([':year' => $year]);
$total = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Total balances in database for year $year: " . $total['count'] . "\n";
