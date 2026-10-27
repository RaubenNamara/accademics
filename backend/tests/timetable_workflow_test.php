<?php
/**
 * End-to-end timetable workflow over the real HTTP API (CLI):
 *   php backend/tests/timetable_workflow_test.php [base-url]
 * Default base URL: http://localhost/accademics/backend/api/
 *
 * Uses a throwaway term (2099 Term 3), temporary logins and existing classes,
 * subjects, department teachers and class rooms (read only). Everything the
 * test creates is removed at the end; the school day is restored if touched.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/TimetableService.php';

$base = rtrim($argv[1] ?? 'http://localhost/accademics/backend/api/', '/') . '/';
$db = (new Database())->getConnection();
tt_ensure_schema($db);
tt_ensure_home_rooms($db);
tt_ensure_subject_departments($db);
const YEAR = 2099;
const TERM = 3;
$pass = 0;
$fail = 0;

function check(string $name, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    echo ($ok ? '  PASS ' : '  FAIL ') . $name . ($ok || $detail === '' ? '' : " — $detail") . PHP_EOL;
}

function call(string $method, string $path, ?array $body = null, ?string $token = null): array
{
    global $base;
    $ch = curl_init($base . $path);
    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = "Authorization: Bearer $token";
    }
    curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 120]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $raw = (string)curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$status, json_decode($raw, true) ?: ['raw' => $raw]];
}

$cleanup = function () use ($db): void {
    $sid = $db->query('SELECT id FROM academic_sessions WHERE academic_year = ' . YEAR . ' AND term = ' . TERM)->fetchColumn();
    $db->exec('DELETE FROM timetable WHERE academic_year = ' . YEAR . ' AND term = ' . TERM);
    if ($sid) {
        $db->exec("DELETE FROM timetable_audit_log WHERE academic_session_id = $sid");
        $db->exec("DELETE FROM academic_sessions WHERE id = $sid");
    }
    $db->exec("DELETE FROM users WHERE email LIKE 'tt-workflow-%@local.test'");
};
$cleanup();

$hash = password_hash('Workflow2026!', PASSWORD_DEFAULT);
$u = $db->prepare('INSERT INTO users (full_name, email, password_hash, role, is_active) VALUES (?, ?, ?, ?, 1)');
$u->execute(['Workflow Admin', 'tt-workflow-admin@local.test', $hash, 'super_admin']);
$u->execute(['Workflow Academic', 'tt-workflow-teacher@local.test', $hash, 'teacher']);
$token = fn(string $who) => call('POST', 'auth.php?action=login', ['email' => "tt-workflow-$who@local.test", 'password' => 'Workflow2026!'])[1]['token'] ?? null;
$admin = $token('admin');
$academic = $token('teacher');
$db->exec("INSERT INTO academic_sessions (session_name, academic_year, term, start_date, end_date, is_active) VALUES ('TEST workflow', " . YEAR . ', ' . TERM . ", '2099-09-01', '2099-12-01', 0)");
$sid = (int)$db->lastInsertId();
$periodsBefore = array_values(tt_load_periods($db));

try {
    check('both test logins work', (bool)$admin && (bool)$academic);

    echo "Setup\n";
    [$st, $setup] = call('GET', "timetable.php?view=setup&academic_session_id=$sid", null, $admin);
    $s = $setup['data']['status'] ?? [];
    check('setup status reports real counts', $st === 200 && $s['classes'] > 0 && $s['lesson_periods'] > 0, json_encode($s));
    $homes = array_values(array_filter($setup['data']['home_rooms'] ?? [], fn($h) => $h['room_id']));
    check('classes have home rooms', count($homes) >= 3, count($homes) . ' linked');

    // Three classes with home rooms; three subjects, each taught by a member of its department.
    $classes = array_slice($homes, 0, 3);
    $subjects = $db->query('SELECT ts.subject_id, MIN(ts.teacher_id) AS teacher_id FROM teacher_subjects ts JOIN teachers t ON t.id = ts.teacher_id
        WHERE t.is_active = 1 OR t.is_active IS NULL GROUP BY ts.subject_id HAVING COUNT(*) >= 1 ORDER BY ts.subject_id LIMIT 3')->fetchAll(PDO::FETCH_ASSOC);
    check('three staffed subject departments available', count($subjects) === 3);
    $lab = (int)$db->query("SELECT id FROM rooms WHERE is_active = 1 AND id NOT IN (SELECT room_id FROM class_home_rooms) LIMIT 1")->fetchColumn();

    foreach ($classes as $ci => $c) {
        foreach ($subjects as $si => $sub) {
            $body = ['academic_session_id' => $sid, 'class_id' => (int)$c['class_id'], 'subject_id' => (int)$sub['subject_id'],
                'teacher_id' => (int)$sub['teacher_id'], 'periods_per_week' => [5, 4, 3][$si], 'double_lesson_allowed' => $si === 0];
            if ($si === 2 && $lab) {
                $body['room_id'] = $lab; // a "lab" subject
            }
            [$st] = call('POST', 'lesson_requirements.php', $body, $academic);
            if ($st !== 201) {
                check("requirement saved ({$c['full_class_name']})", false, (string)$st);
            }
        }
    }
    check('requirements saved by an academic user', (int)$db->query("SELECT COUNT(*) FROM lesson_requirements WHERE academic_session_id = $sid")->fetchColumn() === 9);

    $outsider = (int)$db->query('SELECT id FROM teachers t WHERE (is_active = 1 OR is_active IS NULL) AND NOT EXISTS
        (SELECT 1 FROM teacher_subjects ts WHERE ts.teacher_id = t.id AND ts.subject_id = ' . (int)$subjects[0]['subject_id'] . ') LIMIT 1')->fetchColumn();
    [$st] = call('POST', 'lesson_requirements.php', ['academic_session_id' => $sid, 'class_id' => (int)$classes[0]['class_id'],
        'subject_id' => (int)$subjects[0]['subject_id'], 'teacher_id' => $outsider, 'periods_per_week' => 2], $academic);
    check('a teacher outside the department is refused', $st === 422, (string)$st);

    echo "Teacher availability\n";
    $busyTeacher = (int)$subjects[1]['teacher_id'];
    $lessonPeriods = array_keys(array_filter(tt_load_periods($db), fn($p) => $p['period_type'] === 'lesson'));
    $offSlots = [['day_of_week' => 'Monday', 'period_number' => $lessonPeriods[0], 'is_available' => false],
        ['day_of_week' => 'Monday', 'period_number' => $lessonPeriods[1], 'is_available' => false]];
    [$st] = call('POST', 'teacher_availability.php?action=bulk', ['teacher_id' => $busyTeacher, 'academic_session_id' => $sid, 'availability' => $offSlots], $academic);
    [, $got] = call('GET', "teacher_availability.php?action=by-teacher&academic_session_id=$sid&teacher_id=$busyTeacher", null, $academic);
    check('saving availability stores the unavailable periods', $st === 200 && count($got['data'] ?? []) === 2, json_encode($got['data'] ?? $got));

    echo "Rules\n";
    [$st] = call('POST', 'timetable.php?action=save-rules', ['academic_session_id' => $sid, 'max_lessons_per_day' => 3], $academic);
    check('daily limit saved', $st === 200);

    echo "Generation\n";
    [$st, $gen] = call('POST', 'timetable.php?action=generate', ['academic_session_id' => $sid, 'class_ids' => array_map(fn($c) => (int)$c['class_id'], $classes)], $academic);
    $g = $gen['data'] ?? [];
    check('generator places every required period', $st === 200 && ($g['placed_periods'] ?? 0) === 36 && ($g['unplaced_periods'] ?? -1) === 0, json_encode($g['unplaced'] ?? $gen));

    $entries = tt_fetch_entries($db, YEAR, TERM);
    check('the generated lessons are stored', count($entries) > 0, count($entries) . ' lessons');
    $periods = tt_load_periods($db);
    $badPeriod = $noRoom = $wrongRoom = $doubleAcross = $avail = 0;
    $perTeacherDay = [];
    $homeOf = [];
    foreach ($homes as $h) {
        $homeOf[(int)$h['class_id']] = (int)$h['room_id'];
    }
    foreach ($entries as $e) {
        for ($p = $e['period_number']; $p < $e['period_number'] + $e['spans_periods']; $p++) {
            if (($periods[$p]['period_type'] ?? '') !== 'lesson') {
                $badPeriod++;
            }
            if ($e['teacher_id'] === $busyTeacher && $e['day_of_week'] === 'Monday' && in_array($p, [$lessonPeriods[0], $lessonPeriods[1]], true)) {
                $avail++;
            }
        }
        if (isset(tt_span_info($periods, $e['period_number'], $e['spans_periods'])['error'])) {
            $doubleAcross++;
        }
        if (!$e['room_id']) {
            $noRoom++;
        }
        $expected = $e['subject_id'] === (int)$subjects[2]['subject_id'] && $lab ? $lab : ($homeOf[$e['class_id']] ?? null);
        if ($e['room_id'] !== $expected) {
            $wrongRoom++;
        }
        $perTeacherDay["{$e['teacher_id']}|{$e['day_of_week']}"] = ($perTeacherDay["{$e['teacher_id']}|{$e['day_of_week']}"] ?? 0) + $e['spans_periods'];
    }
    check('no lesson is placed in a break or lunch', $badPeriod === 0, "$badPeriod");
    check('no double lesson runs across a break', $doubleAcross === 0, "$doubleAcross");
    check('every lesson has a room', $noRoom === 0, "$noRoom");
    check('lessons use the class home room, or the lab when one is required', $wrongRoom === 0, "$wrongRoom");
    check('teacher unavailability is respected', $avail === 0, "$avail");
    check('the daily limit is respected', max($perTeacherDay) <= 3, (string)max($perTeacherDay));

    [, $val] = call('POST', 'timetable.php?action=validate', ['academic_session_id' => $sid], $academic);
    $stats = $val['data']['stats'] ?? [];
    check('validation finds no clashes', ($val['data']['summary']['errors'] ?? -1) === 0
        && $stats['teacher_conflicts'] + $stats['class_conflicts'] + $stats['room_conflicts'] === 0, json_encode($val['data']['summary'] ?? $val));
    check('status becomes Validated', ($val['data']['state']['status'] ?? '') === 'validated', json_encode($val['data']['state'] ?? []));

    echo "Manual adjustments\n";
    $a = $entries[0];
    $other = null;
    foreach ($entries as $e) {
        if ($e['class_id'] !== $a['class_id'] && $e['teacher_id'] === $a['teacher_id'] && !$e['is_locked']) {
            $other = $e;
            break;
        }
    }
    if ($other) {
        [$st, $res] = call('PUT', 'timetable.php', ['id' => $a['id'], 'academic_session_id' => $sid, 'class_id' => $a['class_id'], 'subject_id' => $a['subject_id'],
            'teacher_id' => $a['teacher_id'], 'room_id' => $a['room_id'], 'day_of_week' => $other['day_of_week'], 'period_number' => $other['period_number'],
            'spans_periods' => 1], $academic);
        check('moving a lesson onto its teacher’s other lesson is refused', $st === 409, "$st " . ($res['message'] ?? ''));
    }
    [$st] = call('POST', 'timetable.php', ['academic_session_id' => $sid, 'class_id' => $a['class_id'], 'subject_id' => $a['subject_id'],
        'teacher_id' => $a['teacher_id'], 'day_of_week' => 'Monday', 'period_number' => array_search('lunch', array_column($periods, 'period_type', 'period_number'), true) ?: 9], $academic);
    check('a lesson cannot be put in lunch', $st === 422, (string)$st);

    echo "School day\n";
    [$st] = call('POST', 'timetable.php?action=save-periods', ['periods' => $periodsBefore], $academic);
    check('an academic user cannot change the school day', $st === 403, (string)$st);
    $broken = $periodsBefore;
    $broken[1]['start_time'] = '07:00';
    [$st, $res] = call('POST', 'timetable.php?action=save-periods', ['periods' => $broken], $admin);
    check('overlapping periods are refused', $st === 422 && str_contains($res['message'] ?? '', 'before the previous period ends'), $res['message'] ?? '');
    $usedLesson = (int)$entries[0]['period_number'];
    $toBreak = $periodsBefore;
    foreach ($toBreak as &$p) {
        if ((int)$p['period_number'] === $usedLesson) {
            $p['period_type'] = 'break';
        }
    }
    unset($p);
    [$st, $res] = call('POST', 'timetable.php?action=save-periods', ['periods' => $toBreak], $admin);
    check('a period with lessons cannot become a break', $st === 422 && str_contains($res['message'] ?? '', 'cannot become'), $res['message'] ?? '');
    $renamed = $periodsBefore;
    $renamed[0]['label'] = $renamed[0]['label'] . ' (test)';
    [$st] = call('POST', 'timetable.php?action=save-periods', ['periods' => $renamed], $admin);
    $label = (string)$db->query('SELECT label FROM timetable_periods WHERE period_number = 1')->fetchColumn();
    check('an admin can rename a period', $st === 200 && str_ends_with($label, '(test)'));
    [, $state] = call('GET', "timetable.php?view=overview&academic_session_id=$sid", null, $academic);
    check('changing the school day makes the term a draft again', ($state['data']['state']['status'] ?? '') === 'draft');

    echo "Publishing\n";
    [$st, $pub] = call('POST', 'timetable.php?action=publish', ['academic_session_id' => $sid, 'confirm_warnings' => true], $academic);
    check('the validated timetable publishes', $st === 200 && !empty($pub['data']['version_id']), "$st " . ($pub['message'] ?? ''));
    [, $hist] = call('GET', "timetable.php?view=history&academic_session_id=$sid", null, $academic);
    check('history lists the published version and the changes', count($hist['data']['versions'] ?? []) === 1 && count($hist['data']['audit'] ?? []) > 3);
} finally {
    // Restore the school day exactly as it was.
    $restore = $db->prepare('UPDATE timetable_periods SET label = :l, period_type = :t, start_time = :s, end_time = :e WHERE period_number = :n');
    foreach ($periodsBefore as $p) {
        $restore->execute([':l' => $p['label'], ':t' => $p['period_type'], ':s' => $p['start_time'], ':e' => $p['end_time'], ':n' => $p['period_number']]);
    }
    $db->exec("DELETE FROM timetable_audit_log WHERE academic_session_id IS NULL AND action = 'periods' AND user_name = 'Workflow Admin'");
    $cleanup();
    $same = array_values(tt_load_periods($db)) == $periodsBefore;
    echo "\nCleanup: test data removed" . ($same ? ', school day unchanged' : ', SCHOOL DAY DIFFERS — check timetable_periods') . PHP_EOL;
}

echo "\n$pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
