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
                getParents($db);
            } elseif ($action === 'view') {
                getParent($db);
            }
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            if ($action === 'create') {
                createParent($db, $data);
            }
            break;
            
        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            if ($action === 'update') {
                updateParent($db, $data);
            }
            break;
            
        case 'DELETE':
            if ($action === 'delete') {
                deleteParent($db);
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

function getParents($db) {
    $studentId = $_GET['student_id'] ?? 0;
    
    if (!$studentId) {
        throw new Exception('Student ID is required');
    }
    
    $query = "SELECT * FROM parents WHERE student_id = ? ORDER BY is_primary_contact DESC, full_name ASC";
    $stmt = $db->prepare($query);
    $stmt->execute([$studentId]);
    $parents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $parents
    ]);
}

function getParent($db) {
    $id = $_GET['id'] ?? 0;
    
    if (!$id) {
        throw new Exception('Parent ID is required');
    }
    
    $query = "SELECT p.*, s.full_name as student_name, s.admission_number 
              FROM parents p 
              JOIN students s ON p.student_id = s.id 
              WHERE p.id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$id]);
    $parent = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$parent) {
        throw new Exception('Parent not found');
    }
    
    echo json_encode([
        'success' => true,
        'data' => $parent
    ]);
}

function createParent($db, $data) {
    $required = ['student_id', 'full_name', 'phone'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            throw new Exception("Field '$field' is required");
        }
    }
    
    // Check if student exists
    $checkQuery = "SELECT id FROM students WHERE id = ?";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->execute([$data['student_id']]);
    if (!$checkStmt->fetch()) {
        throw new Exception('Student not found');
    }
    
    // If setting as primary contact, remove primary status from other parents
    if (!empty($data['is_primary_contact']) && $data['is_primary_contact'] == true) {
        $updateQuery = "UPDATE parents SET is_primary_contact = FALSE WHERE student_id = ?";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->execute([$data['student_id']]);
    }
    
    $query = "INSERT INTO parents (student_id, full_name, relationship, phone, email, address, is_primary_contact) 
              VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        $data['student_id'],
        $data['full_name'],
        $data['relationship'] ?? 'guardian',
        $data['phone'],
        $data['email'] ?? '',
        $data['address'] ?? '',
        $data['is_primary_contact'] ?? false
    ]);
    
    $parentId = $db->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Parent added successfully',
        'data' => ['id' => $parentId]
    ]);
}

function updateParent($db, $data) {
    $id = $data['id'] ?? 0;
    
    if (!$id) {
        throw new Exception('Parent ID is required');
    }
    
    // Check if parent exists
    $checkQuery = "SELECT student_id FROM parents WHERE id = ?";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->execute([$id]);
    $parent = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$parent) {
        throw new Exception('Parent not found');
    }
    
    // If setting as primary contact, remove primary status from other parents of same student
    if (!empty($data['is_primary_contact']) && $data['is_primary_contact'] == true) {
        $updateQuery = "UPDATE parents SET is_primary_contact = FALSE WHERE student_id = ? AND id != ?";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->execute([$parent['student_id'], $id]);
    }
    
    $query = "UPDATE parents SET 
              full_name = ?,
              relationship = ?,
              phone = ?,
              email = ?,
              address = ?,
              is_primary_contact = ?
              WHERE id = ?";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        $data['full_name'],
        $data['relationship'] ?? 'guardian',
        $data['phone'],
        $data['email'] ?? '',
        $data['address'] ?? '',
        $data['is_primary_contact'] ?? false,
        $id
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Parent updated successfully'
    ]);
}

function deleteParent($db) {
    $id = $_GET['id'] ?? 0;
    
    if (!$id) {
        throw new Exception('Parent ID is required');
    }
    
    $query = "DELETE FROM parents WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$id]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('Parent not found');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Parent deleted successfully'
    ]);
}
