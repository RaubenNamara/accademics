<?php
declare(strict_types=1);

require_once '../config/Database.php';
require_once '../config/HrHelpers.php';
require_once '../config/TimetableService.php';

hr_cors_headers();

$db = (new Database())->getConnection();
require_once __DIR__ . '/../config/TermLock.php';
term_guard($db, 'lesson_requirements', ['session' => 'academic_session_id']); // a closed term is read-only
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        $action = $_GET['action'] ?? '';
        
        if ($action === 'by-session') {
            getBySession($db);
        } else {
            getRequirements($db);
        }
    }
    
    $actor = null;
    if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
        // Same people who may edit the timetable may set what it must contain.
        $actor = tt_actor(hr_require_auth(TT_EDITOR_ROLES));
    }
    
    if ($method === 'POST') {
        $data = hr_request_data();
        $action = $_GET['action'] ?? '';
        
        if ($action === 'bulk') {
            bulkCreate($db, $data);
        } elseif ($action === 'import') {
            // Template upload: `rows` from an Excel/CSV file, or legacy pasted `csv_data`.
            $rows = is_array($data['rows'] ?? null) ? $data['rows'] : tt_csv_rows((string)($data['csv_data'] ?? ''));
            $result = tt_import_requirements($db, (int)($data['academic_session_id'] ?? 0), $rows, !empty($data['dry_run']), !empty($data['replace']), $actor);
            $msg = $result['dry_run'] ? "{$result['valid']} of {$result['total_rows']} row(s) are ready to import" : "Imported: {$result['created']} added, {$result['updated']} updated";
            hr_respond(true, $msg, $result);
        } else {
            createRequirement($db, $data);
        }
    }
    
    if ($method === 'PUT') {
        $data = hr_request_data();
        updateRequirement($db, $data);
    }
    
    if ($method === 'DELETE') {
        $id = (int)($_GET['id'] ?? hr_request_data()['id'] ?? 0);
        deleteRequirement($db, $id);
    }
    
    hr_respond(false, 'Method not allowed', null, 405);
} catch (TtException $e) {
    hr_respond(false, $e->getMessage(), $e->data, $e->status);
} catch (Throwable $e) {
    hr_respond(false, $e->getMessage(), null, 500);
}

function getRequirements(PDO $db): void
{
    $session_id = (int)($_GET['academic_session_id'] ?? 0);
    $class_id = (int)($_GET['class_id'] ?? 0);
    
    $sql = "
        SELECT 
            lr.*,
            c.class_name,
            c.stream_name,
            c.full_class_name,
            s.subject_name,
            s.subject_code,
            t.full_name AS teacher_name,
            t.teacher_code,
            r.room_code,
            r.room_name
        FROM lesson_requirements lr
        LEFT JOIN classes c ON lr.class_id = c.id
        LEFT JOIN subjects_new s ON lr.subject_id = s.id
        LEFT JOIN teachers t ON lr.teacher_id = t.id
        LEFT JOIN rooms r ON lr.room_id = r.id
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($session_id > 0) {
        $sql .= ' AND lr.academic_session_id = :session_id';
        $params[':session_id'] = $session_id;
    }
    
    if ($class_id > 0) {
        $sql .= ' AND lr.class_id = :class_id';
        $params[':class_id'] = $class_id;
    }
    
    $sql .= ' ORDER BY c.class_name, s.subject_name';
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $requirements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    hr_respond(true, 'Lesson requirements loaded', $requirements);
}

function getBySession(PDO $db): void
{
    $session_id = (int)($_GET['academic_session_id'] ?? 0);
    
    if ($session_id <= 0) {
        hr_respond(false, 'Academic session ID is required', null, 400);
    }
    
    $sql = "
        SELECT 
            lr.*,
            c.class_name,
            c.stream_name,
            c.full_class_name,
            s.subject_name,
            s.subject_code,
            t.full_name AS teacher_name,
            t.teacher_code,
            r.room_code,
            r.room_name
        FROM lesson_requirements lr
        LEFT JOIN classes c ON lr.class_id = c.id
        LEFT JOIN subjects_new s ON lr.subject_id = s.id
        LEFT JOIN teachers t ON lr.teacher_id = t.id
        LEFT JOIN rooms r ON lr.room_id = r.id
        WHERE lr.academic_session_id = :session_id
        ORDER BY c.class_name, c.stream_name, s.subject_name
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([':session_id' => $session_id]);
    $requirements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    hr_respond(true, 'Lesson requirements loaded', $requirements);
}

/** JSON list columns accept an array or an already-encoded JSON string (as sent back by the edit form). */
function lr_json_list($value): ?string
{
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        $value = is_array($decoded) ? $decoded : array_filter(array_map('trim', explode(',', $value)));
    }
    return is_array($value) && $value ? json_encode(array_values($value)) : null;
}

/**
 * Validates a requirement and returns the column values. The stream always
 * comes from the chosen class (classes.stream_name), never from free text.
 */
function lr_values(PDO $db, array $data, ?int $excludeId = null): array
{
    $sessionId = (int)($data['academic_session_id'] ?? 0);
    $classId = (int)($data['class_id'] ?? 0);
    $subjectId = (int)($data['subject_id'] ?? 0);
    $teacherId = (int)($data['teacher_id'] ?? 0);
    if ($sessionId <= 0 || $classId <= 0 || $subjectId <= 0 || $teacherId <= 0) {
        hr_respond(false, 'Choose a class, subject and teacher', null, 422);
    }

    $stmt = $db->prepare('SELECT class_name, stream_name, full_class_name FROM classes WHERE id = :id');
    $stmt->execute([':id' => $classId]);
    $class = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$class) {
        hr_respond(false, 'That class no longer exists', null, 422);
    }

    $deptError = tt_department_error($db, $subjectId, $teacherId);
    if ($deptError) {
        hr_respond(false, $deptError, ['errors' => ['teacher_id' => $deptError]], 422);
    }

    $periods = (int)($data['periods_per_week'] ?? 0);
    if ($periods < 1 || $periods > 20) {
        hr_respond(false, 'Periods per week must be between 1 and 20', null, 422);
    }
    $span = !empty($data['preferred_span']) ? (int)$data['preferred_span'] : null;
    $level = tt_class_level((string)$class['class_name']);
    if ($span !== null && !isset(tt_span_options($level)[$span])) {
        hr_respond(false, "{$class['full_class_name']} is $level, so that lesson length is not allowed", null, 422);
    }

    // One requirement per class + subject + teacher in a term.
    $sql = 'SELECT id FROM lesson_requirements WHERE academic_session_id = :s AND class_id = :c AND subject_id = :sub AND teacher_id = :t';
    $params = [':s' => $sessionId, ':c' => $classId, ':sub' => $subjectId, ':t' => $teacherId];
    if ($excludeId) {
        $sql .= ' AND id <> :id';
        $params[':id'] = $excludeId;
    }
    $check = $db->prepare($sql);
    $check->execute($params);
    if ($check->fetch()) {
        hr_respond(false, "{$class['full_class_name']} already has this subject with this teacher, edit that requirement instead", null, 409);
    }

    return [
        ':academic_session_id' => $sessionId,
        ':class_id' => $classId,
        ':stream' => $class['stream_name'] ?: null,
        ':subject_id' => $subjectId,
        ':teacher_id' => $teacherId,
        ':room_id' => !empty($data['room_id']) ? (int)$data['room_id'] : null,
        ':periods_per_week' => $periods,
        ':double_lesson_allowed' => (int)(!empty($data['double_lesson_allowed']) || !empty($data['double_lesson_required']) || ($span ?? 1) > 1),
        ':double_lesson_required' => (int)!empty($data['double_lesson_required']),
        ':preferred_span' => $span,
        ':preferred_days' => lr_json_list($data['preferred_days'] ?? null),
        ':preferred_periods' => lr_json_list($data['preferred_periods'] ?? null),
        ':avoid_days' => lr_json_list($data['avoid_days'] ?? null),
        ':avoid_periods' => lr_json_list($data['avoid_periods'] ?? null),
        ':notes' => trim((string)($data['notes'] ?? '')) ?: null,
    ];
}

function createRequirement(PDO $db, array $data): void
{
    $values = lr_values($db, $data);
    $db->prepare('
        INSERT INTO lesson_requirements (
            academic_session_id, class_id, stream, subject_id, teacher_id, room_id,
            periods_per_week, double_lesson_allowed, double_lesson_required, preferred_span,
            preferred_days, preferred_periods, avoid_days, avoid_periods, notes
        ) VALUES (
            :academic_session_id, :class_id, :stream, :subject_id, :teacher_id, :room_id,
            :periods_per_week, :double_lesson_allowed, :double_lesson_required, :preferred_span,
            :preferred_days, :preferred_periods, :avoid_days, :avoid_periods, :notes
        )')->execute($values);

    hr_respond(true, 'Lesson requirement created', ['id' => (int)$db->lastInsertId()], 201);
}

function updateRequirement(PDO $db, array $data): void
{
    $id = (int)($data['id'] ?? 0);
    if ($id <= 0) {
        hr_respond(false, 'Requirement ID is required', null, 400);
    }
    $stmt = $db->prepare('SELECT academic_session_id FROM lesson_requirements WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $sessionId = $stmt->fetchColumn();
    if (!$sessionId) {
        hr_respond(false, 'This requirement no longer exists', null, 404);
    }

    $values = lr_values($db, ['academic_session_id' => $sessionId] + $data, $id);
    unset($values[':academic_session_id']);
    $db->prepare('
        UPDATE lesson_requirements SET
            class_id = :class_id, stream = :stream, subject_id = :subject_id, teacher_id = :teacher_id,
            room_id = :room_id, periods_per_week = :periods_per_week,
            double_lesson_allowed = :double_lesson_allowed, double_lesson_required = :double_lesson_required,
            preferred_span = :preferred_span, preferred_days = :preferred_days, preferred_periods = :preferred_periods,
            avoid_days = :avoid_days, avoid_periods = :avoid_periods, notes = :notes, updated_at = NOW()
        WHERE id = :id')->execute($values + [':id' => $id]);

    hr_respond(true, 'Lesson requirement updated');
}

function deleteRequirement(PDO $db, int $id): void
{
    if ($id <= 0) {
        hr_respond(false, 'Requirement ID is required', null, 400);
    }
    
    $db->prepare("DELETE FROM lesson_requirements WHERE id = :id")->execute([':id' => $id]);
    
    hr_respond(true, 'Lesson requirement deleted');
}

function bulkCreate(PDO $db, array $data): void
{
    $academic_session_id = (int)($data['academic_session_id'] ?? 0);
    $requirements = $data['requirements'] ?? [];
    
    if ($academic_session_id <= 0 || !is_array($requirements)) {
        hr_respond(false, 'Session ID and requirements array are required', null, 400);
    }
    
    $db->beginTransaction();
    
    try {
        $insert = $db->prepare("
            INSERT INTO lesson_requirements (
                academic_session_id, class_id, stream, subject_id, teacher_id, room_id,
                periods_per_week, double_lesson_allowed, double_lesson_required, preferred_span,
                preferred_days, preferred_periods, avoid_days, avoid_periods, notes
            ) VALUES (
                :academic_session_id, :class_id, :stream, :subject_id, :teacher_id, :room_id,
                :periods_per_week, :double_lesson_allowed, :double_lesson_required, :preferred_span,
                :preferred_days, :preferred_periods, :avoid_days, :avoid_periods, :notes
            )
        ");

        $created = 0;
        $skipped = 0;

        foreach ($requirements as $req) {
            // Check for duplicate
            $check = $db->prepare("
                SELECT id FROM lesson_requirements 
                WHERE academic_session_id = :session_id AND class_id = :class_id 
                AND stream = :stream AND subject_id = :subject_id AND teacher_id = :teacher_id
            ");
            $check->execute([
                ':session_id' => $academic_session_id,
                ':class_id' => (int)$req['class_id'],
                ':stream' => $req['stream'] ?? null,
                ':subject_id' => (int)$req['subject_id'],
                ':teacher_id' => (int)$req['teacher_id']
            ]);
            
            if ($check->fetch()) {
                $skipped++;
                continue;
            }
            
            $insert->execute([
                ':academic_session_id' => $academic_session_id,
                ':class_id' => (int)$req['class_id'],
                ':stream' => $req['stream'] ?? null,
                ':subject_id' => (int)$req['subject_id'],
                ':teacher_id' => (int)$req['teacher_id'],
                ':room_id' => !empty($req['room_id']) ? (int)$req['room_id'] : null,
                ':periods_per_week' => (int)($req['periods_per_week'] ?? 1),
                ':double_lesson_allowed' => !empty($req['double_lesson_allowed']),
                ':double_lesson_required' => !empty($req['double_lesson_required']),
                ':preferred_span' => !empty($req['preferred_span']) ? (int)$req['preferred_span'] : null,
                ':preferred_days' => !empty($req['preferred_days']) ? json_encode($req['preferred_days']) : null,
                ':preferred_periods' => !empty($req['preferred_periods']) ? json_encode($req['preferred_periods']) : null,
                ':avoid_days' => !empty($req['avoid_days']) ? json_encode($req['avoid_days']) : null,
                ':avoid_periods' => !empty($req['avoid_periods']) ? json_encode($req['avoid_periods']) : null,
                ':notes' => $req['notes'] ?? null
            ]);
            
            $created++;
        }
        
        $db->commit();
        
        hr_respond(true, "Created $created requirements, skipped $skipped duplicates", [
            'created' => $created,
            'skipped' => $skipped
        ]);
    } catch (Exception $e) {
        $db->rollBack();
        hr_respond(false, 'Bulk create failed: ' . $e->getMessage(), null, 500);
    }
}
