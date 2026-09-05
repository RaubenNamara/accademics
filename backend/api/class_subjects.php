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
            getClassSubjects($db);
        } elseif ($action === 'by-class') {
            getSubjectsByClass($db);
        } else {
            getAllClassSubjects($db);
        }
    } elseif ($method === 'POST') {
        if ($action === 'assign') {
            assignSubjectToClass($db);
        } elseif ($action === 'bulk-assign') {
            bulkAssignSubjectsToClass($db);
        } else {
            sendJson(400, ['success' => false, 'error' => 'Invalid action']);
        }
    } elseif ($method === 'DELETE') {
        if ($action === 'remove') {
            removeSubjectFromClass($db);
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

function getAllClassSubjects(PDO $db): void
{
    $sql = "
        SELECT
            cs.id,
            cs.class_id,
            cs.subject_id,
            cs.created_at,
            c.class_name,
            c.stream_name,
            c.full_class_name,
            s.subject_name,
            s.subject_code
        FROM class_subjects cs
        INNER JOIN classes c ON cs.class_id = c.id
        INNER JOIN subjects_new s ON cs.subject_id = s.id
        ORDER BY c.class_name ASC, c.stream_name ASC, s.subject_name ASC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute();

    sendJson(200, [
        'success' => true,
        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

function getClassSubjects(PDO $db): void
{
    $class_id = (int)($_GET['class_id'] ?? 0);

    if ($class_id <= 0) {
        sendJson(400, ['success' => false, 'error' => 'Class ID is required']);
    }

    $sql = "
        SELECT
            cs.id,
            cs.class_id,
            cs.subject_id,
            cs.created_at,
            c.class_name,
            c.stream_name,
            c.full_class_name,
            s.subject_name,
            s.subject_code
        FROM class_subjects cs
        INNER JOIN classes c ON cs.class_id = c.id
        INNER JOIN subjects_new s ON cs.subject_id = s.id
        WHERE cs.class_id = :class_id
        ORDER BY s.subject_name ASC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute([':class_id' => $class_id]);

    sendJson(200, [
        'success' => true,
        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

function getSubjectsByClass(PDO $db): void
{
    $class_id = (int)($_GET['class_id'] ?? 0);

    if ($class_id <= 0) {
        sendJson(400, ['success' => false, 'error' => 'Class ID is required']);
    }

    $sql = "
        SELECT
            s.id,
            s.subject_name,
            s.subject_code
        FROM subjects_new s
        INNER JOIN class_subjects cs ON s.id = cs.subject_id
        WHERE cs.class_id = :class_id
        ORDER BY s.subject_name ASC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute([':class_id' => $class_id]);

    sendJson(200, [
        'success' => true,
        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

function assignSubjectToClass(PDO $db): void
{
    $data = $_POST;

    $required = ['class_id', 'subject_id'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
            sendJson(400, ['success' => false, 'error' => "{$field} is required"]);
        }
    }

    $class_id = (int)$data['class_id'];
    $subject_id = (int)$data['subject_id'];

    // Check if class exists
    $checkClass = $db->prepare("SELECT id FROM classes WHERE id = ?");
    $checkClass->execute([$class_id]);
    if (!$checkClass->fetch()) {
        sendJson(404, ['success' => false, 'error' => 'Class not found']);
    }

    // Check if subject exists
    $checkSubject = $db->prepare("SELECT id FROM subjects_new WHERE id = ?");
    $checkSubject->execute([$subject_id]);
    if (!$checkSubject->fetch()) {
        sendJson(404, ['success' => false, 'error' => 'Subject not found']);
    }

    // Check if assignment already exists
    $checkAssignment = $db->prepare("SELECT id FROM class_subjects WHERE class_id = ? AND subject_id = ?");
    $checkAssignment->execute([$class_id, $subject_id]);
    if ($checkAssignment->fetch()) {
        sendJson(409, ['success' => false, 'error' => 'Subject already assigned to this class']);
    }

    $query = "
        INSERT INTO class_subjects (
            class_id,
            subject_id
        )
        VALUES (?, ?)
    ";

    $stmt = $db->prepare($query);
    $ok = $stmt->execute([
        $class_id,
        $subject_id
    ]);

    if (!$ok) {
        sendJson(500, [
            'success' => false,
            'error' => 'Failed to assign subject to class',
            'db_error' => $stmt->errorInfo()
        ]);
    }

    sendJson(201, [
        'success' => true,
        'message' => 'Subject assigned to class successfully'
    ]);
}

function bulkAssignSubjectsToClass(PDO $db): void
{
    $data = json_decode(file_get_contents('php://input'), true);

    $required = ['class_id', 'subject_ids'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || (is_array($data[$field]) && empty($data[$field]))) {
            sendJson(400, ['success' => false, 'error' => "{$field} is required"]);
        }
    }

    $class_id = (int)$data['class_id'];
    $subject_ids = $data['subject_ids'];

    if (!is_array($subject_ids) || empty($subject_ids)) {
        sendJson(400, ['success' => false, 'error' => 'subject_ids must be a non-empty array']);
    }

    // Check if class exists
    $checkClass = $db->prepare("SELECT id FROM classes WHERE id = ?");
    $checkClass->execute([$class_id]);
    if (!$checkClass->fetch()) {
        sendJson(404, ['success' => false, 'error' => 'Class not found']);
    }

    // Delete existing assignments for this class
    $db->prepare("DELETE FROM class_subjects WHERE class_id = ?")->execute([$class_id]);

    // Insert new assignments
    $query = "
        INSERT INTO class_subjects (
            class_id,
            subject_id
        )
        VALUES (?, ?)
    ";

    $stmt = $db->prepare($query);
    $successCount = 0;

    foreach ($subject_ids as $subject_id) {
        $subject_id = (int)$subject_id;
        
        // Check if subject exists
        $checkSubject = $db->prepare("SELECT id FROM subjects_new WHERE id = ?");
        $checkSubject->execute([$subject_id]);
        if ($checkSubject->fetch()) {
            $stmt->execute([$class_id, $subject_id]);
            $successCount++;
        }
    }

    sendJson(200, [
        'success' => true,
        'message' => "Successfully assigned {$successCount} subjects to class"
    ]);
}

function removeSubjectFromClass(PDO $db): void
{
    $class_id = (int)($_GET['class_id'] ?? 0);
    $subject_id = (int)($_GET['subject_id'] ?? 0);

    if ($class_id <= 0) {
        sendJson(400, ['success' => false, 'error' => 'Class ID is required']);
    }

    if ($subject_id <= 0) {
        sendJson(400, ['success' => false, 'error' => 'Subject ID is required']);
    }

    $stmt = $db->prepare("DELETE FROM class_subjects WHERE class_id = ? AND subject_id = ?");
    $stmt->execute([$class_id, $subject_id]);

    if ($stmt->rowCount() === 0) {
        sendJson(404, ['success' => false, 'error' => 'Assignment not found']);
    }

    sendJson(200, [
        'success' => true,
        'message' => 'Subject removed from class successfully'
    ]);
}
