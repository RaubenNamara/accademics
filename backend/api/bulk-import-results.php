<?php
require_once '../config/Database.php';
require_once '../config/JWT.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
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
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['level']) || !isset($data['year']) || !isset($data['term']) || !isset($data['exam_type'])) {
        throw new Exception('Missing required fields: level, year, term, exam_type');
    }
    
    if (!isset($data['results']) || !is_array($data['results'])) {
        throw new Exception('Results data is required and must be an array');
    }
    
    $level = $data['level'];
    $year = $data['year'];
    $term = $data['term'];
    $examType = $data['exam_type'];
    $results = $data['results'];
    
    // Validate level
    if (!in_array($level, ['O_LEVEL', 'A_LEVEL'])) {
        throw new Exception('Invalid level. Must be O_LEVEL or A_LEVEL');
    }
    
    // Validate term
    if (!in_array($term, [1, 2, 3])) {
        throw new Exception('Invalid term. Must be 1, 2, or 3');
    }
    
    // Validate exam type
    $validExamTypes = ['BOT1', 'EOT1', 'BOT2', 'EOT2', 'BOT3', 'EOT3', 'FINAL'];
    if (!in_array($examType, $validExamTypes)) {
        throw new Exception('Invalid exam type');
    }
    
    // Get subjects for the selected level
    $subjectQuery = "SELECT id, subject_code, subject_name FROM subjects 
                     WHERE (level = ? OR level = 'BOTH') AND is_active = TRUE 
                     ORDER BY subject_name ASC";
    $subjectStmt = $db->prepare($subjectQuery);
    $subjectStmt->execute([$level]);
    $subjects = $subjectStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create subject code to ID mapping
    $subjectMap = [];
    foreach ($subjects as $subject) {
        $subjectMap[$subject['subject_code']] = $subject['id'];
    }
    
    $successCount = 0;
    $errorCount = 0;
    $errors = [];
    $duplicates = [];
    
    foreach ($results as $index => $result) {
        try {
            // Validate required fields
            if (empty($result['admission_number'])) {
                throw new Exception('Admission number is required');
            }
            
            if (empty($result['subject_code'])) {
                throw new Exception('Subject code is required');
            }
            
            if (!isset($result['marks'])) {
                throw new Exception('Marks are required');
            }
            
            // Validate marks
            if ($result['marks'] < 0 || $result['marks'] > 100) {
                throw new Exception('Marks must be between 0 and 100');
            }
            
            // Find student by admission number
            $studentQuery = "SELECT id, full_name FROM students WHERE admission_number = ? AND is_active = TRUE";
            $studentStmt = $db->prepare($studentQuery);
            $studentStmt->execute([$result['admission_number']]);
            $student = $studentStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$student) {
                throw new Exception('Student not found with admission number: ' . $result['admission_number']);
            }
            
            // Find subject by code
            if (!isset($subjectMap[$result['subject_code']])) {
                throw new Exception('Subject not found with code: ' . $result['subject_code']);
            }
            
            $subjectId = $subjectMap[$result['subject_code']];
            
            // Check for duplicate
            $duplicateQuery = "SELECT id FROM student_results 
                             WHERE student_id = ? AND subject_id = ? AND year = ? AND term = ? AND exam_type = ?";
            $duplicateStmt = $db->prepare($duplicateQuery);
            $duplicateStmt->execute([
                $student['id'],
                $subjectId,
                $year,
                $term,
                $examType
            ]);
            
            if ($duplicateStmt->fetch()) {
                $duplicates[] = [
                    'row' => $index + 1,
                    'admission_number' => $result['admission_number'],
                    'subject' => $result['subject_code'],
                    'reason' => 'Result already exists'
                ];
                $errorCount++;
                continue;
            }
            
            // Calculate grade
            $grade = calculateGrade($result['marks']);
            
            // Insert result
            $insertQuery = "INSERT INTO student_results (student_id, subject_id, year, term, exam_type, marks, grade, remarks) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $insertStmt = $db->prepare($insertQuery);
            $insertStmt->execute([
                $student['id'],
                $subjectId,
                $year,
                $term,
                $examType,
                $result['marks'],
                $grade,
                $result['remarks'] ?? ''
            ]);
            
            $successCount++;
            
        } catch (Exception $e) {
            $errorCount++;
            $errors[] = [
                'row' => $index + 1,
                'admission_number' => $result['admission_number'] ?? 'N/A',
                'error' => $e->getMessage()
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Bulk import completed',
        'data' => [
            'total' => count($results),
            'success' => $successCount,
            'errors' => $errorCount,
            'duplicates' => count($duplicates),
            'error_details' => $errors,
            'duplicate_details' => $duplicates
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

function calculateGrade($marks) {
    if ($marks >= 75) return 'A';
    if ($marks >= 65) return 'B';
    if ($marks >= 55) return 'C';
    if ($marks >= 45) return 'D';
    if ($marks >= 35) return 'E';
    return 'F';
}
