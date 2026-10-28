<?php
/**
 * Teacher portal (classroom teachers' own data only).
 *
 * GET ?view=me           profile, today's lessons, what they teach, open exams, plan counts
 * GET ?view=timetable    their lessons (the published timetable when there is one)
 * GET ?view=performance  duty scores, lesson observations, attendance summary
 * GET ?view=attendance   their lesson attendance records [&year&term]
 * GET ?view=leave        their leave requests and this year's balances
 * POST ?action=leave        {leave_type, start_date, end_date, reason}   request leave (goes to HR)
 * POST ?action=cancel-leave {id}                                           withdraw a pending request
 */
declare(strict_types=1);

require_once '../config/Database.php';
require_once '../config/HrHelpers.php';
require_once '../config/SchoolService.php';

hr_cors_headers();
$db = (new Database())->getConnection();

try {
    tt_ensure_schema($db);
    sf_ensure_schema($db);
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $me = sf_require_teacher($db);
    $teacher = $me['teacher'];
    $tid = (int)$teacher['id'];
    $employeeId = (int)($db->query("SELECT employee_id FROM teachers WHERE id = $tid")->fetchColumn() ?: 0);

    if ($method === 'POST') {
        $action = (string)($_GET['action'] ?? '');
        $data = hr_request_data();
        if (!$employeeId) {
            throw new TtException('Your teacher record is not linked to a staff record yet. Ask HR to link it.', 409);
        }
        if ($action === 'leave') {
            $type = (string)($data['leave_type'] ?? '');
            $start = trim((string)($data['start_date'] ?? ''));
            $end = trim((string)($data['end_date'] ?? ''));
            $reason = mb_substr(trim((string)($data['reason'] ?? '')), 0, 1000);
            $errors = [];
            if (!in_array($type, SF_LEAVE_TYPES, true)) {
                $errors['leave_type'] = 'Choose the type of leave';
            }
            $validDate = static fn($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && DateTime::createFromFormat('Y-m-d', $d) !== false;
            if (!$validDate($start)) {
                $errors['start_date'] = 'Choose the first day of leave';
            }
            if (!$validDate($end)) {
                $errors['end_date'] = 'Choose the last day of leave';
            } elseif (!isset($errors['start_date']) && $end < $start) {
                $errors['end_date'] = 'The last day cannot be before the first day';
            }
            if (!isset($errors['start_date']) && $start < date('Y-m-d', strtotime('-30 days'))) {
                $errors['start_date'] = 'Leave more than 30 days ago cannot be requested here, talk to HR';
            }
            if ($reason === '') {
                $errors['reason'] = 'Give a short reason';
            }
            if (!$errors) {
                $overlap = $db->prepare("SELECT start_date, end_date FROM leave_requests WHERE employee_id = :e AND status IN ('pending','approved')
                    AND start_date <= :end AND end_date >= :start LIMIT 1");
                $overlap->execute([':e' => $employeeId, ':start' => $start, ':end' => $end]);
                if ($o = $overlap->fetch(PDO::FETCH_ASSOC)) {
                    $errors['start_date'] = "You already have leave from {$o['start_date']} to {$o['end_date']} that overlaps these dates";
                }
            }
            if ($errors) {
                throw new TtException((string)reset($errors), 422, ['errors' => $errors]);
            }
            $days = (float)((new DateTime($start))->diff(new DateTime($end))->days + 1);
            $db->prepare("INSERT INTO leave_requests (employee_id, leave_type, start_date, end_date, days, reason, status, requested_by)
                VALUES (:e, :t, :s, :en, :d, :r, 'pending', :by)")
                ->execute([':e' => $employeeId, ':t' => $type, ':s' => $start, ':en' => $end, ':d' => $days, ':r' => $reason, ':by' => (int)$me['payload']['id']]);
            hr_respond(true, 'Leave request sent to HR', ['id' => (int)$db->lastInsertId(), 'days' => $days], 201);
        }
        if ($action === 'cancel-leave') {
            $stmt = $db->prepare("UPDATE leave_requests SET status = 'cancelled' WHERE id = :id AND employee_id = :e AND status = 'pending'");
            $stmt->execute([':id' => (int)($data['id'] ?? 0), ':e' => $employeeId]);
            if (!$stmt->rowCount()) {
                throw new TtException('Only your own pending requests can be withdrawn', 409);
            }
            hr_respond(true, 'Leave request withdrawn');
        }
        hr_respond(false, 'Unknown action', null, 400);
    }
    if ($method !== 'GET') {
        hr_respond(false, 'Method not allowed', null, 405);
    }
    [$sid, $year, $term] = tt_resolve_session($db, $_GET);
    $view = (string)($_GET['view'] ?? 'me');

    $lessons = static function () use ($db, $sid, $year, $term, $tid): array {
        $published = $sid ? tt_published_entries($db, $sid) : null;
        if ($published !== null) {
            $rows = array_values(array_filter($published, fn($e) => (int)($e['teacher_id'] ?? 0) === $tid));
            return ['entries' => $rows, 'source' => 'published'];
        }
        return ['entries' => tt_fetch_entries($db, $year, $term, ['teacher_id' => $tid]), 'source' => 'draft'];
    };

    if ($view === 'me') {
        $today = date('l');
        $tt = $lessons();
        $todayLessons = array_values(array_filter($tt['entries'], fn($e) => $e['day_of_week'] === $today));
        usort($todayLessons, fn($a, $b) => (int)$a['period_number'] <=> (int)$b['period_number']);
        $plans = $db->prepare('SELECT status, COUNT(*) n FROM lesson_plans WHERE teacher_id = :t GROUP BY status');
        $plans->execute([':t' => $tid]);
        $openExams = $sid ? (int)$db->query("SELECT COUNT(*) FROM exams WHERE academic_session_id = $sid AND status = 'open'")->fetchColumn() : 0;
        $att = $db->prepare('SELECT COALESCE(SUM(expected_minutes),0) expected, COALESCE(SUM(minutes_lost),0) lost, COUNT(*) lessons FROM teacher_lesson_attendance WHERE teacher_id = :t AND year = :y AND term = :tm');
        $att->execute([':t' => $tid, ':y' => $year, ':tm' => $term]);
        hr_respond(true, 'My overview', [
            'teacher' => ['id' => $tid, 'full_name' => $teacher['full_name'], 'teacher_code' => $teacher['teacher_code'], 'email' => $teacher['email'],
                'subject' => $teacher['subject'], 'department' => $teacher['department'], 'position' => $teacher['position']],
            'session' => ['id' => $sid, 'academic_year' => $year, 'term' => $term],
            // Earlier terms reopened under Terms & Years: marks can still be entered for them.
            'reopened_sessions' => (static function () use ($db): array {
                try {
                    return array_map(fn($r) => ['id' => (int)$r['id'], 'academic_year' => (int)$r['academic_year'], 'term' => (int)$r['term']],
                        $db->query('SELECT id, academic_year, term FROM academic_sessions WHERE is_active = 0 AND is_archived = 0 AND reopened_at IS NOT NULL ORDER BY academic_year DESC, term DESC')->fetchAll(PDO::FETCH_ASSOC));
                } catch (Throwable $e) {
                    return [];
                }
            })(),
            'today' => $today,
            'today_lessons' => $todayLessons,
            'timetable_source' => $tt['source'],
            'periods_per_week' => array_sum(array_map(fn($e) => max(1, (int)$e['spans_periods']), array_filter($tt['entries'], fn($e) => ($e['entry_type'] ?? 'lesson') === 'lesson'))),
            'assignments' => $sid ? sf_teacher_assignments($db, $tid, $sid) : [],
            // Classes and subjects the teacher may enter marks for (timetable + department).
            'mark_scope' => $sid ? sf_teacher_mark_scope($db, $tid, $sid) : [],
            'marks_progress' => $sid ? sf_teacher_marks_progress($db, $tid, $sid) : [],
            'open_exams' => $openExams,
            'plans' => array_column($plans->fetchAll(PDO::FETCH_ASSOC), 'n', 'status'),
            'attendance' => $att->fetch(PDO::FETCH_ASSOC),
        ]);
    }

    if ($view === 'timetable') {
        $tt = $lessons();
        hr_respond(true, 'My timetable', $tt + ['periods' => array_values(tt_load_periods($db)), 'days' => TT_DAYS, 'session' => ['id' => $sid, 'academic_year' => $year, 'term' => $term]]);
    }

    if ($view === 'performance') {
        $duty = $db->prepare('SELECT year, term, week_number, punctuality, supervision, cleanliness, time_keeping, participation, total_score, percentage, comment, areas_of_improvement, general_remarks, supervisor, created_at
            FROM duty_performance WHERE teacher_id = :t ORDER BY year DESC, term DESC, week_number DESC');
        $duty->execute([':t' => $tid]);
        $obs = $db->prepare('SELECT o.year, o.term, o.round, o.total_score, o.calculated_rating, o.performance_category, o.strengths_observed, o.general_comment, o.areas_for_improvement,
                s.subject_name, c.full_class_name, o.created_at
            FROM lesson_observations o LEFT JOIN subjects_new s ON s.id = o.subject_id LEFT JOIN classes c ON c.id = o.class_id
            WHERE o.teacher_id = :t ORDER BY o.year DESC, o.term DESC, o.round DESC');
        $obs->execute([':t' => $tid]);
        $att = $db->prepare('SELECT year, term, COUNT(*) lessons, COALESCE(SUM(expected_minutes),0) expected, COALESCE(SUM(actual_minutes),0) actual, COALESCE(SUM(minutes_lost),0) lost
            FROM teacher_lesson_attendance WHERE teacher_id = :t GROUP BY year, term ORDER BY year DESC, term DESC');
        $att->execute([':t' => $tid]);
        $comp = $db->prepare("SELECT COALESCE(SUM(minutes_compensated),0) FROM lesson_compensations WHERE teacher_id = :t");
        $comp->execute([':t' => $tid]);
        $dutyRows = $duty->fetchAll(PDO::FETCH_ASSOC);
        $pcts = array_map('floatval', array_filter(array_column($dutyRows, 'percentage'), fn($v) => $v !== null));
        hr_respond(true, 'My performance', [
            'duty' => $dutyRows,
            'duty_average' => $pcts ? round(array_sum($pcts) / count($pcts), 1) : null,
            'observations' => $obs->fetchAll(PDO::FETCH_ASSOC),
            'attendance_by_term' => $att->fetchAll(PDO::FETCH_ASSOC),
            'minutes_compensated' => (int)$comp->fetchColumn(),
        ]);
    }

    if ($view === 'attendance') {
        $y = (int)($_GET['year'] ?? $year);
        $t = (int)($_GET['term'] ?? $term);
        // With the time made up for each lesson, as on the admin's Lesson Monitoring.
        $stmt = $db->prepare('SELECT a.id, a.attendance_date, a.day_of_week, a.week_number, a.subject, a.class, a.stream, a.time_in, a.time_out,
                a.expected_minutes, a.actual_minutes, a.minutes_lost,
                (SELECT COALESCE(SUM(lc.minutes_compensated), 0) FROM lesson_compensations lc WHERE lc.lesson_monitoring_id = a.id) AS minutes_compensated,
                (SELECT lc.status FROM lesson_compensations lc WHERE lc.lesson_monitoring_id = a.id ORDER BY lc.created_at DESC LIMIT 1) AS compensation_status
            FROM teacher_lesson_attendance a WHERE a.teacher_id = :tid AND a.year = :y AND a.term = :t ORDER BY a.attendance_date DESC, a.time_in DESC LIMIT 1000');
        $stmt->execute([':tid' => $tid, ':y' => $y, ':t' => $t]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            foreach (['id', 'week_number', 'expected_minutes', 'actual_minutes', 'minutes_lost', 'minutes_compensated'] as $k) {
                $r[$k] = (int)$r[$k];
            }
        }
        unset($r);
        $years = $db->prepare('SELECT DISTINCT year, term FROM teacher_lesson_attendance WHERE teacher_id = :t ORDER BY year DESC, term DESC');
        $years->execute([':t' => $tid]);
        hr_respond(true, 'My lesson attendance', [
            'year' => $y, 'term' => $t,
            'records' => $rows,
            'terms' => $years->fetchAll(PDO::FETCH_ASSOC),
            'summary' => [
                'lessons' => count($rows),
                'expected' => array_sum(array_column($rows, 'expected_minutes')),
                'actual' => array_sum(array_column($rows, 'actual_minutes')),
                'lost' => array_sum(array_column($rows, 'minutes_lost')),
                'compensated' => array_sum(array_column($rows, 'minutes_compensated')),
                'compensated_lessons' => count(array_filter($rows, fn($r) => $r['minutes_compensated'] > 0)),
            ],
        ]);
    }

    if ($view === 'leave') {
        $requests = [];
        $balances = [];
        if ($employeeId) {
            $r = $db->prepare('SELECT id, leave_type, start_date, end_date, days, reason, status, approved_at, created_at FROM leave_requests WHERE employee_id = :e ORDER BY start_date DESC, id DESC LIMIT 100');
            $r->execute([':e' => $employeeId]);
            $requests = $r->fetchAll(PDO::FETCH_ASSOC);
            $b = $db->prepare('SELECT leave_type, entitled_days, used_days, remaining_days FROM leave_balances WHERE employee_id = :e AND year = :y');
            $b->execute([':e' => $employeeId, ':y' => (int)date('Y')]);
            $balances = $b->fetchAll(PDO::FETCH_ASSOC);
        }
        hr_respond(true, 'My leave', ['linked' => (bool)$employeeId, 'year' => (int)date('Y'), 'requests' => $requests, 'balances' => $balances, 'types' => SF_LEAVE_TYPES]);
    }

    hr_respond(false, 'Unknown view', null, 400);
} catch (TtException $e) {
    hr_respond(false, $e->getMessage(), $e->data, $e->status);
} catch (Throwable $e) {
    hr_respond(false, $e->getMessage(), null, 500);
}
