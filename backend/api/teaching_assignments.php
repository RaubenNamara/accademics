<?php
/**
 * Teaching assignments: which teacher teaches which subject to which class in a term.
 * Stored as lesson requirements, so the timetable, marks entry and lesson plans all use them.
 *
 * GET  ?academic_session_id=…            departments (subjects) with their teachers, each teacher's
 *                                        classes, the classes offering each subject, and totals
 * POST ?action=assign   {academic_session_id, teacher_id, subject_id, class_ids[]}
 *                                        one teacher per class and subject: a class already given to
 *                                        someone else for that subject moves to this teacher
 * POST ?action=unassign {id}             blocked while the timetable still uses it
 * POST ?action=copy     {academic_session_id, from_session_id}   last term's assignments, where free
 */
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/HrHelpers.php';
require_once __DIR__ . '/../config/TimetableService.php';
require_once __DIR__ . '/../config/SchoolService.php';

hr_cors_headers();
$db = (new Database())->getConnection();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
const TA_DEFAULT_PERIODS = 4;

try {
    tt_ensure_schema($db);
    sf_ensure_schema($db);
    // Admins and the academic office.
    $payload = hr_require_auth(['teacher', 'academic_office']);
    $action = (string)($_GET['action'] ?? '');
    $data = $method === 'GET' ? $_GET : hr_request_data();

    if ($method === 'GET') {
        [$sid] = tt_require_session($db, $_GET);
        hr_respond(true, 'Teaching assignments', ta_overview($db, $sid));
    }

    if ($method === 'POST' && $action === 'assign') {
        [$sid] = tt_require_session($db, $data);
        ta_require_open($db, $sid);
        $teacherId = (int)($data['teacher_id'] ?? 0);
        $subjectId = (int)($data['subject_id'] ?? 0);
        $classIds = array_values(array_unique(array_map('intval', (array)($data['class_ids'] ?? []))));
        if (!$teacherId || !$subjectId || !$classIds) {
            throw new TtException('Choose a teacher, a subject and at least one class', 422);
        }
        $teacher = $db->query("SELECT full_name FROM teachers WHERE id = $teacherId")->fetchColumn() ?: throw new TtException('Teacher not found', 404);
        $moved = [];
        $added = 0;
        $db->beginTransaction();
        foreach ($classIds as $classId) {
            $existing = $db->prepare('SELECT lr.id, lr.teacher_id, t.full_name FROM lesson_requirements lr JOIN teachers t ON t.id = lr.teacher_id
                WHERE lr.academic_session_id = ? AND lr.class_id = ? AND lr.subject_id = ?');
            $existing->execute([$sid, $classId, $subjectId]);
            $rows = $existing->fetchAll(PDO::FETCH_ASSOC);
            if ($rows && (int)$rows[0]['teacher_id'] === $teacherId) {
                continue;
            }
            if ($rows) {
                // One teacher per class and subject: hand it over, keeping lessons-a-week and timetable links.
                $db->prepare('UPDATE lesson_requirements SET teacher_id = ? WHERE id = ?')->execute([$teacherId, $rows[0]['id']]);
                $db->prepare('UPDATE timetable SET teacher_id = ? WHERE lesson_requirement_id = ?')->execute([$teacherId, $rows[0]['id']]);
                $moved[] = $rows[0]['full_name'];
                continue;
            }
            $db->prepare('INSERT INTO lesson_requirements (academic_session_id, class_id, subject_id, teacher_id, periods_per_week) VALUES (?, ?, ?, ?, ?)')
                ->execute([$sid, $classId, $subjectId, $teacherId, ta_default_periods($db, $sid, $classId, $subjectId)]);
            $added++;
        }
        $db->commit();
        $msg = "$teacher: $added class" . ($added === 1 ? '' : 'es') . ' added';
        if ($moved) {
            $msg .= ', ' . count($moved) . ' moved from ' . implode(', ', array_unique($moved));
        }
        hr_respond(true, $msg, ta_overview($db, $sid));
    }

    if ($method === 'POST' && $action === 'unassign') {
        $id = (int)($data['id'] ?? 0);
        $row = $db->query("SELECT academic_session_id FROM lesson_requirements WHERE id = $id")->fetch(PDO::FETCH_ASSOC) ?: throw new TtException('That assignment no longer exists', 404);
        ta_require_open($db, (int)$row['academic_session_id']);
        $used = (int)$db->query("SELECT COUNT(*) FROM timetable WHERE lesson_requirement_id = $id")->fetchColumn();
        if ($used) {
            throw new TtException("The timetable has $used lesson(s) for this class. Remove them from the timetable first, or give the class to another teacher instead.", 409);
        }
        $db->prepare('DELETE FROM lesson_requirements WHERE id = ?')->execute([$id]);
        hr_respond(true, 'Removed', ta_overview($db, (int)$row['academic_session_id']));
    }

    if ($method === 'POST' && $action === 'copy') {
        [$sid] = tt_require_session($db, $data);
        ta_require_open($db, $sid);
        $from = (int)($data['from_session_id'] ?? 0) ?: throw new TtException('Choose the term to copy from', 422);
        // Only class/subject pairs nobody teaches yet this term, by teachers who are still active.
        $stmt = $db->prepare('INSERT INTO lesson_requirements (academic_session_id, class_id, stream, subject_id, teacher_id, room_id, periods_per_week, double_lesson_allowed, double_lesson_required, preferred_span)
            SELECT :to, o.class_id, o.stream, o.subject_id, o.teacher_id, o.room_id, o.periods_per_week, o.double_lesson_allowed, o.double_lesson_required, o.preferred_span
            FROM lesson_requirements o JOIN teachers t ON t.id = o.teacher_id AND (t.is_active = 1 OR t.is_active IS NULL)
            WHERE o.academic_session_id = :from
              AND NOT EXISTS (SELECT 1 FROM lesson_requirements n WHERE n.academic_session_id = :to2 AND n.class_id = o.class_id AND n.subject_id = o.subject_id)');
        $stmt->execute([':to' => $sid, ':from' => $from, ':to2' => $sid]);
        $n = $stmt->rowCount();
        hr_respond(true, $n ? "Copied $n assignment(s)" : 'Nothing to copy: every class and subject is already assigned, or that term has none', ta_overview($db, $sid));
    }

    hr_respond(false, 'Unknown action', null, 400);
} catch (TtException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    hr_respond(false, $e->getMessage(), $e->data ?? null, $e->status ?? 422);
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    hr_respond(false, $e->getMessage(), null, 500);
}

function ta_require_open(PDO $db, int $sid): void
{
    if ((int)$db->query("SELECT is_archived FROM academic_sessions WHERE id = $sid")->fetchColumn() === 1) {
        throw new TtException('That term is closed. Assign teachers in the open term.', 409);
    }
}

/** Lessons a week for a new assignment: what the class had for the subject before, else a default. */
function ta_default_periods(PDO $db, int $sid, int $classId, int $subjectId): int
{
    $stmt = $db->prepare('SELECT periods_per_week FROM lesson_requirements WHERE class_id = ? AND subject_id = ? AND academic_session_id <> ? ORDER BY academic_session_id DESC LIMIT 1');
    $stmt->execute([$classId, $subjectId, $sid]);
    return (int)($stmt->fetchColumn() ?: TA_DEFAULT_PERIODS);
}

function ta_overview(PDO $db, int $sid): array
{
    $classes = $db->query('SELECT id, full_class_name, class_name, stream_name FROM classes ORDER BY class_name, stream_name')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($classes as &$c) {
        $c['id'] = (int)$c['id'];
        $c['level'] = tt_class_level((string)$c['class_name']);
    }
    unset($c);
    $offered = [];
    foreach (ac_offered($db) as $level => $rows) {
        $offered[$level] = array_map('intval', array_column($rows, 'id'));
    }

    $teachers = $db->query('SELECT t.id, t.full_name, t.teacher_code, t.is_active FROM teachers t WHERE t.is_active = 1 OR t.is_active IS NULL ORDER BY t.full_name')->fetchAll(PDO::FETCH_ASSOC);
    $byTeacher = [];
    foreach ($teachers as $t) {
        $byTeacher[(int)$t['id']] = ['id' => (int)$t['id'], 'full_name' => $t['full_name'], 'teacher_code' => $t['teacher_code']];
    }
    $links = $db->query('SELECT teacher_id, subject_id FROM teacher_subjects')->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->prepare('SELECT lr.id, lr.class_id, lr.subject_id, lr.teacher_id, lr.periods_per_week,
            (SELECT COUNT(*) FROM timetable tt WHERE tt.lesson_requirement_id = lr.id) AS timetabled
        FROM lesson_requirements lr WHERE lr.academic_session_id = ?');
    $stmt->execute([$sid]);
    $assignments = array_map(fn($r) => array_map('intval', $r), $stmt->fetchAll(PDO::FETCH_ASSOC));

    $subjects = $db->query('SELECT id, subject_name, subject_code FROM subjects_new ORDER BY subject_name')->fetchAll(PDO::FETCH_ASSOC);
    $departments = [];
    foreach ($subjects as $s) {
        $id = (int)$s['id'];
        $classIds = array_values(array_map(fn($c) => $c['id'], array_filter($classes, fn($c) => in_array($id, $offered[$c['level']] ?? [], true))));
        $members = array_values(array_filter(array_map(fn($l) => (int)$l['subject_id'] === $id ? ($byTeacher[(int)$l['teacher_id']] ?? null) : null, $links)));
        $mine = array_values(array_filter($assignments, fn($a) => $a['subject_id'] === $id));
        // A teacher given this subject from outside its department is listed with it too.
        $listed = array_column($members, 'id');
        foreach (array_unique(array_column($mine, 'teacher_id')) as $tid) {
            if (!in_array($tid, $listed, true) && isset($byTeacher[$tid])) {
                $members[] = $byTeacher[$tid] + ['outside_department' => true];
            }
        }
        if (!$members && !$mine) {
            continue;
        }
        $departments[] = [
            'subject_id' => $id,
            'name' => $s['subject_name'],
            'code' => $s['subject_code'],
            'teachers' => $members,
            'class_ids' => $classIds,
            'covered' => count(array_unique(array_column($mine, 'class_id'))),
        ];
    }

    $assignedTeachers = array_unique(array_column($assignments, 'teacher_id'));
    $needed = array_sum(array_map(fn($d) => count($d['class_ids']), $departments));
    $covered = array_sum(array_column($departments, 'covered'));
    $sessions = $db->query('SELECT a.id, a.session_name, a.is_active, a.is_archived, (SELECT COUNT(*) FROM lesson_requirements lr WHERE lr.academic_session_id = a.id) AS assignments
        FROM academic_sessions a ORDER BY a.academic_year DESC, a.term DESC')->fetchAll(PDO::FETCH_ASSOC);
    return [
        'academic_session_id' => $sid,
        'closed' => (int)$db->query("SELECT is_archived FROM academic_sessions WHERE id = $sid")->fetchColumn() === 1,
        'classes' => $classes,
        'departments' => $departments,
        'assignments' => $assignments,
        'teachers' => array_values($byTeacher),
        'sessions' => $sessions,
        'stats' => [
            'teachers' => count($byTeacher),
            'assigned_teachers' => count(array_intersect(array_keys($byTeacher), $assignedTeachers)),
            'needed' => $needed,
            'covered' => $covered,
            'assignments' => count($assignments),
        ],
    ];
}
