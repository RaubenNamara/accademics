<?php
declare(strict_types=1);

require_once '../config/Database.php';
require_once '../config/JWT.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function sendJson(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function getAuthorizationHeader(): string
{
    $headers = [];

    if (function_exists('getallheaders')) {
        $headers = getallheaders();
    }

    if (!empty($headers['Authorization'])) {
        return trim((string)$headers['Authorization']);
    }

    if (!empty($headers['authorization'])) {
        return trim((string)$headers['authorization']);
    }

    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        return trim((string)$_SERVER['HTTP_AUTHORIZATION']);
    }

    if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        return trim((string)$_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
    }

    return '';
}

function optionalAuth(): void
{
    $auth = getAuthorizationHeader();

    if ($auth === '') {
        return;
    }

    $token = preg_replace('/^Bearer\s+/i', '', $auth);
    $jwt = new JWT();
    $payload = $jwt->decode($token);

    if (!$payload) {
        sendJson(401, [
            'success' => false,
            'error' => 'Invalid or expired token'
        ]);
    }
}

try {
    $database = new Database();
    $db = $database->getConnection();

    optionalAuth();

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $action = $_GET['action'] ?? '';

    if ($method === 'GET') {
        if ($action === 'view') {
            getSubject($db);
        } else {
            getSubjects($db);
        }
    } elseif ($method === 'POST') {
        if ($action === 'create') {
            createSubject($db);
        } else {
            sendJson(400, ['success' => false, 'error' => 'Invalid action']);
        }
    } elseif ($method === 'PUT') {
        if ($action === 'update') {
            updateSubject($db);
        } else {
            sendJson(400, ['success' => false, 'error' => 'Invalid action']);
        }
    } elseif ($method === 'DELETE') {
        if ($action === 'delete') {
            deleteSubject($db);
        } else {
            sendJson(400, ['success' => false, 'error' => 'Invalid action']);
        }
    } else {
        sendJson(405, ['success' => false, 'error' => 'Method not allowed']);
    }
} catch (PDOException $e) {
    sendJson(500, [
        'success' => false,
        'error' => 'Database error',
        'database_error' => $e->getMessage()
    ]);
} catch (Throwable $e) {
    sendJson(500, [
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function getSubjects(PDO $db): void
{
    $sql = "
        SELECT
            id,
            subject_name,
            subject_code,
            created_at,
            updated_at
        FROM subjects_new
        ORDER BY subject_name ASC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute();

    sendJson(200, [
        'success' => true,
        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

function getSubject(PDO $db): void
{
    $id = (int)($_GET['id'] ?? 0);

    if ($id <= 0) {
        sendJson(400, ['success' => false, 'error' => 'Subject ID is required']);
    }

    $stmt = $db->prepare("SELECT * FROM subjects_new WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $subject = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$subject) {
        sendJson(404, ['success' => false, 'error' => 'Subject not found']);
    }

    sendJson(200, [
        'success' => true,
        'data' => $subject
    ]);
}

function createSubject(PDO $db): void
{
    $data = $_POST;

    $required = ['subject_name', 'subject_code'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
            sendJson(400, ['success' => false, 'error' => "{$field} is required"]);
        }
    }

    $subject_name = trim((string)$data['subject_name']);
    $subject_code = trim((string)$data['subject_code']);

    // Check if subject code already exists
    $check = $db->prepare("SELECT id FROM subjects_new WHERE subject_code = ?");
    $check->execute([$subject_code]);

    if ($check->fetch()) {
        sendJson(409, ['success' => false, 'error' => 'Subject code already exists']);
    }

    $query = "
        INSERT INTO subjects_new (
            subject_name,
            subject_code
        )
        VALUES (?, ?)
    ";

    $stmt = $db->prepare($query);
    $ok = $stmt->execute([
        $subject_name,
        $subject_code
    ]);

    if (!$ok) {
        sendJson(500, [
            'success' => false,
            'error' => 'Failed to create subject',
            'db_error' => $stmt->errorInfo()
        ]);
    }

    $subjectId = (int)$db->lastInsertId();

    sendJson(201, [
        'success' => true,
        'message' => 'Subject created successfully',
        'subject_id' => $subjectId
    ]);
}

function updateSubject(PDO $db): void
{
    $data = json_decode(file_get_contents('php://input'), true);

    $id = (int)($data['id'] ?? 0);

    if ($id <= 0) {
        sendJson(400, ['success' => false, 'error' => 'Subject ID is required']);
    }

    $required = ['subject_name', 'subject_code'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
            sendJson(400, ['success' => false, 'error' => "{$field} is required"]);
        }
    }

    $subject_name = trim((string)$data['subject_name']);
    $subject_code = trim((string)$data['subject_code']);

    // Check if subject code already exists (excluding current one)
    $dup = $db->prepare("SELECT id FROM subjects_new WHERE subject_code = ? AND id != ?");
    $dup->execute([$subject_code, $id]);

    if ($dup->fetch()) {
        sendJson(409, ['success' => false, 'error' => 'Subject code already exists']);
    }

    $query = "
        UPDATE subjects_new SET
            subject_name = ?,
            subject_code = ?
        WHERE id = ?
    ";

    $stmt = $db->prepare($query);
    $ok = $stmt->execute([
        $subject_name,
        $subject_code,
        $id
    ]);

    if (!$ok) {
        sendJson(500, [
            'success' => false,
            'error' => 'Failed to update subject',
            'db_error' => $stmt->errorInfo()
        ]);
    }

    sendJson(200, [
        'success' => true,
        'message' => 'Subject updated successfully'
    ]);
}

function deleteSubject(PDO $db): void
{
    $id = (int)($_GET['id'] ?? 0);

    if ($id <= 0) {
        sendJson(400, ['success' => false, 'error' => 'Subject ID is required']);
    }

    // Check if subject exists
    $check = $db->prepare("SELECT id FROM subjects_new WHERE id = ?");
    $check->execute([$id]);

    if (!$check->fetch()) {
        sendJson(404, ['success' => false, 'error' => 'Subject not found']);
    }

    // Delete from class_subjects first (due to foreign key constraint)
    $db->prepare("DELETE FROM class_subjects WHERE subject_id = ?")->execute([$id]);
    // Delete subject
    $db->prepare("DELETE FROM subjects_new WHERE id = ?")->execute([$id]);

    sendJson(200, [
        'success' => true,
        'message' => 'Subject deleted successfully'
    ]);
}
