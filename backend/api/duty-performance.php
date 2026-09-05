<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/Database.php';
require_once '../config/JWT.php';

$database = new Database();
$db = $database->getConnection();

// Verify token (temporarily disabled for testing)
$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
$user = ['id' => 1, 'full_name' => 'Admin', 'role' => 'admin']; // Temporarily bypass auth

if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
    $token = $matches[1];
    $user = JWT::decode($token) ?: ['id' => 1, 'full_name' => 'Admin', 'role' => 'admin'];
}

if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

switch($method) {
    case 'GET':
        // If ID is provided, get single record
        if (isset($_GET['id'])) {
            $query = "SELECT dp.*, t.full_name as teacher_name 
                      FROM duty_performance dp
                      LEFT JOIN teachers t ON dp.teacher_id = t.id
                      WHERE dp.id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $_GET['id']);
            $stmt->execute();
            $record = $stmt->fetch();
            
            if ($record) {
                http_response_code(200);
                echo json_encode(['success' => true, 'data' => $record]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Record not found']);
            }
            break;
        }
        
        // Otherwise get all records with optional filters
        $query = "SELECT dp.*, t.full_name as teacher_name 
                  FROM duty_performance dp
                  LEFT JOIN teachers t ON dp.teacher_id = t.id
                  WHERE 1=1";
        $params = [];
        
        if (isset($_GET['teacher_id'])) {
            $query .= " AND dp.teacher_id = :teacher_id";
            $params[':teacher_id'] = $_GET['teacher_id'];
        }
        if (isset($_GET['year'])) {
            $query .= " AND dp.year = :year";
            $params[':year'] = $_GET['year'];
        }
        if (isset($_GET['term'])) {
            $query .= " AND dp.term = :term";
            $params[':term'] = $_GET['term'];
        }
        if (isset($_GET['week']) && $_GET['week'] !== '') {
            $query .= " AND dp.week_number = :week";
            $params[':week'] = $_GET['week'];
        }
        
        // Get best performers for awards
        if (isset($_GET['best']) && $_GET['best'] === 'true') {
            $query .= " AND dp.percentage >= 80";
            $query .= " ORDER BY dp.percentage DESC, dp.total_score DESC";
        } else {
            $query .= " ORDER BY t.full_name";
        }
        
        $stmt = $db->prepare($query);
        foreach($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        
        http_response_code(200);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        break;
        
    case 'POST':
    case 'PUT':
        $data = json_decode(file_get_contents("php://input"));
        
        if (!empty($data->teacher_id) && !empty($data->year) && !empty($data->term)) {
            $query = "INSERT INTO duty_performance 
                      (teacher_id, year, term, week_number, punctuality, supervision, cleanliness, time_keeping, participation, total_score, percentage, comment, areas_of_improvement, general_remarks, supervisor)
                      VALUES (:teacher_id, :year, :term, :week_number, :punctuality, :supervision, :cleanliness, :time_keeping, :participation, :total_score, :percentage, :comment, :areas_of_improvement, :general_remarks, :supervisor)
                      ON DUPLICATE KEY UPDATE
                      week_number = VALUES(week_number), punctuality = VALUES(punctuality), supervision = VALUES(supervision),
                      cleanliness = VALUES(cleanliness), time_keeping = VALUES(time_keeping),
                      participation = VALUES(participation), total_score = VALUES(total_score),
                      percentage = VALUES(percentage), comment = VALUES(comment),
                      areas_of_improvement = VALUES(areas_of_improvement), general_remarks = VALUES(general_remarks), supervisor = VALUES(supervisor)";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':teacher_id', $data->teacher_id);
            $stmt->bindParam(':year', $data->year);
            $stmt->bindParam(':term', $data->term);
            $stmt->bindParam(':week_number', $data->week_number);
            $stmt->bindParam(':punctuality', $data->punctuality);
            $stmt->bindParam(':supervision', $data->supervision);
            $stmt->bindParam(':cleanliness', $data->cleanliness);
            $stmt->bindParam(':time_keeping', $data->time_keeping);
            $stmt->bindParam(':participation', $data->participation);
            $stmt->bindParam(':total_score', $data->total_score);
            $stmt->bindParam(':percentage', $data->percentage);
            $stmt->bindParam(':comment', $data->comment);
            $stmt->bindParam(':areas_of_improvement', $data->areas_of_improvement);
            $stmt->bindParam(':general_remarks', $data->general_remarks);
            $stmt->bindParam(':supervisor', $data->supervisor);
            
            if ($stmt->execute()) {
                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'Duty performance saved']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to save']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Required fields missing']);
        }
        break;

    case 'DELETE':
        if (isset($_GET['id'])) {
            $query = "DELETE FROM duty_performance WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $_GET['id']);
            
            if ($stmt->execute()) {
                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'Duty performance deleted']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to delete']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID required']);
        }
        break;
}
?>
