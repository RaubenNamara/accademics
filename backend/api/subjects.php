<?php
require_once '../config/Database.php';
require_once '../config/JWT.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $jwt = new JWT();
    $authHeader = getallheaders();
    
    if (!isset($authHeader['Authorization'])) {
        throw new Exception('Authorization header missing');
    }
    
    $token = str_replace('Bearer ', '', $authHeader['Authorization']);
    $payload = $jwt->decode($token);
    
    if (!$payload) {
        throw new Exception('Invalid or expired token');
    }
    
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    
    switch ($method) {
        case 'GET':
            if ($action === 'list') {
                getSubjects($db);
            } elseif ($action === 'view') {
                getSubject($db);
            }
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            if ($action === 'create') {
                createSubject($db, $data);
            }
            break;
            
        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            if ($action === 'update') {
                updateSubject($db, $data);
            }
            break;
            
        case 'DELETE':
            if ($action === 'delete') {
                deleteSubject($db);
            }
            break;
            
        default:
            throw new Exception('Method not allowed');
    }
    
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

function getSubjects($db) {
    $level = $_GET['level'] ?? '';
    
    $where = "WHERE is_active = TRUE";
    $params = [];
    
    if ($level) {
        $where .= " AND (level = ? OR level = 'BOTH')";
        $params[] = $level;
    }
    
    $query = "SELECT * FROM subjects $where ORDER BY subject_name ASC";
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $subjects
    ]);
}

function getSubject($db) {
    $id = $_GET['id'] ?? 0;
    
    if (!$id) {
        throw new Exception('Subject ID is required');
    }
    
    $query = "SELECT * FROM subjects WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$id]);
    $subject = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$subject) {
        throw new Exception('Subject not found');
    }
    
    echo json_encode([
        'success' => true,
        'data' => $subject
    ]);
}

function createSubject($db, $data) {
    $required = ['subject_code', 'subject_name', 'level'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            throw new Exception("Field '$field' is required");
        }
    }
    
    // Validate level
    if (!in_array($data['level'], ['O_LEVEL', 'A_LEVEL', 'BOTH'])) {
        throw new Exception('Invalid level. Must be O_LEVEL, A_LEVEL, or BOTH');
    }
    
    // Check if subject code already exists
    $checkQuery = "SELECT id FROM subjects WHERE subject_code = ?";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->execute([$data['subject_code']]);
    if ($checkStmt->fetch()) {
        throw new Exception('Subject code already exists');
    }
    
    $query = "INSERT INTO subjects (subject_code, subject_name, level, is_compulsory, is_active) 
              VALUES (?, ?, ?, ?, ?)";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        $data['subject_code'],
        $data['subject_name'],
        $data['level'],
        $data['is_compulsory'] ?? false,
        $data['is_active'] ?? true
    ]);
    
    $subjectId = $db->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Subject created successfully',
        'data' => ['id' => $subjectId]
    ]);
}

function updateSubject($db, $data) {
    $id = $data['id'] ?? 0;
    
    if (!$id) {
        throw new Exception('Subject ID is required');
    }
    
    // Check if subject exists
    $checkQuery = "SELECT id FROM subjects WHERE id = ?";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->execute([$id]);
    if (!$checkStmt->fetch()) {
        throw new Exception('Subject not found');
    }
    
    // Check if subject code is being changed and if it conflicts
    if (!empty($data['subject_code'])) {
        $codeCheck = "SELECT id FROM subjects WHERE subject_code = ? AND id != ?";
        $codeStmt = $db->prepare($codeCheck);
        $codeStmt->execute([$data['subject_code'], $id]);
        if ($codeStmt->fetch()) {
            throw new Exception('Subject code already exists');
        }
    }
    
    // Validate level if provided
    if (!empty($data['level']) && !in_array($data['level'], ['O_LEVEL', 'A_LEVEL', 'BOTH'])) {
        throw new Exception('Invalid level. Must be O_LEVEL, A_LEVEL, or BOTH');
    }
    
    $query = "UPDATE subjects SET 
              subject_code = ?,
              subject_name = ?,
              level = ?,
              is_compulsory = ?,
              is_active = ?
              WHERE id = ?";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        $data['subject_code'],
        $data['subject_name'],
        $data['level'],
        $data['is_compulsory'] ?? false,
        $data['is_active'] ?? true,
        $id
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Subject updated successfully'
    ]);
}

function deleteSubject($db) {
    $id = $_GET['id'] ?? 0;
    
    if (!$id) {
        throw new Exception('Subject ID is required');
    }
    
    // Soft delete
    $query = "UPDATE subjects SET is_active = FALSE WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$id]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('Subject not found');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Subject deleted successfully'
    ]);
}
