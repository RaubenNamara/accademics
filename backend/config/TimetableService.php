<?php
declare(strict_types=1);

/**
 * Timetable scheduling rules shared by every timetable endpoint.
 *
 * Model: `timetable` is the single working (draft) copy per academic year +
 * term. Each lesson/event also claims rows in `timetable_slots` (one per
 * period per teacher/class/room) whose UNIQUE key makes double-booking
 * impossible at the database level, even for concurrent requests. Publishing
 * stores an immutable JSON snapshot in `timetable_versions`; restoring copies
 * a snapshot back into the working copy.
 */

require_once __DIR__ . '/HrHelpers.php';

const TT_DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
const TT_EDITOR_ROLES = ['teacher', 'academic_office'];
const TT_DEFAULT_MAX_PER_DAY = 7;

/** Error carrying an HTTP status and structured data for the client. */
class TtException extends RuntimeException
{
    public int $status;
    public $data;

    public function __construct(string $message, int $status = 400, $data = null)
    {
        parent::__construct($message);
        $this->status = $status;
        $this->data = $data;
    }
}

/* ------------------------------------------------------------------ */
/* Schema                                                              */
/* ------------------------------------------------------------------ */

function tt_table_exists(PDO $db, string $table): bool
{
    $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t');
    $stmt->execute([':t' => $table]);
    return (int)$stmt->fetchColumn() > 0;
}

function tt_column_exists(PDO $db, string $table, string $column): bool
{
    $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c');
    $stmt->execute([':t' => $table, ':c' => $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function tt_index_exists(PDO $db, string $table, string $index): bool
{
    $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND INDEX_NAME = :i');
    $stmt->execute([':t' => $table, ':i' => $index]);
    return (int)$stmt->fetchColumn() > 0;
}

/**
 * Additive, idempotent migration (also in backend/migrations/37_timetable_integrity.php).
 * Runs once: afterwards the marker check is a single cheap query.
 */
function tt_ensure_schema(PDO $db): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $marker = $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'timetable_versions' AND COLUMN_NAME = 'snapshot'")->fetchColumn();
    if (!((int)$marker > 0 && tt_table_exists($db, 'timetable_slots') && tt_table_exists($db, 'timetable_audit_log'))) {
        tt_run_migration($db);
    }
    tt_ensure_home_rooms($db);
    tt_ensure_subject_departments($db);
    $done = true;
}

function tt_run_migration(PDO $db, ?callable $log = null): void
{
    $log = $log ?? static function (string $m): void {};

    foreach (['timetable', 'timetable_versions', 'academic_sessions', 'lesson_requirements', 'teacher_availability', 'rooms'] as $required) {
        if (!tt_table_exists($db, $required)) {
            throw new TtException("The timetable tables are not installed (missing `$required`). Run backend/migrations/36_timetable_suite_rebuild.sql first.", 500);
        }
    }

    $db->exec("CREATE TABLE IF NOT EXISTS timetable_slots (
        id INT AUTO_INCREMENT PRIMARY KEY,
        timetable_id INT NOT NULL,
        academic_year INT NOT NULL,
        term TINYINT UNSIGNED NOT NULL,
        day_of_week VARCHAR(20) NOT NULL,
        period_number TINYINT UNSIGNED NOT NULL,
        resource_type ENUM('teacher','class','room') NOT NULL,
        resource_id INT NOT NULL,
        UNIQUE KEY uq_timetable_slot (academic_year, term, day_of_week, period_number, resource_type, resource_id),
        KEY idx_slot_entry (timetable_id),
        CONSTRAINT fk_slot_entry FOREIGN KEY (timetable_id) REFERENCES timetable (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    $log('timetable_slots ready');

    $db->exec("CREATE TABLE IF NOT EXISTS timetable_session_state (
        academic_session_id INT NOT NULL PRIMARY KEY,
        revision INT NOT NULL DEFAULT 0,
        status ENUM('draft','validated','published') NOT NULL DEFAULT 'draft',
        validated_revision INT NULL,
        validated_at DATETIME NULL,
        validated_by VARCHAR(255) NULL,
        published_revision INT NULL,
        published_version_id INT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_tt_state_session FOREIGN KEY (academic_session_id) REFERENCES academic_sessions (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    $log('timetable_session_state ready');

    $db->exec("CREATE TABLE IF NOT EXISTS timetable_audit_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        academic_session_id INT NULL,
        timetable_id INT NULL,
        action VARCHAR(40) NOT NULL,
        summary VARCHAR(500) NOT NULL,
        before_data LONGTEXT NULL,
        after_data LONGTEXT NULL,
        user_id INT NULL,
        user_name VARCHAR(255) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_tt_audit_session (academic_session_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    $log('timetable_audit_log ready');

    $columns = [
        'client_request_id' => 'VARCHAR(64) NULL',
        'lesson_requirement_id' => 'INT NULL',
        'notes' => 'TEXT NULL',
        'created_by' => 'INT NULL',
        'updated_by' => 'INT NULL',
    ];
    foreach ($columns as $col => $def) {
        if (!tt_column_exists($db, 'timetable', $col)) {
            $db->exec("ALTER TABLE timetable ADD COLUMN $col $def");
            $log("timetable.$col added");
        }
    }
    if (!tt_index_exists($db, 'timetable', 'uq_timetable_request')) {
        $db->exec('ALTER TABLE timetable ADD UNIQUE KEY uq_timetable_request (client_request_id)');
    }
    if (!tt_index_exists($db, 'timetable', 'idx_timetable_slot_lookup')) {
        $db->exec('ALTER TABLE timetable ADD KEY idx_timetable_slot_lookup (academic_year, term, day_of_week, period_number)');
    }

    $versionColumns = [
        'snapshot' => 'LONGTEXT NULL',
        'entry_count' => 'INT NULL',
        'effective_date' => 'DATE NULL',
        'created_by_user_id' => 'INT NULL',
        'created_by_name' => 'VARCHAR(255) NULL',
    ];
    foreach ($versionColumns as $col => $def) {
        if (!tt_column_exists($db, 'timetable_versions', $col)) {
            $db->exec("ALTER TABLE timetable_versions ADD COLUMN $col $def");
            $log("timetable_versions.$col added");
        }
    }

    // Backfill slots for existing rows. INSERT IGNORE: rows that already clash
    // with an earlier row keep their data untouched and are reported by validation.
    $backfilled = (int)$db->exec("
        INSERT IGNORE INTO timetable_slots (timetable_id, academic_year, term, day_of_week, period_number, resource_type, resource_id)
        SELECT t.id, t.academic_year, t.term, t.day_of_week, t.period_number + n.k, r.resource_type, r.resource_id
        FROM timetable t
        JOIN (SELECT 0 k UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5) n
          ON n.k < GREATEST(t.spans_periods, 1)
        JOIN (
            SELECT id AS tid, 'class' AS resource_type, class_id AS resource_id FROM timetable WHERE class_id IS NOT NULL
            UNION ALL SELECT id, 'teacher', teacher_id FROM timetable WHERE teacher_id IS NOT NULL AND entry_type = 'lesson'
            UNION ALL SELECT id, 'room', room_id FROM timetable WHERE room_id IS NOT NULL AND entry_type = 'lesson'
        ) r ON r.tid = t.id
        WHERE NOT EXISTS (SELECT 1 FROM timetable_slots s WHERE s.timetable_id = t.id)
    ");
    $log("slots backfilled: $backfilled");
}

/* ------------------------------------------------------------------ */
/* Sessions, actors, state, audit                                      */
/* ------------------------------------------------------------------ */

/** Resolves [session_id, year, term] from explicit id, year/term, or the active session. */
function tt_resolve_session(PDO $db, array $data): array
{
    $sessionId = isset($data['academic_session_id']) && (int)$data['academic_session_id'] > 0
        ? (int)$data['academic_session_id']
        : null;

    if ($sessionId) {
        $stmt = $db->prepare('SELECT id, academic_year, term FROM academic_sessions WHERE id = :id');
        $stmt->execute([':id' => $sessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($session) {
            return [(int)$session['id'], (int)$session['academic_year'], (int)$session['term']];
        }
    }

    if (isset($data['academic_year']) || isset($data['year']) || isset($data['term'])) {
        $year = (int)($data['academic_year'] ?? $data['year'] ?? date('Y'));
        $term = (int)($data['term'] ?? 1);
        $stmt = $db->prepare('SELECT id FROM academic_sessions WHERE academic_year = :y AND term = :t LIMIT 1');
        $stmt->execute([':y' => $year, ':t' => $term]);
        $found = $stmt->fetch(PDO::FETCH_ASSOC);
        return [$found ? (int)$found['id'] : null, $year, $term];
    }

    $active = $db->query('SELECT id, academic_year, term FROM academic_sessions WHERE is_active = 1 LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    if ($active) {
        return [(int)$active['id'], (int)$active['academic_year'], (int)$active['term']];
    }

    return [null, (int)date('Y'), 1];
}

function tt_require_session(PDO $db, array $data): array
{
    [$sessionId, $year, $term] = tt_resolve_session($db, $data);
    if (!$sessionId) {
        throw new TtException('Select (or create) an academic session for this year and term first', 400);
    }
    return [$sessionId, $year, $term];
}

function tt_actor(array $payload): array
{
    return [
        'id' => isset($payload['id']) ? (int)$payload['id'] : null,
        'name' => (string)($payload['full_name'] ?? $payload['email'] ?? 'Unknown user'),
        'role' => (string)($payload['role'] ?? ''),
    ];
}

function tt_audit(PDO $db, ?int $sessionId, ?int $timetableId, string $action, string $summary, $before, $after, array $actor): void
{
    $db->prepare('INSERT INTO timetable_audit_log (academic_session_id, timetable_id, action, summary, before_data, after_data, user_id, user_name)
        VALUES (:sid, :tid, :action, :summary, :before, :after, :uid, :uname)')
        ->execute([
            ':sid' => $sessionId,
            ':tid' => $timetableId,
            ':action' => $action,
            ':summary' => mb_substr($summary, 0, 500),
            ':before' => $before === null ? null : json_encode($before),
            ':after' => $after === null ? null : json_encode($after),
            ':uid' => $actor['id'],
            ':uname' => $actor['name'],
        ]);
}

/** Any change to the working copy makes it a draft again. */
function tt_bump_revision(PDO $db, int $sessionId): void
{
    $db->prepare("INSERT INTO timetable_session_state (academic_session_id, revision, status) VALUES (:sid, 1, 'draft')
        ON DUPLICATE KEY UPDATE revision = revision + 1, status = 'draft'")
        ->execute([':sid' => $sessionId]);
}

/** $errorCount: blocking issues found by a fresh validation, so the badge never claims "validated" over a live clash. */
function tt_session_state(PDO $db, int $sessionId, ?int $errorCount = null): array
{
    $stmt = $db->prepare('SELECT * FROM timetable_session_state WHERE academic_session_id = :sid');
    $stmt->execute([':sid' => $sessionId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['revision' => 0, 'validated_revision' => null, 'published_revision' => null, 'published_version_id' => null, 'validated_at' => null, 'validated_by' => null];

    $revision = (int)$row['revision'];
    $status = 'draft';
    if ($row['published_revision'] !== null && (int)$row['published_revision'] === $revision) {
        $status = 'published';
    } elseif ($row['validated_revision'] !== null && (int)$row['validated_revision'] === $revision && !$errorCount) {
        $status = 'validated';
    }

    $published = null;
    $pubStmt = $db->prepare("SELECT id, version_number, version_name, published_at, effective_date, created_by_name, entry_count
        FROM timetable_versions WHERE academic_session_id = :sid AND status = 'published' ORDER BY published_at DESC LIMIT 1");
    $pubStmt->execute([':sid' => $sessionId]);
    $published = $pubStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    return [
        'status' => $status,
        'revision' => $revision,
        'validated_at' => $row['validated_at'],
        'validated_by' => $row['validated_by'],
        'published_version' => $published,
        'has_unpublished_changes' => $published !== null && $status !== 'published',
    ];
}

/** Serialises writes for one term (MySQL named lock), released on shutdown as a safety net. */
function tt_lock(PDO $db, int $year, int $term): string
{
    $name = "accademics_timetable_{$year}_{$term}";
    $stmt = $db->prepare('SELECT GET_LOCK(:n, 10)');
    $stmt->execute([':n' => $name]);
    if ((int)$stmt->fetchColumn() !== 1) {
        throw new TtException('The timetable is busy with another change. Please try again in a moment.', 423);
    }
    register_shutdown_function(static function () use ($db, $name): void {
        try {
            $db->prepare('SELECT RELEASE_LOCK(:n)')->execute([':n' => $name]);
        } catch (Throwable $e) {
        }
    });
    return $name;
}

function tt_unlock(PDO $db, string $name): void
{
    $db->prepare('SELECT RELEASE_LOCK(:n)')->execute([':n' => $name]);
}

/* ------------------------------------------------------------------ */
/* Lookups                                                             */
/* ------------------------------------------------------------------ */

function tt_load_periods(PDO $db): array
{
    $rows = $db->query('SELECT period_number, label, period_type, start_time, end_time FROM timetable_periods WHERE is_active = 1 ORDER BY period_number')
        ->fetchAll(PDO::FETCH_ASSOC);
    $byNumber = [];
    foreach ($rows as $r) {
        $r['period_number'] = (int)$r['period_number'];
        $byNumber[$r['period_number']] = $r;
    }
    return $byNumber;
}

/**
 * [start .. start+span-1] must all be lesson periods and back-to-back in time.
 * Returns ['error' => msg] or ['start_time','end_time','duration_minutes'].
 */
function tt_span_info(array $periods, int $start, int $span): array
{
    $prev = null;
    for ($p = $start; $p < $start + $span; $p++) {
        if (!isset($periods[$p])) {
            return ['error' => $p === $start ? "Period $p does not exist" : 'Lesson extends beyond the last period of the day'];
        }
        $row = $periods[$p];
        if ($row['period_type'] !== 'lesson') {
            return ['error' => "Period {$p} ({$row['label']}) is a {$row['period_type']}, not a lesson slot"];
        }
        if ($prev !== null && $prev['end_time'] !== $row['start_time']) {
            return ['error' => 'Lesson would run across a break or gap in the schedule'];
        }
        $prev = $row;
    }
    $startTime = $periods[$start]['start_time'];
    $endTime = $periods[$start + $span - 1]['end_time'];
    return [
        'start_time' => $startTime,
        'end_time' => $endTime,
        'duration_minutes' => (int)((strtotime($endTime) - strtotime($startTime)) / 60),
    ];
}

/** All entries for a term with display labels. */
function tt_fetch_entries(PDO $db, int $year, int $term, array $filters = []): array
{
    $sql = "
        SELECT tt.*,
               t.full_name AS teacher_name, t.teacher_code,
               c.class_name, c.stream_name, c.full_class_name,
               s.subject_name, s.subject_code,
               r.room_code, r.room_name, r.room_type
        FROM timetable tt
        LEFT JOIN teachers t ON t.id = tt.teacher_id
        LEFT JOIN classes c ON c.id = tt.class_id
        LEFT JOIN subjects_new s ON s.id = tt.subject_id
        LEFT JOIN rooms r ON r.id = tt.room_id
        WHERE tt.academic_year = :year AND tt.term = :term";
    $params = [':year' => $year, ':term' => $term];
    foreach (['teacher_id', 'class_id', 'room_id', 'subject_id'] as $f) {
        if (!empty($filters[$f])) {
            $sql .= " AND tt.$f = :$f";
            $params[":$f"] = (int)$filters[$f];
        }
    }
    $sql .= " ORDER BY FIELD(tt.day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), tt.period_number";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        foreach (['id', 'period_number', 'spans_periods', 'class_id', 'subject_id', 'teacher_id', 'room_id', 'is_locked', 'lesson_requirement_id', 'academic_session_id'] as $k) {
            if (array_key_exists($k, $r) && $r[$k] !== null) {
                $r[$k] = (int)$r[$k];
            }
        }
        $r['spans_periods'] = max(1, (int)$r['spans_periods']);
    }
    return $rows;
}

function tt_entry_label(array $e): string
{
    $what = $e['entry_type'] === 'event' ? ($e['event_name'] ?: 'Event') : ($e['subject_name'] ?? 'Lesson');
    $who = $e['entry_type'] === 'lesson' && !empty($e['teacher_name']) ? " with {$e['teacher_name']}" : '';
    $cls = !empty($e['full_class_name']) ? " for {$e['full_class_name']}" : '';
    return $what . $cls . $who;
}

function tt_period_label(array $periods, int $start, int $span): string
{
    $label = $periods[$start]['label'] ?? "Period $start";
    if ($span > 1) {
        $endLabel = $periods[$start + $span - 1]['label'] ?? 'Period ' . ($start + $span - 1);
        return "$label – $endLabel";
    }
    return $label;
}

function tt_max_per_day(PDO $db, int $sessionId): array
{
    $stmt = $db->prepare("SELECT constraint_value FROM timetable_constraints
        WHERE academic_session_id = :sid AND constraint_type = 'max_lessons_per_day' AND is_active = 1 LIMIT 1");
    $stmt->execute([':sid' => $sessionId]);
    $raw = $stmt->fetchColumn();
    $value = $raw ? json_decode((string)$raw, true) : null;
    if (is_array($value) && ($value['enabled'] ?? true) && (int)($value['max'] ?? 0) > 0) {
        return ['max' => (int)$value['max'], 'configured' => true];
    }
    return ['max' => TT_DEFAULT_MAX_PER_DAY, 'configured' => false];
}

/* ------------------------------------------------------------------ */
/* Lesson validation + conflicts                                       */
/* ------------------------------------------------------------------ */

/**
 * Normalises a lesson/event payload and validates fields that do not depend
 * on other lessons. Returns [slot, fieldErrors].
 */
function tt_build_entry(PDO $db, array $data, array $periods): array
{
    [$sessionId, $year, $term] = tt_require_session($db, $data);
    $errors = [];
    $type = trim((string)($data['entry_type'] ?? 'lesson'));
    if (!in_array($type, ['lesson', 'event'], true)) {
        $errors['entry_type'] = 'Entry type must be lesson or event';
        $type = 'lesson';
    }

    $slot = [
        'academic_session_id' => $sessionId,
        'academic_year' => $year,
        'term' => $term,
        'entry_type' => $type,
        'day_of_week' => trim((string)($data['day_of_week'] ?? $data['day'] ?? '')),
        'period_number' => (int)($data['period_number'] ?? $data['period'] ?? 0),
        'spans_periods' => max(1, (int)($data['spans_periods'] ?? 1)),
        'class_id' => (int)($data['class_id'] ?? 0) ?: null,
        'subject_id' => (int)($data['subject_id'] ?? 0) ?: null,
        'teacher_id' => (int)($data['teacher_id'] ?? 0) ?: null,
        'room_id' => (int)($data['room_id'] ?? 0) ?: null,
        'notes' => trim((string)($data['notes'] ?? '')) ?: null,
        'event_name' => trim((string)($data['event_name'] ?? '')) ?: null,
        'event_color' => trim((string)($data['event_color'] ?? '')) ?: null,
        'event_type' => trim((string)($data['event_type'] ?? '')) ?: null,
        'event_description' => $data['event_description'] ?? null,
        'event_id' => (int)($data['event_id'] ?? 0) ?: null,
        'client_request_id' => preg_match('/^[A-Za-z0-9_-]{8,64}$/', (string)($data['client_request_id'] ?? '')) ? (string)$data['client_request_id'] : null,
    ];

    if (!in_array($slot['day_of_week'], TT_DAYS, true)) {
        $errors['day_of_week'] = 'Choose a school day (Monday to Friday)';
    }
    if ($slot['period_number'] <= 0) {
        $errors['period_number'] = 'Choose a period';
    }
    if (!$slot['class_id']) {
        $errors['class_id'] = 'Choose a class';
    }

    $classRow = null;
    if ($slot['class_id']) {
        $stmt = $db->prepare('SELECT id, class_name, stream_name, full_class_name FROM classes WHERE id = :id');
        $stmt->execute([':id' => $slot['class_id']]);
        $classRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$classRow) {
            $errors['class_id'] = 'That class no longer exists';
        } else {
            $slot['stream'] = $classRow['stream_name'] ?: null;
        }
    }

    if ($type === 'lesson') {
        $slot['event_name'] = $slot['event_color'] = $slot['event_type'] = $slot['event_description'] = null;
        $slot['event_id'] = null;
        if (!$slot['subject_id']) {
            $errors['subject_id'] = 'Choose a subject';
        } else {
            $stmt = $db->prepare('SELECT id FROM subjects_new WHERE id = :id');
            $stmt->execute([':id' => $slot['subject_id']]);
            if (!$stmt->fetch()) {
                $errors['subject_id'] = 'That subject no longer exists';
            }
        }
        if (!$slot['teacher_id']) {
            $errors['teacher_id'] = 'Choose a teacher';
        } else {
            $stmt = $db->prepare('SELECT id, is_active FROM teachers WHERE id = :id');
            $stmt->execute([':id' => $slot['teacher_id']]);
            $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$teacher) {
                $errors['teacher_id'] = 'That teacher no longer exists';
            } elseif (isset($teacher['is_active']) && (int)$teacher['is_active'] === 0) {
                $errors['teacher_id'] = 'This teacher is inactive and cannot be timetabled';
            }
        }
        if ($slot['subject_id'] && $slot['teacher_id'] && !isset($errors['subject_id']) && !isset($errors['teacher_id'])) {
            $deptError = tt_department_error($db, (int)$slot['subject_id'], (int)$slot['teacher_id']);
            if ($deptError) {
                $errors['teacher_id'] = $deptError;
            }
        }
        if ($slot['room_id']) {
            $stmt = $db->prepare('SELECT id, is_active FROM rooms WHERE id = :id');
            $stmt->execute([':id' => $slot['room_id']]);
            $room = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$room) {
                $errors['room_id'] = 'That room no longer exists';
            } elseif ((int)$room['is_active'] === 0) {
                $errors['room_id'] = 'This room is marked inactive';
            }
        }
        if ($classRow) {
            $level = tt_class_level((string)$classRow['class_name']);
            $options = tt_span_options($level);
            if (!isset($options[$slot['spans_periods']])) {
                $allowed = implode(', ', array_map(fn($o) => "{$o['label']} ({$o['minutes']} min)", $options));
                $errors['spans_periods'] = "{$classRow['full_class_name']} is $level: lessons can only be $allowed";
            }
        }
    } else {
        $slot['subject_id'] = $slot['teacher_id'] = $slot['room_id'] = null;
        if (!$slot['event_name'] && !$slot['event_id']) {
            $errors['event_name'] = 'Give the event a name';
        }
        if ($slot['spans_periods'] > 9) {
            $errors['spans_periods'] = 'An event can span at most 9 periods';
        }
    }

    if (!isset($errors['period_number'])) {
        if ($type === 'lesson') {
            $info = tt_span_info($periods, $slot['period_number'], $slot['spans_periods']);
        } else {
            // Events may cover breaks; they only need real, existing periods.
            $info = ['start_time' => null];
            for ($p = $slot['period_number']; $p < $slot['period_number'] + $slot['spans_periods']; $p++) {
                if (!isset($periods[$p])) {
                    $info = ['error' => 'Event extends beyond the last period of the day'];
                    break;
                }
            }
            if (!isset($info['error'])) {
                $info = [
                    'start_time' => $periods[$slot['period_number']]['start_time'],
                    'end_time' => $periods[$slot['period_number'] + $slot['spans_periods'] - 1]['end_time'],
                ];
                $info['duration_minutes'] = (int)((strtotime($info['end_time']) - strtotime($info['start_time'])) / 60);
            }
        }
        if (isset($info['error'])) {
            $errors['period_number'] = $info['error'];
        } else {
            $slot['start_time'] = $info['start_time'];
            $slot['end_time'] = $info['end_time'];
            $slot['duration_minutes'] = $info['duration_minutes'];
        }
    }

    return [$slot, $errors];
}

/**
 * Checks a proposed entry against the rest of the term. Returns a list of
 * ['type','severity','message'], any 'error' blocks saving.
 */
function tt_find_conflicts(PDO $db, array $slot, ?int $excludeId, array $periods): array
{
    $issues = [];
    $start = (int)$slot['period_number'];
    $end = $start + (int)$slot['spans_periods'] - 1;

    $where = ['tt.academic_year = :year', 'tt.term = :term', 'tt.day_of_week = :day',
        'tt.period_number <= :end', '(tt.period_number + GREATEST(tt.spans_periods, 1) - 1) >= :start'];
    $params = [':year' => $slot['academic_year'], ':term' => $slot['term'], ':day' => $slot['day_of_week'], ':start' => $start, ':end' => $end];
    if ($excludeId) {
        $where[] = 'tt.id <> :exclude';
        $params[':exclude'] = $excludeId;
    }
    $match = ['tt.class_id = :class_id'];
    $params[':class_id'] = (int)$slot['class_id'];
    if ($slot['entry_type'] === 'lesson') {
        if ($slot['teacher_id']) {
            $match[] = "(tt.teacher_id = :teacher_id AND tt.entry_type = 'lesson')";
            $params[':teacher_id'] = (int)$slot['teacher_id'];
        }
        if ($slot['room_id']) {
            $match[] = "(tt.room_id = :room_id AND tt.entry_type = 'lesson')";
            $params[':room_id'] = (int)$slot['room_id'];
        }
    }
    $sql = "SELECT tt.*, t.full_name AS teacher_name, c.full_class_name, s.subject_name, r.room_name, r.room_code
        FROM timetable tt
        LEFT JOIN teachers t ON t.id = tt.teacher_id
        LEFT JOIN classes c ON c.id = tt.class_id
        LEFT JOIN subjects_new s ON s.id = tt.subject_id
        LEFT JOIN rooms r ON r.id = tt.room_id
        WHERE " . implode(' AND ', $where) . ' AND (' . implode(' OR ', $match) . ')';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $other) {
        $when = $slot['day_of_week'] . ', ' . tt_period_label($periods, (int)$other['period_number'], max(1, (int)$other['spans_periods']));
        if ((int)$other['class_id'] === (int)$slot['class_id']) {
            $label = tt_entry_label(['full_class_name' => null] + $other);
            $issues[] = ['type' => 'class_clash', 'severity' => 'error', 'conflicting_id' => (int)$other['id'],
                'message' => "{$other['full_class_name']} already has $label on $when"];
        }
        if ($slot['entry_type'] === 'lesson' && $slot['teacher_id'] && (int)$other['teacher_id'] === (int)$slot['teacher_id'] && $other['entry_type'] === 'lesson') {
            $issues[] = ['type' => 'teacher_clash', 'severity' => 'error', 'conflicting_id' => (int)$other['id'],
                'message' => "{$other['teacher_name']} is already teaching {$other['subject_name']} to {$other['full_class_name']} on $when"];
        }
        if ($slot['entry_type'] === 'lesson' && $slot['room_id'] && (int)$other['room_id'] === (int)$slot['room_id'] && $other['entry_type'] === 'lesson') {
            $room = $other['room_name'] ?: $other['room_code'];
            $issues[] = ['type' => 'room_clash', 'severity' => 'error', 'conflicting_id' => (int)$other['id'],
                'message' => "$room is already booked by {$other['full_class_name']} ({$other['subject_name']}) on $when"];
        }
    }

    if ($slot['entry_type'] !== 'lesson' || !$slot['teacher_id']) {
        return $issues;
    }

    // Teacher marked unavailable.
    $stmt = $db->prepare('SELECT period_number, reason FROM teacher_availability
        WHERE teacher_id = :tid AND academic_session_id = :sid AND day_of_week = :day
          AND period_number BETWEEN :start AND :end AND is_available = 0');
    $stmt->execute([':tid' => $slot['teacher_id'], ':sid' => $slot['academic_session_id'], ':day' => $slot['day_of_week'], ':start' => $start, ':end' => $end]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $why = $row['reason'] ? " ({$row['reason']})" : '';
        $issues[] = ['type' => 'teacher_unavailable', 'severity' => 'error',
            'message' => "The teacher is marked unavailable on {$slot['day_of_week']}, " . ($periods[(int)$row['period_number']]['label'] ?? "period {$row['period_number']}") . $why];
    }

    // Over-allocation against the lesson requirement (duplicate scheduling).
    $stmt = $db->prepare('SELECT SUM(periods_per_week) AS required, GROUP_CONCAT(DISTINCT teacher_id) AS teachers
        FROM lesson_requirements WHERE academic_session_id = :sid AND class_id = :cid AND subject_id = :subj');
    $stmt->execute([':sid' => $slot['academic_session_id'], ':cid' => $slot['class_id'], ':subj' => $slot['subject_id']]);
    $req = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($req && $req['required'] !== null) {
        $sql = "SELECT COALESCE(SUM(GREATEST(spans_periods, 1)), 0) FROM timetable
            WHERE academic_year = :y AND term = :t AND class_id = :cid AND subject_id = :subj AND entry_type = 'lesson'";
        $p = [':y' => $slot['academic_year'], ':t' => $slot['term'], ':cid' => $slot['class_id'], ':subj' => $slot['subject_id']];
        if ($excludeId) {
            $sql .= ' AND id <> :exclude';
            $p[':exclude'] = $excludeId;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($p);
        $scheduled = (int)$stmt->fetchColumn();
        $required = (int)$req['required'];
        if ($scheduled + (int)$slot['spans_periods'] > $required) {
            $issues[] = ['type' => 'over_allocation', 'severity' => 'error',
                'message' => "This subject needs $required period(s) a week for this class and $scheduled are already scheduled. Adding {$slot['spans_periods']} more would exceed the requirement."];
        }
        $teachers = array_map('intval', explode(',', (string)$req['teachers']));
        if (!in_array((int)$slot['teacher_id'], $teachers, true)) {
            $issues[] = ['type' => 'wrong_teacher', 'severity' => 'warning',
                'message' => 'This teacher is not the one assigned to this subject for this class in Lesson Requirements.'];
        }
    }

    // Daily workload.
    $limit = tt_max_per_day($db, (int)$slot['academic_session_id']);
    $sql = "SELECT COALESCE(SUM(GREATEST(spans_periods, 1)), 0) FROM timetable
        WHERE academic_year = :y AND term = :t AND day_of_week = :day AND teacher_id = :tid AND entry_type = 'lesson'";
    $p = [':y' => $slot['academic_year'], ':t' => $slot['term'], ':day' => $slot['day_of_week'], ':tid' => $slot['teacher_id']];
    if ($excludeId) {
        $sql .= ' AND id <> :exclude';
        $p[':exclude'] = $excludeId;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($p);
    $dayLoad = (int)$stmt->fetchColumn() + (int)$slot['spans_periods'];
    if ($dayLoad > $limit['max']) {
        $issues[] = ['type' => 'workload', 'severity' => $limit['configured'] ? 'error' : 'warning',
            'message' => "The teacher would teach $dayLoad periods on {$slot['day_of_week']} (limit {$limit['max']})."];
    }

    return $issues;
}

/** Re-writes the occupancy rows of one entry. Throws PDOException (23000) on a clash. */
function tt_write_slots(PDO $db, int $id, array $slot): void
{
    $db->prepare('DELETE FROM timetable_slots WHERE timetable_id = :id')->execute([':id' => $id]);
    $resources = [['class', (int)$slot['class_id']]];
    if ($slot['entry_type'] === 'lesson') {
        if ($slot['teacher_id']) {
            $resources[] = ['teacher', (int)$slot['teacher_id']];
        }
        if ($slot['room_id']) {
            $resources[] = ['room', (int)$slot['room_id']];
        }
    }
    $stmt = $db->prepare('INSERT INTO timetable_slots (timetable_id, academic_year, term, day_of_week, period_number, resource_type, resource_id)
        VALUES (:id, :y, :t, :day, :p, :rt, :rid)');
    for ($p = (int)$slot['period_number']; $p < (int)$slot['period_number'] + (int)$slot['spans_periods']; $p++) {
        foreach ($resources as [$type, $rid]) {
            $stmt->execute([':id' => $id, ':y' => $slot['academic_year'], ':t' => $slot['term'], ':day' => $slot['day_of_week'],
                ':p' => $p, ':rt' => $type, ':rid' => $rid]);
        }
    }
}

function tt_is_duplicate_key(Throwable $e): bool
{
    return $e instanceof PDOException && ($e->getCode() === '23000' || (int)($e->errorInfo[1] ?? 0) === 1062);
}

function tt_get_entry(PDO $db, int $id): ?array
{
    $stmt = $db->prepare('SELECT * FROM timetable WHERE id = :id');
    $stmt->execute([':id' => $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Creates (id = null) or updates an entry under the term lock and a
 * transaction. Blocking conflicts throw 409 with the reasons.
 */
function tt_save_entry(PDO $db, array $data, ?int $id, array $actor): array
{
    $periods = tt_load_periods($db);
    [$slot, $errors] = tt_build_entry($db, $data, $periods);
    if ($errors) {
        throw new TtException((string)reset($errors), 422, ['errors' => $errors]);
    }

    $lock = tt_lock($db, (int)$slot['academic_year'], (int)$slot['term']);
    try {
        // Double-submit protection: the same request id returns the row it already created.
        if (!$id && $slot['client_request_id']) {
            $stmt = $db->prepare('SELECT id FROM timetable WHERE client_request_id = :r');
            $stmt->execute([':r' => $slot['client_request_id']]);
            $existing = $stmt->fetchColumn();
            if ($existing) {
                return ['id' => (int)$existing, 'duplicate' => true, 'warnings' => []];
            }
        }

        $before = null;
        if ($id) {
            $before = tt_get_entry($db, $id);
            if (!$before) {
                throw new TtException('This lesson no longer exists, it may have been removed by someone else.', 404);
            }
            if ((int)$before['is_locked'] === 1 && empty($data['unlock'])) {
                throw new TtException('This lesson is locked. Unlock it before making changes.', 423);
            }
        }

        $issues = tt_find_conflicts($db, $slot, $id, $periods);
        $blocking = array_values(array_filter($issues, fn($i) => $i['severity'] === 'error'));
        $warnings = array_values(array_filter($issues, fn($i) => $i['severity'] !== 'error'));
        if ($blocking) {
            throw new TtException($blocking[0]['message'], 409, ['conflicts' => $blocking, 'warnings' => $warnings]);
        }

        $db->beginTransaction();
        $cols = ['academic_session_id', 'academic_year', 'term', 'day_of_week', 'period_number', 'spans_periods', 'duration_minutes',
            'start_time', 'end_time', 'class_id', 'stream', 'subject_id', 'teacher_id', 'room_id', 'entry_type', 'event_id',
            'event_name', 'event_color', 'event_type', 'event_description', 'notes'];
        $values = [];
        foreach ($cols as $c) {
            $values[":$c"] = $slot[$c] ?? null;
        }
        if ($id) {
            $set = implode(', ', array_map(fn($c) => "$c = :$c", $cols));
            $values[':id'] = $id;
            $values[':updated_by'] = $actor['id'];
            $db->prepare("UPDATE timetable SET $set, updated_by = :updated_by, is_locked = 0 WHERE id = :id")->execute($values);
        } else {
            $values[':client_request_id'] = $slot['client_request_id'];
            $values[':created_by'] = $actor['id'];
            $db->prepare('INSERT INTO timetable (' . implode(', ', $cols) . ', client_request_id, created_by) VALUES ('
                . implode(', ', array_keys(array_slice($values, 0, count($cols)))) . ', :client_request_id, :created_by)')->execute($values);
            $id = (int)$db->lastInsertId();
        }

        try {
            tt_write_slots($db, $id, $slot);
        } catch (Throwable $e) {
            if (tt_is_duplicate_key($e)) {
                $db->rollBack();
                throw new TtException('Another change was saved for this period a moment ago. Refresh and try again.', 409, ['conflicts' => [['type' => 'concurrent', 'severity' => 'error', 'message' => 'The period was taken by a concurrent change.']]]);
            }
            throw $e;
        }

        tt_bump_revision($db, (int)$slot['academic_session_id']);
        $after = tt_get_entry($db, $id);
        $periodsText = $slot['day_of_week'] . ' ' . tt_period_label($periods, (int)$slot['period_number'], (int)$slot['spans_periods']);
        tt_audit($db, (int)$slot['academic_session_id'], $id, $before ? 'update' : 'create',
            ($before ? 'Updated ' : 'Added ') . ($slot['entry_type'] === 'event' ? 'event' : 'lesson') . " on $periodsText", $before, $after, $actor);
        $db->commit();
        return ['id' => $id, 'duplicate' => false, 'warnings' => $warnings];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    } finally {
        tt_unlock($db, $lock);
    }
}

function tt_delete_entry(PDO $db, int $id, array $actor): void
{
    $entry = tt_get_entry($db, $id);
    if (!$entry) {
        throw new TtException('This lesson no longer exists', 404);
    }
    if ((int)$entry['is_locked'] === 1) {
        throw new TtException('This lesson is locked. Unlock it before removing it.', 423);
    }
    $lock = tt_lock($db, (int)$entry['academic_year'], (int)$entry['term']);
    try {
        $db->beginTransaction();
        $db->prepare('DELETE FROM timetable WHERE id = :id')->execute([':id' => $id]);
        if ($entry['academic_session_id']) {
            tt_bump_revision($db, (int)$entry['academic_session_id']);
        }
        tt_audit($db, $entry['academic_session_id'] ? (int)$entry['academic_session_id'] : null, $id, 'delete',
            "Removed {$entry['entry_type']} on {$entry['day_of_week']} period {$entry['period_number']}", $entry, null, $actor);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    } finally {
        tt_unlock($db, $lock);
    }
}

function tt_set_locked(PDO $db, int $id, bool $locked, array $actor): void
{
    $entry = tt_get_entry($db, $id);
    if (!$entry) {
        throw new TtException('This lesson no longer exists', 404);
    }
    $db->prepare('UPDATE timetable SET is_locked = :l WHERE id = :id')->execute([':l' => $locked ? 1 : 0, ':id' => $id]);
    tt_audit($db, $entry['academic_session_id'] ? (int)$entry['academic_session_id'] : null, $id, $locked ? 'lock' : 'unlock',
        ($locked ? 'Locked' : 'Unlocked') . " lesson on {$entry['day_of_week']} period {$entry['period_number']}", null, null, $actor);
}

/* ------------------------------------------------------------------ */
/* Whole-timetable validation                                          */
/* ------------------------------------------------------------------ */

function tt_time_range(array $e, array $periods): array
{
    $start = (int)$e['period_number'];
    $end = $start + max(1, (int)$e['spans_periods']) - 1;
    $t1 = $e['start_time'] ?: ($periods[$start]['start_time'] ?? null);
    $t2 = $e['end_time'] ?: ($periods[$end]['end_time'] ?? null);
    return [$start, $end, $t1, $t2];
}

/** Do two entries overlap, by period range or by actual clock time? */
function tt_overlaps(array $a, array $b, array $periods): bool
{
    if ($a['day_of_week'] !== $b['day_of_week']) {
        return false;
    }
    [$as, $ae, $at1, $at2] = tt_time_range($a, $periods);
    [$bs, $be, $bt1, $bt2] = tt_time_range($b, $periods);
    if ($as <= $be && $bs <= $ae) {
        return true;
    }
    return $at1 && $at2 && $bt1 && $bt2 && $at1 < $bt2 && $bt1 < $at2;
}

/**
 * Full server-side validation of a term. Returns issues (each with severity,
 * explanation, affected entities, day/period, suggestion, lesson ids) plus a
 * summary and dashboard statistics computed from real data.
 */
function tt_validate(PDO $db, int $sessionId, int $year, int $term): array
{
    $periods = tt_load_periods($db);
    $entries = tt_fetch_entries($db, $year, $term);
    $byId = [];
    foreach ($entries as $e) {
        $byId[$e['id']] = $e;
    }
    $issues = [];
    $add = static function (array $issue) use (&$issues): void {
        $issue['id'] = $issue['type'] . '-' . count($issues);
        $issues[] = $issue;
    };
    $when = static function (array $e) use ($periods): string {
        return $e['day_of_week'] . ', ' . tt_period_label($periods, (int)$e['period_number'], max(1, (int)$e['spans_periods']));
    };

    // 1. Period definitions.
    $prev = null;
    foreach ($periods as $p) {
        if ($p['end_time'] <= $p['start_time']) {
            $add(['type' => 'period_definition', 'severity' => 'error', 'title' => 'Invalid period times',
                'message' => "{$p['label']} ends ({$p['end_time']}) before it starts ({$p['start_time']}).",
                'suggestion' => 'Fix the period times in the period settings.', 'entry_ids' => []]);
        }
        if ($prev && $p['start_time'] < $prev['end_time']) {
            $add(['type' => 'period_definition', 'severity' => 'error', 'title' => 'Overlapping periods',
                'message' => "{$p['label']} starts at {$p['start_time']}, before {$prev['label']} ends at {$prev['end_time']}.",
                'suggestion' => 'Adjust the period times so they do not overlap.', 'entry_ids' => []]);
        }
        $prev = $p;
    }

    // 2. Clashes (sweep each resource per day).
    $groups = [];
    foreach ($entries as $e) {
        $groups['class'][$e['class_id']][$e['day_of_week']][] = $e;
        if ($e['entry_type'] === 'lesson') {
            if ($e['teacher_id']) {
                $groups['teacher'][$e['teacher_id']][$e['day_of_week']][] = $e;
            }
            if ($e['room_id']) {
                $groups['room'][$e['room_id']][$e['day_of_week']][] = $e;
            }
        }
    }
    $seenPairs = [];
    foreach (['teacher', 'class', 'room'] as $kind) {
        foreach ($groups[$kind] ?? [] as $days) {
            foreach ($days as $list) {
                $n = count($list);
                for ($i = 0; $i < $n; $i++) {
                    for ($j = $i + 1; $j < $n; $j++) {
                        $a = $list[$i];
                        $b = $list[$j];
                        if (!tt_overlaps($a, $b, $periods)) {
                            continue;
                        }
                        $pair = "$kind-" . min($a['id'], $b['id']) . '-' . max($a['id'], $b['id']);
                        if (isset($seenPairs[$pair])) {
                            continue;
                        }
                        $seenPairs[$pair] = true;
                        if ($kind === 'teacher') {
                            $add(['type' => 'teacher_clash', 'severity' => 'error', 'title' => 'Teacher clash',
                                'message' => "{$a['teacher_name']} is scheduled to teach {$a['full_class_name']} ({$a['subject_name']}) and {$b['full_class_name']} ({$b['subject_name']}) at the same time.",
                                'teacher' => $a['teacher_name'], 'class' => "{$a['full_class_name']}, {$b['full_class_name']}",
                                'day' => $a['day_of_week'], 'period' => $when($a),
                                'suggestion' => "Move one of the lessons to a period when {$a['teacher_name']} is free, or assign another teacher.",
                                'entry_ids' => [$a['id'], $b['id']]]);
                        } elseif ($kind === 'class') {
                            $add(['type' => 'class_clash', 'severity' => 'error', 'title' => 'Class clash',
                                'message' => "{$a['full_class_name']} has " . tt_entry_label($a) . ' and ' . tt_entry_label($b) . ' at the same time.',
                                'class' => $a['full_class_name'], 'day' => $a['day_of_week'], 'period' => $when($a),
                                'suggestion' => "Move one of them to a free period in {$a['full_class_name']}'s week.",
                                'entry_ids' => [$a['id'], $b['id']]]);
                        } else {
                            $room = $a['room_name'] ?: $a['room_code'];
                            $add(['type' => 'room_clash', 'severity' => 'error', 'title' => 'Room clash',
                                'message' => "$room is booked by {$a['full_class_name']} ({$a['subject_name']}) and {$b['full_class_name']} ({$b['subject_name']}) at the same time.",
                                'room' => $room, 'class' => "{$a['full_class_name']}, {$b['full_class_name']}",
                                'day' => $a['day_of_week'], 'period' => $when($a),
                                'suggestion' => 'Assign a different free room to one of the lessons, or move it.',
                                'entry_ids' => [$a['id'], $b['id']]]);
                        }
                    }
                }
            }
        }
    }

    // 3. Invalid allocations (bad day/period/span, stale times).
    foreach ($entries as $e) {
        $span = max(1, (int)$e['spans_periods']);
        if (!in_array($e['day_of_week'], TT_DAYS, true)) {
            $add(['type' => 'invalid_period', 'severity' => 'error', 'title' => 'Outside school days',
                'message' => tt_entry_label($e) . " is scheduled on {$e['day_of_week']}, which is not a school day.",
                'class' => $e['full_class_name'], 'day' => $e['day_of_week'], 'period' => "Period {$e['period_number']}",
                'suggestion' => 'Move it to a weekday.', 'entry_ids' => [$e['id']]]);
            continue;
        }
        if ($e['entry_type'] === 'lesson') {
            $info = tt_span_info($periods, (int)$e['period_number'], $span);
            if (isset($info['error'])) {
                $add(['type' => 'invalid_period', 'severity' => 'error', 'title' => 'Invalid period',
                    'message' => tt_entry_label($e) . ': ' . $info['error'] . '.',
                    'class' => $e['full_class_name'], 'day' => $e['day_of_week'], 'period' => "Period {$e['period_number']}",
                    'suggestion' => 'Move the lesson to lesson periods that are back to back.', 'entry_ids' => [$e['id']]]);
                continue;
            }
            if ($e['start_time'] && substr((string)$e['start_time'], 0, 5) !== substr((string)$info['start_time'], 0, 5)) {
                $add(['type' => 'invalid_period', 'severity' => 'warning', 'title' => 'Lesson times out of date',
                    'message' => tt_entry_label($e) . " is stored as starting at {$e['start_time']} but that period now starts at {$info['start_time']}.",
                    'class' => $e['full_class_name'], 'day' => $e['day_of_week'], 'period' => $when($e),
                    'suggestion' => 'Open and save the lesson to refresh its times.', 'entry_ids' => [$e['id']]]);
            }
            $level = tt_class_level((string)$e['class_name']);
            if (!isset(tt_span_options($level)[$span])) {
                $add(['type' => 'invalid_period', 'severity' => 'error', 'title' => 'Lesson too long',
                    'message' => tt_entry_label($e) . " lasts $span periods, which is not allowed for $level.",
                    'class' => $e['full_class_name'], 'day' => $e['day_of_week'], 'period' => $when($e),
                    'suggestion' => 'Split it into allowed lesson lengths.', 'entry_ids' => [$e['id']]]);
            }
        }
    }

    // 4. Teacher availability.
    $stmt = $db->prepare('SELECT teacher_id, day_of_week, period_number, reason FROM teacher_availability WHERE academic_session_id = :sid AND is_available = 0');
    $stmt->execute([':sid' => $sessionId]);
    $unavailable = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $unavailable[(int)$row['teacher_id']][$row['day_of_week']][(int)$row['period_number']] = $row['reason'];
    }
    foreach ($entries as $e) {
        if ($e['entry_type'] !== 'lesson' || !$e['teacher_id'] || empty($unavailable[$e['teacher_id']][$e['day_of_week']])) {
            continue;
        }
        for ($p = (int)$e['period_number']; $p < (int)$e['period_number'] + max(1, (int)$e['spans_periods']); $p++) {
            if (array_key_exists($p, $unavailable[$e['teacher_id']][$e['day_of_week']])) {
                $add(['type' => 'teacher_unavailable', 'severity' => 'error', 'title' => 'Teacher unavailable',
                    'message' => "{$e['teacher_name']} is marked unavailable on {$e['day_of_week']}, " . ($periods[$p]['label'] ?? "period $p") . ' but has ' . tt_entry_label($e) . '.',
                    'teacher' => $e['teacher_name'], 'class' => $e['full_class_name'], 'day' => $e['day_of_week'], 'period' => $when($e),
                    'suggestion' => "Move the lesson to a period when {$e['teacher_name']} is available.", 'entry_ids' => [$e['id']]]);
                break;
            }
        }
    }

    // 5. Requirements: missing, over-allocated, wrong teacher.
    $stmt = $db->prepare('SELECT lr.*, s.subject_name, c.full_class_name, t.full_name AS teacher_name
        FROM lesson_requirements lr
        JOIN subjects_new s ON s.id = lr.subject_id
        JOIN classes c ON c.id = lr.class_id
        JOIN teachers t ON t.id = lr.teacher_id
        WHERE lr.academic_session_id = :sid');
    $stmt->execute([':sid' => $sessionId]);
    $requirements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $scheduledBy = [];
    foreach ($entries as $e) {
        if ($e['entry_type'] === 'lesson') {
            $key = $e['class_id'] . '|' . $e['subject_id'];
            $scheduledBy[$key]['total'] = ($scheduledBy[$key]['total'] ?? 0) + max(1, (int)$e['spans_periods']);
            $scheduledBy[$key]['teachers'][$e['teacher_id']] = ($scheduledBy[$key]['teachers'][$e['teacher_id']] ?? 0) + max(1, (int)$e['spans_periods']);
            $scheduledBy[$key]['ids'][] = $e['id'];
        }
    }
    $requiredBy = [];
    foreach ($requirements as $r) {
        $key = $r['class_id'] . '|' . $r['subject_id'];
        $requiredBy[$key]['total'] = ($requiredBy[$key]['total'] ?? 0) + (int)$r['periods_per_week'];
        $requiredBy[$key]['teachers'][(int)$r['teacher_id']] = true;
        $requiredBy[$key]['rows'][] = $r;
    }

    $totalRequired = 0;
    $unscheduledTotal = 0;
    $unscheduled = [];
    foreach ($requirements as $r) {
        $key = $r['class_id'] . '|' . $r['subject_id'];
        $need = (int)$r['periods_per_week'];
        $totalRequired += $need;
        $placed = (int)($scheduledBy[$key]['teachers'][(int)$r['teacher_id']] ?? 0);
        $missing = max(0, $need - $placed);
        if ($missing > 0) {
            $unscheduledTotal += $missing;
            $unscheduled[] = [
                'requirement_id' => (int)$r['id'], 'class_id' => (int)$r['class_id'], 'class' => $r['full_class_name'],
                'subject_id' => (int)$r['subject_id'], 'subject' => $r['subject_name'],
                'teacher_id' => (int)$r['teacher_id'], 'teacher' => $r['teacher_name'],
                'room_id' => $r['room_id'] ? (int)$r['room_id'] : null,
                'required' => $need, 'scheduled' => $placed, 'missing' => $missing,
                'preferred_span' => (int)($r['preferred_span'] ?: ((int)$r['double_lesson_required'] ? 2 : 1)),
            ];
            $add(['type' => 'missing_lessons', 'severity' => 'warning', 'title' => 'Missing lessons',
                'message' => "{$r['full_class_name']} has $placed of $need {$r['subject_name']} period(s) scheduled with {$r['teacher_name']}.",
                'class' => $r['full_class_name'], 'teacher' => $r['teacher_name'],
                'suggestion' => "Schedule $missing more period(s), or run the generator in “fill missing lessons” mode.",
                'entry_ids' => [], 'requirement_id' => (int)$r['id'], 'class_id' => (int)$r['class_id']]);
        }
    }
    foreach ($scheduledBy as $key => $s) {
        $req = $requiredBy[$key] ?? null;
        if (!$req) {
            continue;
        }
        $r0 = $req['rows'][0];
        if ($s['total'] > $req['total']) {
            $add(['type' => 'over_allocation', 'severity' => 'error', 'title' => 'Too many lessons',
                'message' => "{$r0['full_class_name']} has {$s['total']} {$r0['subject_name']} period(s) scheduled but only {$req['total']} are required.",
                'class' => $r0['full_class_name'],
                'suggestion' => 'Remove the extra lesson(s), or raise periods per week in Lesson Requirements if intended.',
                'entry_ids' => $s['ids']]);
        }
        foreach ($s['teachers'] as $tid => $count) {
            if (!isset($req['teachers'][$tid])) {
                $ids = array_values(array_filter($s['ids'], fn($id) => $byId[$id]['teacher_id'] === $tid));
                $add(['type' => 'wrong_teacher', 'severity' => 'warning', 'title' => 'Unexpected teacher',
                    'message' => ($byId[$ids[0]]['teacher_name'] ?? 'A teacher') . " teaches {$r0['subject_name']} to {$r0['full_class_name']}, but Lesson Requirements assign it to " . implode(', ', array_unique(array_column($req['rows'], 'teacher_name'))) . '.',
                    'class' => $r0['full_class_name'], 'teacher' => $byId[$ids[0]]['teacher_name'] ?? null,
                    'suggestion' => 'Change the lesson’s teacher, or update the requirement.', 'entry_ids' => $ids]);
            }
        }
    }

    // 6. Workload per day.
    $limit = tt_max_per_day($db, $sessionId);
    $load = [];
    foreach ($entries as $e) {
        if ($e['entry_type'] === 'lesson' && $e['teacher_id']) {
            $load[$e['teacher_id']][$e['day_of_week']]['n'] = ($load[$e['teacher_id']][$e['day_of_week']]['n'] ?? 0) + max(1, (int)$e['spans_periods']);
            $load[$e['teacher_id']][$e['day_of_week']]['ids'][] = $e['id'];
            $load[$e['teacher_id']]['name'] = $e['teacher_name'];
        }
    }
    foreach ($load as $days) {
        $name = $days['name'];
        unset($days['name']);
        foreach ($days as $day => $d) {
            if ($d['n'] > $limit['max']) {
                $add(['type' => 'workload', 'severity' => $limit['configured'] ? 'error' : 'warning', 'title' => 'Heavy teaching day',
                    'message' => "$name teaches {$d['n']} periods on $day (limit {$limit['max']}).",
                    'teacher' => $name, 'day' => $day,
                    'suggestion' => 'Move some of these lessons to a lighter day.', 'entry_ids' => $d['ids']]);
            }
        }
    }

    // 7. Rooms: too small for the class, or switched off.
    $enrolment = tt_class_enrolment($db);
    $roomInfo = [];
    foreach ($db->query('SELECT id, room_code, room_name, capacity, is_active FROM rooms')->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $roomInfo[(int)$r['id']] = $r;
    }
    $roomSeen = [];
    foreach ($entries as $e) {
        if ($e['entry_type'] !== 'lesson' || !$e['room_id'] || !isset($roomInfo[$e['room_id']])) {
            continue;
        }
        $room = $roomInfo[$e['room_id']];
        $students = $enrolment[$e['class_id']] ?? 0;
        $key = $e['room_id'] . '|' . $e['class_id'];
        if ($students > (int)$room['capacity']) {
            $roomSeen['size'][$key]['ids'][] = $e['id'];
            $roomSeen['size'][$key]['msg'] = "{$e['full_class_name']} has $students students but {$room['room_name']} ({$room['room_code']}) seats {$room['capacity']}.";
            $roomSeen['size'][$key]['class'] = $e['full_class_name'];
            $roomSeen['size'][$key]['room'] = $room['room_name'];
        }
        if ((int)$room['is_active'] === 0) {
            $roomSeen['inactive'][$e['room_id']]['ids'][] = $e['id'];
            $roomSeen['inactive'][$e['room_id']]['room'] = $room['room_name'];
        }
    }
    foreach ($roomSeen['size'] ?? [] as $s) {
        $add(['type' => 'room_capacity', 'severity' => 'warning', 'title' => 'Room too small', 'message' => $s['msg'],
            'class' => $s['class'], 'room' => $s['room'], 'suggestion' => 'Use a bigger room for these lessons, or correct the room capacity.', 'entry_ids' => $s['ids']]);
    }
    foreach ($roomSeen['inactive'] ?? [] as $s) {
        $add(['type' => 'room_capacity', 'severity' => 'warning', 'title' => 'Inactive room in use',
            'message' => "{$s['room']} is marked inactive but has " . count($s['ids']) . ' lesson(s).', 'room' => $s['room'],
            'suggestion' => 'Move those lessons to an active room, or activate the room again.', 'entry_ids' => $s['ids']]);
    }

    // Stats from real data.
    $scheduledPeriods = 0;
    $lessonCount = 0;
    foreach ($entries as $e) {
        if ($e['entry_type'] === 'lesson') {
            $lessonCount++;
            $scheduledPeriods += max(1, (int)$e['spans_periods']);
        }
    }
    $count = static fn(string $type) => count(array_filter($issues, fn($i) => $i['type'] === $type));
    $errors = count(array_filter($issues, fn($i) => $i['severity'] === 'error'));
    $warnings = count($issues) - $errors;

    return [
        'issues' => $issues,
        'unscheduled' => $unscheduled,
        'summary' => [
            'errors' => $errors,
            'warnings' => $warnings,
            'state' => $errors > 0 ? 'blocking' : ($warnings > 0 ? 'warnings' : 'passed'),
        ],
        'stats' => [
            'required_periods' => $totalRequired,
            'scheduled_periods' => $scheduledPeriods,
            'scheduled_lessons' => $lessonCount,
            'unscheduled_periods' => $unscheduledTotal,
            'teacher_conflicts' => $count('teacher_clash'),
            'class_conflicts' => $count('class_clash'),
            'room_conflicts' => $count('room_clash'),
            'completion' => $totalRequired > 0 ? (int)round(100 * ($totalRequired - $unscheduledTotal) / $totalRequired) : null,
            'requirements' => count($requirements),
        ],
        'validated_at' => date('c'),
    ];
}

/* ------------------------------------------------------------------ */
/* Generation                                                          */
/* ------------------------------------------------------------------ */

function tt_json_list($raw): array
{
    if ($raw === null || $raw === '') {
        return [];
    }
    $v = json_decode((string)$raw, true);
    return is_array($v) ? $v : [];
}

/**
 * Constraint-based greedy scheduler. Hard constraints: no teacher/class/room
 * overlap, teacher availability, contiguous lesson periods, allowed lesson
 * lengths, avoid-days/periods, configured daily limits. Soft: spread a
 * subject across the week, honour preferred days/periods, balance daily load.
 * Locked and (in "fill" mode) all existing lessons are kept.
 */
function tt_generate(PDO $db, array $opts, array $actor): array
{
    [$sessionId, $year, $term] = tt_require_session($db, $opts);
    $mode = ($opts['mode'] ?? 'fill') === 'regenerate' ? 'regenerate' : 'fill';
    $dryRun = !empty($opts['dry_run']);
    $classIds = array_values(array_filter(array_map('intval', (array)($opts['class_ids'] ?? []))));
    if (!$classIds && !empty($opts['class_id'])) {
        $classIds = [(int)$opts['class_id']];
    }

    $periods = tt_load_periods($db);
    $lessonStarts = array_keys(array_filter($periods, fn($p) => $p['period_type'] === 'lesson'));
    if (!$lessonStarts) {
        throw new TtException('No lesson periods are defined. Set up the periods first.', 400);
    }

    $lock = tt_lock($db, $year, $term);
    try {
        $db->beginTransaction();

        $removed = 0;
        if ($mode === 'regenerate') {
            $sql = "DELETE FROM timetable WHERE academic_year = :y AND term = :t AND entry_type = 'lesson' AND (is_locked = 0 OR is_locked IS NULL)";
            $params = [':y' => $year, ':t' => $term];
            if ($classIds) {
                $in = implode(',', $classIds);
                $sql .= " AND class_id IN ($in)";
            }
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $removed = $stmt->rowCount();
        }

        $entries = tt_fetch_entries($db, $year, $term);
        $occ = ['class' => [], 'teacher' => [], 'room' => []];
        $subjectDay = [];
        $teacherDay = [];
        $classDay = [];
        $placedFor = [];
        foreach ($entries as $e) {
            for ($p = $e['period_number']; $p < $e['period_number'] + $e['spans_periods']; $p++) {
                $occ['class'][$e['class_id']][$e['day_of_week']][$p] = true;
                if ($e['entry_type'] === 'lesson') {
                    if ($e['teacher_id']) {
                        $occ['teacher'][$e['teacher_id']][$e['day_of_week']][$p] = true;
                    }
                    if ($e['room_id']) {
                        $occ['room'][$e['room_id']][$e['day_of_week']][$p] = true;
                    }
                }
            }
            if ($e['entry_type'] === 'lesson') {
                $subjectDay[$e['class_id']][$e['subject_id']][$e['day_of_week']] = ($subjectDay[$e['class_id']][$e['subject_id']][$e['day_of_week']] ?? 0) + 1;
                $teacherDay[$e['teacher_id']][$e['day_of_week']] = ($teacherDay[$e['teacher_id']][$e['day_of_week']] ?? 0) + $e['spans_periods'];
                $classDay[$e['class_id']][$e['day_of_week']] = ($classDay[$e['class_id']][$e['day_of_week']] ?? 0) + $e['spans_periods'];
                $k = "{$e['class_id']}|{$e['subject_id']}|{$e['teacher_id']}";
                $placedFor[$k] = ($placedFor[$k] ?? 0) + $e['spans_periods'];
            }
        }

        $stmt = $db->prepare('SELECT teacher_id, day_of_week, period_number FROM teacher_availability WHERE academic_session_id = :sid AND is_available = 0');
        $stmt->execute([':sid' => $sessionId]);
        $unavailable = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $unavailable[(int)$row['teacher_id']][$row['day_of_week']][(int)$row['period_number']] = true;
        }
        $limit = tt_max_per_day($db, $sessionId);
        $homeRooms = tt_home_rooms($db);

        $sql = 'SELECT lr.*, s.subject_name, c.class_name, c.full_class_name, c.stream_name, t.full_name AS teacher_name, t.is_active AS teacher_active
            FROM lesson_requirements lr
            JOIN subjects_new s ON s.id = lr.subject_id
            JOIN classes c ON c.id = lr.class_id
            JOIN teachers t ON t.id = lr.teacher_id
            WHERE lr.academic_session_id = :sid';
        if ($classIds) {
            $sql .= ' AND lr.class_id IN (' . implode(',', $classIds) . ')';
        }
        $stmt = $db->prepare($sql);
        $stmt->execute([':sid' => $sessionId]);
        $requirements = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$requirements) {
            throw new TtException('There are no lesson requirements for ' . ($classIds ? 'the selected classes' : 'this term') . '. Add them under Setup → Lesson Requirements first.', 400);
        }

        // Most constrained first: required doubles, busiest teachers, most periods.
        $teacherLoad = [];
        foreach ($requirements as $r) {
            $teacherLoad[$r['teacher_id']] = ($teacherLoad[$r['teacher_id']] ?? 0) + (int)$r['periods_per_week'];
        }
        usort($requirements, function ($a, $b) use ($teacherLoad) {
            return [(int)$b['double_lesson_required'], $teacherLoad[$b['teacher_id']], (int)$b['periods_per_week']]
                <=> [(int)$a['double_lesson_required'], $teacherLoad[$a['teacher_id']], (int)$a['periods_per_week']];
        });

        $insert = $db->prepare("INSERT INTO timetable (academic_session_id, academic_year, term, day_of_week, period_number,
            spans_periods, duration_minutes, start_time, end_time, class_id, stream, subject_id, teacher_id, room_id, entry_type,
            lesson_requirement_id, created_by)
            VALUES (:sid, :y, :t, :day, :p, :span, :dur, :st, :et, :cid, :stream, :subj, :tid, :rid, 'lesson', :req, :by)");

        $generated = 0;
        $placedPeriods = 0;
        $unplaced = [];
        $reasonText = [
            'class_busy' => 'the class is already busy',
            'teacher_busy' => 'the teacher is already teaching',
            'room_busy' => 'the room is already booked',
            'teacher_unavailable' => 'the teacher is marked unavailable',
            'avoid' => 'avoided days/periods in the requirement',
            'daily_limit' => 'the teacher’s daily lesson limit',
            'span' => 'no back-to-back lesson periods for a double lesson',
            'inactive' => 'the teacher is inactive',
        ];

        foreach ($requirements as $req) {
            $cid = (int)$req['class_id'];
            $tid = (int)$req['teacher_id'];
            $sid = (int)$req['subject_id'];
            // A named room (e.g. a lab) wins; otherwise the class's own home room.
            $rid = $req['room_id'] ? (int)$req['room_id'] : ($homeRooms[$cid] ?? null);
            $key = "$cid|$sid|$tid";
            $need = (int)$req['periods_per_week'] - (int)($placedFor[$key] ?? 0);
            if ($need <= 0) {
                continue;
            }
            if ($req['teacher_active'] !== null && (int)$req['teacher_active'] === 0) {
                $unplaced[] = ['class' => $req['full_class_name'], 'subject' => $req['subject_name'], 'teacher' => $req['teacher_name'],
                    'missing' => $need, 'reasons' => [$reasonText['inactive']]];
                continue;
            }

            $options = tt_span_options(tt_class_level((string)$req['class_name']));
            $span = (int)($req['preferred_span'] ?? 0) ?: (((int)$req['double_lesson_required'] || (int)$req['double_lesson_allowed']) ? 2 : 1);
            if (!isset($options[$span])) {
                $span = 1;
            }
            $doubleRequired = (int)$req['double_lesson_required'] === 1;
            $preferredDays = tt_json_list($req['preferred_days']);
            $preferredPeriods = array_map('intval', tt_json_list($req['preferred_periods']));
            $avoidDays = tt_json_list($req['avoid_days']);
            $avoidPeriods = array_map('intval', tt_json_list($req['avoid_periods']));
            $reasons = [];

            while ($need > 0) {
                $try = min($span, $need);
                if ($try > 1 && !isset($options[$try])) {
                    $try = 1;
                }
                $best = null;
                foreach (TT_DAYS as $day) {
                    if (in_array($day, $avoidDays, true)) {
                        $reasons['avoid'] = ($reasons['avoid'] ?? 0) + 1;
                        continue;
                    }
                    if ($limit['configured'] && ($teacherDay[$tid][$day] ?? 0) + $try > $limit['max']) {
                        $reasons['daily_limit'] = ($reasons['daily_limit'] ?? 0) + 1;
                        continue;
                    }
                    foreach ($lessonStarts as $start) {
                        $info = tt_span_info($periods, $start, $try);
                        if (isset($info['error'])) {
                            $reasons['span'] = ($reasons['span'] ?? 0) + 1;
                            continue;
                        }
                        $blocked = null;
                        for ($p = $start; $p < $start + $try; $p++) {
                            if (in_array($p, $avoidPeriods, true)) { $blocked = 'avoid'; break; }
                            if (!empty($occ['class'][$cid][$day][$p])) { $blocked = 'class_busy'; break; }
                            if (!empty($occ['teacher'][$tid][$day][$p])) { $blocked = 'teacher_busy'; break; }
                            if ($rid && !empty($occ['room'][$rid][$day][$p])) { $blocked = 'room_busy'; break; }
                            if (!empty($unavailable[$tid][$day][$p])) { $blocked = 'teacher_unavailable'; break; }
                        }
                        if ($blocked) {
                            $reasons[$blocked] = ($reasons[$blocked] ?? 0) + 1;
                            continue;
                        }
                        // Soft constraints: lower score is better.
                        $score = 40 * ($subjectDay[$cid][$sid][$day] ?? 0)
                            + 3 * ($teacherDay[$tid][$day] ?? 0)
                            + 2 * ($classDay[$cid][$day] ?? 0)
                            + 0.2 * $start;
                        if ($preferredDays && in_array($day, $preferredDays, true)) {
                            $score -= 15;
                        }
                        if ($preferredPeriods && in_array($start, $preferredPeriods, true)) {
                            $score -= 8;
                        }
                        if (!$limit['configured'] && ($teacherDay[$tid][$day] ?? 0) + $try > TT_DEFAULT_MAX_PER_DAY) {
                            $score += 60;
                        }
                        if ($best === null || $score < $best['score']) {
                            $best = ['score' => $score, 'day' => $day, 'start' => $start, 'span' => $try, 'info' => $info];
                        }
                    }
                }

                if ($best === null) {
                    if ($try > 1 && !$doubleRequired) {
                        $span = 1; // fall back to single periods
                        continue;
                    }
                    arsort($reasons);
                    $unplaced[] = [
                        'class' => $req['full_class_name'], 'subject' => $req['subject_name'], 'teacher' => $req['teacher_name'],
                        'missing' => $need,
                        'reasons' => array_values(array_map(fn($k) => $reasonText[$k], array_slice(array_keys($reasons), 0, 3))),
                    ];
                    break;
                }

                $insert->execute([
                    ':sid' => $sessionId, ':y' => $year, ':t' => $term, ':day' => $best['day'], ':p' => $best['start'],
                    ':span' => $best['span'], ':dur' => $best['info']['duration_minutes'],
                    ':st' => $best['info']['start_time'], ':et' => $best['info']['end_time'],
                    ':cid' => $cid, ':stream' => $req['stream_name'] ?: null, ':subj' => $sid, ':tid' => $tid, ':rid' => $rid,
                    ':req' => (int)$req['id'], ':by' => $actor['id'],
                ]);
                $newId = (int)$db->lastInsertId();
                tt_write_slots($db, $newId, ['entry_type' => 'lesson', 'class_id' => $cid, 'teacher_id' => $tid, 'room_id' => $rid,
                    'academic_year' => $year, 'term' => $term, 'day_of_week' => $best['day'], 'period_number' => $best['start'], 'spans_periods' => $best['span']]);

                for ($p = $best['start']; $p < $best['start'] + $best['span']; $p++) {
                    $occ['class'][$cid][$best['day']][$p] = true;
                    $occ['teacher'][$tid][$best['day']][$p] = true;
                    if ($rid) {
                        $occ['room'][$rid][$best['day']][$p] = true;
                    }
                }
                $subjectDay[$cid][$sid][$best['day']] = ($subjectDay[$cid][$sid][$best['day']] ?? 0) + 1;
                $teacherDay[$tid][$best['day']] = ($teacherDay[$tid][$best['day']] ?? 0) + $best['span'];
                $classDay[$cid][$best['day']] = ($classDay[$cid][$best['day']] ?? 0) + $best['span'];
                $need -= $best['span'];
                $generated++;
                $placedPeriods += $best['span'];
            }
        }

        $result = [
            'mode' => $mode,
            'dry_run' => $dryRun,
            'removed' => $removed,
            'generated' => $generated,
            'placed_periods' => $placedPeriods,
            'unplaced' => $unplaced,
            'unplaced_periods' => array_sum(array_column($unplaced, 'missing')),
        ];

        if ($dryRun) {
            $db->rollBack();
        } else {
            tt_bump_revision($db, $sessionId);
            tt_audit($db, $sessionId, null, 'generate',
                ucfirst($mode) . ": placed $placedPeriods period(s) in $generated lesson(s)" . ($removed ? ", replaced $removed unlocked lesson(s)" : '')
                . ($result['unplaced_periods'] ? ", {$result['unplaced_periods']} period(s) could not be placed" : ''),
                null, ['class_ids' => $classIds, 'mode' => $mode], $actor);
            $db->commit();
        }
        return $result;
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    } finally {
        tt_unlock($db, $lock);
    }
}

/* ------------------------------------------------------------------ */
/* Publishing, versions, restore                                       */
/* ------------------------------------------------------------------ */

function tt_mark_validated(PDO $db, int $sessionId, array $actor): void
{
    $db->prepare("INSERT INTO timetable_session_state (academic_session_id, revision, status, validated_revision, validated_at, validated_by)
        VALUES (:sid, 0, 'validated', 0, NOW(), :by)
        ON DUPLICATE KEY UPDATE validated_revision = revision, validated_at = NOW(), validated_by = :by2,
            status = IF(published_revision = revision, 'published', 'validated')")
        ->execute([':sid' => $sessionId, ':by' => $actor['name'], ':by2' => $actor['name']]);
}

function tt_publish(PDO $db, array $data, array $actor): array
{
    [$sessionId, $year, $term] = tt_require_session($db, $data);
    $lock = tt_lock($db, $year, $term);
    try {
        $validation = tt_validate($db, $sessionId, $year, $term);
        if ($validation['summary']['errors'] > 0) {
            throw new TtException("Publishing is blocked: {$validation['summary']['errors']} blocking problem(s) must be fixed first.", 422, ['validation' => $validation]);
        }
        if ($validation['summary']['warnings'] > 0 && empty($data['confirm_warnings'])) {
            throw new TtException("There are {$validation['summary']['warnings']} warning(s). Confirm to publish anyway.", 409, ['needs_confirmation' => true, 'validation' => $validation]);
        }
        $entries = tt_fetch_entries($db, $year, $term);
        if (!$entries) {
            throw new TtException('There is nothing to publish yet, the timetable is empty.', 400);
        }

        $effective = (string)($data['effective_date'] ?? '');
        if ($effective !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $effective)) {
            throw new TtException('Effective date must be a valid date', 422, ['errors' => ['effective_date' => 'Use a valid date']]);
        }

        $db->beginTransaction();
        $next = $db->prepare('SELECT COALESCE(MAX(version_number), 0) + 1 FROM timetable_versions WHERE academic_session_id = :sid');
        $next->execute([':sid' => $sessionId]);
        $number = (int)$next->fetchColumn();

        $db->prepare("UPDATE timetable_versions SET status = 'archived', archived_at = NOW() WHERE academic_session_id = :sid AND status = 'published'")
            ->execute([':sid' => $sessionId]);
        $db->prepare("INSERT INTO timetable_versions (academic_session_id, version_name, version_number, status, published_at, notes,
                snapshot, entry_count, effective_date, created_by_user_id, created_by_name)
            VALUES (:sid, :name, :num, 'published', NOW(), :notes, :snap, :cnt, :eff, :uid, :uname)")
            ->execute([
                ':sid' => $sessionId,
                ':name' => trim((string)($data['version_name'] ?? '')) ?: "Version $number",
                ':num' => $number,
                ':notes' => trim((string)($data['notes'] ?? '')) ?: null,
                ':snap' => json_encode($entries),
                ':cnt' => count($entries),
                ':eff' => $effective ?: null,
                ':uid' => $actor['id'],
                ':uname' => $actor['name'],
            ]);
        $versionId = (int)$db->lastInsertId();

        $db->prepare("INSERT INTO timetable_session_state (academic_session_id, revision, status, validated_revision, validated_at, validated_by, published_revision, published_version_id)
            VALUES (:sid, 0, 'published', 0, NOW(), :by, 0, :vid)
            ON DUPLICATE KEY UPDATE status = 'published', validated_revision = revision, validated_at = NOW(), validated_by = :by2,
                published_revision = revision, published_version_id = :vid2")
            ->execute([':sid' => $sessionId, ':by' => $actor['name'], ':by2' => $actor['name'], ':vid' => $versionId, ':vid2' => $versionId]);
        tt_audit($db, $sessionId, null, 'publish', "Published version $number (" . count($entries) . ' entries)', null, ['version_id' => $versionId], $actor);
        $db->commit();
        return ['version_id' => $versionId, 'version_number' => $number];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    } finally {
        tt_unlock($db, $lock);
    }
}

/** Replaces the working copy with a published snapshot and republishes it. */
function tt_restore(PDO $db, int $versionId, array $actor): array
{
    $stmt = $db->prepare('SELECT v.*, s.academic_year, s.term FROM timetable_versions v JOIN academic_sessions s ON s.id = v.academic_session_id WHERE v.id = :id');
    $stmt->execute([':id' => $versionId]);
    $version = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$version) {
        throw new TtException('Version not found', 404);
    }
    if (empty($version['snapshot'])) {
        throw new TtException('This version was saved before snapshots existed, so it has no stored lessons to restore.', 400);
    }
    $rows = json_decode((string)$version['snapshot'], true) ?: [];
    $sessionId = (int)$version['academic_session_id'];
    $year = (int)$version['academic_year'];
    $term = (int)$version['term'];

    $lock = tt_lock($db, $year, $term);
    try {
        $db->beginTransaction();
        $before = $db->prepare('SELECT COUNT(*) FROM timetable WHERE academic_year = :y AND term = :t');
        $before->execute([':y' => $year, ':t' => $term]);
        $beforeCount = (int)$before->fetchColumn();
        $db->prepare('DELETE FROM timetable WHERE academic_year = :y AND term = :t')->execute([':y' => $year, ':t' => $term]);

        $cols = ['day_of_week', 'period_number', 'spans_periods', 'duration_minutes', 'start_time', 'end_time', 'class_id', 'stream',
            'subject_id', 'teacher_id', 'room_id', 'entry_type', 'event_id', 'event_name', 'event_color', 'event_type', 'event_description',
            'is_locked', 'lesson_requirement_id', 'notes'];
        $insert = $db->prepare('INSERT INTO timetable (academic_session_id, academic_year, term, timetable_version_id, created_by, '
            . implode(', ', $cols) . ') VALUES (:sid, :y, :t, :vid, :by, ' . implode(', ', array_map(fn($c) => ":$c", $cols)) . ')');
        $restored = 0;
        $skipped = [];
        foreach ($rows as $row) {
            $params = [':sid' => $sessionId, ':y' => $year, ':t' => $term, ':vid' => $versionId, ':by' => $actor['id']];
            foreach ($cols as $c) {
                $params[":$c"] = $row[$c] ?? null;
            }
            $params[':is_locked'] = (int)($row['is_locked'] ?? 0);
            $db->exec('SAVEPOINT tt_row');
            try {
                $insert->execute($params);
                $newId = (int)$db->lastInsertId();
                tt_write_slots($db, $newId, $row + ['academic_year' => $year, 'term' => $term]);
                $restored++;
            } catch (Throwable $e) {
                // e.g. the class/teacher was deleted since publication, or a legacy clash in the snapshot.
                $db->exec('ROLLBACK TO SAVEPOINT tt_row');
                $skipped[] = tt_entry_label($row) . " ({$row['day_of_week']} period {$row['period_number']})";
            }
        }

        $db->prepare("UPDATE timetable_versions SET status = 'archived', archived_at = NOW() WHERE academic_session_id = :sid AND status = 'published' AND id <> :id")
            ->execute([':sid' => $sessionId, ':id' => $versionId]);
        $db->prepare("UPDATE timetable_versions SET status = 'published', published_at = NOW(), archived_at = NULL WHERE id = :id")
            ->execute([':id' => $versionId]);
        tt_bump_revision($db, $sessionId);
        $db->prepare("UPDATE timetable_session_state SET status = IF(:skipped = 0, 'published', 'draft'),
                published_revision = IF(:skipped2 = 0, revision, published_revision),
                validated_revision = IF(:skipped3 = 0, revision, validated_revision), published_version_id = :vid
            WHERE academic_session_id = :sid")
            ->execute([':skipped' => count($skipped), ':skipped2' => count($skipped), ':skipped3' => count($skipped), ':vid' => $versionId, ':sid' => $sessionId]);
        tt_audit($db, $sessionId, null, 'restore', "Restored version {$version['version_number']}: $restored entr(ies) restored, replaced $beforeCount"
            . ($skipped ? ', ' . count($skipped) . ' skipped' : ''), null, ['version_id' => $versionId, 'skipped' => $skipped], $actor);
        $db->commit();
        return ['restored' => $restored, 'skipped' => $skipped];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    } finally {
        tt_unlock($db, $lock);
    }
}

function tt_versions(PDO $db, int $sessionId): array
{
    $stmt = $db->prepare('SELECT id, version_number, version_name, status, published_at, archived_at, effective_date, notes,
            entry_count, created_by_name, (snapshot IS NOT NULL) AS restorable
        FROM timetable_versions WHERE academic_session_id = :sid ORDER BY version_number DESC');
    $stmt->execute([':sid' => $sessionId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function tt_audit_log(PDO $db, int $sessionId, int $limit = 100): array
{
    $stmt = $db->prepare('SELECT id, timetable_id, action, summary, user_name, created_at FROM timetable_audit_log
        WHERE academic_session_id = :sid ORDER BY id DESC LIMIT ' . max(1, min(500, $limit)));
    $stmt->execute([':sid' => $sessionId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** Lessons from the published snapshot (read-only view). */
function tt_published_entries(PDO $db, int $sessionId): ?array
{
    $stmt = $db->prepare("SELECT snapshot FROM timetable_versions WHERE academic_session_id = :sid AND status = 'published' AND snapshot IS NOT NULL ORDER BY published_at DESC LIMIT 1");
    $stmt->execute([':sid' => $sessionId]);
    $raw = $stmt->fetchColumn();
    return $raw ? (json_decode((string)$raw, true) ?: []) : null;
}

/* ------------------------------------------------------------------ */
/* Class home rooms                                                    */
/* ------------------------------------------------------------------ */

/**
 * class_id -> room_id. Created on first use and linked once to rooms named
 * after the class ("S.1 - A Class"), which is how class rooms are named.
 */
function tt_ensure_home_rooms(PDO $db): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $existed = tt_table_exists($db, 'class_home_rooms');
    if ($existed) {
        $done = true;
        return;
    }
    if ($db->inTransaction()) {
        throw new LogicException('class_home_rooms must be created before a transaction starts (DDL commits it)');
    }
    $db->exec('CREATE TABLE IF NOT EXISTS class_home_rooms (
        class_id INT NOT NULL PRIMARY KEY,
        room_id INT NOT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_home_room (room_id),
        CONSTRAINT fk_home_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE CASCADE,
        CONSTRAINT fk_home_room FOREIGN KEY (room_id) REFERENCES rooms (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci');
    tt_link_home_rooms_by_name($db);
    $done = true;
}

/** Links each class without a home room to the room named "S.1 - A Class" for it. Returns links made. */
function tt_link_home_rooms_by_name(PDO $db): int
{
    $rooms = [];
    foreach ($db->query('SELECT id, room_name FROM rooms WHERE id NOT IN (SELECT room_id FROM class_home_rooms)')->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $rooms[tt_key($r['room_name'])] = (int)$r['id'];
    }
    $made = 0;
    $ins = $db->prepare('INSERT IGNORE INTO class_home_rooms (class_id, room_id) VALUES (:c, :r)');
    foreach ($db->query('SELECT id, class_name, stream_name FROM classes WHERE id NOT IN (SELECT class_id FROM class_home_rooms)')->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $level = preg_replace('/^S\.?\s*(\d+)$/i', 'S.$1', trim((string)$c['class_name']));
        $key = tt_key($level . ' - ' . $c['stream_name'] . ' Class');
        if (isset($rooms[$key])) {
            $ins->execute([':c' => $c['id'], ':r' => $rooms[$key]]);
            $made += $ins->rowCount();
            unset($rooms[$key]);
        }
    }
    return $made;
}

function tt_home_rooms(PDO $db): array
{
    tt_ensure_home_rooms($db);
    $map = [];
    foreach ($db->query('SELECT h.class_id, h.room_id FROM class_home_rooms h JOIN rooms r ON r.id = h.room_id WHERE r.is_active = 1')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $map[(int)$row['class_id']] = (int)$row['room_id'];
    }
    return $map;
}

/** class_id -> number of students (from the student register), for room-size checks. */
function tt_class_enrolment(PDO $db): array
{
    $out = [];
    try {
        $rows = $db->query('SELECT c.id, COUNT(s.id) AS n FROM classes c
            JOIN students s ON TRIM(s.class) COLLATE utf8mb4_unicode_ci = TRIM(c.class_name) COLLATE utf8mb4_unicode_ci AND TRIM(s.stream) COLLATE utf8mb4_unicode_ci = TRIM(c.stream_name) COLLATE utf8mb4_unicode_ci GROUP BY c.id')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $out[(int)$r['id']] = (int)$r['n'];
        }
    } catch (Throwable $e) {
        // No student register available: size checks are skipped.
    }
    return $out;
}

/* ------------------------------------------------------------------ */
/* School day (periods, breaks, lunch) and scheduling rules            */
/* ------------------------------------------------------------------ */

function tt_valid_time(string $t): ?string
{
    if (!preg_match('/^([01]?\d|2[0-3]):([0-5]\d)(:\d\d)?$/', trim($t), $m)) {
        return null;
    }
    return sprintf('%02d:%02d:00', (int)$m[1], (int)$m[2]);
}

/**
 * Saves the school day. Existing periods keep their numbers (lessons point at
 * them); new periods can be added at the end and unused ones removed from the
 * end. A period that has lessons cannot become a break. Lesson times are
 * recalculated and every term must be validated again.
 */
function tt_save_periods(PDO $db, array $rows, array $actor): array
{
    $current = tt_load_periods($db);
    if (!$rows) {
        throw new TtException('The school day needs at least one period', 422);
    }
    $errors = [];
    $clean = [];
    $labels = [];
    $prevEnd = null;
    foreach (array_values($rows) as $i => $r) {
        $number = $i + 1;
        $label = trim((string)($r['label'] ?? ''));
        $type = (string)($r['period_type'] ?? 'lesson');
        $start = tt_valid_time((string)($r['start_time'] ?? ''));
        $end = tt_valid_time((string)($r['end_time'] ?? ''));
        $row = "Row $number";
        if ($label === '') {
            $errors[] = "$row: give the period a name";
        } elseif (isset($labels[strtolower($label)])) {
            $errors[] = "$row: the name “{$label}” is used twice";
        }
        $labels[strtolower($label)] = true;
        if (!in_array($type, ['lesson', 'break', 'lunch', 'assembly'], true)) {
            $errors[] = "$row: choose lesson, break, lunch or assembly";
        }
        if (!$start || !$end) {
            $errors[] = "$row: enter start and end times like 08:00";
        } elseif ($end <= $start) {
            $errors[] = "$row ($label) ends before it starts";
        } elseif ($prevEnd && $start < $prevEnd) {
            $errors[] = "$row ($label) starts at " . substr($start, 0, 5) . ' before the previous period ends at ' . substr($prevEnd, 0, 5);
        }
        $prevEnd = $end ?: $prevEnd;
        $clean[$number] = ['period_number' => $number, 'label' => $label, 'period_type' => $type, 'start_time' => $start, 'end_time' => $end];
    }
    if (!count(array_filter($clean, fn($p) => $p['period_type'] === 'lesson'))) {
        $errors[] = 'The day needs at least one lesson period';
    }

    // Periods in use cannot disappear or stop being lessons.
    $use = [];
    foreach ($db->query('SELECT period_number, spans_periods FROM timetable')->fetchAll(PDO::FETCH_ASSOC) as $e) {
        for ($p = (int)$e['period_number']; $p < (int)$e['period_number'] + max(1, (int)$e['spans_periods']); $p++) {
            $use[$p] = ($use[$p] ?? 0) + 1;
        }
    }
    foreach ($current as $n => $old) {
        if (!isset($clean[$n]) && !empty($use[$n])) {
            $errors[] = "{$old['label']} has {$use[$n]} lesson(s) on the timetable, so it cannot be removed";
        } elseif (isset($clean[$n]) && $old['period_type'] === 'lesson' && $clean[$n]['period_type'] !== 'lesson' && !empty($use[$n])) {
            $errors[] = "{$old['label']} has {$use[$n]} lesson(s), so it cannot become a {$clean[$n]['period_type']}, move those lessons first";
        }
    }
    if ($errors) {
        throw new TtException($errors[0], 422, ['errors' => $errors]);
    }

    $db->beginTransaction();
    try {
        $db->exec('DELETE FROM timetable_periods WHERE period_number > ' . count($clean));
        $db->exec('DELETE FROM teacher_availability WHERE period_number > ' . count($clean));
        $up = $db->prepare('INSERT INTO timetable_periods (period_number, label, period_type, start_time, end_time, is_active)
            VALUES (:n, :l, :t, :s, :e, 1)
            ON DUPLICATE KEY UPDATE label = VALUES(label), period_type = VALUES(period_type), start_time = VALUES(start_time), end_time = VALUES(end_time), is_active = 1');
        foreach ($clean as $p) {
            $up->execute([':n' => $p['period_number'], ':l' => $p['label'], ':t' => $p['period_type'], ':s' => $p['start_time'], ':e' => $p['end_time']]);
        }
        // Keep stored lesson times in step with their periods.
        $sync = $db->prepare('UPDATE timetable t
            JOIN timetable_periods ps ON ps.period_number = t.period_number
            JOIN timetable_periods pe ON pe.period_number = t.period_number + GREATEST(t.spans_periods, 1) - 1
            SET t.start_time = ps.start_time, t.end_time = pe.end_time,
                t.duration_minutes = TIMESTAMPDIFF(MINUTE, ps.start_time, pe.end_time)');
        $sync->execute();
        foreach ($db->query('SELECT id FROM academic_sessions')->fetchAll(PDO::FETCH_COLUMN) as $sid) {
            tt_bump_revision($db, (int)$sid);
        }
        tt_audit($db, null, null, 'periods', 'Updated the school day: ' . count($clean) . ' periods', array_values($current), array_values($clean), $actor);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
    return ['periods' => array_values(tt_load_periods($db)), 'lessons_retimed' => $sync->rowCount()];
}

function tt_save_daily_limit(PDO $db, int $sessionId, ?int $max, array $actor): void
{
    if ($max !== null && ($max < 1 || $max > 12)) {
        throw new TtException('The daily limit must be between 1 and 12 periods', 422);
    }
    $db->prepare("DELETE FROM timetable_constraints WHERE academic_session_id = :s AND constraint_type = 'max_lessons_per_day'")->execute([':s' => $sessionId]);
    if ($max !== null) {
        $db->prepare("INSERT INTO timetable_constraints (constraint_type, academic_session_id, constraint_value, is_active) VALUES ('max_lessons_per_day', :s, :v, 1)")
            ->execute([':s' => $sessionId, ':v' => json_encode(['enabled' => true, 'max' => $max])]);
    }
    tt_bump_revision($db, $sessionId);
    tt_audit($db, $sessionId, null, 'rules', $max === null ? 'Removed the teacher daily lesson limit' : "Set the teacher daily lesson limit to $max periods", null, ['max' => $max], $actor);
}

/** Setup checklist with real counts, for the Setup screen. */
function tt_setup_status(PDO $db, ?int $sessionId): array
{
    tt_ensure_home_rooms($db);
    tt_ensure_subject_departments($db);
    $one = fn(string $sql, array $p = []) => (function () use ($db, $sql, $p) {
        $s = $db->prepare($sql);
        $s->execute($p);
        return (int)$s->fetchColumn();
    })();
    $periods = tt_load_periods($db);
    $classes = $one('SELECT COUNT(*) FROM classes');
    $withHome = $one('SELECT COUNT(*) FROM class_home_rooms');
    $subjects = $one('SELECT COUNT(*) FROM subjects_new');
    $staffed = $one('SELECT COUNT(DISTINCT subject_id) FROM teacher_subjects');
    return [
        'session' => $sessionId !== null,
        'lesson_periods' => count(array_filter($periods, fn($p) => $p['period_type'] === 'lesson')),
        'breaks' => count(array_filter($periods, fn($p) => $p['period_type'] !== 'lesson')),
        'classes' => $classes,
        'classes_with_home_room' => $withHome,
        'rooms' => $one('SELECT COUNT(*) FROM rooms WHERE is_active = 1'),
        'subjects' => $subjects,
        'subjects_with_teachers' => $staffed,
        'requirements' => $sessionId ? $one('SELECT COUNT(*) FROM lesson_requirements WHERE academic_session_id = :s', [':s' => $sessionId]) : 0,
        'availability_marks' => $sessionId ? $one('SELECT COUNT(*) FROM teacher_availability WHERE academic_session_id = :s AND is_available = 0', [':s' => $sessionId]) : 0,
        'daily_limit' => $sessionId ? tt_max_per_day($db, $sessionId) : ['max' => TT_DEFAULT_MAX_PER_DAY, 'configured' => false],
    ];
}

/* ------------------------------------------------------------------ */
/* Subject departments: which teachers teach which subject             */
/* ------------------------------------------------------------------ */

function tt_ensure_subject_departments(PDO $db): void
{
    static $done = false;
    if ($done) {
        return;
    }
    if (tt_table_exists($db, 'teacher_subjects')) {
        $done = true;
        return;
    }
    if ($db->inTransaction()) {
        throw new LogicException('teacher_subjects must be created before a transaction starts (DDL commits it)');
    }
    $db->exec('CREATE TABLE IF NOT EXISTS teacher_subjects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        teacher_id INT NOT NULL,
        subject_id INT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_teacher_subject (teacher_id, subject_id),
        KEY idx_subject_teachers (subject_id),
        CONSTRAINT fk_ts_teacher FOREIGN KEY (teacher_id) REFERENCES teachers (id) ON DELETE CASCADE,
        CONSTRAINT fk_ts_subject FOREIGN KEY (subject_id) REFERENCES subjects_new (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci');
    $done = true;
}

/** subject_id => [teacher_id, …] for every subject that has a department. */
function tt_subject_teachers(PDO $db): array
{
    tt_ensure_subject_departments($db);
    $map = [];
    foreach ($db->query('SELECT subject_id, teacher_id FROM teacher_subjects')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $map[(int)$row['subject_id']][] = (int)$row['teacher_id'];
    }
    return $map;
}

/**
 * A teacher may only be given a subject they belong to. Subjects whose
 * department has no members yet are not restricted (so nothing is blocked
 * before departments are set up). Returns an error message or null.
 */
function tt_department_error(PDO $db, int $subjectId, int $teacherId): ?string
{
    tt_ensure_subject_departments($db);
    $stmt = $db->prepare('SELECT COUNT(*) AS members, SUM(teacher_id = :t) AS is_member FROM teacher_subjects WHERE subject_id = :s');
    $stmt->execute([':s' => $subjectId, ':t' => $teacherId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ((int)$row['members'] === 0 || (int)$row['is_member'] > 0) {
        return null;
    }
    $names = $db->prepare('SELECT (SELECT full_name FROM teachers WHERE id = :t) AS teacher, (SELECT subject_name FROM subjects_new WHERE id = :s) AS subject');
    $names->execute([':t' => $teacherId, ':s' => $subjectId]);
    $n = $names->fetch(PDO::FETCH_ASSOC);
    return "{$n['teacher']} is not in the {$n['subject']} department, add them under Timetable → Subject Departments, or choose a {$n['subject']} teacher";
}

/** Abbreviations used in teacher records → subject name. */
const TT_SUBJECT_ALIASES = [
    'math' => 'Mathematics', 'maths' => 'Mathematics', 'mtc' => 'Mathematics', 'mth' => 'Mathematics',
    'bio' => 'Biology', 'chem' => 'Chemistry', 'che' => 'Chemistry', 'phy' => 'Physics', 'phys' => 'Physics',
    'hist' => 'History', 'eng' => 'English', 'geog' => 'Geography', 'geo' => 'Geography', 'fart' => 'Fine Art', 'art' => 'Fine Art',
    'pe' => 'Physical Education', 'econ' => 'Economics', 'kisw' => 'Kiswahili', 'kis' => 'Kiswahili', 'td' => 'Technical Drawing',
    'gp' => 'General Paper', 'lug' => 'Luganda', 'lit' => 'Literature', 'agric' => 'AGRI', 'agriculture' => 'AGRI',
    'entrepreneurship' => 'ENT', 'computer' => 'ICT', 'computerstudies' => 'ICT', 'divinity' => 'DIVINITY',
    'submath' => 'Sub-Maths', 'submaths' => 'Sub-Maths', 'submathematics' => 'Sub-Maths',
];

/**
 * Adds teachers to subject departments from their HR records (subject,
 * second subject, department code). Only adds, never removes.
 */
function tt_sync_subject_departments(PDO $db, bool $dryRun = false): array
{
    tt_ensure_subject_departments($db);
    $byKey = [];
    foreach ($db->query('SELECT id, subject_name, subject_code FROM subjects_new')->fetchAll(PDO::FETCH_ASSOC) as $s) {
        $byKey[tt_key($s['subject_name'])] = (int)$s['id'];
        $byKey[tt_key($s['subject_code'])] = (int)$s['id'];
    }
    foreach (TT_SUBJECT_ALIASES as $alias => $name) {
        if (isset($byKey[tt_key($name)]) && !isset($byKey[$alias])) {
            $byKey[$alias] = $byKey[tt_key($name)];
        }
    }

    $add = [];
    $unmatched = [];
    $noSubject = [];
    foreach ($db->query('SELECT id, full_name, subject, second_subject, department FROM teachers WHERE is_active = 1 OR is_active IS NULL')->fetchAll(PDO::FETCH_ASSOC) as $t) {
        $found = false;
        foreach (['subject', 'second_subject', 'department'] as $field) {
            foreach (preg_split('/[,;\/&]+|\band\b/i', (string)$t[$field]) as $part) {
                $k = tt_key($part);
                if ($k === '') {
                    continue;
                }
                if (isset($byKey[$k])) {
                    $add["{$t['id']}|{$byKey[$k]}"] = [(int)$t['id'], $byKey[$k]];
                    $found = true;
                } elseif ($field !== 'department') {
                    $unmatched[] = "{$t['full_name']}: “" . trim($part) . '”';
                }
            }
        }
        if (!$found) {
            $noSubject[] = $t['full_name'];
        }
    }

    $added = 0;
    if (!$dryRun && $add) {
        $ins = $db->prepare('INSERT IGNORE INTO teacher_subjects (teacher_id, subject_id) VALUES (:t, :s)');
        foreach ($add as [$tid, $sid]) {
            $ins->execute([':t' => $tid, ':s' => $sid]);
            $added += $ins->rowCount();
        }
    }
    return ['added' => $added, 'links_found' => count($add), 'unmatched' => array_values(array_unique($unmatched)), 'teachers_without_subject' => $noSubject];
}

/* ------------------------------------------------------------------ */
/* Lesson requirements import (template upload)                        */
/* ------------------------------------------------------------------ */

/** Lower-case, letters and digits only: "S.1  a" and "S1A" compare equal. */
function tt_key(?string $s): string
{
    return preg_replace('/[^a-z0-9]/', '', strtolower(trim((string)$s)));
}

/** Template headers in any wording ("Periods per week", "periods_per_week") map to one field name. */
function tt_import_field(string $header): ?string
{
    $aliases = [
        'class' => ['class', 'classstream', 'classandstream', 'fullclassname'],
        'stream' => ['stream'],
        'subject' => ['subject', 'subjectname', 'subjectcode'],
        'teacher' => ['teacher', 'teachername', 'teachercode'],
        'periods_per_week' => ['periodsperweek', 'periods', 'periodsweek', 'lessonsperweek'],
        'lesson_length' => ['lessonlength', 'preferredspan', 'length', 'span'],
        'double_lesson_allowed' => ['doubleallowed', 'doublelessonallowed', 'doublesallowed'],
        'double_lesson_required' => ['doublerequired', 'doublelessonrequired', 'doublesrequired'],
        'room' => ['room', 'roomcode', 'roomname'],
        'preferred_days' => ['preferreddays'],
        'avoid_days' => ['avoiddays'],
        'notes' => ['notes', 'note', 'comments'],
    ];
    $k = tt_key($header);
    foreach ($aliases as $field => $names) {
        if (in_array($k, $names, true)) {
            return $field;
        }
    }
    return null;
}

function tt_parse_bool($v): ?bool
{
    $k = tt_key(is_bool($v) ? ($v ? 'yes' : 'no') : (string)$v);
    if (in_array($k, ['', 'no', 'n', 'false', '0', 'none'], true)) {
        return false;
    }
    if (in_array($k, ['yes', 'y', 'true', '1', 'x'], true)) {
        return true;
    }
    return null;
}

/** CSV text (with or without BOM / Windows line endings) -> list of rows keyed by header. */
function tt_csv_rows(string $csv): array
{
    $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv);
    $lines = preg_split('/\r\n|\n|\r/', $csv);
    $headers = str_getcsv((string)array_shift($lines));
    $rows = [];
    foreach ($lines as $line) {
        if (trim($line) === '') {
            continue;
        }
        $values = str_getcsv($line);
        $row = [];
        foreach ($headers as $i => $h) {
            $row[$h] = $values[$i] ?? '';
        }
        $rows[] = $row;
    }
    return $rows;
}

/**
 * Validates and (unless $dryRun) saves lesson requirements from template rows.
 * Each row: class, subject, teacher, periods per week, lesson length, doubles, room, days, notes.
 * Valid rows are saved; invalid ones are reported with their spreadsheet line number.
 * Existing requirements (same class + subject + teacher) are updated, not duplicated.
 * $replace removes this term's requirements that are not in the file.
 */
function tt_import_requirements(PDO $db, int $sessionId, array $rawRows, bool $dryRun, bool $replace, array $actor): array
{
    $stmt = $db->prepare('SELECT id FROM academic_sessions WHERE id = :id');
    $stmt->execute([':id' => $sessionId]);
    if (!$stmt->fetch()) {
        throw new TtException('Choose an academic session first', 400);
    }
    if (count($rawRows) > 3000) {
        throw new TtException('The file has more than 3000 rows; split it into smaller files.', 422);
    }

    $index = static function (array $rows, array $keyFields): array {
        $map = [];
        foreach ($rows as $r) {
            foreach ($keyFields as $f) {
                $k = is_callable($f) ? $f($r) : tt_key($r[$f] ?? '');
                if ($k !== '') {
                    $map[$k][$r['id']] = $r;
                }
            }
        }
        return $map;
    };
    $classes = $db->query('SELECT id, class_name, stream_name, full_class_name FROM classes')->fetchAll(PDO::FETCH_ASSOC);
    $classMap = $index($classes, ['full_class_name', fn($c) => tt_key($c['class_name'] . $c['stream_name'])]);
    $classByLevel = [];
    foreach ($classes as $c) {
        $classByLevel[tt_key($c['class_name'])][] = $c['full_class_name'];
    }
    $subjectMap = $index($db->query('SELECT id, subject_name, subject_code FROM subjects_new')->fetchAll(PDO::FETCH_ASSOC), ['subject_name', 'subject_code']);
    $teacherMap = $index($db->query('SELECT id, full_name, teacher_code, is_active FROM teachers')->fetchAll(PDO::FETCH_ASSOC), ['full_name', 'teacher_code']);
    $roomMap = $index($db->query('SELECT id, room_code, room_name FROM rooms')->fetchAll(PDO::FETCH_ASSOC), ['room_code', 'room_name']);
    $lessonSlots = count(array_filter(tt_load_periods($db), fn($p) => $p['period_type'] === 'lesson')) * count(TT_DAYS);

    $pickOne = static function (array $map, string $value, string $what, ?callable $describe = null): array {
        $hits = $map[tt_key($value)] ?? [];
        if (!$hits) {
            return [null, "$what “{$value}” was not found"];
        }
        if (count($hits) > 1) {
            $names = implode(', ', array_map($describe ?? fn($r) => (string)reset($r), array_slice(array_values($hits), 0, 4)));
            return [null, "$what “{$value}” matches more than one record ($names), use the code instead"];
        }
        return [reset($hits), null];
    };

    $valid = [];
    $errors = [];
    $seen = [];
    $classLoad = [];
    $teacherLoad = [];
    foreach (array_values($rawRows) as $i => $raw) {
        $line = $i + 2; // row 1 is the header
        $row = [];
        foreach ((array)$raw as $header => $value) {
            $field = tt_import_field((string)$header);
            if ($field) {
                $row[$field] = is_string($value) ? trim($value) : $value;
            }
        }
        if (implode('', array_map('strval', $row)) === '') {
            continue; // blank line
        }
        $problems = [];

        $classText = trim(($row['class'] ?? '') . (!empty($row['stream']) ? ' ' . $row['stream'] : ''));
        $class = null;
        if ($classText === '') {
            $problems[] = 'Class is empty';
        } else {
            [$class, $err] = $pickOne($classMap, $classText, 'Class');
            if (!$class && isset($classByLevel[tt_key($classText)])) {
                $err = "“{$classText}” has several streams (" . implode(', ', array_slice($classByLevel[tt_key($classText)], 0, 6)) . '), write the stream too, e.g. ' . $classByLevel[tt_key($classText)][0];
            }
            if ($err) {
                $problems[] = $err;
            }
        }
        [$subject, $err] = ($row['subject'] ?? '') === '' ? [null, 'Subject is empty'] : $pickOne($subjectMap, (string)$row['subject'], 'Subject');
        if ($err) {
            $problems[] = $err;
        }
        [$teacher, $err] = ($row['teacher'] ?? '') === '' ? [null, 'Teacher is empty']
            : $pickOne($teacherMap, (string)$row['teacher'], 'Teacher', fn($t) => "{$t['full_name']} ({$t['teacher_code']})");
        if ($err) {
            $problems[] = $err;
        } elseif ($teacher && isset($teacher['is_active']) && (int)$teacher['is_active'] === 0) {
            $problems[] = "{$teacher['full_name']} is inactive";
        }
        $room = null;
        if (($row['room'] ?? '') !== '') {
            [$room, $err] = $pickOne($roomMap, (string)$row['room'], 'Room');
            if ($err) {
                $problems[] = $err;
            }
        }

        $periods = $row['periods_per_week'] ?? '';
        if (!is_numeric($periods) || (int)$periods < 1 || (int)$periods > 20 || (float)$periods != (int)$periods) {
            $problems[] = 'Periods per week must be a whole number from 1 to 20';
        }
        $allowed = tt_parse_bool($row['double_lesson_allowed'] ?? '');
        $required = tt_parse_bool($row['double_lesson_required'] ?? '');
        if ($allowed === null) {
            $problems[] = 'Double allowed must be Yes or No';
        }
        if ($required === null) {
            $problems[] = 'Double required must be Yes or No';
        }
        $lengthText = tt_key((string)($row['lesson_length'] ?? ''));
        $lengths = ['' => null, 'single' => 1, '1' => 1, 'double' => 2, '2' => 2, 'triple' => 3, '3' => 3, 'quadruple' => 4, 'quad' => 4, '4' => 4];
        $span = array_key_exists($lengthText, $lengths) ? $lengths[$lengthText] : -1; // blank = no preference
        if ($span === -1) {
            $problems[] = 'Lesson length must be Single, Double, Triple or Quadruple';
        }
        if ($required && $span === null) {
            $span = 2;
        }
        if ($class && $span) {
            $level = tt_class_level((string)$class['class_name']);
            if (!isset(tt_span_options($level)[$span])) {
                $problems[] = "{$class['full_class_name']} is $level, so lessons can be at most " . (count(tt_span_options($level)) === 2 ? 'Double' : 'Quadruple');
            }
        }
        $days = [];
        foreach (['preferred_days', 'avoid_days'] as $f) {
            $days[$f] = [];
            foreach (array_filter(array_map('trim', preg_split('/[,;\/]+/', (string)($row[$f] ?? '')))) as $d) {
                $match = array_values(array_filter(TT_DAYS, fn($day) => str_starts_with(strtolower($day), strtolower(substr($d, 0, 3)))));
                if ($match) {
                    $days[$f][] = $match[0];
                } else {
                    $problems[] = ucfirst(str_replace('_', ' ', $f)) . " has an unknown day “{$d}”";
                }
            }
        }

        if ($subject && $teacher && ($deptError = tt_department_error($db, (int)$subject['id'], (int)$teacher['id']))) {
            $problems[] = $deptError;
        }
        if ($class && $subject && $teacher) {
            $dupKey = "{$class['id']}|{$subject['id']}|{$teacher['id']}";
            if (isset($seen[$dupKey])) {
                $problems[] = "Duplicate of line {$seen[$dupKey]} (same class, subject and teacher)";
            } else {
                $seen[$dupKey] = $line;
            }
        }

        if ($problems) {
            $errors[] = ['line' => $line, 'class' => $classText, 'subject' => (string)($row['subject'] ?? ''), 'messages' => $problems];
            continue;
        }
        $valid[] = [
            'line' => $line,
            'class_id' => (int)$class['id'], 'class' => $class['full_class_name'], 'stream' => $class['stream_name'] ?: null,
            'subject_id' => (int)$subject['id'], 'teacher_id' => (int)$teacher['id'], 'teacher' => $teacher['full_name'],
            'room_id' => $room ? (int)$room['id'] : null,
            'periods_per_week' => (int)$periods,
            'double_lesson_allowed' => ($allowed || $required || ($span ?? 1) > 1) ? 1 : 0,
            'double_lesson_required' => $required ? 1 : 0,
            'preferred_span' => $span,
            'preferred_days' => $days['preferred_days'] ? json_encode($days['preferred_days']) : null,
            'avoid_days' => $days['avoid_days'] ? json_encode($days['avoid_days']) : null,
            'notes' => (string)($row['notes'] ?? '') ?: null,
        ];
        $classLoad[$class['full_class_name']] = ($classLoad[$class['full_class_name']] ?? 0) + (int)$periods;
        $teacherLoad[$teacher['full_name']] = ($teacherLoad[$teacher['full_name']] ?? 0) + (int)$periods;
    }

    $warnings = [];
    foreach ($classLoad as $name => $n) {
        if ($n > $lessonSlots) {
            $warnings[] = "$name needs $n periods a week but the week only has $lessonSlots lesson periods, some lessons cannot be placed.";
        }
    }
    foreach ($teacherLoad as $name => $n) {
        if ($n > $lessonSlots) {
            $warnings[] = "$name would teach $n periods a week but the week only has $lessonSlots lesson periods.";
        }
    }

    $created = 0;
    $updated = 0;
    $removed = 0;
    if (!$dryRun && $valid) {
        $db->beginTransaction();
        try {
            $find = $db->prepare('SELECT id FROM lesson_requirements WHERE academic_session_id = :s AND class_id = :c AND subject_id = :sub AND teacher_id = :t LIMIT 1');
            $insert = $db->prepare('INSERT INTO lesson_requirements (academic_session_id, class_id, stream, subject_id, teacher_id, room_id,
                    periods_per_week, double_lesson_allowed, double_lesson_required, preferred_span, preferred_days, avoid_days, notes)
                VALUES (:s, :c, :stream, :sub, :t, :room, :ppw, :da, :dr, :span, :pd, :ad, :notes)');
            $update = $db->prepare('UPDATE lesson_requirements SET stream = :stream, room_id = :room, periods_per_week = :ppw,
                    double_lesson_allowed = :da, double_lesson_required = :dr, preferred_span = :span, preferred_days = :pd, avoid_days = :ad, notes = :notes
                WHERE id = :id');
            $keep = [];
            foreach ($valid as $v) {
                $find->execute([':s' => $sessionId, ':c' => $v['class_id'], ':sub' => $v['subject_id'], ':t' => $v['teacher_id']]);
                $existing = $find->fetchColumn();
                $values = [':stream' => $v['stream'], ':room' => $v['room_id'], ':ppw' => $v['periods_per_week'], ':da' => $v['double_lesson_allowed'],
                    ':dr' => $v['double_lesson_required'], ':span' => $v['preferred_span'], ':pd' => $v['preferred_days'], ':ad' => $v['avoid_days'], ':notes' => $v['notes']];
                if ($existing) {
                    $update->execute($values + [':id' => (int)$existing]);
                    $keep[] = (int)$existing;
                    $updated++;
                } else {
                    $insert->execute($values + [':s' => $sessionId, ':c' => $v['class_id'], ':sub' => $v['subject_id'], ':t' => $v['teacher_id']]);
                    $keep[] = (int)$db->lastInsertId();
                    $created++;
                }
            }
            if ($replace) {
                $stmt = $db->prepare('DELETE FROM lesson_requirements WHERE academic_session_id = :s AND id NOT IN (' . implode(',', $keep) . ')');
                $stmt->execute([':s' => $sessionId]);
                $removed = $stmt->rowCount();
            }
            tt_audit($db, $sessionId, null, 'import', "Imported lesson requirements: $created added, $updated updated" . ($removed ? ", $removed removed" : '')
                . (count($errors) ? ', ' . count($errors) . ' row(s) skipped' : ''), null, null, $actor);
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    return [
        'dry_run' => $dryRun,
        'total_rows' => count($valid) + count($errors),
        'valid' => count($valid),
        'created' => $created,
        'updated' => $updated,
        'removed' => $removed,
        'errors' => $errors,
        'warnings' => $warnings,
        'preview' => array_map(fn($v) => array_intersect_key($v, array_flip(['line', 'class', 'teacher', 'periods_per_week', 'preferred_span'])), array_slice($valid, 0, 8)),
        'lesson_slots_per_week' => $lessonSlots,
    ];
}

/** Everything the editor needs in one request. */
function tt_meta(PDO $db): array
{
    $classes = $db->query('SELECT id, class_name, stream_name, full_class_name FROM classes ORDER BY class_name, stream_name')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($classes as &$c) {
        $c['id'] = (int)$c['id'];
        $c['level'] = tt_class_level((string)$c['class_name']);
    }
    unset($c);
    $teachers = $db->query('SELECT id, full_name, teacher_code, subject, is_active FROM teachers ORDER BY full_name')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($teachers as &$t) {
        $t['id'] = (int)$t['id'];
        $t['is_active'] = $t['is_active'] === null ? 1 : (int)$t['is_active'];
    }
    unset($t);
    $subjects = $db->query('SELECT id, subject_name, subject_code FROM subjects_new ORDER BY subject_name')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($subjects as &$s) {
        $s['id'] = (int)$s['id'];
    }
    unset($s);
    $rooms = $db->query('SELECT id, room_code, room_name, room_type, capacity, is_active FROM rooms ORDER BY room_code')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rooms as &$r) {
        $r['id'] = (int)$r['id'];
        $r['is_active'] = (int)$r['is_active'];
    }
    unset($r);
    $sessions = $db->query('SELECT id, session_name, academic_year, term, is_active, start_date, end_date FROM academic_sessions ORDER BY academic_year DESC, term DESC')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($sessions as &$s) {
        $s['id'] = (int)$s['id'];
        $s['academic_year'] = (int)$s['academic_year'];
        $s['term'] = (int)$s['term'];
        $s['is_active'] = (int)$s['is_active'];
    }
    unset($s);

    return [
        'days' => TT_DAYS,
        'periods' => array_values(tt_load_periods($db)),
        'span_options' => ['O-Level' => tt_span_options('O-Level'), 'A-Level' => tt_span_options('A-Level')],
        'classes' => $classes,
        'teachers' => $teachers,
        'subjects' => $subjects,
        'rooms' => $rooms,
        'sessions' => $sessions,
        'subject_teachers' => (object)tt_subject_teachers($db),
        'home_rooms' => (object)tt_home_rooms($db),
    ];
}
