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
            getClass($db);
        } elseif ($action === 'distinct-class-names') {
            getDistinctClassNames($db);
        } elseif ($action === 'streams-by-class') {
            getStreamsByClass($db);
        } else {
            getClasses($db);
        }
    } elseif ($method === 'POST') {
        if ($action === 'create') {
            createClass($db);
        } else {
            sendJson(400, ['success' => false, 'error' => 'Invalid action']);
        }
    } elseif ($method === 'PUT') {
        if ($action === 'update') {
            updateClass($db);
        } else {
            sendJson(400, ['success' => false, 'error' => 'Invalid action']);
        }
    } elseif ($method === 'DELETE') {
        if ($action === 'delete') {
            deleteClass($db);
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

function getClasses(PDO $db): void
{
    $class_name = trim((string)($_GET['class_name'] ?? ''));
    $stream_name = trim((string)($_GET['stream_name'] ?? ''));

    $conditions = [];
    $params = [];

    if ($class_name !== '') {
        $conditions[] = 'class_name = :class_name';
        $params[':class_name'] = $class_name;
    }

    if ($stream_name !== '') {
        $conditions[] = 'stream_name = :stream_name';
        $params[':stream_name'] = $stream_name;
    }

    $whereSql = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

    $sql = "
        SELECT
            id,
            class_name,
            stream_name,
            full_class_name,
            created_at,
            updated_at
        FROM classes
        $whereSql
        ORDER BY class_name ASC, stream_name ASC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    sendJson(200, [
        'success' => true,
        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

function getDistinctClassNames(PDO $db): void
{
    $sql = "
        SELECT DISTINCT class_name
        FROM classes
        ORDER BY class_name ASC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute();

    $classNames = $stmt->fetchAll(PDO::FETCH_COLUMN);

    sendJson(200, [
        'success' => true,
        'data' => $classNames
    ]);
}

function getStreamsByClass(PDO $db): void
{
    $class_name = trim((string)($_GET['class_name'] ?? ''));

    if ($class_name === '') {
        sendJson(400, ['success' => false, 'error' => 'Class name is required']);
    }

    $sql = "
        SELECT id, stream_name
        FROM classes
        WHERE class_name = :class_name
        ORDER BY stream_name ASC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute([':class_name' => $class_name]);

    $streams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendJson(200, [
        'success' => true,
        'data' => $streams
    ]);
}

function getClass(PDO $db): void
{
    $id = (int)($_GET['id'] ?? 0);

    if ($id <= 0) {
        sendJson(400, ['success' => false, 'error' => 'Class ID is required']);
    }

    $stmt = $db->prepare("SELECT * FROM classes WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $class = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$class) {
        sendJson(404, ['success' => false, 'error' => 'Class not found']);
    }

    // Get subjects for this class
    $subjectStmt = $db->prepare("
        SELECT s.*
        FROM subjects_new s
        INNER JOIN class_subjects cs ON s.id = cs.subject_id
        WHERE cs.class_id = :id
        ORDER BY s.subject_name ASC
    ");
    $subjectStmt->execute([':id' => $id]);
    $subjects = $subjectStmt->fetchAll(PDO::FETCH_ASSOC);

    $class['subjects'] = $subjects;

    sendJson(200, [
        'success' => true,
        'data' => $class
    ]);
}

function createClass(PDO $db): void
{
    $data = $_POST;

    $required = ['class_name', 'stream_name'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
            sendJson(400, ['success' => false, 'error' => "{$field} is required"]);
        }
    }

    $class_name = trim((string)$data['class_name']);
    $stream_name = trim((string)$data['stream_name']);
    $full_class_name = $class_name . ' ' . $stream_name;

    // Check if class already exists
    $check = $db->prepare("SELECT id FROM classes WHERE class_name = ? AND stream_name = ?");
    $check->execute([$class_name, $stream_name]);

    if ($check->fetch()) {
        sendJson(409, ['success' => false, 'error' => 'Class with this name and stream already exists']);
    }

    $query = "
        INSERT INTO classes (
            class_name,
            stream_name,
            full_class_name
        )
        VALUES (?, ?, ?)
    ";

    $stmt = $db->prepare($query);
    $ok = $stmt->execute([
        $class_name,
        $stream_name,
        $full_class_name
    ]);

    if (!$ok) {
        sendJson(500, [
            'success' => false,
            'error' => 'Failed to create class',
            'db_error' => $stmt->errorInfo()
        ]);
    }

    $classId = (int)$db->lastInsertId();

    sendJson(201, [
        'success' => true,
        'message' => 'Class created successfully',
        'class_id' => $classId
    ]);
}

function updateClass(PDO $db): void
{
    $data = json_decode(file_get_contents('php://input'), true);

    $id = (int)($data['id'] ?? 0);

    if ($id <= 0) {
        sendJson(400, ['success' => false, 'error' => 'Class ID is required']);
    }

    $required = ['class_name', 'stream_name'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
            sendJson(400, ['success' => false, 'error' => "{$field} is required"]);
        }
    }

    $class_name = trim((string)$data['class_name']);
    $stream_name = trim((string)$data['stream_name']);
    $full_class_name = $class_name . ' ' . $stream_name;

    // Check if class already exists (excluding current one)
    $dup = $db->prepare("SELECT id FROM classes WHERE class_name = ? AND stream_name = ? AND id != ?");
    $dup->execute([$class_name, $stream_name, $id]);

    if ($dup->fetch()) {
        sendJson(409, ['success' => false, 'error' => 'Class with this name and stream already exists']);
    }

    $query = "
        UPDATE classes SET
            class_name = ?,
            stream_name = ?,
            full_class_name = ?
        WHERE id = ?
    ";

    $stmt = $db->prepare($query);
    $ok = $stmt->execute([
        $class_name,
        $stream_name,
        $full_class_name,
        $id
    ]);

    if (!$ok) {
        sendJson(500, [
            'success' => false,
            'error' => 'Failed to update class',
            'db_error' => $stmt->errorInfo()
        ]);
    }

    sendJson(200, [
        'success' => true,
        'message' => 'Class updated successfully'
    ]);
}

function deleteClass(PDO $db): void
{
    $id = (int)($_GET['id'] ?? 0);

    if ($id <= 0) {
        sendJson(400, ['success' => false, 'error' => 'Class ID is required']);
    }

    // Check if class exists
    $check = $db->prepare("SELECT id FROM classes WHERE id = ?");
    $check->execute([$id]);

    if (!$check->fetch()) {
        sendJson(404, ['success' => false, 'error' => 'Class not found']);
    }

    $db->prepare("DELETE FROM class_subjects WHERE class_id = ?")->execute([$id]);
    $db->prepare("DELETE FROM classes WHERE id = ?")->execute([$id]);

    sendJson(200, [
        'success' => true,
        'message' => 'Class deleted successfully'
    ]);
}
