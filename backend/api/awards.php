<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/Database.php';
require_once '../config/JWT.php';

$database = new Database();
$db = $database->getConnection();

// Verify token
$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
$user = null;

if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
    $token = $matches[1];
    $user = JWT::decode($token);
}

if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

switch($method) {
    case 'GET':
        $query = "SELECT ta.*, t.full_name as teacher_name, t.subject 
                  FROM teacher_awards ta
                  LEFT JOIN teachers t ON ta.teacher_id = t.id
                  WHERE 1=1";
        $params = [];
        
        if (isset($_GET['year'])) {
            $query .= " AND ta.year = :year";
            $params[':year'] = $_GET['year'];
        }
        if (isset($_GET['award_type'])) {
            $query .= " AND ta.award_type = :award_type";
            $params[':award_type'] = $_GET['award_type'];
        }
        if (isset($_GET['teacher_id'])) {
            $query .= " AND ta.teacher_id = :teacher_id";
            $params[':teacher_id'] = $_GET['teacher_id'];
        }
        
        $query .= " ORDER BY ta.awarded_at DESC";
        $stmt = $db->prepare($query);
        foreach($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        
        http_response_code(200);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents("php://input"));
        
        if (!empty($data->teacher_id) && !empty($data->award_type) && !empty($data->year)) {
            $query = "INSERT INTO teacher_awards 
                      (teacher_id, award_type, year, term, week_number, month_name, reason)
                      VALUES (:teacher_id, :award_type, :year, :term, :week_number, :month_name, :reason)";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':teacher_id', $data->teacher_id);
            $stmt->bindParam(':award_type', $data->award_type);
            $stmt->bindParam(':year', $data->year);
            $stmt->bindParam(':term', $data->term);
            $stmt->bindParam(':week_number', $data->week_number);
            $stmt->bindParam(':month_name', $data->month_name);
            $stmt->bindParam(':reason', $data->reason);
            
            if ($stmt->execute()) {
                http_response_code(201);
                echo json_encode(['success' => true, 'message' => 'Award created', 'id' => $db->lastInsertId()]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to create award']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Required fields missing']);
        }
        break;
        
    case 'DELETE':
        if (isset($_GET['id'])) {
            $query = "DELETE FROM teacher_awards WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $_GET['id']);
            
            if ($stmt->execute()) {
                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'Award deleted']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to delete award']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID is required']);
        }
        break;
}
?>
