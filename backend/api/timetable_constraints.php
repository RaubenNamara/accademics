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
            getConstraints($db);
        }
    }
    
    if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
        hr_require_auth(['admin', 'academic_office']);
    }
    
    if ($method === 'POST') {
        $data = hr_request_data();
        createConstraint($db, $data);
    }
    
    if ($method === 'PUT') {
        $data = hr_request_data();
        updateConstraint($db, $data);
    }
    
    if ($method === 'DELETE') {
        $id = (int)($_GET['id'] ?? hr_request_data()['id'] ?? 0);
        deleteConstraint($db, $id);
    }
    
    hr_respond(false, 'Method not allowed', null, 405);
} catch (Throwable $e) {
    hr_respond(false, $e->getMessage(), null, 500);
}

function getConstraints(PDO $db): void
{
    $session_id = (int)($_GET['academic_session_id'] ?? 0);
    
    $sql = "
        SELECT 
            id,
            constraint_type,
            academic_session_id,
            constraint_value,
            is_active,
            created_at,
            updated_at
        FROM timetable_constraints
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($session_id > 0) {
        $sql .= ' AND academic_session_id = :session_id';
        $params[':session_id'] = $session_id;
    }
    
    $sql .= ' ORDER BY constraint_type';
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $constraints = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Parse JSON values
    foreach ($constraints as &$constraint) {
        if (!empty($constraint['constraint_value'])) {
            $constraint['constraint_value'] = json_decode($constraint['constraint_value'], true);
        }
    }
    
    hr_respond(true, 'Timetable constraints loaded', $constraints);
}

function getBySession(PDO $db): void
{
    $session_id = (int)($_GET['academic_session_id'] ?? 0);
    
    if ($session_id <= 0) {
        hr_respond(false, 'Academic session ID is required', null, 400);
    }
    
    $sql = "
        SELECT 
            id,
            constraint_type,
            academic_session_id,
            constraint_value,
            is_active,
            created_at,
            updated_at
        FROM timetable_constraints
        WHERE academic_session_id = :session_id
        ORDER BY constraint_type
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([':session_id' => $session_id]);
    $constraints = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Parse JSON values
    foreach ($constraints as &$constraint) {
        if (!empty($constraint['constraint_value'])) {
            $constraint['constraint_value'] = json_decode($constraint['constraint_value'], true);
        }
    }
    
    hr_respond(true, 'Timetable constraints loaded', $constraints);
}

function createConstraint(PDO $db, array $data): void
{
    $constraint_type = $data['constraint_type'] ?? '';
    $academic_session_id = (int)($data['academic_session_id'] ?? 0);
    
    if (!$constraint_type || $academic_session_id <= 0) {
        hr_respond(false, 'Constraint type and session ID are required', null, 400);
    }
    
    $valid_types = [
        'no_double_booking',
        'max_lessons_per_day',
        'min_free_periods',
        'double_lessons_allowed',
        'subject_sequencing',
        'class_balance',
        'room_restriction',
        'teacher_preference'
    ];
    
    if (!in_array($constraint_type, $valid_types, true)) {
        hr_respond(false, 'Invalid constraint type', null, 400);
    }
    
    $sql = "
        INSERT INTO timetable_constraints (
            constraint_type, academic_session_id, constraint_value, is_active
        ) VALUES (
            :constraint_type, :academic_session_id, :constraint_value, :is_active
        )
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':constraint_type' => $constraint_type,
        ':academic_session_id' => $academic_session_id,
        ':constraint_value' => !empty($data['constraint_value']) ? json_encode($data['constraint_value']) : null,
        ':is_active' => !empty($data['is_active'])
    ]);
    
    hr_respond(true, 'Constraint created', ['id' => (int)$db->lastInsertId()], 201);
}

function updateConstraint(PDO $db, array $data): void
{
    $id = (int)($data['id'] ?? 0);
    
    if ($id <= 0) {
        hr_respond(false, 'Constraint ID is required', null, 400);
    }
    
    $sql = "
        UPDATE timetable_constraints SET
            constraint_value = :constraint_value,
            is_active = :is_active,
            updated_at = NOW()
        WHERE id = :id
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':id' => $id,
        ':constraint_value' => !empty($data['constraint_value']) ? json_encode($data['constraint_value']) : null,
        ':is_active' => !empty($data['is_active'])
    ]);
    
    hr_respond(true, 'Constraint updated');
}

function deleteConstraint(PDO $db, int $id): void
{
    if ($id <= 0) {
        hr_respond(false, 'Constraint ID is required', null, 400);
    }
    
    $db->prepare("DELETE FROM timetable_constraints WHERE id = :id")->execute([':id' => $id]);
    
    hr_respond(true, 'Constraint deleted');
}
