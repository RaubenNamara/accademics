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
    
    // Temporarily disable authentication for debugging
    /*
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
    */
    
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    
    switch ($method) {
        case 'GET':
            if ($action === 'list') {
                getResults($db);
            } elseif ($action === 'view') {
                getResult($db);
            } elseif ($action === 'history') {
                getStudentHistory($db);
            }
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            if ($action === 'create') {
                createResult($db, $data);
            } elseif ($action === 'bulk-create') {
                bulkCreateResults($db, $data);
            }
            break;
            
        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            if ($action === 'update') {
                updateResult($db, $data);
            }
            break;
            
        case 'DELETE':
            if ($action === 'delete') {
                deleteResult($db);
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

function getResults($db) {
    $studentId = $_GET['student_id'] ?? 0;
    $year = $_GET['year'] ?? date('Y');
    $term = $_GET['term'] ?? '';
    $subjectId = $_GET['subject_id'] ?? '';
    $class = $_GET['class'] ?? '';
    $stream = $_GET['stream'] ?? '';
    
    $where = "WHERE sr.year = ?";
    $params = [$year];
    
    if ($studentId) {
        $where .= " AND sr.student_id = ?";
        $params[] = $studentId;
    }
    
    if ($class) {
        $where .= " AND (sr.class = ? OR s.class = ?)";
        $params[] = $class;
        $params[] = $class;
    }
    
    if ($stream) {
        $where .= " AND (sr.stream = ? OR s.stream = ?)";
        $params[] = $stream;
        $params[] = $stream;
    }
    
    if ($term) {
        $where .= " AND sr.term = ?";
        $params[] = $term;
    }
    
    if ($subjectId) {
        $where .= " AND sr.subject_id = ?";
        $params[] = $subjectId;
    }
    
    $query = "SELECT sr.*, s.full_name as student_name, s.admission_number, 
              COALESCE(sr.class, s.class) as class,
              COALESCE(sr.stream, s.stream) as stream,
              sub.subject_name, sub.subject_code 
              FROM student_results sr 
              JOIN students s ON sr.student_id = s.id 
              JOIN subjects sub ON sr.subject_id = sub.id 
              $where 
              ORDER BY sr.term DESC, sr.exam_type, sub.subject_name ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $results
    ]);
}

function getResult($db) {
    $id = $_GET['id'] ?? 0;
    
    if (!$id) {
        throw new Exception('Result ID is required');
    }
    
    $query = "SELECT sr.*, s.full_name as student_name, s.admission_number, s.class, s.stream,
              sub.subject_name, sub.subject_code 
              FROM student_results sr 
              JOIN students s ON sr.student_id = s.id 
              JOIN subjects sub ON sr.subject_id = sub.id 
              WHERE sr.id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        throw new Exception('Result not found');
    }
    
    echo json_encode([
        'success' => true,
        'data' => $result
    ]);
}

function getStudentHistory($db) {
    $studentId = $_GET['student_id'] ?? 0;
    
    if (!$studentId) {
        throw new Exception('Student ID is required');
    }
    
    // Check if student exists
    $checkQuery = "SELECT id, full_name, admission_number, class, stream, level FROM students WHERE id = ?";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->execute([$studentId]);
    $student = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        throw new Exception('Student not found');
    }
    
    // Get all results grouped by year and term
    $query = "SELECT sr.*, sub.subject_name, sub.subject_code 
              FROM student_results sr 
              JOIN subjects sub ON sr.subject_id = sub.id 
              WHERE sr.student_id = ? 
              ORDER BY sr.year DESC, sr.term DESC, sr.exam_type, sub.subject_name ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$studentId]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group results by year and term
    $grouped = [];
    foreach ($results as $result) {
        $key = $result['year'] . '_T' . $result['term'];
        if (!isset($grouped[$key])) {
            $grouped[$key] = [
                'year' => $result['year'],
                'term' => $result['term'],
                'results' => []
            ];
        }
        $grouped[$key]['results'][] = $result;
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'student' => $student,
            'history' => array_values($grouped)
        ]
    ]);
}

function createResult($db, $data) {
    $required = ['student_id', 'subject_id', 'year', 'term', 'exam_type', 'marks'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            throw new Exception("Field '$field' is required");
        }
    }
    
    // Validate exam type
    $validExamTypes = ['BOT1', 'EOT1', 'BOT2', 'EOT2', 'BOT3', 'EOT3', 'FINAL'];
    if (!in_array($data['exam_type'], $validExamTypes)) {
        throw new Exception('Invalid exam type');
    }
    
    // Validate term
    if (!in_array($data['term'], [1, 2, 3])) {
        throw new Exception('Invalid term. Must be 1, 2, or 3');
    }
    
    // Validate marks
    if ($data['marks'] < 0 || $data['marks'] > 100) {
        throw new Exception('Marks must be between 0 and 100');
    }
    
    // Check if student exists
    $studentQuery = "SELECT id FROM students WHERE id = ?";
    $studentStmt = $db->prepare($studentQuery);
    $studentStmt->execute([$data['student_id']]);
    if (!$studentStmt->fetch()) {
        throw new Exception('Student not found');
    }
    
    // Check if subject exists
    $subjectQuery = "SELECT id FROM subjects WHERE id = ?";
    $subjectStmt = $db->prepare($subjectQuery);
    $subjectStmt->execute([$data['subject_id']]);
    if (!$subjectStmt->fetch()) {
        throw new Exception('Subject not found');
    }
    
    // Check for duplicate
    $duplicateQuery = "SELECT id FROM student_results 
                       WHERE student_id = ? AND subject_id = ? AND year = ? AND term = ? AND exam_type = ?";
    $duplicateStmt = $db->prepare($duplicateQuery);
    $duplicateStmt->execute([
        $data['student_id'],
        $data['subject_id'],
        $data['year'],
        $data['term'],
        $data['exam_type']
    ]);
    if ($duplicateStmt->fetch()) {
        throw new Exception('Result already exists for this student, subject, year, term, and exam type');
    }
    
    // Calculate grade
    $grade = calculateGrade($data['marks']);
    
    $query = "INSERT INTO student_results (student_id, subject_id, class, stream, year, term, exam_type, marks, grade, remarks) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        $data['student_id'],
        $data['subject_id'],
        $data['class'] ?? '',
        $data['stream'] ?? '',
        $data['year'],
        $data['term'],
        $data['exam_type'],
        $data['marks'],
        $grade,
        $data['remarks'] ?? ''
    ]);
    
    $resultId = $db->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Result added successfully',
        'data' => ['id' => $resultId, 'grade' => $grade]
    ]);
}

function updateResult($db, $data) {
    $id = $data['id'] ?? 0;
    
    if (!$id) {
        throw new Exception('Result ID is required');
    }
    
    // Check if result exists
    $checkQuery = "SELECT id FROM student_results WHERE id = ?";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->execute([$id]);
    if (!$checkStmt->fetch()) {
        throw new Exception('Result not found');
    }
    
    // Validate marks if provided
    if (isset($data['marks']) && ($data['marks'] < 0 || $data['marks'] > 100)) {
        throw new Exception('Marks must be between 0 and 100');
    }
    
    // Recalculate grade if marks changed
    if (isset($data['marks'])) {
        $data['grade'] = calculateGrade($data['marks']);
    }
    
    $query = "UPDATE student_results SET 
              student_id = ?,
              subject_id = ?,
              class = ?,
              stream = ?,
              year = ?,
              term = ?,
              exam_type = ?,
              marks = ?,
              grade = ?,
              remarks = ?
              WHERE id = ?";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        $data['student_id'],
        $data['subject_id'],
        $data['class'] ?? '',
        $data['stream'] ?? '',
        $data['year'],
        $data['term'],
        $data['exam_type'],
        $data['marks'],
        $data['grade'] ?? null,
        $data['remarks'] ?? '',
        $id
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Result updated successfully'
    ]);
}

function deleteResult($db) {
    $id = $_GET['id'] ?? 0;
    
    if (!$id) {
        throw new Exception('Result ID is required');
    }
    
    $query = "DELETE FROM student_results WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$id]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('Result not found');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Result deleted successfully'
    ]);
}

function calculateGrade($marks) {
    if ($marks >= 80) return 'A';
    if ($marks >= 70) return 'B';
    if ($marks >= 60) return 'C';
    if ($marks >= 50) return 'D';
    return 'E';
}

function bulkCreateResults($db, $data) {
    if (!isset($data['results']) || !is_array($data['results'])) {
        throw new Exception('Results array is required');
    }
    
    $results = $data['results'];
    $successCount = 0;
    $errors = [];
    
    foreach ($results as $result) {
        try {
            $required = ['student_id', 'subject_id', 'year', 'term', 'exam_type', 'marks'];
            foreach ($required as $field) {
                if (empty($result[$field])) {
                    throw new Exception("Field '$field' is required");
                }
            }
            
            // Validate exam type
            $validExamTypes = ['BOT1', 'EOT1', 'BOT2', 'EOT2', 'BOT3', 'EOT3', 'FINAL'];
            if (!in_array($result['exam_type'], $validExamTypes)) {
                throw new Exception('Invalid exam type');
            }
            
            // Validate term
            if (!in_array($result['term'], [1, 2, 3])) {
                throw new Exception('Invalid term. Must be 1, 2, or 3');
            }
            
            // Validate marks
            if ($result['marks'] < 0 || $result['marks'] > 100) {
                throw new Exception('Marks must be between 0 and 100');
            }
            
            // Check if student exists
            $studentQuery = "SELECT id FROM students WHERE id = ?";
            $studentStmt = $db->prepare($studentQuery);
            $studentStmt->execute([$result['student_id']]);
            if (!$studentStmt->fetch()) {
                throw new Exception('Student not found');
            }
            
            // Check if subject exists
            $subjectQuery = "SELECT id FROM subjects WHERE id = ?";
            $subjectStmt = $db->prepare($subjectQuery);
            $subjectStmt->execute([$result['subject_id']]);
            if (!$subjectStmt->fetch()) {
                throw new Exception('Subject not found');
            }
            
            // Check for duplicate
            $duplicateQuery = "SELECT id FROM student_results 
                           WHERE student_id = ? AND subject_id = ? AND year = ? AND term = ? AND exam_type = ?";
            $duplicateStmt = $db->prepare($duplicateQuery);
            $duplicateStmt->execute([
                $result['student_id'],
                $result['subject_id'],
                $result['year'],
                $result['term'],
                $result['exam_type']
            ]);
            if ($duplicateStmt->fetch()) {
                // Skip duplicate (or could update instead)
                continue;
            }
            
            // Calculate grade
            $grade = calculateGrade($result['marks']);
            
            $query = "INSERT INTO student_results (student_id, subject_id, class, stream, year, term, exam_type, marks, grade, remarks) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $db->prepare($query);
            $stmt->execute([
                $result['student_id'],
                $result['subject_id'],
                $result['class'] ?? '',
                $result['stream'] ?? '',
                $result['year'],
                $result['term'],
                $result['exam_type'],
                $result['marks'],
                $grade,
                $result['remarks'] ?? ''
            ]);
            
            $successCount++;
        } catch (Exception $e) {
            $errors[] = [
                'subject_id' => $result['subject_id'] ?? 'unknown',
                'error' => $e->getMessage()
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Successfully created $successCount results",
        'data' => [
            'success_count' => $successCount,
            'total' => count($results),
            'errors' => $errors
        ]
    ]);
}
