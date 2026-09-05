<?php
declare(strict_types=1);

require_once '../config/Database.php';
require_once '../config/HrHelpers.php';

hr_cors_headers();

$db = (new Database())->getConnection();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        $id = (int)($_GET['id'] ?? 0);
        $year = (int)($_GET['period_year'] ?? 0);
        $month = (int)($_GET['period_month'] ?? 0);
        $employeeId = (int)($_GET['employee_id'] ?? 0);

        if ($id > 0) {
            $stmt = $db->prepare("
                SELECT p.*, e.hr_code, e.first_name, e.last_name, e.staff_type, e.bank_name, e.account_number
                FROM payroll p
                INNER JOIN employees e ON e.id = p.employee_id
                WHERE p.id = :id LIMIT 1
            ");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                hr_respond(false, 'Payslip not found', null, 404);
            }
            hr_respond(true, 'Payslip loaded', $row);
        }

        $sql = "
            SELECT p.*, e.hr_code, e.first_name, e.last_name, e.staff_type
            FROM payroll p
            INNER JOIN employees e ON e.id = p.employee_id
            WHERE 1=1
        ";
        $params = [];
        if ($year > 0) {
            $sql .= ' AND p.period_year = :year';
            $params[':year'] = $year;
        }
        if ($month > 0) {
            $sql .= ' AND p.period_month = :month';
            $params[':month'] = $month;
        }
        if ($employeeId > 0) {
            $sql .= ' AND p.employee_id = :eid';
            $params[':eid'] = $employeeId;
        }
        $sql .= ' ORDER BY p.period_year DESC, p.period_month DESC, e.last_name ASC';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        hr_respond(true, 'Payroll records', $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
        hr_require_auth(['admin', 'hr_manager']);
    }

    if ($method === 'POST') {
        $data = hr_request_data();
        
        // Debug: Log received data
        error_log('Payroll POST data: ' . json_encode($data));
        
        $employeeId = (int)($data['employee_id'] ?? 0);
        $year = (int)($data['period_year'] ?? date('Y'));
        $month = (int)($data['period_month'] ?? (int)date('n'));

        $basic = (float)($data['basic_salary'] ?? 0);
        $allowances = (float)($data['total_allowances'] ?? 0);
        $deductions = (float)($data['total_deductions'] ?? 0);
        $net = $basic + $allowances - $deductions;

        if ($employeeId <= 0) {
            error_log('Payroll error: Employee ID missing or invalid');
            hr_respond(false, 'Employee required', null, 400);
        }

        $auth = JWT::decode(preg_replace('/^Bearer\s+/i', '', hr_auth_header()));
        error_log('Payroll auth: ' . json_encode($auth));
        
        $details = json_encode($data['details'] ?? ['allowances' => [], 'deductions' => []]);

        // Validate that the employee exists
        $empCheck = $db->prepare('SELECT id FROM employees WHERE id = :eid LIMIT 1');
        $empCheck->execute([':eid' => $employeeId]);
        if (!$empCheck->fetch()) {
            error_log('Payroll error: Employee not found with ID: ' . $employeeId);
            hr_respond(false, 'Employee not found', null, 404);
        }

        // Validate that the user exists for generated_by (if provided)
        $generatedBy = null;
        if ($auth && isset($auth['id'])) {
            $userCheck = $db->prepare('SELECT id FROM users WHERE id = :uid LIMIT 1');
            $userCheck->execute([':uid' => $auth['id']]);
            if ($userCheck->fetch()) {
                $generatedBy = $auth['id'];
            }
        }

        $stmt = $db->prepare("
            INSERT INTO payroll (employee_id, period_year, period_month, basic_salary, total_allowances,
                total_deductions, net_pay, details, generated_by)
            VALUES (:eid, :year, :month, :basic, :allow, :deduct, :net, :details, :uid)
            ON DUPLICATE KEY UPDATE
                basic_salary = :basic2, total_allowances = :allow2, total_deductions = :deduct2,
                net_pay = :net2, details = :details2, generated_at = NOW()
        ");
        
        try {
            $stmt->execute([
                ':eid' => $employeeId,
                ':year' => $year,
                ':month' => $month,
                ':basic' => $basic,
                ':allow' => $allowances,
                ':deduct' => $deductions,
                ':net' => $net,
                ':details' => $details,
                ':uid' => $generatedBy,
                ':basic2' => $basic,
                ':allow2' => $allowances,
                ':deduct2' => $deductions,
                ':net2' => $net,
                ':details2' => $details,
            ]);
            error_log('Payroll saved successfully for employee ID: ' . $employeeId);
            hr_respond(true, 'Payroll saved', ['net_pay' => $net], 201);
        } catch (PDOException $e) {
            error_log('Payroll database error: ' . $e->getMessage());
            hr_respond(false, 'Database error: ' . $e->getMessage(), null, 500);
        }
    }

    if ($method === 'DELETE') {
        $id = (int)($_GET['id'] ?? 0);
        $db->prepare('DELETE FROM payroll WHERE id = :id')->execute([':id' => $id]);
        hr_respond(true, 'Payroll record deleted');
    }

    hr_respond(false, 'Method not allowed', null, 405);
} catch (Throwable $e) {
    hr_respond(false, $e->getMessage(), null, 500);
}
