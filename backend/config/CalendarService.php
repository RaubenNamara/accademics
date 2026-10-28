<?php
/**
 * Academic calendar (open / close terms and years), year-end promotion, A-Level entry and the
 * subjects each student offers.
 *
 * A term is "open" when academic_sessions.is_active = 1 (the current term, only one at a time),
 * "reopened" when a closed term has been opened again for data entry (reopened_at set, is_archived = 0;
 * the current term stays open), "closed" when is_archived = 1, otherwise "upcoming". Closing the open term opens the next one; closing Term 3
 * closes the year: students move up a class in the same stream, S.4 become "completed_o_level"
 * (kept, greyed, until admitted to S.5) and S.6 become "completed_a_level".
 */
declare(strict_types=1);

require_once __DIR__ . '/SchoolService.php';

const AC_STATUSES = ['active', 'completed_o_level', 'completed_a_level', 'left'];
/** O-Level compulsory subjects (codes) until the school sets its own list. */
/** Subjects each level does not teach until the school sets its own lists (codes). */
const AC_DEFAULT_NOT_OFFERED = [
    'O-Level' => ['DIV', 'ECON', 'g.p', 'SUB-MATHEMATICS'],
    'A-Level' => ['ENG', 'CRE'],
];
const AC_DEFAULT_COMPULSORY = [
    'O-Level' => ['ENG', 'MATHS', 'PHY', 'CHE', 'BIO', 'GEO', 'HIST'],
    'A-Level' => ['g.p'],
];

function ac_ensure_schema(PDO $db): void
{
    static $done = false;
    if ($done) {
        return;
    }
    sf_ensure_schema($db);
    $ready = tt_column_exists($db, 'students', 'status') && tt_table_exists($db, 'student_subjects')
        && tt_table_exists($db, 'student_promotions') && tt_column_exists($db, 'report_settings', 'compulsory_subjects') && tt_column_exists($db, 'report_settings', 'offered_subjects')
        && tt_column_exists($db, 'academic_sessions', 'closed_at') && tt_column_exists($db, 'academic_sessions', 'reopened_at');
    if (!$ready) {
        ac_run_migration($db);
    }
    $done = true;
}

function ac_run_migration(PDO $db, ?callable $log = null): void
{
    $log = $log ?? static function (string $m): void {};
    if ($db->inTransaction()) {
        throw new LogicException('ac_run_migration must run outside a transaction (DDL commits implicitly)');
    }
    if (!tt_column_exists($db, 'students', 'status')) {
        $db->exec("ALTER TABLE students ADD COLUMN status VARCHAR(30) NOT NULL DEFAULT 'active' AFTER is_active, ADD KEY idx_students_status (status)");
        $log('students.status added (every student starts as active)');
    }
    if (!tt_column_exists($db, 'academic_sessions', 'closed_at')) {
        $db->exec('ALTER TABLE academic_sessions ADD COLUMN closed_at DATETIME NULL, ADD COLUMN closed_by VARCHAR(150) NULL');
        $log('academic_sessions.closed_at / closed_by added');
    }
    if (!tt_column_exists($db, 'academic_sessions', 'reopened_at')) {
        $db->exec('ALTER TABLE academic_sessions ADD COLUMN reopened_at DATETIME NULL, ADD COLUMN reopened_by VARCHAR(150) NULL');
        $log('academic_sessions.reopened_at / reopened_by added');
    }
    if (!tt_column_exists($db, 'report_settings', 'compulsory_subjects')) {
        $db->exec('ALTER TABLE report_settings ADD COLUMN compulsory_subjects LONGTEXT NULL');
        $log('report_settings.compulsory_subjects added');
    }
    if (!tt_column_exists($db, 'report_settings', 'offered_subjects')) {
        $db->exec('ALTER TABLE report_settings ADD COLUMN offered_subjects LONGTEXT NULL');
        $log('report_settings.offered_subjects added');
    }
    $db->exec("CREATE TABLE IF NOT EXISTS student_subjects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        subject_id INT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_student_subject (student_id, subject_id),
        KEY idx_ss_subject (subject_id),
        CONSTRAINT fk_ss_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
        CONSTRAINT fk_ss_subject FOREIGN KEY (subject_id) REFERENCES subjects_new (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    $db->exec("CREATE TABLE IF NOT EXISTS student_promotions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        academic_year INT NOT NULL,
        action VARCHAR(30) NOT NULL,
        from_class VARCHAR(50) NULL,
        from_stream VARCHAR(50) NULL,
        to_class VARCHAR(50) NULL,
        to_stream VARCHAR(50) NULL,
        from_status VARCHAR(30) NULL,
        to_status VARCHAR(30) NULL,
        done_by VARCHAR(150) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_sp_student (student_id),
        KEY idx_sp_year (academic_year),
        CONSTRAINT fk_sp_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    $log('student_subjects and student_promotions ready');
}

/* ------------------------------------------------------------------ */
/* Terms                                                               */
/* ------------------------------------------------------------------ */

function ac_session_status(array $s): string
{
    if ((int)$s['is_active'] === 1) {
        return 'open';
    }
    if ((int)$s['is_archived'] === 1) {
        return 'closed';
    }
    return !empty($s['reopened_at']) ? 'reopened' : 'upcoming';
}

function ac_open_session(PDO $db): ?array
{
    $row = $db->query('SELECT * FROM academic_sessions WHERE is_active = 1 ORDER BY academic_year DESC, term DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/** [year, term] of the term after the given one. */
function ac_next_term(int $year, int $term): array
{
    return $term >= 3 ? [$year + 1, 1] : [$year, $term + 1];
}

/** Rough default dates for a new term (Uganda: Feb–May, May–Aug, Sep–Dec). */
function ac_default_dates(int $year, int $term): array
{
    return [
        1 => ["$year-02-02", "$year-05-01"],
        2 => ["$year-05-25", "$year-08-21"],
        3 => ["$year-09-14", "$year-12-04"],
    ][$term];
}

function ac_calendar(PDO $db): array
{
    $rows = $db->query('SELECT s.*, (SELECT COUNT(*) FROM exams e WHERE e.academic_session_id = s.id) AS exams,
            (SELECT COUNT(*) FROM exams e WHERE e.academic_session_id = s.id AND e.status = \'open\') AS open_exams
        FROM academic_sessions s ORDER BY academic_year DESC, term DESC')->fetchAll(PDO::FETCH_ASSOC);
    $years = [];
    foreach ($rows as $r) {
        $r['status'] = ac_session_status($r);
        $years[(int)$r['academic_year']][] = $r;
    }
    $open = ac_open_session($db);
    $next = null;
    if ($open) {
        [$ny, $nt] = ac_next_term((int)$open['academic_year'], (int)$open['term']);
        $stmt = $db->prepare('SELECT * FROM academic_sessions WHERE academic_year = :y AND term = :t');
        $stmt->execute([':y' => $ny, ':t' => $nt]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        [$ds, $de] = ac_default_dates($ny, $nt);
        $next = ['academic_year' => $ny, 'term' => $nt, 'exists' => (bool)$existing,
            'start_date' => $existing['start_date'] ?? $ds, 'end_date' => $existing['end_date'] ?? $de];
    }
    return ['open' => $open, 'next' => $next, 'years' => array_map(fn($y, $terms) => ['year' => $y, 'terms' => $terms], array_keys($years), $years)];
}

/** True once a year's students have been moved up (closing its Term 3 the first time). */
function ac_year_promoted(PDO $db, int $year): bool
{
    try {
        $q = $db->prepare("SELECT 1 FROM student_promotions WHERE academic_year = :y AND action = 'year_end' LIMIT 1");
        $q->execute([':y' => $year]);
        return (bool)$q->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Opens a closed term again, alongside the current term, so data can still be entered for it (marks,
 * exams, assignments…). The current term stays open. Students are not moved; close it again when done.
 */
function ac_reopen_term(PDO $db, int $sessionId, string $by): array
{
    ac_ensure_schema($db);
    $q = $db->prepare('SELECT * FROM academic_sessions WHERE id = :id');
    $q->execute([':id' => $sessionId]);
    $t = $q->fetch(PDO::FETCH_ASSOC) ?: throw new TtException('Term not found', 404);
    if (ac_session_status($t) !== 'closed') {
        throw new TtException('Only a closed term can be reopened', 409);
    }
    $db->prepare('UPDATE academic_sessions SET is_archived = 0, reopened_at = NOW(), reopened_by = :by WHERE id = :id')
        ->execute([':by' => $by, ':id' => $sessionId]);
    return ['id' => (int)$t['id'], 'academic_year' => (int)$t['academic_year'], 'term' => (int)$t['term']];
}

/** Closes a reopened term again: its open exams close and nothing more can be entered for it. */
function ac_close_reopened(PDO $db, int $sessionId, string $by): array
{
    ac_ensure_schema($db);
    $q = $db->prepare('SELECT * FROM academic_sessions WHERE id = :id');
    $q->execute([':id' => $sessionId]);
    $t = $q->fetch(PDO::FETCH_ASSOC) ?: throw new TtException('Term not found', 404);
    if (ac_session_status($t) !== 'reopened') {
        throw new TtException('That term is not reopened', 409);
    }
    $db->beginTransaction();
    try {
        $c = $db->prepare("UPDATE exams SET status = 'closed' WHERE academic_session_id = :s AND status = 'open'");
        $c->execute([':s' => $sessionId]);
        $db->prepare('UPDATE academic_sessions SET is_archived = 1, reopened_at = NULL, reopened_by = NULL, closed_at = NOW(), closed_by = :by WHERE id = :id')
            ->execute([':by' => $by, ':id' => $sessionId]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
    return ['id' => (int)$t['id'], 'academic_year' => (int)$t['academic_year'], 'term' => (int)$t['term'], 'exams_closed' => $c->rowCount()];
}

/** What closing the open term will do, so the admin can check before confirming. */
function ac_close_preview(PDO $db): array
{
    $open = ac_open_session($db) ?? throw new TtException('No term is open', 409);
    $sid = (int)$open['id'];
    $exams = $db->query("SELECT name, status, (SELECT COUNT(*) FROM exam_marks m WHERE m.exam_id = e.id) AS marks FROM exams e WHERE academic_session_id = $sid ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $requirements = (int)$db->query("SELECT COUNT(*) FROM lesson_requirements WHERE academic_session_id = $sid")->fetchColumn();
    $yearEnd = (int)$open['term'] === 3;
    $promoted = $yearEnd && ac_year_promoted($db, (int)$open['academic_year']);
    return [
        'session' => $open,
        'year_end' => $yearEnd,
        'already_promoted' => $promoted,
        'exams' => $exams,
        'requirements' => $requirements,
        'promotion' => $yearEnd && !$promoted ? ac_promotion_plan($db) : null,
    ];
}

/**
 * Closes the open term and opens the next. $opts: start_date, end_date, copy_requirements (bool),
 * confirm (must equal the year when closing Term 3).
 */
function ac_close_term(PDO $db, array $opts, string $by): array
{
    ac_ensure_schema($db);
    $open = ac_open_session($db) ?? throw new TtException('No term is open', 409);
    $year = (int)$open['academic_year'];
    $term = (int)$open['term'];
    $yearEnd = $term === 3;
    // Typing the year is asked for only when students will actually be moved up.
    if ($yearEnd && !ac_year_promoted($db, $year) && trim((string)($opts['confirm'] ?? '')) !== (string)$year) {
        throw new TtException("Type $year to confirm closing the year", 422, ['errors' => ['confirm' => "Type $year to confirm"]]);
    }
    [$ny, $nt] = ac_next_term($year, $term);
    [$ds, $de] = ac_default_dates($ny, $nt);
    $start = trim((string)($opts['start_date'] ?? '')) ?: $ds;
    $end = trim((string)($opts['end_date'] ?? '')) ?: $de;
    $isDate = static fn($d) => (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
    if (!$isDate($start) || !$isDate($end) || $end <= $start) {
        throw new TtException('Give the new term a start date and a later end date', 422, ['errors' => ['end_date' => 'End must be after start']]);
    }
    // Students move up only the first time a year's Term 3 closes (a reopened Term 3 closes without it).
    $plan = $yearEnd && !ac_year_promoted($db, $year) ? ac_promotion_plan($db) : null;
    if ($plan) {
        foreach ($plan['missing_streams'] as $c) {
            ac_create_class_if_missing($db, $c['class_name'], $c['stream_name']);
        }
    }

    $db->beginTransaction();
    try {
        $closed = $db->prepare("UPDATE exams SET status = 'closed' WHERE academic_session_id = :s AND status = 'open'");
        $closed->execute([':s' => $open['id']]);
        $db->prepare('UPDATE academic_sessions SET is_active = 0, is_archived = 1, closed_at = NOW(), closed_by = :by WHERE id = :id')
            ->execute([':by' => $by, ':id' => $open['id']]);
        $find = $db->prepare('SELECT id FROM academic_sessions WHERE academic_year = :y AND term = :t');
        $find->execute([':y' => $ny, ':t' => $nt]);
        $nextId = (int)($find->fetchColumn() ?: 0);
        if ($nextId) {
            $db->prepare('UPDATE academic_sessions SET is_active = 1, is_archived = 0, reopened_at = NULL, reopened_by = NULL, start_date = :s, end_date = :e WHERE id = :id')
                ->execute([':s' => $start, ':e' => $end, ':id' => $nextId]);
        } else {
            $db->prepare('INSERT INTO academic_sessions (session_name, academic_year, term, start_date, end_date, is_active, is_archived) VALUES (:n, :y, :t, :s, :e, 1, 0)')
                ->execute([':n' => "$ny Term $nt", ':y' => $ny, ':t' => $nt, ':s' => $start, ':e' => $end]);
            $nextId = (int)$db->lastInsertId();
        }
        $copied = 0;
        $hasReq = (int)$db->query("SELECT COUNT(*) FROM lesson_requirements WHERE academic_session_id = $nextId")->fetchColumn();
        if (!empty($opts['copy_requirements']) && !$hasReq) {
            $cols = array_values(array_diff(
                $db->query('SHOW COLUMNS FROM lesson_requirements')->fetchAll(PDO::FETCH_COLUMN),
                ['id', 'academic_session_id', 'created_at', 'updated_at']
            ));
            $list = implode(', ', array_map(fn($c) => "`$c`", $cols));
            $copy = $db->prepare("INSERT INTO lesson_requirements (academic_session_id, $list) SELECT :n, $list FROM lesson_requirements WHERE academic_session_id = :o");
            $copy->execute([':n' => $nextId, ':o' => $open['id']]);
            $copied = $copy->rowCount();
        }
        $promotion = $plan ? ac_apply_promotion($db, $plan, $year, $by) : null;
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
    return [
        'closed' => ['id' => (int)$open['id'], 'academic_year' => $year, 'term' => $term, 'exams_closed' => $closed->rowCount()],
        'opened' => ['id' => $nextId, 'academic_year' => $ny, 'term' => $nt, 'start_date' => $start, 'end_date' => $end],
        'requirements_copied' => $copied,
        'promotion' => $promotion,
    ];
}

/* ------------------------------------------------------------------ */
/* Promotion                                                           */
/* ------------------------------------------------------------------ */

/** "S1" / "S.1" / "S 1" -> 1 */
function ac_class_number(string $class): ?int
{
    return preg_match('/^\s*S\.?\s*(\d)/i', $class, $m) ? (int)$m[1] : null;
}

function ac_create_class_if_missing(PDO $db, string $className, string $stream): void
{
    $db->prepare('INSERT IGNORE INTO classes (class_name, stream_name, full_class_name) VALUES (:c, :s, :f)')
        ->execute([':c' => $className, ':s' => $stream, ':f' => "$className $stream"]);
}

/**
 * Year-end moves for every current student: [moves => [{from, stream, to, count}], completed_o, completed_a,
 * missing_streams, ids]. Built from one snapshot so a student moves once.
 */
function ac_promotion_plan(PDO $db): array
{
    $rows = $db->query("SELECT id, TRIM(class) AS class, TRIM(stream) AS stream FROM students
        WHERE status = 'active' AND (is_active = 1 OR is_active IS NULL)")->fetchAll(PDO::FETCH_ASSOC);
    $classes = [];
    foreach ($db->query('SELECT class_name, stream_name FROM classes')->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $classes[strtoupper(trim($c['class_name'])) . '|' . strtoupper(trim($c['stream_name']))] = true;
    }
    $groups = [];
    $skipped = 0;
    foreach ($rows as $r) {
        $n = ac_class_number($r['class']);
        if ($n === null || $n < 1 || $n > 6) {
            $skipped++;
            continue;
        }
        // Keep the school's way of writing class names ("S1" or "S.1").
        $to = $n === 4 || $n === 6 ? null : preg_replace('/\d/', (string)($n + 1), $r['class'], 1);
        $key = "{$r['class']}|{$r['stream']}";
        $groups[$key] ??= ['from' => $r['class'], 'stream' => $r['stream'], 'to' => $to, 'number' => $n, 'ids' => []];
        $groups[$key]['ids'][] = (int)$r['id'];
    }
    uasort($groups, fn($a, $b) => [$b['number'], $a['stream']] <=> [$a['number'], $b['stream']]);
    $missing = [];
    foreach ($groups as $g) {
        if ($g['to'] && !isset($classes[strtoupper($g['to']) . '|' . strtoupper($g['stream'])])) {
            $missing[$g['to'] . '|' . $g['stream']] = ['class_name' => $g['to'], 'stream_name' => $g['stream']];
        }
    }
    $count = fn(int $n) => array_sum(array_map(fn($g) => $g['number'] === $n ? count($g['ids']) : 0, $groups));
    return [
        'groups' => array_values($groups),
        'moves' => array_values(array_map(fn($g) => ['from' => $g['from'], 'stream' => $g['stream'], 'to' => $g['to'], 'count' => count($g['ids'])],
            array_filter($groups, fn($g) => $g['to'] !== null))),
        'completed_o_level' => $count(4),
        'completed_a_level' => $count(6),
        'missing_streams' => array_values($missing),
        'skipped' => $skipped,
    ];
}

function ac_apply_promotion(PDO $db, array $plan, int $year, string $by): array
{
    $log = $db->prepare('INSERT INTO student_promotions (student_id, academic_year, action, from_class, from_stream, to_class, to_stream, from_status, to_status, done_by)
        VALUES (:id, :y, :a, :fc, :fs, :tc, :ts, \'active\', :st, :by)');
    $moved = 0;
    $completed = ['completed_o_level' => 0, 'completed_a_level' => 0];
    foreach ($plan['groups'] as $g) {
        $ids = implode(',', array_map('intval', $g['ids']));
        if ($ids === '') {
            continue;
        }
        if ($g['to'] === null) {
            $status = $g['number'] === 4 ? 'completed_o_level' : 'completed_a_level';
            $db->exec("UPDATE students SET status = '$status' WHERE id IN ($ids)");
            $completed[$status] += count($g['ids']);
        } else {
            $stmt = $db->prepare("UPDATE students SET class = :to, level = :lvl WHERE id IN ($ids)");
            $stmt->execute([':to' => $g['to'], ':lvl' => tt_class_level($g['to'])]);
            $moved += count($g['ids']);
            $status = 'active';
        }
        foreach ($g['ids'] as $id) {
            $log->execute([':id' => $id, ':y' => $year, ':a' => 'year_end', ':fc' => $g['from'], ':fs' => $g['stream'],
                ':tc' => $g['to'] ?? $g['from'], ':ts' => $g['stream'], ':st' => $status, ':by' => $by]);
        }
    }
    return ['promoted' => $moved] + $completed + ['streams_created' => count($plan['missing_streams'])];
}

/* ------------------------------------------------------------------ */
/* Subjects a student offers                                           */
/* ------------------------------------------------------------------ */

function ac_all_subjects(PDO $db): array
{
    return array_map(fn($s) => ['id' => (int)$s['id'], 'subject_name' => $s['subject_name'], 'subject_code' => $s['subject_code']],
        $db->query('SELECT id, subject_name, subject_code FROM subjects_new ORDER BY subject_name')->fetchAll(PDO::FETCH_ASSOC));
}

/** level => [ids] saved in a report_settings JSON column, or null when not set yet. */
function ac_saved_lists(PDO $db, string $column): ?array
{
    $raw = $db->query("SELECT $column FROM report_settings WHERE id = 1")->fetchColumn();
    $saved = $raw ? json_decode((string)$raw, true) : null;
    return is_array($saved) ? $saved : null;
}

/** level => [subject rows] the school teaches at that level (settings, else everything but the default exclusions). */
function ac_offered(PDO $db): array
{
    $saved = ac_saved_lists($db, 'offered_subjects');
    $all = ac_all_subjects($db);
    $out = [];
    foreach (['O-Level', 'A-Level'] as $level) {
        if ($saved && isset($saved[$level]) && is_array($saved[$level])) {
            $ids = array_map('intval', $saved[$level]);
            $out[$level] = array_values(array_filter($all, fn($s) => in_array($s['id'], $ids, true)));
        } else {
            $codes = array_map('strtolower', AC_DEFAULT_NOT_OFFERED[$level]);
            $out[$level] = array_values(array_filter($all, fn($s) => !in_array(strtolower(trim($s['subject_code'])), $codes, true)));
        }
    }
    return $out;
}

/** level => [subject rows] every student at the level takes (always among the subjects offered there). */
function ac_compulsory(PDO $db): array
{
    $saved = ac_saved_lists($db, 'compulsory_subjects');
    $offered = ac_offered($db);
    $out = [];
    foreach (['O-Level', 'A-Level'] as $level) {
        if ($saved && isset($saved[$level]) && is_array($saved[$level])) {
            $ids = array_map('intval', $saved[$level]);
            $out[$level] = array_values(array_filter($offered[$level], fn($s) => in_array($s['id'], $ids, true)));
        } else {
            $codes = array_map('strtolower', AC_DEFAULT_COMPULSORY[$level]);
            $out[$level] = array_values(array_filter($offered[$level], fn($s) => in_array(strtolower(trim($s['subject_code'])), $codes, true)));
        }
    }
    return $out;
}

/** Saves both lists: {offered: {level: [ids]}, compulsory: {level: [ids]}}; compulsory must be offered. */
function ac_save_level_subjects(PDO $db, array $data): array
{
    $valid = array_column(ac_all_subjects($db), 'id');
    $offered = [];
    $compulsory = [];
    foreach (['O-Level', 'A-Level'] as $level) {
        $offered[$level] = array_values(array_intersect(array_unique(array_map('intval', (array)($data['offered'][$level] ?? []))), $valid));
        if (!$offered[$level]) {
            throw new TtException("$level must offer at least one subject", 422);
        }
        $compulsory[$level] = array_values(array_intersect(array_unique(array_map('intval', (array)($data['compulsory'][$level] ?? []))), $offered[$level]));
    }
    $db->exec('INSERT IGNORE INTO report_settings (id) VALUES (1)');
    $db->prepare('UPDATE report_settings SET offered_subjects = :o, compulsory_subjects = :c WHERE id = 1')
        ->execute([':o' => json_encode($offered), ':c' => json_encode($compulsory)]);
    return ['offered' => ac_offered($db), 'compulsory' => ac_compulsory($db)];
}

function ac_student(PDO $db, int $id): array
{
    $stmt = $db->prepare('SELECT id, admission_number, full_name, class, stream, level, status FROM students WHERE id = :id');
    $stmt->execute([':id' => $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: throw new TtException('Student not found', 404);
}

function ac_level_of(array $student): string
{
    return in_array($student['level'] ?? '', ['O-Level', 'A-Level'], true) ? $student['level'] : tt_class_level((string)$student['class']);
}

function ac_student_subjects(PDO $db, int $studentId): array
{
    $student = ac_student($db, $studentId);
    $level = ac_level_of($student);
    $compulsory = ac_compulsory($db)[$level];
    $compIds = array_column($compulsory, 'id');
    $stmt = $db->prepare('SELECT s.id, s.subject_name, s.subject_code FROM student_subjects ss JOIN subjects_new s ON s.id = ss.subject_id WHERE ss.student_id = :id ORDER BY s.subject_name');
    $stmt->execute([':id' => $studentId]);
    $options = array_values(array_filter($stmt->fetchAll(PDO::FETCH_ASSOC), fn($s) => !in_array((int)$s['id'], $compIds, true)));
    return [
        'student' => $student,
        'level' => $level,
        'compulsory' => $compulsory,
        'options' => array_map(fn($s) => ['id' => (int)$s['id'], 'subject_name' => $s['subject_name'], 'subject_code' => $s['subject_code']], $options),
        'available' => ac_offered($db)[$level],
    ];
}

/** Stores the optional subjects; compulsory ones always apply and are not stored. */
function ac_set_student_subjects(PDO $db, int $studentId, array $subjectIds, bool $inTransaction = false): array
{
    $student = ac_student($db, $studentId);
    $level = ac_level_of($student);
    $compIds = array_column(ac_compulsory($db)[$level], 'id');
    $offered = ac_offered($db)[$level];
    $wanted = array_values(array_unique(array_map('intval', $subjectIds)));
    $notOffered = array_diff($wanted, array_column($offered, 'id'));
    if ($notOffered) {
        $names = array_column(array_filter(ac_all_subjects($db), fn($s) => in_array($s['id'], $notOffered, true)), 'subject_name');
        throw new TtException(implode(', ', $names) . " " . (count($names) === 1 ? 'is' : 'are') . " not offered at $level", 422);
    }
    $ids = array_values(array_diff($wanted, $compIds));
    $own = !$inTransaction && !$db->inTransaction();
    if ($own) {
        $db->beginTransaction();
    }
    try {
        $db->prepare('DELETE FROM student_subjects WHERE student_id = :id')->execute([':id' => $studentId]);
        $ins = $db->prepare('INSERT INTO student_subjects (student_id, subject_id) VALUES (:st, :su)');
        foreach ($ids as $sid) {
            $ins->execute([':st' => $studentId, ':su' => $sid]);
        }
        if ($own) {
            $db->commit();
        }
    } catch (Throwable $e) {
        if ($own) {
            $db->rollBack();
        }
        throw $e;
    }
    return ac_student_subjects($db, $studentId);
}

/** student_id => true for students who have chosen this subject (for option subjects on mark sheets). */
function ac_subject_takers(PDO $db, int $subjectId, array $studentIds): array
{
    if (!$studentIds) {
        return [];
    }
    $in = implode(',', array_map('intval', $studentIds));
    $rows = $db->query("SELECT student_id, subject_id FROM student_subjects WHERE student_id IN ($in)")->fetchAll(PDO::FETCH_ASSOC);
    $any = [];
    $takers = [];
    foreach ($rows as $r) {
        $any[(int)$r['student_id']] = true;
        if ((int)$r['subject_id'] === $subjectId) {
            $takers[(int)$r['student_id']] = true;
        }
    }
    return ['takers' => $takers, 'with_options' => $any];
}

/* ------------------------------------------------------------------ */
/* A-Level entry                                                       */
/* ------------------------------------------------------------------ */

/** Admits students who completed S.4 into an S.5 stream with a subject combination (G.P is added by being compulsory). */
function ac_admit_a_level(PDO $db, array $studentIds, int $classId, array $subjectIds, string $by): array
{
    $class = sf_class($db, $classId) ?? throw new TtException('Choose an S.5 stream', 422);
    if (ac_class_number((string)$class['class_name']) !== 5) {
        throw new TtException('Students can only be admitted into an S.5 stream', 422);
    }
    $studentIds = array_values(array_unique(array_filter(array_map('intval', $studentIds))));
    if (!$studentIds) {
        throw new TtException('Choose at least one student', 422);
    }
    $subjectIds = array_values(array_filter(array_map('intval', $subjectIds)));
    $compIds = array_column(ac_compulsory($db)['A-Level'], 'id');
    if (count(array_diff($subjectIds, $compIds)) < 2) {
        throw new TtException('Choose the combination: at least two subjects besides the compulsory ones', 422, ['errors' => ['subject_ids' => 'Choose the combination']]);
    }
    $in = implode(',', $studentIds);
    $rows = $db->query("SELECT id, admission_number, full_name, class, stream, status FROM students WHERE id IN ($in)")->fetchAll(PDO::FETCH_ASSOC);
    $notReady = array_filter($rows, fn($r) => $r['status'] !== 'completed_o_level');
    if (count($rows) !== count($studentIds) || $notReady) {
        $names = implode(', ', array_map(fn($r) => $r['admission_number'], $notReady));
        throw new TtException('Only students who have completed S.4 can be admitted to S.5' . ($names ? " ($names)" : ''), 422);
    }
    $year = (int)(ac_open_session($db)['academic_year'] ?? date('Y'));
    $db->beginTransaction();
    try {
        $up = $db->prepare("UPDATE students SET class = :c, stream = :s, level = 'A-Level', status = 'active' WHERE id = :id");
        $log = $db->prepare("INSERT INTO student_promotions (student_id, academic_year, action, from_class, from_stream, to_class, to_stream, from_status, to_status, done_by)
            VALUES (:id, :y, 'a_level_entry', :fc, :fs, :tc, :ts, 'completed_o_level', 'active', :by)");
        foreach ($rows as $r) {
            $up->execute([':c' => $class['class_name'], ':s' => $class['stream_name'], ':id' => $r['id']]);
            $log->execute([':id' => $r['id'], ':y' => $year, ':fc' => $r['class'], ':fs' => $r['stream'], ':tc' => $class['class_name'], ':ts' => $class['stream_name'], ':by' => $by]);
            ac_set_student_subjects($db, (int)$r['id'], $subjectIds, true);
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
    return ['admitted' => count($rows), 'class' => $class['full_class_name']];
}
