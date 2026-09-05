<?php
require_once 'backend/config/Database.php';

$db = (new Database())->getConnection();

// Test deleting a leave request
echo "Testing DELETE for leave request...\n";

// Get a pending request to delete
$stmt = $db->query("SELECT id, employee_id, leave_type, days, status FROM leave_requests WHERE status = 'pending' LIMIT 1");
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if ($request) {
    echo "Found request ID: " . $request['id'] . " (status: " . $request['status'] . ")\n";
    
    // Simulate the DELETE endpoint logic
    $req = $db->prepare('SELECT employee_id, leave_type, days, status FROM leave_requests WHERE id = :id');
    $req->execute([':id' => $request['id']]);
    $row = $req->fetch(PDO::FETCH_ASSOC);
    
    if ($row) {
        // If the request was approved, adjust the balance
        if ($row['status'] === 'approved') {
            $year = (int)date('Y');
            $db->prepare("
                UPDATE leave_balances
                SET used_days = GREATEST(0, used_days - :days),
                    remaining_days = LEAST(entitled_days, remaining_days + :days)
                WHERE employee_id = :eid AND leave_type = :type AND year = :year
            ")->execute([
                ':days' => $row['days'],
                ':eid' => $row['employee_id'],
                ':type' => $row['leave_type'],
                ':year' => $year,
            ]);
        }
        
        // Delete the request
        $stmt = $db->prepare('DELETE FROM leave_requests WHERE id = :id');
        $stmt->execute([':id' => $request['id']]);
        
        echo "✓ Request deleted successfully\n";
    }
} else {
    echo "No pending requests found to delete\n";
    
    // Try to delete any request
    $stmt = $db->query("SELECT id, status FROM leave_requests LIMIT 1");
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($request) {
        echo "Found request ID: " . $request['id'] . " (status: " . $request['status'] . ")\n";
        
        // Delete it
        $stmt = $db->prepare('DELETE FROM leave_requests WHERE id = :id');
        $stmt->execute([':id' => $request['id']]);
        echo "✓ Request deleted successfully\n";
    } else {
        echo "No requests found at all\n";
    }
}

// Test deleting a leave balance
echo "\nTesting DELETE for leave balance...\n";

// Get a balance to delete
$stmt = $db->query("SELECT id, employee_id, leave_type FROM leave_balances LIMIT 1");
$balance = $stmt->fetch(PDO::FETCH_ASSOC);

if ($balance) {
    echo "Found balance ID: " . $balance['id'] . " for employee " . $balance['employee_id'] . " - " . $balance['leave_type'] . "\n";
    
    // Delete the balance
    $stmt = $db->prepare('DELETE FROM leave_balances WHERE id = :id');
    $stmt->execute([':id' => $balance['id']]);
    
    echo "✓ Balance deleted successfully\n";
} else {
    echo "No balances found to delete\n";
}

// Verify counts
echo "\nCurrent counts:\n";
$stmt = $db->query("SELECT COUNT(*) as count FROM leave_requests");
$reqCount = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Leave requests: " . $reqCount['count'] . "\n";

$stmt = $db->query("SELECT COUNT(*) as count FROM leave_balances");
$balCount = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Leave balances: " . $balCount['count'] . "\n";
