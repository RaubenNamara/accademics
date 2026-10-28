<?php
/**
 * Academic calendar, year-end promotion, A-Level entry and student subjects — run against a COPY of the
 * database, never the real one (closing a year promotes every student):
 *
 *   mysql -e "CREATE DATABASE accademics_caltest"; mysqldump accademics | mysql accademics_caltest
 *   php backend/tests/academic_calendar_test.php accademics_caltest
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/SchoolService.php';

$dbName = $argv[1] ?? '';
if ($dbName === '' || $dbName === 'accademics') {
    fwrite(STDERR, "Give the name of a throwaway copy of the database (not accademics).\n");
    exit(2);
}
$db = new PDO("mysql:host=localhost;dbname=$dbName;charset=utf8mb4", 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
tt_ensure_schema($db);
sf_ensure_schema($db);

$pass = 0;
$fail = 0;
function check(string $name, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    echo ($ok ? '  PASS ' : '  FAIL ') . $name . ($ok || $detail === '' ? '' : " — $detail") . PHP_EOL;
}
function expectError(callable $fn, int $status): bool
{
    try {
        $fn();
    } catch (TtException $e) {
        return $e->status === $status;
    }
    return false;
}
$one = fn(string $sql) => $db->query($sql)->fetchColumn();
$byClass = function () use ($db): array {
    $out = [];
    foreach ($db->query("SELECT class, stream, status, COUNT(*) n FROM students GROUP BY class, stream, status")->fetchAll() as $r) {
        $out["{$r['class']}|{$r['stream']}|{$r['status']}"] = (int)$r['n'];
    }
    return $out;
};

// The copy may have been taken after a term was closed: start it from Term 1 of that year.
$open = ac_open_session($db);
$y0 = (int)$open['academic_year'];
$db->exec("DELETE FROM academic_sessions WHERE academic_year = $y0 AND term > 1");
$db->exec('UPDATE academic_sessions SET is_active = 0');
$db->exec("UPDATE academic_sessions SET is_active = 1, is_archived = 0 WHERE academic_year = $y0 AND term = 1");
$open = ac_open_session($db);
echo "Open term at start: {$open['session_name']}\n";
$year = (int)$open['academic_year'];

echo "Closing terms\n";
$sid = (int)$open['id'];
$db->exec("INSERT INTO exams (academic_session_id, name, exam_type, max_marks, weight, class_level, status) VALUES ($sid, 'CAL TEST', 'EOT', 100, 80, 'all', 'open')");
$examId = (int)$db->lastInsertId();
$s1a = (int)$one("SELECT id FROM classes WHERE class_name = 'S1' AND stream_name = 'A'");
$student = ['id' => (int)sf_class_students($db, $s1a)[0]['id'], 'class_id' => $s1a];
$exam = sf_exam($db, $examId);
sf_save_marks($db, $exam, (int)$student['class_id'], 1, [['student_id' => $student['id'], 'marks' => 70]], null);
check('marks can be entered in the open term', (int)$one("SELECT COUNT(*) FROM exam_marks WHERE exam_id = $examId") === 1);
$reqBefore = (int)$one("SELECT COUNT(*) FROM lesson_requirements WHERE academic_session_id = $sid");

$res = ac_close_term($db, ['copy_requirements' => true], 'Test');
$t2 = ac_open_session($db);
check('closing Term 1 opens Term 2 of the same year', (int)$t2['academic_year'] === $year && (int)$t2['term'] === 2, json_encode($res['opened']));
check('Term 1 is marked closed', (int)$one("SELECT is_archived FROM academic_sessions WHERE id = $sid") === 1 && (int)$one("SELECT is_active FROM academic_sessions WHERE id = $sid") === 0);
check('only one term is open', (int)$one('SELECT COUNT(*) FROM academic_sessions WHERE is_active = 1') === 1);
check("Term 1's open exams are closed", $one("SELECT status FROM exams WHERE id = $examId") === 'closed');
check('lesson requirements are copied to the new term', (int)$one("SELECT COUNT(*) FROM lesson_requirements WHERE academic_session_id = {$t2['id']}") === $reqBefore, (string)$reqBefore);
$db->exec("UPDATE exams SET status = 'open' WHERE id = $examId");
check('marks cannot be entered in a closed term (even if its exam is reopened)',
    expectError(fn() => sf_save_marks($db, sf_exam($db, $examId), (int)$student['class_id'], 1, [['student_id' => $student['id'], 'marks' => 50]], null), 423));
check('no students moved when closing Term 1', (int)$one("SELECT COUNT(*) FROM student_promotions") === 0);

ac_close_term($db, [], 'Test');
$t3 = ac_open_session($db);
check('closing Term 2 opens Term 3', (int)$t3['term'] === 3 && (int)$t3['academic_year'] === $year);

echo "Closing the year\n";
check('closing the year needs the year typed to confirm', expectError(fn() => ac_close_term($db, ['confirm' => 'yes'], 'Test'), 422));
$before = $byClass();
$preview = ac_close_preview($db);
$s5streams = array_column($db->query("SELECT DISTINCT stream FROM students WHERE class = 'S5' AND status = 'active'")->fetchAll(), 'stream');
$s6streams = array_column($db->query("SELECT stream_name FROM classes WHERE class_name = 'S6'")->fetchAll(), 'stream_name');
$expectMissing = array_values(array_diff($s5streams, $s6streams));
check('preview lists streams that will be created (S5 streams with no S6 stream)',
    count(array_filter($preview['promotion']['missing_streams'], fn($m) => $m['class_name'] === 'S6')) === count($expectMissing), json_encode($preview['promotion']['missing_streams']));
$s4 = array_sum(array_map(fn($k, $v) => str_starts_with($k, 'S4|') && str_ends_with($k, '|active') ? $v : 0, array_keys($before), $before));
$s6 = array_sum(array_map(fn($k, $v) => str_starts_with($k, 'S6|') && str_ends_with($k, '|active') ? $v : 0, array_keys($before), $before));
check('preview counts S.4 and S.6 finishing', $preview['promotion']['completed_o_level'] === $s4 && $preview['promotion']['completed_a_level'] === $s6, "$s4 / $s6");

$res = ac_close_term($db, ['confirm' => (string)$year, 'copy_requirements' => true], 'Test');
$next = ac_open_session($db);
check('closing the year opens next year Term 1', (int)$next['academic_year'] === $year + 1 && (int)$next['term'] === 1);
$after = $byClass();
check('S1 A moves to S2 A', ($after['S2|A|active'] ?? 0) === ($before['S1|A|active'] ?? -1), json_encode([$before['S1|A|active'] ?? null, $after['S2|A|active'] ?? null]));
check('S3 W moves to S4 W', ($after['S4|W|active'] ?? 0) === ($before['S3|W|active'] ?? -1));
check('nobody is left in S1 (new intake comes later)', !array_filter($after, fn($v, $k) => str_starts_with($k, 'S1|'), ARRAY_FILTER_USE_BOTH));
check('S.4 are not promoted: they stay in S4 as "completed S.4"',
    ($after['S4|A|completed_o_level'] ?? 0) === ($before['S4|A|active'] ?? -1) && !isset($after['S5|A|completed_o_level']));
check('old S.4 do not appear in the new S.4 class lists', count(sf_class_students($db, (int)$one("SELECT id FROM classes WHERE class_name = 'S4' AND stream_name = 'A'"))) === ($before['S3|A|active'] ?? 0));
check('S.6 finish as "completed S.6"', array_sum(array_map(fn($k, $v) => str_ends_with($k, '|completed_a_level') ? $v : 0, array_keys($after), $after)) === $s6);
check('S5 B1 moves to a newly created S6 B1', ($after['S6|B1|active'] ?? 0) === ($before['S5|B1|active'] ?? -1)
    && (int)$one("SELECT COUNT(*) FROM classes WHERE class_name = 'S6' AND stream_name = 'B1'") === 1);
check('every student counted once', array_sum($after) === array_sum($before));
check('each move is recorded', (int)$one('SELECT COUNT(*) FROM student_promotions') === array_sum($before) && $res['promotion']['promoted'] === array_sum($before) - $s4 - $s6);
check('levels follow the class (S4→S5 none; S5→S6 A-Level)', (int)$one("SELECT COUNT(*) FROM students WHERE class = 'S6' AND status = 'active' AND level <> 'A-Level'") === 0);

echo "Reopening a closed term (the current term stays open)\n";
$current = (int)$next['id'];
$res = ac_reopen_term($db, $sid, 'Test');
$row = $db->query("SELECT * FROM academic_sessions WHERE id = $sid")->fetch();
check('a closed term can be reopened', ac_session_status($row) === 'reopened', json_encode($res));
check('the current term stays open', (int)ac_open_session($db)['id'] === $current && (int)$one("SELECT is_active FROM academic_sessions WHERE id = $current") === 1);
check('still only one current term', (int)$one('SELECT COUNT(*) FROM academic_sessions WHERE is_active = 1') === 1);
// The earlier student has since moved up a class, so take someone in a class now.
$s2a = (int)$one("SELECT id FROM classes WHERE class_name = 'S2' AND stream_name = 'A'");
$now = (int)sf_class_students($db, $s2a)[0]['id'];
$db->exec("UPDATE exams SET status = 'open' WHERE id = $examId");
$saved = sf_save_marks($db, sf_exam($db, $examId), $s2a, 1, [['student_id' => $now, 'marks' => 64]], null);
check('marks can be entered in the reopened term', $saved['saved'] === 1 && (float)$one("SELECT marks FROM exam_marks WHERE exam_id = $examId AND student_id = $now") === 64.0, json_encode($saved));
check('only a closed term can be reopened', expectError(fn() => ac_reopen_term($db, $sid, 'Test'), 409) && expectError(fn() => ac_reopen_term($db, $current, 'Test'), 409));
check('the calendar lists it as reopened', in_array('reopened', array_column(array_merge(...array_column(ac_calendar($db)['years'], 'terms')), 'status'), true));

$res = ac_close_reopened($db, $sid, 'Test');
check('a reopened term can be closed again', (int)$one("SELECT is_archived FROM academic_sessions WHERE id = $sid") === 1 && $one("SELECT reopened_at FROM academic_sessions WHERE id = $sid") === null);
check('closing it again closes its open exams', $one("SELECT status FROM exams WHERE id = $examId") === 'closed' && $res['exams_closed'] === 1);
$db->exec("UPDATE exams SET status = 'open' WHERE id = $examId");
check('marks are locked again once it is closed', expectError(fn() => sf_save_marks($db, sf_exam($db, $examId), $s2a, 1, [['student_id' => $now, 'marks' => 50]], null), 423));
check('only a reopened term can be "closed again"', expectError(fn() => ac_close_reopened($db, $sid, 'Test'), 409) && expectError(fn() => ac_close_reopened($db, $current, 'Test'), 409));

$classesBefore = $byClass();
$movesBefore = (int)$one('SELECT COUNT(*) FROM student_promotions');
ac_reopen_term($db, (int)$t3['id'], 'Test');
$res = ac_close_term($db, [], 'Test');
check('closing the current term still moves on while another term is reopened', (int)ac_open_session($db)['term'] === 2 && (int)$one("SELECT COUNT(*) FROM academic_sessions WHERE id = {$t3['id']} AND is_archived = 0 AND reopened_at IS NOT NULL") === 1);
check('reopening never moves students', $byClass() === $classesBefore && (int)$one('SELECT COUNT(*) FROM student_promotions') === $movesBefore);

echo "Subjects and A-Level entry\n";
$comp = ac_compulsory($db);
check('O-Level has 7 compulsory subjects by default', count($comp['O-Level']) === 7, implode(', ', array_column($comp['O-Level'], 'subject_name')));
check('A-Level has General Paper compulsory', array_column($comp['A-Level'], 'subject_code') === ['g.p']);
$o = (int)$one("SELECT id FROM students WHERE class = 'S2' AND status = 'active' LIMIT 1");
$cre = (int)$one("SELECT id FROM subjects_new WHERE subject_code = 'CRE'");
$eng = (int)$one("SELECT id FROM subjects_new WHERE subject_code = 'ENG'");
$r = ac_set_student_subjects($db, $o, [$cre, $eng]);
check('options are saved; compulsory subjects are always included but not duplicated', count($r['options']) === 1 && $r['options'][0]['id'] === $cre && count($r['compulsory']) === 7);

$ids = array_map('intval', array_column($db->query("SELECT id FROM students WHERE status = 'completed_o_level' ORDER BY id LIMIT 2")->fetchAll(), 'id'));
$s5 = (int)$one("SELECT id FROM classes WHERE class_name = 'S5' AND stream_name = 'A'");
$phy = (int)$one("SELECT id FROM subjects_new WHERE subject_code = 'PHY'");
$che = (int)$one("SELECT id FROM subjects_new WHERE subject_code = 'CHE'");
$math = (int)$one("SELECT id FROM subjects_new WHERE subject_code = 'MATHS'");
check('A-Level entry needs a combination', expectError(fn() => ac_admit_a_level($db, $ids, $s5, [], 'Test'), 422));
check('only an S.5 stream can be chosen', expectError(fn() => ac_admit_a_level($db, $ids, (int)$one("SELECT id FROM classes WHERE class_name = 'S6' LIMIT 1"), [$phy, $che, $math], 'Test'), 422));
check('a current student cannot be "admitted"', expectError(fn() => ac_admit_a_level($db, [$o], $s5, [$phy, $che, $math], 'Test'), 422));
$res = ac_admit_a_level($db, $ids, $s5, [$phy, $che, $math], 'Test');
$st = $db->query('SELECT class, stream, level, status FROM students WHERE id = ' . $ids[0])->fetch();
check('completed S.4 students are admitted to S5 A', $res['admitted'] === 2 && $st == ['class' => 'S5', 'stream' => 'A', 'level' => 'A-Level', 'status' => 'active'], json_encode($st));
$subs = ac_student_subjects($db, $ids[0]);
check('they offer the combination plus G.P', array_column($subs['compulsory'], 'subject_code') === ['g.p'] && count($subs['options']) === 3);

echo "Subjects offered per level\n";
$offered = ac_offered($db);
$codes = fn(string $lv) => array_map('strtolower', array_column($offered[$lv], 'subject_code'));
check('O-Level does not offer Divinity, Economics, G.P or Sub-Maths', !array_intersect(['div', 'econ', 'g.p', 'sub-mathematics'], $codes('O-Level')));
check('A-Level does not offer English or CRE', !array_intersect(['eng', 'cre'], $codes('A-Level')) && in_array('g.p', $codes('A-Level'), true));
$econ = (int)$one("SELECT id FROM subjects_new WHERE subject_code = 'ECON'");
check('an O-Level student cannot be given Economics', expectError(fn() => ac_set_student_subjects($db, $o, [$econ]), 422));
check('an A-Level student cannot be given English', expectError(fn() => ac_set_student_subjects($db, $ids[0], [$eng, $phy]), 422));
$saved = ac_save_level_subjects($db, ['offered' => ['O-Level' => array_column($offered['O-Level'], 'id'), 'A-Level' => array_column($offered['A-Level'], 'id')],
    'compulsory' => ['O-Level' => [$eng, $econ], 'A-Level' => [$gpId = (int)$one("SELECT id FROM subjects_new WHERE subject_code = 'g.p'")]]]);
check('a subject not offered at a level cannot be made compulsory there', array_column($saved['compulsory']['O-Level'], 'id') === [$eng]);

echo "Mark sheets follow subjects offered\n";
$db->exec("INSERT INTO exams (academic_session_id, name, exam_type, max_marks, weight, class_level, status) VALUES ({$next['id']}, 'CAL TEST 2', 'EOT', 100, 80, 'all', 'open')");
$e2 = sf_exam($db, (int)$db->lastInsertId());
$sheet = sf_mark_sheet($db, $e2, $s5, $phy);
check('an option subject sheet lists only students who offer it', count($sheet['students']) === 2 && $sheet['offering_only'] === true, (string)count($sheet['students']));
$gp = (int)$one("SELECT id FROM subjects_new WHERE subject_code = 'g.p'");
check('a compulsory subject sheet lists the whole stream', count(sf_mark_sheet($db, $e2, $s5, $gp)['students']) === count(sf_class_students($db, $s5)));

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
