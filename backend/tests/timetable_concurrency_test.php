<?php
/**
 * Concurrency test (CLI): php backend/tests/timetable_concurrency_test.php
 *
 * Launches N separate PHP processes that simultaneously try to book the same
 * teacher in the same period (each for a different class). Exactly one may
 * succeed. Uses a throwaway session (2099 Term 2) and removes everything after.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/TimetableService.php';

const YEAR = 2099;
const TERM = 2;
$db = (new Database())->getConnection();

if (($argv[1] ?? '') === '--child') {
    // Child: wait for the shared start time, then attempt the booking.
    [, , $sid, $classId, $teacherId, $subjectId, $startAt] = $argv;
    while (microtime(true) < (float)$startAt) {
        usleep(500);
    }
    try {
        $r = tt_save_entry($db, ['academic_session_id' => (int)$sid, 'entry_type' => 'lesson', 'class_id' => (int)$classId,
            'teacher_id' => (int)$teacherId, 'subject_id' => (int)$subjectId, 'day_of_week' => 'Monday', 'period_number' => 1], null,
            ['id' => null, 'name' => 'Concurrency test', 'role' => 'super_admin']);
        echo "OK {$r['id']}";
    } catch (TtException $e) {
        echo "REJECTED {$e->status}";
    }
    exit(0);
}

tt_ensure_schema($db);
$cleanup = function () use ($db): void {
    $sid = $db->query('SELECT id FROM academic_sessions WHERE academic_year = ' . YEAR . ' AND term = ' . TERM)->fetchColumn();
    $db->exec('DELETE FROM timetable WHERE academic_year = ' . YEAR . ' AND term = ' . TERM);
    if ($sid) {
        $db->exec("DELETE FROM timetable_audit_log WHERE academic_session_id = $sid");
        $db->exec("DELETE FROM academic_sessions WHERE id = $sid");
    }
};
$cleanup();
$db->exec("INSERT INTO academic_sessions (session_name, academic_year, term, start_date, end_date, is_active) VALUES ('TEST 2099 T2', " . YEAR . ', ' . TERM . ", '2099-05-01', '2099-08-01', 0)");
$sid = (int)$db->lastInsertId();
$classes = $db->query('SELECT id FROM classes ORDER BY id LIMIT 10')->fetchAll(PDO::FETCH_COLUMN);
$teacher = (int)$db->query('SELECT id FROM teachers WHERE is_active = 1 OR is_active IS NULL ORDER BY id LIMIT 1')->fetchColumn();
$subject = (int)$db->query('SELECT id FROM subjects_new ORDER BY id LIMIT 1')->fetchColumn();
// Make sure the teacher may teach the subject (link removed afterwards if the test added it).
tt_ensure_subject_departments($db);
$db->prepare('INSERT IGNORE INTO teacher_subjects (teacher_id, subject_id) VALUES (:t, :s)')->execute([':t' => $teacher, ':s' => $subject]);
$addedLink = (bool)$db->query('SELECT ROW_COUNT()')->fetchColumn();

$startAt = microtime(true) + 1.5;
$procs = [];
foreach ($classes as $cid) {
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . " --child $sid $cid $teacher $subject $startAt";
    $procs[] = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $outputs[] = $pipes;
}
$results = [];
foreach ($procs as $i => $p) {
    $results[] = trim(stream_get_contents($outputs[$i][1]) . stream_get_contents($outputs[$i][2]));
    proc_close($p);
}

$ok = count(array_filter($results, fn($r) => str_starts_with($r, 'OK')));
$rejected = count(array_filter($results, fn($r) => str_starts_with($r, 'REJECTED 409')));
$rows = (int)$db->query('SELECT COUNT(*) FROM timetable WHERE academic_year = ' . YEAR . ' AND term = ' . TERM . " AND teacher_id = $teacher")->fetchColumn();
echo implode(PHP_EOL, array_map(fn($r, $i) => "  process $i: $r", $results, array_keys($results))) . PHP_EOL;
$cleanup();
if ($addedLink) {
    $db->prepare('DELETE FROM teacher_subjects WHERE teacher_id = :t AND subject_id = :s')->execute([':t' => $teacher, ':s' => $subject]);
}

$pass = $ok === 1 && $rows === 1 && $rejected === count($classes) - 1;
echo ($pass ? 'PASS' : 'FAIL') . ": $ok succeeded, $rejected rejected as clashes, $rows lesson row(s) stored for the teacher\n";
exit($pass ? 0 : 1);
