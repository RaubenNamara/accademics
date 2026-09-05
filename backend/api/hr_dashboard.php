<?php
declare(strict_types=1);

require_once '../config/Database.php';
require_once '../config/HrHelpers.php';

hr_cors_headers('GET, OPTIONS');

try {
    $db = (new Database())->getConnection();
    $year = (int)($_GET['year'] ?? date('Y'));

    $stats = [
        'total_staff' => 0,
        'teaching_staff' => 0,
        'non_teaching_staff' => 0,
        'active_staff' => 0,
        'departments' => [],
        'payroll_summary' => ['total_net' => 0, 'records' => 0, 'month' => (int)date('n'), 'year' => $year],
        'leave_summary' => ['pending' => 0, 'approved' => 0, 'rejected' => 0],
        'recent_staff' => [],
    ];

    try {
        $stmt = $db->query("SELECT COUNT(*) AS c FROM employees");
        $stats['total_staff'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);

        $stmt = $db->query("SELECT COUNT(*) AS c FROM employees WHERE staff_type = 'teaching'");
        $stats['teaching_staff'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);

        $stmt = $db->query("SELECT COUNT(*) AS c FROM employees WHERE staff_type = 'non_teaching'");
        $stats['non_teaching_staff'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);

        $stmt = $db->query("SELECT COUNT(*) AS c FROM employees WHERE status = 'active'");
        $stats['active_staff'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
    } catch (Throwable $e) {
        $stmt = $db->query('SELECT COUNT(*) AS c FROM teachers');
        $stats['teaching_staff'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
        $stats['total_staff'] = $stats['teaching_staff'];
    }

    try {
        $deptStmt = $db->query("
            SELECT d.name, COUNT(e.id) AS staff_count
            FROM departments d
            LEFT JOIN employees e ON e.department_id = d.id AND e.status = 'active'
            GROUP BY d.id, d.name
            ORDER BY staff_count DESC
        ");
        $stats['departments'] = $deptStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        $stats['departments'] = [];
    }

    try {
        $payStmt = $db->prepare("
            SELECT COUNT(*) AS records, COALESCE(SUM(net_pay), 0) AS total_net
            FROM payroll WHERE period_year = :y AND period_month = :m
        ");
        $payStmt->execute([':y' => $year, ':m' => (int)date('n')]);
        $pay = $payStmt->fetch(PDO::FETCH_ASSOC);
        $stats['payroll_summary'] = [
            'records' => (int)($pay['records'] ?? 0),
            'total_net' => (float)($pay['total_net'] ?? 0),
            'month' => (int)date('n'),
            'year' => $year,
        ];
    } catch (Throwable $e) {
    }

    try {
        $leaveStmt = $db->query("
            SELECT status, COUNT(*) AS c FROM leave_requests GROUP BY status
        ");
        while ($row = $leaveStmt->fetch(PDO::FETCH_ASSOC)) {
            $status = (string)$row['status'];
            if ($status === 'pending') {
                $stats['leave_summary']['pending'] = (int)$row['c'];
            } elseif ($status === 'approved') {
                $stats['leave_summary']['approved'] = (int)$row['c'];
            } elseif ($status === 'rejected') {
                $stats['leave_summary']['rejected'] = (int)$row['c'];
            }
        }
    } catch (Throwable $e) {
    }

    try {
        $recentStmt = $db->query("
            SELECT e.id, e.hr_code, e.first_name, e.last_name, e.staff_type, e.status, e.created_at,
                   d.name AS department_name
            FROM employees e
            LEFT JOIN departments d ON d.id = e.department_id
            ORDER BY e.created_at DESC
            LIMIT 8
        ");
        $stats['recent_staff'] = $recentStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        $recentStmt = $db->query("
            SELECT id, teacher_code AS hr_code, full_name, is_active, created_at
            FROM teachers ORDER BY created_at DESC LIMIT 8
        ");
        $rows = $recentStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$r) {
            $names = hr_split_name((string)($r['full_name'] ?? ''));
            $r['first_name'] = $names['first'];
            $r['last_name'] = $names['last'];
            $r['staff_type'] = 'teaching';
            $r['status'] = ((int)($r['is_active'] ?? 1)) === 1 ? 'active' : 'inactive';
            unset($r['full_name'], $r['is_active']);
        }
        $stats['recent_staff'] = $rows;
    }

    hr_respond(true, 'HR dashboard loaded', $stats);
} catch (Throwable $e) {
    hr_respond(false, $e->getMessage(), null, 500);
}
