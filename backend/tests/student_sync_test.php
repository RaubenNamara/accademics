<?php
/**
 * Student sync (config/StudentSync.php) against a THROWAWAY copy of the database (CLI):
 *   php backend/tests/student_sync_test.php [database]      (default accademics_synctest)
 *
 * Uses made-up SMIS rosters built from the copy's own students; never calls SMIS and never
 * touches the real database. Add --live to also run one real SMIS roster against the copy.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/TimetableService.php';
require_once __DIR__ . '/../config/StudentSync.php';

$dbName = $argv[1] ?? 'accademics_synctest';
if ($dbName === 'accademics' || str_starts_with($dbName, '--')) {
    exit("Give a throwaway copy, not the real database.\n");
}
$db = new PDO("mysql:host=localhost;dbname=$dbName;charset=utf8mb4", 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pass = 0;
$fail = 0;
function check(string $name, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    echo ($ok ? '  PASS ' : '  FAIL ') . $name . ($ok || $detail === '' ? '' : " - $detail") . PHP_EOL;
}
$one = fn(string $sql) => $db->query($sql)->fetchColumn();
$student = fn(int $id) => $db->query("SELECT * FROM students WHERE id = $id")->fetch(PDO::FETCH_ASSOC);

ss_ensure_schema($db);
$active = $db->query("SELECT id, admission_number AS reg, full_name AS name, `class`, stream FROM students WHERE status = 'active' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$roster = array_map(fn($s) => ['reg' => $s['reg'], 'name' => $s['name'], 'class' => $s['class'], 'stream' => $s['stream']], $active);

// A learner with parents, so we can see they stay attached when the learner leaves.
$leaverId = (int)$one("SELECT s.id FROM students s JOIN parents p ON p.student_id = s.id WHERE s.status = 'active' LIMIT 1");
$leaver = $student($leaverId);
$parentsBefore = (int)$one("SELECT COUNT(*) FROM parents WHERE student_id = $leaverId");
$resultsBefore = (int)$one("SELECT COUNT(*) FROM student_results WHERE student_id = $leaverId");
$mover = $active[5];
$adminLeftId = (int)$active[9]['id'];
$db->exec("UPDATE students SET status = 'left', left_by_sync = 0 WHERE id = $adminLeftId"); // an admin marked them left by hand

echo "Safety\n";
$r = ss_run($db, null, []);
check('an empty SMIS answer is refused', !$r['ok'] && str_contains($r['message'], 'no students'), $r['message']);
$r = ss_run($db, null, array_slice($roster, 0, 10));
check('a far-too-small SMIS answer is refused and nothing changes', !$r['ok'] && (int)$one("SELECT COUNT(*) FROM students WHERE status = 'left'") === 1, $r['message']);

echo "First run\n";
$run = array_values(array_filter($roster, fn($s) => $s['reg'] !== $leaver['admission_number']));
foreach ($run as &$s) {
    if ($s['reg'] === $mover['reg']) {
        $s['class'] = 'S4';
        $s['stream'] = 'SYNCQA';
    }
}
unset($s);
$run[] = ['reg' => 'SYNCQA-001', 'name' => 'Syncqa New Learner', 'class' => 'S1', 'stream' => 'A'];
$r = ss_run($db, null, $run);
check('the run succeeds', $r['ok'], $r['message']);
echo '    ' . $r['message'] . PHP_EOL;
$new = $db->query("SELECT * FROM students WHERE admission_number = 'SYNCQA-001'")->fetch(PDO::FETCH_ASSOC);
check('a learner new in SMIS is added as current O-Level', $new && $new['status'] === 'active' && $new['level'] === 'O-Level' && $new['class'] === 'S1');
$moved = $student((int)$mover['id']);
check('a learner whose class changed in SMIS moves class and stream', $moved['class'] === 'S4' && $moved['stream'] === 'SYNCQA' && $moved['status'] === 'active');
check('a new class stream is created for them', (int)$one("SELECT COUNT(*) FROM classes WHERE class_name = 'S4' AND stream_name = 'SYNCQA'") === 1);
$left = $student($leaverId);
check('a learner no longer in SMIS is marked left (by the sync, with a date)', $left['status'] === 'left' && (int)$left['left_by_sync'] === 1 && $left['left_at'] !== null);
check('their e-file is kept: parents and results still attached', (int)$one("SELECT COUNT(*) FROM parents WHERE student_id = $leaverId") === $parentsBefore
    && (int)$one("SELECT COUNT(*) FROM student_results WHERE student_id = $leaverId") === $resultsBefore && $parentsBefore > 0, "parents $parentsBefore");
check('names and other details are not overwritten', $left['full_name'] === $leaver['full_name'] && $left['admission_number'] === $leaver['admission_number']);
check('a learner an admin marked left stays left even though SMIS lists them', $student($adminLeftId)['status'] === 'left');
check('the run is logged with its counts', (int)$one("SELECT added FROM student_sync_runs ORDER BY id DESC LIMIT 1") === 1
    && (int)$one("SELECT left_school FROM student_sync_runs ORDER BY id DESC LIMIT 1") === 1 && (int)$one("SELECT moved FROM student_sync_runs ORDER BY id DESC LIMIT 1") === 1);

echo "Second run\n";
$run[] = ['reg' => $leaver['admission_number'], 'name' => $leaver['full_name'], 'class' => $leaver['class'], 'stream' => $leaver['stream']];
$r = ss_run($db, null, $run);
$back = $student($leaverId);
check('a learner who reappears in SMIS is back in school, e-file intact', $r['ok'] && $back['status'] === 'active' && $back['left_at'] === null
    && (int)$one("SELECT COUNT(*) FROM parents WHERE student_id = $leaverId") === $parentsBefore, $r['message']);
$r = ss_run($db, null, $run);
check('running again changes nothing', $r['ok'] && str_contains($r['message'], 'already up to date'), $r['message']);
check('the status shows the last runs', count(ss_status($db)['runs']) >= 3 && !ss_status($db)['running']);

if (in_array('--live', $argv, true)) {
    echo "Real SMIS roster against the copy\n";
    $live = ss_fetch_roster();
    echo '    SMIS lists ' . count($live) . " students\n";
    $r = ss_run($db, null, $live);
    echo '    ' . $r['message'] . PHP_EOL;
    check('the real SMIS roster syncs', $r['ok'], $r['message']);
}

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
