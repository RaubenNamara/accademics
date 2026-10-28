<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/ApiGuard.php';
api_guard();
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

    // School at a glance: the open term, students, lesson time, reviews and who is away. Each part is optional.
    $school = ['term' => null, 'students' => null, 'lessons' => null, 'plans' => null, 'exams_open' => null, 'assignments' => null, 'on_leave_today' => []];
    $sid = null;
    try {
        $t = $db->query('SELECT id, academic_year, term, start_date, end_date FROM academic_sessions WHERE is_active = 1 ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        if ($t) {
            $sid = (int)$t['id'];
            $start = $t['start_date'] ? strtotime((string)$t['start_date']) : null;
            $end = $t['end_date'] ? strtotime((string)$t['end_date']) : null;
            $weeks = $start && $end ? max(1, (int)ceil(($end - $start + 86400) / (7 * 86400))) : null;
            $week = $start ? max(1, (int)floor((time() - $start) / (7 * 86400)) + 1) : null;
            $school['term'] = ['year' => (int)$t['academic_year'], 'term' => (int)$t['term'], 'start' => $t['start_date'], 'end' => $t['end_date'],
                'weeks' => $weeks, 'week' => $week && $weeks ? min($week, $weeks) : $week];
        }
    } catch (Throwable $e) {
    }
    try {
        $hasStatus = (bool)$db->query("SHOW COLUMNS FROM students LIKE 'status'")->fetch();
        $where = $hasStatus ? "WHERE (status = 'active' OR status IS NULL)" : '';
        $row = $db->query("SELECT COUNT(*) total, SUM(class REGEXP '^S ?[1-4]') o_level, SUM(class REGEXP '^S ?[56]') a_level FROM students $where")->fetch(PDO::FETCH_ASSOC);
        $school['students'] = ['total' => (int)$row['total'], 'o_level' => (int)$row['o_level'], 'a_level' => (int)$row['a_level']];
    } catch (Throwable $e) {
    }
    if ($school['term']) {
        try {
            $q = $db->prepare('SELECT week_number, COUNT(*) lessons, SUM(expected_minutes) expected, SUM(actual_minutes) actual, SUM(minutes_lost) lost
                FROM teacher_lesson_attendance WHERE year = :y AND term = :t GROUP BY week_number ORDER BY week_number');
            $q->execute([':y' => $school['term']['year'], ':t' => $school['term']['term']]);
            $byWeek = array_map(fn($r) => ['week' => (int)$r['week_number'], 'lessons' => (int)$r['lessons'], 'expected' => (int)$r['expected'], 'actual' => (int)$r['actual'], 'lost' => (int)$r['lost']], $q->fetchAll(PDO::FETCH_ASSOC));
            $c = $db->prepare('SELECT COALESCE(SUM(lc.minutes_compensated), 0) FROM lesson_compensations lc JOIN teacher_lesson_attendance a ON a.id = lc.lesson_monitoring_id WHERE a.year = :y AND a.term = :t');
            $c->execute([':y' => $school['term']['year'], ':t' => $school['term']['term']]);
            $school['lessons'] = [
                'lessons' => array_sum(array_column($byWeek, 'lessons')),
                'expected' => array_sum(array_column($byWeek, 'expected')),
                'actual' => array_sum(array_column($byWeek, 'actual')),
                'lost' => array_sum(array_column($byWeek, 'lost')),
                'compensated' => (int)$c->fetchColumn(),
                'by_week' => $byWeek,
            ];
        } catch (Throwable $e) {
        }
    }
    try {
        $hasSeen = (bool)$db->query("SHOW COLUMNS FROM lesson_plans LIKE 'staff_seen_at'")->fetch();
        $row = $db->query("SELECT COUNT(*) waiting" . ($hasSeen ? ', SUM(staff_seen_at IS NULL) new_plans' : ', 0 new_plans') . " FROM lesson_plans WHERE status = 'submitted'")->fetch(PDO::FETCH_ASSOC);
        $school['plans'] = ['waiting' => (int)$row['waiting'], 'new' => (int)$row['new_plans']];
    } catch (Throwable $e) {
    }
    if ($sid) {
        try {
            $school['exams_open'] = (int)$db->query("SELECT COUNT(*) FROM exams WHERE academic_session_id = $sid AND status = 'open'")->fetchColumn();
        } catch (Throwable $e) {
        }
        try {
            $active = (int)$db->query('SELECT COUNT(*) FROM teachers WHERE is_active = 1 OR is_active IS NULL')->fetchColumn();
            $with = (int)$db->query("SELECT COUNT(DISTINCT lr.teacher_id) FROM lesson_requirements lr JOIN teachers t ON t.id = lr.teacher_id AND (t.is_active = 1 OR t.is_active IS NULL) WHERE lr.academic_session_id = $sid")->fetchColumn();
            $school['assignments'] = ['teachers' => $active, 'with_classes' => $with];
        } catch (Throwable $e) {
        }
    }
    try {
        $school['on_leave_today'] = $db->query("SELECT TRIM(CONCAT(e.first_name, ' ', e.last_name)) name, lr.leave_type, lr.end_date
            FROM leave_requests lr JOIN employees e ON e.id = lr.employee_id
            WHERE lr.status = 'approved' AND CURDATE() BETWEEN lr.start_date AND lr.end_date ORDER BY lr.end_date LIMIT 6")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
    }
    $stats['school'] = $school;

    hr_respond(true, 'HR dashboard loaded', $stats);
} catch (Throwable $e) {
    hr_respond(false, $e->getMessage(), null, 500);
}
