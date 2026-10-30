<?php
/**
 * Academic dashboard (academic office and super admin): the open term at a glance.
 *
 * GET  term, learners, lesson time (by week, teachers losing most time), lesson plans to review,
 *      marks entry per exam, subject averages this term, teaching coverage and what needs attention.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/HrHelpers.php';
require_once __DIR__ . '/../config/TimetableService.php';

hr_cors_headers('GET, OPTIONS');

try {
    $payload = hr_require_auth();
    if (!in_array((string)($payload['role'] ?? ''), ['super_admin', 'teacher', 'academic_office'], true)) {
        hr_respond(false, 'The academic dashboard is for the academic office', null, 403);
    }
    $db = (new Database())->getConnection();
    $out = ['term' => null, 'students' => null, 'lessons' => null, 'teachers_lost' => [], 'plans' => null, 'recent_plans' => [],
        'exams' => [], 'subjects' => [], 'coverage' => null, 'attention' => []];

    // The open term and how far into it we are.
    $sid = null;
    $t = $db->query('SELECT id, academic_year, term, start_date, end_date FROM academic_sessions WHERE is_active = 1 ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    if ($t) {
        $sid = (int)$t['id'];
        $start = $t['start_date'] ? strtotime((string)$t['start_date']) : null;
        $end = $t['end_date'] ? strtotime((string)$t['end_date']) : null;
        $weeks = $start && $end ? max(1, (int)ceil(($end - $start + 86400) / (7 * 86400))) : null;
        $week = $start ? max(1, (int)floor((time() - $start) / (7 * 86400)) + 1) : null;
        $out['term'] = ['id' => $sid, 'year' => (int)$t['academic_year'], 'term' => (int)$t['term'], 'start' => $t['start_date'], 'end' => $t['end_date'],
            'weeks' => $weeks, 'week' => $week && $weeks ? min($week, $weeks) : $week];
    }

    // Learners
    try {
        $row = $db->query("SELECT SUM(status = 'active') in_school, SUM(status = 'active' AND level = 'O-Level') o_level,
                SUM(status = 'active' AND level = 'A-Level') a_level, SUM(status = 'left') left_school FROM students")->fetch(PDO::FETCH_ASSOC);
        $out['students'] = array_map('intval', $row);
    } catch (Throwable $e) {
    }

    if ($out['term']) {
        $y = $out['term']['year'];
        $tm = $out['term']['term'];
        // Lesson time by week, and the teachers losing the most time.
        try {
            $q = $db->prepare('SELECT week_number, COUNT(*) lessons, SUM(expected_minutes) expected, SUM(actual_minutes) actual, SUM(minutes_lost) lost
                FROM teacher_lesson_attendance WHERE year = :y AND term = :t GROUP BY week_number ORDER BY week_number');
            $q->execute([':y' => $y, ':t' => $tm]);
            $byWeek = array_map(fn($r) => ['week' => (int)$r['week_number'], 'lessons' => (int)$r['lessons'], 'expected' => (int)$r['expected'],
                'actual' => (int)$r['actual'], 'lost' => (int)$r['lost']], $q->fetchAll(PDO::FETCH_ASSOC));
            $expected = array_sum(array_column($byWeek, 'expected'));
            $out['lessons'] = ['lessons' => array_sum(array_column($byWeek, 'lessons')), 'expected' => $expected,
                'actual' => array_sum(array_column($byWeek, 'actual')), 'lost' => array_sum(array_column($byWeek, 'lost')), 'by_week' => $byWeek];
            $q = $db->prepare('SELECT t.full_name name, COUNT(*) lessons, SUM(a.minutes_lost) lost, SUM(a.expected_minutes) expected
                FROM teacher_lesson_attendance a JOIN teachers t ON t.id = a.teacher_id
                WHERE a.year = :y AND a.term = :t GROUP BY a.teacher_id HAVING lost > 0 ORDER BY lost DESC LIMIT 5');
            $q->execute([':y' => $y, ':t' => $tm]);
            $out['teachers_lost'] = array_map(fn($r) => ['name' => $r['name'], 'lessons' => (int)$r['lessons'], 'lost' => (int)$r['lost'], 'expected' => (int)$r['expected']], $q->fetchAll(PDO::FETCH_ASSOC));
        } catch (Throwable $e) {
        }
    }

    // Lesson plans
    try {
        $row = $db->query("SELECT SUM(status = 'submitted') waiting, SUM(status = 'submitted' AND staff_seen_at IS NULL) new_plans,
                SUM(status = 'approved') approved, SUM(status = 'returned') returned FROM lesson_plans" . ($sid ? " WHERE academic_session_id = $sid OR status = 'submitted'" : ''))->fetch(PDO::FETCH_ASSOC);
        $out['plans'] = ['waiting' => (int)$row['waiting'], 'new' => (int)$row['new_plans'], 'approved' => (int)$row['approved'], 'returned' => (int)$row['returned']];
        $out['recent_plans'] = $db->query("SELECT p.id, p.topic, p.status, COALESCE(p.submitted_at, p.updated_at) submitted_at, p.staff_seen_at IS NULL is_new,
                t.full_name teacher, c.full_class_name class_name, s.subject_name
            FROM lesson_plans p JOIN teachers t ON t.id = p.teacher_id LEFT JOIN classes c ON c.id = p.class_id LEFT JOIN subjects_new s ON s.id = p.subject_id
            WHERE p.status = 'submitted' ORDER BY COALESCE(p.submitted_at, p.updated_at) DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
    }

    if ($sid) {
        // Marks entry per exam: marks in, against roughly what is expected (learners x subjects taught).
        try {
            $expected = (int)$db->query("SELECT COALESCE(SUM(x.subjects * x.learners), 0) FROM (
                    SELECT lr.class_id, COUNT(DISTINCT lr.subject_id) subjects,
                        (SELECT COUNT(*) FROM students s JOIN classes c2 ON TRIM(s.class) COLLATE utf8mb4_unicode_ci = TRIM(c2.class_name) COLLATE utf8mb4_unicode_ci
                            AND TRIM(s.stream) COLLATE utf8mb4_unicode_ci = TRIM(c2.stream_name) COLLATE utf8mb4_unicode_ci
                         WHERE c2.id = lr.class_id AND s.status = 'active') learners
                    FROM lesson_requirements lr WHERE lr.academic_session_id = $sid GROUP BY lr.class_id) x")->fetchColumn();
            $out['exams'] = array_map(fn($e) => ['id' => (int)$e['id'], 'name' => $e['name'], 'type' => $e['exam_type'], 'status' => $e['status'],
                'entered' => (int)$e['entered'], 'expected' => $expected],
                $db->query("SELECT e.id, e.name, e.exam_type, e.status, (SELECT COUNT(*) FROM exam_marks m WHERE m.exam_id = e.id AND m.marks IS NOT NULL) entered
                    FROM exams e WHERE e.academic_session_id = $sid AND e.status <> 'draft' ORDER BY e.status = 'open' DESC, e.id DESC")->fetchAll(PDO::FETCH_ASSOC));
        } catch (Throwable $e) {
        }
        // Subject averages this term (marks as a share of each exam's "out of").
        try {
            $out['subjects'] = array_map(fn($r) => ['subject' => $r['subject_name'], 'average' => round((float)$r['avg_pct'], 1), 'marks' => (int)$r['n']],
                $db->query("SELECT s.subject_name, AVG(m.marks / NULLIF(e.max_marks, 0) * 100) avg_pct, COUNT(*) n
                    FROM exam_marks m JOIN exams e ON e.id = m.exam_id JOIN subjects_new s ON s.id = m.subject_id
                    WHERE e.academic_session_id = $sid AND m.marks IS NOT NULL GROUP BY m.subject_id HAVING n >= 1 ORDER BY avg_pct DESC")->fetchAll(PDO::FETCH_ASSOC));
        } catch (Throwable $e) {
        }
        // Teaching coverage: classes with subjects assigned, teachers with classes.
        try {
            $row = $db->query("SELECT COUNT(DISTINCT class_id) classes, COUNT(DISTINCT teacher_id) teachers, COUNT(DISTINCT CONCAT(class_id, '-', subject_id)) slots
                FROM lesson_requirements WHERE academic_session_id = $sid")->fetch(PDO::FETCH_ASSOC);
            $allClasses = (int)$db->query('SELECT COUNT(*) FROM classes')->fetchColumn();
            $activeTeachers = (int)$db->query('SELECT COUNT(*) FROM teachers WHERE is_active = 1 OR is_active IS NULL')->fetchColumn();
            $out['coverage'] = ['classes' => (int)$row['classes'], 'all_classes' => $allClasses, 'teachers' => (int)$row['teachers'],
                'all_teachers' => $activeTeachers, 'slots' => (int)$row['slots']];
        } catch (Throwable $e) {
        }
    }

    // What needs attention, most urgent first.
    $att = &$out['attention'];
    if (($out['plans']['new'] ?? 0) > 0) {
        $att[] = ['tone' => 'amber', 'text' => "{$out['plans']['new']} new lesson plan(s) to review", 'to' => '/lesson-plans'];
    } elseif (($out['plans']['waiting'] ?? 0) > 0) {
        $att[] = ['tone' => 'amber', 'text' => "{$out['plans']['waiting']} lesson plan(s) waiting for review", 'to' => '/lesson-plans'];
    }
    foreach ($out['exams'] as $e) {
        if ($e['status'] === 'open' && $e['expected'] > 0 && $e['entered'] / $e['expected'] < 0.5) {
            $att[] = ['tone' => 'sky', 'text' => "{$e['name']}: only " . round($e['entered'] / $e['expected'] * 100) . '% of marks entered', 'to' => '/examinations'];
        }
    }
    if ($out['coverage'] && $out['coverage']['all_classes'] > $out['coverage']['classes']) {
        $n = $out['coverage']['all_classes'] - $out['coverage']['classes'];
        $att[] = ['tone' => 'red', 'text' => "$n class stream(s) have no subject teachers assigned", 'to' => '/teaching-assignments'];
    }
    if ($out['lessons'] && $out['lessons']['expected'] > 0 && $out['lessons']['lost'] / $out['lessons']['expected'] > 0.1) {
        $att[] = ['tone' => 'red', 'text' => round($out['lessons']['lost'] / $out['lessons']['expected'] * 100) . '% of lesson time lost this term', 'to' => '/lesson-monitoring'];
    }
    unset($att);

    hr_respond(true, 'Academic dashboard', $out);
} catch (Throwable $e) {
    error_log('academic_dashboard.php: ' . $e->getMessage());
    hr_respond(false, 'Could not load the academic dashboard', null, 500);
}
