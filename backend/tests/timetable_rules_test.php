<?php
/**
 * Timetable rules test (CLI): php backend/tests/timetable_rules_test.php
 *
 * Runs against the configured database inside a throwaway academic session
 * (2099 Term 3) with its own test rooms. Existing classes/teachers/subjects
 * are only referenced, never modified. Everything created is removed at the end.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/TimetableService.php';

$db = (new Database())->getConnection();
tt_ensure_schema($db);

const YEAR = 2099;
const TERM = 3;
$actor = ['id' => null, 'name' => 'Timetable test', 'role' => 'super_admin'];
$pass = 0;
$fail = 0;

function check(string $name, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    echo ($ok ? '  PASS ' : '  FAIL ') . $name . ($ok || $detail === '' ? '' : " — $detail") . PHP_EOL;
}

/** Runs $fn and returns [status, exception|null, result]. */
function attempt(callable $fn): array
{
    try {
        return [200, null, $fn()];
    } catch (TtException $e) {
        return [$e->status, $e, null];
    }
}

function conflictTypes(?TtException $e): array
{
    return array_column($e->data['conflicts'] ?? [], 'type');
}

function cleanup(PDO $db): void
{
    $sid = $db->query('SELECT id FROM academic_sessions WHERE academic_year = ' . YEAR . ' AND term = ' . TERM)->fetchColumn();
    $db->exec('DELETE FROM timetable WHERE academic_year = ' . YEAR . ' AND term = ' . TERM);
    if ($sid) {
        $db->exec("DELETE FROM timetable_audit_log WHERE academic_session_id = $sid");
        $db->exec("DELETE FROM academic_sessions WHERE id = $sid"); // cascades requirements, availability, versions, state
    }
    $db->exec("DELETE FROM rooms WHERE room_code LIKE 'TTEST-%'");
}

cleanup($db);
$db->exec("INSERT INTO academic_sessions (session_name, academic_year, term, start_date, end_date, is_active) VALUES ('TEST 2099 T3', " . YEAR . ', ' . TERM . ", '2099-09-01', '2099-12-01', 0)");
$sid = (int)$db->lastInsertId();
$db->exec("INSERT INTO rooms (room_code, room_name, room_type) VALUES ('TTEST-1', 'Test Lab 1', 'laboratory'), ('TTEST-2', 'Test Room 2', 'classroom')");
$room1 = (int)$db->query("SELECT id FROM rooms WHERE room_code = 'TTEST-1'")->fetchColumn();
$room2 = (int)$db->query("SELECT id FROM rooms WHERE room_code = 'TTEST-2'")->fetchColumn();

$oLevel = $db->query("SELECT id FROM classes WHERE class_name REGEXP '^S ?[1-4]' ORDER BY id LIMIT 3")->fetchAll(PDO::FETCH_COLUMN);
$aLevel = (int)$db->query("SELECT id FROM classes WHERE class_name REGEXP '^S ?[56]' ORDER BY id LIMIT 1")->fetchColumn();
$teachers = $db->query('SELECT id FROM teachers WHERE is_active = 1 OR is_active IS NULL ORDER BY id LIMIT 5')->fetchAll(PDO::FETCH_COLUMN);
$subjects = $db->query('SELECT id FROM subjects_new ORDER BY id LIMIT 4')->fetchAll(PDO::FETCH_COLUMN);
[$c1, $c2, $c3] = array_map('intval', $oLevel);
[$t1, $t2, $t3, $t4, $t5] = array_map('intval', $teachers);
[$s1, $s2, $s3, $s4] = array_map('intval', $subjects);

// The test pairs its teachers with its subjects freely, so put them in those departments for the
// duration of the run (only links that did not exist are added, and only those are removed).
tt_ensure_subject_departments($db);
$addedLinks = [];
$link = $db->prepare('INSERT IGNORE INTO teacher_subjects (teacher_id, subject_id) VALUES (:t, :s)');
foreach ([$t1, $t2, $t3, $t4, $t5] as $tid) {
    foreach ([$s1, $s2, $s3, $s4] as $sId) {
        $link->execute([':t' => $tid, ':s' => $sId]);
        if ($link->rowCount()) {
            $addedLinks[] = [$tid, $sId];
        }
    }
}

$base = ['academic_session_id' => $sid, 'entry_type' => 'lesson'];
$lesson = fn(array $x) => $base + $x + ['spans_periods' => 1];
$save = fn(array $x, ?int $id = null) => attempt(fn() => tt_save_entry($db, $lesson($x), $id, $actor));

try {
    echo "Clash prevention\n";
    [$st, , $a] = $save(['class_id' => $c1, 'teacher_id' => $t1, 'subject_id' => $s1, 'room_id' => $room1, 'day_of_week' => 'Monday', 'period_number' => 1]);
    check('creates a valid lesson', $st === 200 && $a['id'] > 0);
    $lessonA = $a['id'];

    [$st, $e] = $save(['class_id' => $c2, 'teacher_id' => $t1, 'subject_id' => $s2, 'day_of_week' => 'Monday', 'period_number' => 1]);
    check('blocks a teacher clash across classes', $st === 409 && in_array('teacher_clash', conflictTypes($e), true), (string)$st);

    [$st, $e] = $save(['class_id' => $c1, 'teacher_id' => $t2, 'subject_id' => $s2, 'day_of_week' => 'Monday', 'period_number' => 1]);
    check('blocks a class clash', $st === 409 && in_array('class_clash', conflictTypes($e), true), (string)$st);

    [$st, $e] = $save(['class_id' => $c2, 'teacher_id' => $t2, 'subject_id' => $s2, 'room_id' => $room1, 'day_of_week' => 'Monday', 'period_number' => 1]);
    check('blocks a room clash', $st === 409 && in_array('room_clash', conflictTypes($e), true), (string)$st);

    [$st, , $b] = $save(['class_id' => $c2, 'teacher_id' => $t2, 'subject_id' => $s2, 'day_of_week' => 'Monday', 'period_number' => 2, 'spans_periods' => 2]);
    check('creates a double lesson (periods 2-3)', $st === 200);
    [$st, $e] = $save(['class_id' => $c3, 'teacher_id' => $t2, 'subject_id' => $s3, 'day_of_week' => 'Monday', 'period_number' => 3]);
    check('blocks a lesson overlapping the 2nd half of a double', $st === 409 && in_array('teacher_clash', conflictTypes($e), true), (string)$st);

    echo "Invalid allocations\n";
    [$st, $e] = $save(['class_id' => $c3, 'teacher_id' => $t3, 'subject_id' => $s3, 'day_of_week' => 'Tuesday', 'period_number' => 3, 'spans_periods' => 2]);
    check('rejects a double that runs into the break', $st === 422 && isset($e->data['errors']['period_number']), (string)$st);
    [$st, $e] = $save(['class_id' => $c3, 'teacher_id' => $t3, 'subject_id' => $s3, 'day_of_week' => 'Tuesday', 'period_number' => 9, 'spans_periods' => 2]);
    check('rejects a lesson past the last period', $st === 422, (string)$st);
    [$st, $e] = $save(['class_id' => $c3, 'teacher_id' => $t3, 'subject_id' => $s3, 'day_of_week' => 'Tuesday', 'period_number' => 5, 'spans_periods' => 3]);
    check('rejects a triple for an O-Level class', $st === 422 && isset($e->data['errors']['spans_periods']), (string)$st);
    if ($aLevel) {
        [$st] = $save(['class_id' => $aLevel, 'teacher_id' => $t4, 'subject_id' => $s4, 'day_of_week' => 'Tuesday', 'period_number' => 5, 'spans_periods' => 3]);
        check('allows a triple for an A-Level class', $st === 200, (string)$st);
    }
    [$st, $e] = $save(['class_id' => $c3, 'teacher_id' => $t3, 'subject_id' => $s3, 'day_of_week' => 'Saturday', 'period_number' => 1]);
    check('rejects a non-school day', $st === 422 && isset($e->data['errors']['day_of_week']), (string)$st);
    [$st, $e] = $save(['class_id' => $c3, 'day_of_week' => 'Tuesday', 'period_number' => 1]);
    check('requires subject and teacher', $st === 422 && isset($e->data['errors']['subject_id'], $e->data['errors']['teacher_id']), (string)$st);
    check('a validation error explains itself', $st === 422 && $e->getMessage() === 'Choose a subject', $e ? $e->getMessage() : '');

    $db->prepare("INSERT INTO teacher_availability (teacher_id, academic_session_id, day_of_week, period_number, is_available, reason) VALUES (:t, :s, 'Tuesday', 1, 0, 'Staff meeting')")
        ->execute([':t' => $t3, ':s' => $sid]);
    [$st, $e] = $save(['class_id' => $c3, 'teacher_id' => $t3, 'subject_id' => $s3, 'day_of_week' => 'Tuesday', 'period_number' => 1]);
    check('blocks a lesson when the teacher is unavailable', $st === 409 && in_array('teacher_unavailable', conflictTypes($e), true), (string)$st);

    echo "Duplicates\n";
    $req = 'test-dup-' . bin2hex(random_bytes(6));
    [$st1, , $r1] = $save(['class_id' => $c3, 'teacher_id' => $t3, 'subject_id' => $s3, 'day_of_week' => 'Wednesday', 'period_number' => 1, 'client_request_id' => $req]);
    [$st2, , $r2] = $save(['class_id' => $c3, 'teacher_id' => $t3, 'subject_id' => $s3, 'day_of_week' => 'Wednesday', 'period_number' => 1, 'client_request_id' => $req]);
    $count = (int)$db->query('SELECT COUNT(*) FROM timetable WHERE academic_year = ' . YEAR . ' AND term = ' . TERM . " AND day_of_week = 'Wednesday' AND period_number = 1 AND class_id = $c3")->fetchColumn();
    check('a repeated submission returns the same lesson', $st1 === 200 && $st2 === 200 && $r2['duplicate'] && $r1['id'] === $r2['id'] && $count === 1);

    $db->prepare('INSERT INTO lesson_requirements (academic_session_id, class_id, subject_id, teacher_id, periods_per_week) VALUES (:s, :c, :sub, :t, 2)')
        ->execute([':s' => $sid, ':c' => $c1, ':sub' => $s2, ':t' => $t2]);
    $save(['class_id' => $c1, 'teacher_id' => $t2, 'subject_id' => $s2, 'day_of_week' => 'Thursday', 'period_number' => 1]);
    $save(['class_id' => $c1, 'teacher_id' => $t2, 'subject_id' => $s2, 'day_of_week' => 'Thursday', 'period_number' => 2]);
    [$st, $e] = $save(['class_id' => $c1, 'teacher_id' => $t2, 'subject_id' => $s2, 'day_of_week' => 'Friday', 'period_number' => 1]);
    check('blocks scheduling beyond the weekly requirement', $st === 409 && in_array('over_allocation', conflictTypes($e), true), (string)$st);

    echo "Editing and locking\n";
    // t2 already teaches c1 on Thursday period 1.
    [$st, $e] = $save(['class_id' => $c2, 'teacher_id' => $t2, 'subject_id' => $s2, 'day_of_week' => 'Thursday', 'period_number' => 1], $b['id']);
    check('blocks moving a lesson into a clash', $st === 409 && in_array('teacher_clash', conflictTypes($e), true), (string)$st);
    [$st] = $save(['class_id' => $c2, 'teacher_id' => $t2, 'subject_id' => $s2, 'day_of_week' => 'Monday', 'period_number' => 2, 'spans_periods' => 2, 'notes' => 'edited'], $b['id']);
    check('re-saving a lesson in its own slot is allowed', $st === 200, (string)$st);
    [$st] = $save(['class_id' => $c2, 'teacher_id' => $t2, 'subject_id' => $s2, 'day_of_week' => 'Monday', 'period_number' => 5, 'spans_periods' => 2], $b['id']);
    $slotsFreed = (int)$db->query("SELECT COUNT(*) FROM timetable_slots WHERE timetable_id = {$b['id']} AND period_number IN (2,3)")->fetchColumn();
    check('rescheduling moves its occupancy slots', $st === 200 && $slotsFreed === 0);

    tt_set_locked($db, $lessonA, true, $actor);
    [$st] = $save(['class_id' => $c1, 'teacher_id' => $t1, 'subject_id' => $s1, 'day_of_week' => 'Monday', 'period_number' => 6], $lessonA);
    check('a locked lesson cannot be edited', $st === 423, (string)$st);
    [$st] = attempt(fn() => tt_delete_entry($db, $lessonA, $actor));
    check('a locked lesson cannot be deleted', $st === 423, (string)$st);

    echo "Database-level guarantee\n";
    try {
        $db->prepare("INSERT INTO timetable_slots (timetable_id, academic_year, term, day_of_week, period_number, resource_type, resource_id) VALUES (:id, :y, :t, 'Monday', 1, 'teacher', :tid)")
            ->execute([':id' => $b['id'], ':y' => YEAR, ':t' => TERM, ':tid' => $t1]);
        check('unique slot key rejects a raw double booking', false, 'insert succeeded');
    } catch (PDOException $ex) {
        check('unique slot key rejects a raw double booking', tt_is_duplicate_key($ex));
    }

    echo "Validation\n";
    // Simulate legacy data written before this module existed (bypasses the service).
    $db->exec('INSERT INTO timetable (academic_session_id, academic_year, term, day_of_week, period_number, spans_periods, class_id, subject_id, teacher_id, entry_type)
        VALUES (' . $sid . ', ' . YEAR . ', ' . TERM . ", 'Friday', 6, 2, $c3, $s4, $t5, 'lesson'), ($sid, " . YEAR . ', ' . TERM . ", 'Friday', 7, 1, $c2, $s4, $t5, 'lesson')");
    $v = tt_validate($db, $sid, YEAR, TERM);
    $types = array_column($v['issues'], 'type');
    check('detects a legacy overlapping teacher clash', in_array('teacher_clash', $types, true));
    check('detects missing lessons', in_array('missing_lessons', $types, true) || $v['stats']['unscheduled_periods'] === 0);
    check('reports blocking state', $v['summary']['state'] === 'blocking');
    check('stats come from data', $v['stats']['scheduled_lessons'] > 0 && $v['stats']['teacher_conflicts'] >= 1);

    echo "Publishing\n";
    [$st, $e] = attempt(fn() => tt_publish($db, ['academic_session_id' => $sid], $actor));
    check('publishing is blocked while clashes exist', $st === 422, (string)$st);
    $db->exec('DELETE FROM timetable WHERE academic_year = ' . YEAR . ' AND term = ' . TERM . " AND day_of_week = 'Friday' AND teacher_id = $t5");
    $db->prepare('INSERT INTO lesson_requirements (academic_session_id, class_id, subject_id, teacher_id, periods_per_week) VALUES (:s, :c, :sub, :t, 4)')
        ->execute([':s' => $sid, ':c' => $c3, ':sub' => $s1, ':t' => $t4]);
    [$st, $e] = attempt(fn() => tt_publish($db, ['academic_session_id' => $sid], $actor));
    check('warnings need explicit confirmation', $st === 409 && !empty($e->data['needs_confirmation']), (string)$st);
    [$st, , $pub] = attempt(fn() => tt_publish($db, ['academic_session_id' => $sid, 'confirm_warnings' => true, 'effective_date' => '2099-09-08'], $actor));
    check('publishes once confirmed', $st === 200 && $pub['version_number'] === 1, (string)$st);
    check('status is published', tt_session_state($db, $sid)['status'] === 'published');
    $publishedCount = count(tt_published_entries($db, $sid));

    tt_set_locked($db, $lessonA, false, $actor);
    $save(['class_id' => $c1, 'teacher_id' => $t1, 'subject_id' => $s1, 'room_id' => $room2, 'day_of_week' => 'Monday', 'period_number' => 1], $lessonA);
    $state = tt_session_state($db, $sid);
    check('editing after publishing makes a draft', $state['status'] === 'draft' && $state['has_unpublished_changes']);
    check('the published snapshot is unchanged', count(tt_published_entries($db, $sid)) === $publishedCount);

    [$st, , $res] = attempt(fn() => tt_restore($db, $pub['version_id'], $actor));
    $roomNow = (int)$db->query("SELECT room_id FROM timetable WHERE academic_year = " . YEAR . " AND term = " . TERM . " AND day_of_week = 'Monday' AND period_number = 1 AND class_id = $c1")->fetchColumn();
    check('restore brings back the published lessons', $st === 200 && $res['restored'] === $publishedCount && $roomNow === $room1, "status $st");
    check('restored timetable is published again', tt_session_state($db, $sid)['status'] === 'published');
    // Restoring re-creates rows, so look lesson A up again.
    $lessonA = (int)$db->query('SELECT id FROM timetable WHERE academic_year = ' . YEAR . ' AND term = ' . TERM . " AND day_of_week = 'Monday' AND period_number = 1 AND class_id = $c1")->fetchColumn();

    echo "Generation\n";
    $db->prepare('INSERT INTO lesson_requirements (academic_session_id, class_id, subject_id, teacher_id, periods_per_week, double_lesson_allowed) VALUES (:s, :c, :sub, :t, 5, 1)')
        ->execute([':s' => $sid, ':c' => $c2, ':sub' => $s3, ':t' => $t3]);
    $before = (int)$db->query('SELECT COUNT(*) FROM timetable WHERE academic_year = ' . YEAR . ' AND term = ' . TERM)->fetchColumn();
    [$st, , $dry] = attempt(fn() => tt_generate($db, ['academic_session_id' => $sid, 'class_ids' => [$c2, $c3], 'dry_run' => true], $actor));
    $after = (int)$db->query('SELECT COUNT(*) FROM timetable WHERE academic_year = ' . YEAR . ' AND term = ' . TERM)->fetchColumn();
    check('a preview places lessons without saving them', $st === 200 && $dry['placed_periods'] > 0 && $before === $after, "st=$st");

    [$st, , $gen] = attempt(fn() => tt_generate($db, ['academic_session_id' => $sid, 'class_ids' => [$c2, $c3]], $actor));
    $v = tt_validate($db, $sid, YEAR, TERM);
    $clashes = $v['stats']['teacher_conflicts'] + $v['stats']['class_conflicts'] + $v['stats']['room_conflicts'];
    check('generation fills the requirements', $st === 200 && $gen['placed_periods'] === 9 && $gen['unplaced_periods'] === 0, json_encode($gen['unplaced'] ?? []));
    check('generated timetable has no clashes', $clashes === 0, "$clashes clash(es)");
    check('generation respects teacher unavailability', (int)$db->query("SELECT COUNT(*) FROM timetable WHERE academic_year = " . YEAR . " AND term = " . TERM . " AND teacher_id = $t3 AND day_of_week = 'Tuesday' AND period_number = 1")->fetchColumn() === 0);

    tt_set_locked($db, $lessonA, true, $actor);
    [$st, , $regen] = attempt(fn() => tt_generate($db, ['academic_session_id' => $sid, 'class_ids' => [$c1], 'mode' => 'regenerate'], $actor));
    $lockedStillThere = (int)$db->query("SELECT COUNT(*) FROM timetable WHERE id = $lessonA")->fetchColumn();
    check('regenerating keeps locked lessons', $st === 200 && $lockedStillThere === 1 && $regen['removed'] >= 2, "removed {$regen['removed']}");

    $db->prepare('INSERT INTO lesson_requirements (academic_session_id, class_id, subject_id, teacher_id, periods_per_week) VALUES (:s, :c, :sub, :t, 1)')
        ->execute([':s' => $sid, ':c' => $c3, ':sub' => $s4, ':t' => $t5]);
    $ins = $db->prepare("INSERT INTO teacher_availability (teacher_id, academic_session_id, day_of_week, period_number, is_available) VALUES (:t, :s, :d, :p, 0)");
    foreach (TT_DAYS as $d) {
        foreach (array_keys(tt_load_periods($db)) as $p) {
            $ins->execute([':t' => $t5, ':s' => $sid, ':d' => $d, ':p' => $p]);
        }
    }
    [$st, , $imp] = attempt(fn() => tt_generate($db, ['academic_session_id' => $sid, 'class_ids' => [$c3]], $actor));
    $u = $imp['unplaced'][0] ?? [];
    check('an impossible requirement is explained, not forced', $st === 200 && ($u['missing'] ?? 0) === 1 && in_array('the teacher is marked unavailable', $u['reasons'] ?? [], true), json_encode($u));

    echo "Requirements template import\n";
    $cls = $db->query("SELECT full_class_name, class_name, stream_name FROM classes WHERE id = $c3")->fetch(PDO::FETCH_ASSOC);
    $multiStream = (string)$db->query('SELECT class_name FROM classes GROUP BY class_name HAVING COUNT(*) > 1 LIMIT 1')->fetchColumn();
    $tRow = $db->query("SELECT full_name, teacher_code FROM teachers WHERE id = $t4")->fetch(PDO::FETCH_ASSOC);
    $subName = (string)$db->query("SELECT subject_name FROM subjects_new WHERE id = $s2")->fetchColumn();
    $sub3 = $db->query("SELECT subject_name, subject_code FROM subjects_new WHERE id = $s3")->fetch(PDO::FETCH_ASSOC);
    $rows = [
        ['Class' => strtolower(str_replace(' ', '  ', $cls['full_class_name'])), 'Subject' => $subName, 'Teacher' => $tRow['teacher_code'], 'Periods per week' => '4', 'Lesson length' => 'Double', 'Double allowed' => 'yes', 'Double required' => 'no', 'Room' => 'TTEST-1'],
        ['Class' => $cls['class_name'], 'Stream' => $cls['stream_name'], 'Subject' => $sub3['subject_code'], 'Teacher' => strtoupper($tRow['full_name']), 'Periods per week' => 3, 'Lesson length' => '', 'Double allowed' => 'No', 'Double required' => 'No', 'Avoid days' => 'Fri'],
        ['Class' => $multiStream, 'Subject' => $subName, 'Teacher' => $tRow['full_name'], 'Periods per week' => 2, 'Double allowed' => 'no', 'Double required' => 'no'],
        ['Class' => $cls['full_class_name'], 'Subject' => 'Underwater Basketry', 'Teacher' => $tRow['full_name'], 'Periods per week' => 2, 'Double allowed' => 'maybe', 'Double required' => 'no'],
        ['Class' => $cls['full_class_name'], 'Subject' => $subName, 'Teacher' => $tRow['teacher_code'], 'Periods per week' => 4, 'Double allowed' => 'no', 'Double required' => 'no'],
        ['Class' => $cls['full_class_name'], 'Subject' => $sub3['subject_name'], 'Teacher' => $tRow['full_name'], 'Periods per week' => 2, 'Lesson length' => 'Triple', 'Double allowed' => 'no', 'Double required' => 'no'],
        ['Class' => '', 'Subject' => '', 'Teacher' => '', 'Periods per week' => ''],
    ];
    $before = (int)$db->query("SELECT COUNT(*) FROM lesson_requirements WHERE academic_session_id = $sid")->fetchColumn();
    $dry = tt_import_requirements($db, $sid, $rows, true, false, $actor);
    $after = (int)$db->query("SELECT COUNT(*) FROM lesson_requirements WHERE academic_session_id = $sid")->fetchColumn();
    check('a preview saves nothing', $before === $after && $dry['valid'] === 2, json_encode(['valid' => $dry['valid'], 'errors' => $dry['errors']]));
    $msgs = implode(' | ', array_merge(...array_column($dry['errors'], 'messages')));
    $lines = array_column($dry['errors'], 'line');
    check('class names match regardless of case and spacing; teacher by code or name', $dry['valid'] === 2);
    check('a class without its stream is rejected with the stream list', str_contains($msgs, 'several streams'), $msgs);
    check('unknown subject and bad yes/no are reported', str_contains($msgs, 'Underwater Basketry') && str_contains($msgs, 'Yes or No'), $msgs);
    check('a duplicate row is reported with the line it repeats', str_contains($msgs, 'Duplicate of line 2'), $msgs);
    check('a triple for an O-Level class is rejected', str_contains($msgs, 'at most Double'), $msgs);
    check('errors carry spreadsheet line numbers and blank rows are skipped', $lines === [4, 5, 6, 7], json_encode($lines));

    $res = tt_import_requirements($db, $sid, $rows, false, false, $actor);
    $saved = $db->query("SELECT * FROM lesson_requirements WHERE academic_session_id = $sid AND class_id = $c3 AND teacher_id = $t4 AND subject_id = $s2")->fetch(PDO::FETCH_ASSOC);
    check('valid rows are saved with their settings', $res['created'] + $res['updated'] === 2 && (int)$saved['periods_per_week'] === 4 && (int)$saved['preferred_span'] === 2 && (int)$saved['room_id'] === $room1, json_encode($saved));
    $other = $db->query("SELECT double_lesson_allowed, avoid_days FROM lesson_requirements WHERE academic_session_id = $sid AND class_id = $c3 AND teacher_id = $t4 AND subject_id = $s3")->fetch(PDO::FETCH_ASSOC);
    check('“No” is saved as no and short day names are understood', (int)$other['double_lesson_allowed'] === 0 && json_decode($other['avoid_days']) === ['Friday'], json_encode($other));
    $count1 = (int)$db->query("SELECT COUNT(*) FROM lesson_requirements WHERE academic_session_id = $sid")->fetchColumn();
    $res2 = tt_import_requirements($db, $sid, $rows, false, false, $actor);
    $count2 = (int)$db->query("SELECT COUNT(*) FROM lesson_requirements WHERE academic_session_id = $sid")->fetchColumn();
    check('importing the same file again updates instead of duplicating', $res2['updated'] === 2 && $res2['created'] === 0 && $count1 === $count2);

    $csv = "\xEF\xBB\xBFClass,Subject,Teacher,Periods per week,Double allowed,Double required\r\n\"{$cls['full_class_name']}\",{$subName},{$tRow['teacher_code']},5,no,no\r\n";
    $res3 = tt_import_requirements($db, $sid, tt_csv_rows($csv), false, true, $actor);
    $left = (int)$db->query("SELECT COUNT(*) FROM lesson_requirements WHERE academic_session_id = $sid")->fetchColumn();
    check('CSV with Excel BOM and Windows line endings imports; replace mode removes the rest', $res3['updated'] === 1 && $res3['removed'] > 0 && $left === 1, json_encode($res3));

    $heavy = [];
    foreach ($subjects as $k => $sId) {
        $heavy[] = ['Class' => $cls['full_class_name'], 'Subject' => $db->query("SELECT subject_name FROM subjects_new WHERE id = $sId")->fetchColumn(), 'Teacher' => $tRow['teacher_code'], 'Periods per week' => 12, 'Double allowed' => 'no', 'Double required' => 'no'];
    }
    $res4 = tt_import_requirements($db, $sid, $heavy, true, false, $actor);
    check('a class needing more periods than the week has is warned about', (bool)array_filter($res4['warnings'], fn($w) => str_contains($w, $cls['full_class_name'])), json_encode($res4['warnings']));

    echo "Subject departments\n";
    $outsider = (int)$db->query("SELECT id FROM teachers t WHERE (is_active = 1 OR is_active IS NULL)
        AND NOT EXISTS (SELECT 1 FROM teacher_subjects ts WHERE ts.teacher_id = t.id AND ts.subject_id = $s1) LIMIT 1")->fetchColumn();
    [$st, $e] = $save(['class_id' => $c2, 'teacher_id' => $outsider, 'subject_id' => $s1, 'day_of_week' => 'Thursday', 'period_number' => 10]);
    check('a teacher outside the subject’s department is refused', $st === 422 && str_contains($e->data['errors']['teacher_id'] ?? '', 'department'), $st . ' ' . ($e ? $e->getMessage() : ''));
    $imp = tt_import_requirements($db, $sid, [['Class' => $cls['full_class_name'], 'Subject' => $db->query("SELECT subject_name FROM subjects_new WHERE id = $s1")->fetchColumn(),
        'Teacher' => $db->query("SELECT teacher_code FROM teachers WHERE id = $outsider")->fetchColumn(), 'Periods per week' => 2, 'Double allowed' => 'no', 'Double required' => 'no']], true, false, $actor);
    check('the import refuses a teacher outside the department', $imp['valid'] === 0 && str_contains(json_encode($imp['errors']), 'department'), json_encode($imp['errors']));
    $empty = (int)$db->query('SELECT s.id FROM subjects_new s WHERE NOT EXISTS (SELECT 1 FROM teacher_subjects ts WHERE ts.subject_id = s.id) LIMIT 1')->fetchColumn();
    if ($empty) {
        check('a subject with no department yet accepts any teacher', tt_department_error($db, $empty, $outsider) === null);
    }

    echo "Audit trail\n";
    $actions = array_column(tt_audit_log($db, $sid), 'action');
    check('changes are audited', count(array_intersect(['create', 'update', 'lock', 'publish', 'restore', 'generate'], $actions)) === 6);
} finally {
    cleanup($db);
    $unlink = $db->prepare('DELETE FROM teacher_subjects WHERE teacher_id = :t AND subject_id = :s');
    foreach ($addedLinks as [$tid, $sId]) {
        $unlink->execute([':t' => $tid, ':s' => $sId]);
    }
    $left = (int)$db->query('SELECT COUNT(*) FROM timetable WHERE academic_year = ' . YEAR . ' AND term = ' . TERM)->fetchColumn();
    echo "\nCleanup: " . ($left === 0 ? 'test data removed' : "$left rows left!") . PHP_EOL;
}

echo "\n$pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
