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
        
        if ($action === 'active') {
            getActiveSession($db);
        } else {
            getSessions($db);
        }
    }
    
    if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
        hr_require_auth(['admin', 'academic_office']);
    }
    
    if ($method === 'POST') {
        $data = hr_request_data();
        createSession($db, $data);
    }
    
    if ($method === 'PUT') {
        $data = hr_request_data();
        updateSession($db, $data);
    }
    
    if ($method === 'DELETE') {
        $id = (int)($_GET['id'] ?? hr_request_data()['id'] ?? 0);
        deleteSession($db, $id);
    }
    
    hr_respond(false, 'Method not allowed', null, 405);
} catch (Throwable $e) {
    hr_respond(false, $e->getMessage(), null, 500);
}

function getSessions(PDO $db): void
{
    $sql = "
        SELECT 
            id,
            session_name,
            academic_year,
            term,
            start_date,
            end_date,
            is_active,
            is_archived,
            created_at,
            updated_at
        FROM academic_sessions
        ORDER BY academic_year DESC, term DESC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    hr_respond(true, 'Academic sessions loaded', $sessions);
}

function getActiveSession(PDO $db): void
{
    $sql = "
        SELECT 
            id,
            session_name,
            academic_year,
            term,
            start_date,
            end_date,
            is_active,
            is_archived
        FROM academic_sessions
        WHERE is_active = TRUE AND is_archived = FALSE
        LIMIT 1
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
        hr_respond(false, 'No active session found', null, 404);
    }
    
    hr_respond(true, 'Active session loaded', $session);
}

function createSession(PDO $db, array $data): void
{
    $session_name = trim($data['session_name'] ?? '');
    $academic_year = (int)($data['academic_year'] ?? 0);
    $term = (int)($data['term'] ?? 1);
    $start_date = $data['start_date'] ?? '';
    $end_date = $data['end_date'] ?? '';
    
    if (!$session_name || $academic_year <= 0 || !$start_date || !$end_date) {
        hr_respond(false, 'Session name, academic year, start date, and end date are required', null, 400);
    }
    
    if (!in_array($term, [1, 2, 3], true)) {
        hr_respond(false, 'Term must be 1, 2, or 3', null, 400);
    }
    
    // Check for duplicate session
    $check = $db->prepare("
        SELECT id FROM academic_sessions 
        WHERE academic_year = :year AND term = :term
    ");
    $check->execute([':year' => $academic_year, ':term' => $term]);
    
    if ($check->fetch()) {
        hr_respond(false, 'Session for this academic year and term already exists', null, 409);
    }
    
    // If this is set as active, deactivate other active sessions
    if (!empty($data['is_active'])) {
        $db->prepare("UPDATE academic_sessions SET is_active = FALSE")->execute();
    }
    
    $sql = "
        INSERT INTO academic_sessions (
            session_name, academic_year, term, start_date, end_date, is_active, is_archived
        ) VALUES (
            :session_name, :academic_year, :term, :start_date, :end_date, :is_active, :is_archived
        )
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':session_name' => $session_name,
        ':academic_year' => $academic_year,
        ':term' => $term,
        ':start_date' => $start_date,
        ':end_date' => $end_date,
        ':is_active' => !empty($data['is_active']),
        ':is_archived' => !empty($data['is_archived'])
    ]);
    
    hr_respond(true, 'Academic session created', ['id' => (int)$db->lastInsertId()], 201);
}

function updateSession(PDO $db, array $data): void
{
    $id = (int)($data['id'] ?? 0);
    
    if ($id <= 0) {
        hr_respond(false, 'Session ID is required', null, 400);
    }
    
    // Check if session exists
    $check = $db->prepare("SELECT id FROM academic_sessions WHERE id = :id");
    $check->execute([':id' => $id]);
    
    if (!$check->fetch()) {
        hr_respond(false, 'Session not found', null, 404);
    }
    
    // If activating this session, deactivate others
    if (!empty($data['is_active'])) {
        $db->prepare("UPDATE academic_sessions SET is_active = FALSE WHERE id != :id")->execute([':id' => $id]);
    }
    
    $sql = "
        UPDATE academic_sessions SET
            session_name = :session_name,
            academic_year = :academic_year,
            term = :term,
            start_date = :start_date,
            end_date = :end_date,
            is_active = :is_active,
            is_archived = :is_archived,
            updated_at = NOW()
        WHERE id = :id
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':id' => $id,
        ':session_name' => trim($data['session_name'] ?? ''),
        ':academic_year' => (int)($data['academic_year'] ?? 0),
        ':term' => (int)($data['term'] ?? 1),
        ':start_date' => $data['start_date'] ?? '',
        ':end_date' => $data['end_date'] ?? '',
        ':is_active' => !empty($data['is_active']),
        ':is_archived' => !empty($data['is_archived'])
    ]);
    
    hr_respond(true, 'Academic session updated');
}

function deleteSession(PDO $db, int $id): void
{
    if ($id <= 0) {
        hr_respond(false, 'Session ID is required', null, 400);
    }
    
    // Check if session exists
    $check = $db->prepare("SELECT id FROM academic_sessions WHERE id = :id");
    $check->execute([':id' => $id]);
    
    if (!$check->fetch()) {
        hr_respond(false, 'Session not found', null, 404);
    }
    
    // Check if session has timetable data
    $checkTimetable = $db->prepare("SELECT COUNT(*) FROM timetable WHERE academic_session_id = :id");
    $checkTimetable->execute([':id' => $id]);
    $count = $checkTimetable->fetchColumn();
    
    if ($count > 0) {
        hr_respond(false, 'Cannot delete session with existing timetable data. Archive it instead.', null, 400);
    }
    
    $db->prepare("DELETE FROM academic_sessions WHERE id = :id")->execute([':id' => $id]);
    
    hr_respond(true, 'Academic session deleted');
}
