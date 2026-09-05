<?php
declare(strict_types=1);

require_once '../config/Database.php';
require_once '../config/HrHelpers.php';

hr_cors_headers();

$db = (new Database())->getConnection();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function leave_calc_days(string $start, string $end): float
{
    $s = new DateTime($start);
    $e = new DateTime($end);
    return (float)($e->diff($s)->days + 1);
}

try {
    if ($method === 'GET') {
        $action = trim((string)($_GET['action'] ?? 'requests'));
        $employeeId = (int)($_GET['employee_id'] ?? 0);
        $status = trim((string)($_GET['status'] ?? ''));

        if ($action === 'balances') {
            $year = (int)($_GET['year'] ?? date('Y'));
            $sql = 'SELECT lb.*, e.hr_code, e.first_name, e.last_name FROM leave_balances lb
                INNER JOIN employees e ON e.id = lb.employee_id WHERE lb.year = :year';
            $params = [':year' => $year];
            if ($employeeId > 0) {
                $sql .= ' AND lb.employee_id = :eid';
                $params[':eid'] = $employeeId;
            }
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            hr_respond(true, 'Leave balances', $stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        $sql = "
            SELECT lr.*, e.hr_code, e.first_name, e.last_name, e.staff_type
            FROM leave_requests lr
            INNER JOIN employees e ON e.id = lr.employee_id
            WHERE 1=1
        ";
        $params = [];
        if ($employeeId > 0) {
            $sql .= ' AND lr.employee_id = :eid';
            $params[':eid'] = $employeeId;
        }
        if ($status !== '') {
            $sql .= ' AND lr.status = :status';
            $params[':status'] = $status;
        }
        $sql .= ' ORDER BY lr.created_at DESC';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        hr_respond(true, 'Leave requests', $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
        hr_require_auth(['admin', 'hr_manager', 'academic_office', 'staff']);
    }

    if ($method === 'POST') {
        $data = hr_request_data();
        error_log('Leave POST data: ' . json_encode($data));
        
        $employeeId = (int)($data['employee_id'] ?? 0);
        $start = trim((string)($data['start_date'] ?? ''));
        $end = trim((string)($data['end_date'] ?? ''));

        if ($employeeId <= 0 || $start === '' || $end === '') {
            error_log('Leave validation failed: employee_id=' . $employeeId . ', start=' . $start . ', end=' . $end);
            hr_respond(false, 'Employee and dates are required', null, 400);
        }

        $days = leave_calc_days($start, $end);
        $requestedBy = null;
        try {
            $auth = hr_auth_header() ? JWT::decode(preg_replace('/^Bearer\s+/i', '', hr_auth_header())) : null;
            $requestedBy = $auth['id'] ?? null;
        } catch (Throwable $e) {
            // Token decode failed, proceed without requested_by
            error_log('JWT decode failed: ' . $e->getMessage());
        }
        
        $stmt = $db->prepare("
            INSERT INTO leave_requests (employee_id, leave_type, start_date, end_date, days, reason, status, requested_by)
            VALUES (:eid, :type, :start, :end, :days, :reason, 'pending', :requested_by)
        ");
        
        $params = [
            ':eid' => $employeeId,
            ':type' => $data['leave_type'] ?? 'annual',
            ':start' => $start,
            ':end' => $end,
            ':days' => $days,
            ':reason' => trim((string)($data['reason'] ?? '')),
            ':requested_by' => $requestedBy,
        ];
        
        error_log('Leave insert params: ' . json_encode($params));
        
        try {
            $stmt->execute($params);
            $newId = (int)$db->lastInsertId();
            error_log('Leave request inserted with ID: ' . $newId);
            hr_respond(true, 'Leave request submitted', ['id' => $newId], 201);
        } catch (Throwable $e) {
            error_log('Leave insert failed: ' . $e->getMessage());
            hr_respond(false, 'Failed to save leave request: ' . $e->getMessage(), null, 500);
        }
    }

    if ($method === 'PATCH') {
        $data = hr_request_data();
        $id = (int)($data['id'] ?? 0);
        $status = trim((string)($data['status'] ?? ''));

        if ($id <= 0 || !in_array($status, ['approved', 'rejected', 'cancelled'], true)) {
            hr_respond(false, 'Invalid approval action', null, 400);
        }

        $auth = hr_require_auth(['admin', 'hr_manager']);
        $userId = $auth['id'] ?? null;

        $stmt = $db->prepare("
            UPDATE leave_requests SET status = :status, approved_by = :uid, approved_at = NOW() WHERE id = :id
        ");
        $stmt->execute([':status' => $status, ':uid' => $userId, ':id' => $id]);

        if ($status === 'approved') {
            $req = $db->prepare('SELECT employee_id, leave_type, days FROM leave_requests WHERE id = :id');
            $req->execute([':id' => $id]);
            $row = $req->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $year = (int)date('Y');
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
                
                // Fix: Recalculate remaining days after the update
                $db->prepare("
                    UPDATE leave_balances 
                    SET remaining_days = GREATEST(0, entitled_days - used_days)
                    WHERE employee_id = :eid AND leave_type = :type AND year = :year
                ")->execute([
                    ':eid' => $row['employee_id'],
                    ':type' => $row['leave_type'],
                    ':year' => $year,
                ]);
            }
        }

        hr_respond(true, 'Leave request updated');
    }

    if ($method === 'DELETE') {
        $action = trim((string)($_GET['action'] ?? 'request'));
        $id = (int)($_GET['id'] ?? 0);

        if ($id <= 0) {
            hr_respond(false, 'ID is required', null, 400);
        }

        hr_require_auth(['admin', 'hr_manager']);

        if ($action === 'request') {
            // Get the request details before deleting to potentially adjust balance
            $req = $db->prepare('SELECT employee_id, leave_type, days, status FROM leave_requests WHERE id = :id');
            $req->execute([':id' => $id]);
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
                $stmt->execute([':id' => $id]);
                
                hr_respond(true, 'Leave request deleted');
            } else {
                hr_respond(false, 'Leave request not found', null, 404);
            }
        } elseif ($action === 'balance') {
            $stmt = $db->prepare('DELETE FROM leave_balances WHERE id = :id');
            $stmt->execute([':id' => $id]);
            
            if ($stmt->rowCount() > 0) {
                hr_respond(true, 'Leave balance deleted');
            } else {
                hr_respond(false, 'Leave balance not found', null, 404);
            }
        } else {
            hr_respond(false, 'Invalid action. Use action=request or action=balance', null, 400);
        }
    }

    hr_respond(false, 'Method not allowed', null, 405);
} catch (Throwable $e) {
    hr_respond(false, $e->getMessage(), null, 500);
}
