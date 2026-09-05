<?php
declare(strict_types=1);

require_once '../config/Database.php';
require_once '../config/HrHelpers.php';

hr_cors_headers();

$db = (new Database())->getConnection();
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
    
    if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
        hr_require_auth(['admin', 'academic_office']);
    }
    
    if ($method === 'POST') {
        $data = hr_request_data();
        $action = $_GET['action'] ?? '';
        
        if ($action === 'bulk') {
            bulkCreate($db, $data);
        } elseif ($action === 'import') {
            importCSV($db, $data);
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
        ORDER BY c.class_name, s.subject_name
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([':session_id' => $session_id]);
    $requirements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    hr_respond(true, 'Lesson requirements loaded', $requirements);
}

function createRequirement(PDO $db, array $data): void
{
    $academic_session_id = (int)($data['academic_session_id'] ?? 0);
    $class_id = (int)($data['class_id'] ?? 0);
    $subject_id = (int)($data['subject_id'] ?? 0);
    $teacher_id = (int)($data['teacher_id'] ?? 0);
    
    if ($academic_session_id <= 0 || $class_id <= 0 || $subject_id <= 0 || $teacher_id <= 0) {
        hr_respond(false, 'Session, class, subject, and teacher are required', null, 400);
    }
    
    // Check for duplicate
    $check = $db->prepare("
        SELECT id FROM lesson_requirements 
        WHERE academic_session_id = :session_id AND class_id = :class_id 
        AND subject_id = :subject_id AND teacher_id = :teacher_id
    ");
    $check->execute([
        ':session_id' => $academic_session_id,
        ':class_id' => $class_id,
        ':subject_id' => $subject_id,
        ':teacher_id' => $teacher_id
    ]);
    
    if ($check->fetch()) {
        hr_respond(false, 'This lesson requirement already exists', null, 409);
    }
    
    $sql = "
        INSERT INTO lesson_requirements (
            academic_session_id, class_id, subject_id, teacher_id, room_id,
            periods_per_week, prefer_double_lessons, require_consecutive,
            specific_days, specific_periods, notes
        ) VALUES (
            :academic_session_id, :class_id, :subject_id, :teacher_id, :room_id,
            :periods_per_week, :prefer_double_lessons, :require_consecutive,
            :specific_days, :specific_periods, :notes
        )
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':academic_session_id' => $academic_session_id,
        ':class_id' => $class_id,
        ':subject_id' => $subject_id,
        ':teacher_id' => $teacher_id,
        ':room_id' => !empty($data['room_id']) ? (int)$data['room_id'] : null,
        ':periods_per_week' => (int)($data['periods_per_week'] ?? 1),
        ':prefer_double_lessons' => !empty($data['prefer_double_lessons']),
        ':require_consecutive' => !empty($data['require_consecutive']),
        ':specific_days' => !empty($data['specific_days']) ? json_encode($data['specific_days']) : null,
        ':specific_periods' => !empty($data['specific_periods']) ? json_encode($data['specific_periods']) : null,
        ':notes' => $data['notes'] ?? null
    ]);
    
    hr_respond(true, 'Lesson requirement created', ['id' => (int)$db->lastInsertId()], 201);
}

function updateRequirement(PDO $db, array $data): void
{
    $id = (int)($data['id'] ?? 0);
    
    if ($id <= 0) {
        hr_respond(false, 'Requirement ID is required', null, 400);
    }
    
    $sql = "
        UPDATE lesson_requirements SET
            room_id = :room_id,
            periods_per_week = :periods_per_week,
            prefer_double_lessons = :prefer_double_lessons,
            require_consecutive = :require_consecutive,
            specific_days = :specific_days,
            specific_periods = :specific_periods,
            notes = :notes,
            updated_at = NOW()
        WHERE id = :id
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':id' => $id,
        ':room_id' => !empty($data['room_id']) ? (int)$data['room_id'] : null,
        ':periods_per_week' => (int)($data['periods_per_week'] ?? 1),
        ':prefer_double_lessons' => !empty($data['prefer_double_lessons']),
        ':require_consecutive' => !empty($data['require_consecutive']),
        ':specific_days' => !empty($data['specific_days']) ? json_encode($data['specific_days']) : null,
        ':specific_periods' => !empty($data['specific_periods']) ? json_encode($data['specific_periods']) : null,
        ':notes' => $data['notes'] ?? null
    ]);
    
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
                periods_per_week, double_lesson_allowed, double_lesson_required,
                preferred_days, preferred_periods, avoid_days, avoid_periods, notes
            ) VALUES (
                :academic_session_id, :class_id, :stream, :subject_id, :teacher_id, :room_id,
                :periods_per_week, :double_lesson_allowed, :double_lesson_required,
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

function importCSV(PDO $db, array $data): void
{
    $academic_session_id = (int)($data['academic_session_id'] ?? 0);
    $csv_data = $data['csv_data'] ?? '';
    
    if ($academic_session_id <= 0 || !$csv_data) {
        hr_respond(false, 'Session ID and CSV data are required', null, 400);
    }
    
    $lines = explode("\n", $csv_data);
    $headers = str_getcsv(array_shift($lines));
    
    $db->beginTransaction();
    
    try {
        $insert = $db->prepare("
            INSERT INTO lesson_requirements (
                academic_session_id, class_id, stream, subject_id, teacher_id, room_id,
                periods_per_week, double_lesson_allowed, double_lesson_required,
                preferred_days, preferred_periods, avoid_days, avoid_periods, notes
            ) VALUES (
                :academic_session_id, :class_id, :stream, :subject_id, :teacher_id, :room_id,
                :periods_per_week, :double_lesson_allowed, :double_lesson_required,
                :preferred_days, :preferred_periods, :avoid_days, :avoid_periods, :notes
            )
        ");
        
        $created = 0;
        $errors = [];
        
        foreach ($lines as $line_num => $line) {
            if (empty(trim($line))) continue;
            
            $values = str_getcsv($line);
            $row = array_combine($headers, $values);
            
            // Map CSV columns to database fields
            $class_id = getClassIdByName($db, $row['class'] ?? '');
            $subject_id = getSubjectIdByName($db, $row['subject'] ?? '');
            $teacher_id = getTeacherIdByName($db, $row['teacher'] ?? '');
            
            if (!$class_id || !$subject_id || !$teacher_id) {
                $errors[] = "Line " . ($line_num + 2) . ": Missing class, subject, or teacher";
                continue;
            }
            
            $insert->execute([
                ':academic_session_id' => $academic_session_id,
                ':class_id' => $class_id,
                ':stream' => $row['stream'] ?? null,
                ':subject_id' => $subject_id,
                ':teacher_id' => $teacher_id,
                ':room_id' => getRoomIdByCode($db, $row['room'] ?? ''),
                ':periods_per_week' => (int)($row['periods_per_week'] ?? 1),
                ':double_lesson_allowed' => !empty($row['double_lesson_allowed']),
                ':double_lesson_required' => !empty($row['double_lesson_required']),
                ':preferred_days' => !empty($row['preferred_days']) ? json_encode(explode(',', $row['preferred_days'])) : null,
                ':preferred_periods' => !empty($row['preferred_periods']) ? json_encode(explode(',', $row['preferred_periods'])) : null,
                ':avoid_days' => !empty($row['avoid_days']) ? json_encode(explode(',', $row['avoid_days'])) : null,
                ':avoid_periods' => !empty($row['avoid_periods']) ? json_encode(explode(',', $row['avoid_periods'])) : null,
                ':notes' => $row['notes'] ?? null
            ]);
            
            $created++;
        }
        
        $db->commit();
        
        hr_respond(true, "Imported $created requirements", [
            'created' => $created,
            'errors' => $errors
        ]);
    } catch (Exception $e) {
        $db->rollBack();
        hr_respond(false, 'Import failed: ' . $e->getMessage(), null, 500);
    }
}

function getClassIdByName(PDO $db, string $name): ?int
{
    $stmt = $db->prepare("SELECT id FROM classes WHERE class_name = ?");
    $stmt->execute([$name]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? (int)$result['id'] : null;
}

function getSubjectIdByName(PDO $db, string $name): ?int
{
    $stmt = $db->prepare("SELECT id FROM subjects_new WHERE subject_name = ?");
    $stmt->execute([$name]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? (int)$result['id'] : null;
}

function getTeacherIdByName(PDO $db, string $name): ?int
{
    $stmt = $db->prepare("SELECT id FROM teachers WHERE full_name = ?");
    $stmt->execute([$name]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? (int)$result['id'] : null;
}

function getRoomIdByCode(PDO $db, string $code): ?int
{
    if (empty($code)) return null;
    $stmt = $db->prepare("SELECT id FROM rooms WHERE room_code = ?");
    $stmt->execute([$code]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? (int)$result['id'] : null;
}
