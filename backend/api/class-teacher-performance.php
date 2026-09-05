<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS");
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

// Handle _method parameter for DELETE workaround
if ($method === 'POST' && isset($_POST['_method'])) {
    $method = strtoupper($_POST['_method']);
}

// Also check for JSON body _method
if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"));
    if ($data && isset($data->_method)) {
        $method = strtoupper($data->_method);
    }
}

switch($method) {
    case 'GET':
        $query = "SELECT ctp.*, t.full_name as teacher_name 
                      FROM class_teacher_performance ctp
                      LEFT JOIN teachers t ON ctp.teacher_id = t.id
                      WHERE 1=1";
        $params = [];
        
        if (isset($_GET['teacher_id'])) {
            $query .= " AND ctp.teacher_id = :teacher_id";
            $params[':teacher_id'] = $_GET['teacher_id'];
        }
        if (isset($_GET['year'])) {
            $query .= " AND ctp.year = :year";
            $params[':year'] = $_GET['year'];
        }
        if (isset($_GET['term'])) {
            $query .= " AND ctp.term = :term";
            $params[':term'] = $_GET['term'];
        }
        if (isset($_GET['week']) && $_GET['week'] !== '') {
            $query .= " AND ctp.week = :week";
            $params[':week'] = $_GET['week'];
        }
        
        $query .= " ORDER BY ctp.week ASC, t.full_name ASC";
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
        
        if (!empty($data->teacher_id) && !empty($data->year) && !empty($data->term) && !empty($data->week)) {
            $query = "INSERT INTO class_teacher_performance
                      (teacher_id, year, term, class, stream, week, roll_call_score, mentorship_score, devotion_score, cleanliness_score, parent_contacted, weekly_score,
                       bt1, t1, t2, t3, c1, c2, c3, average_score, average_comment, academic_score)
                      VALUES (:teacher_id, :year, :term, :class, :stream, :week, :roll_call_score, :mentorship_score, :devotion_score, :cleanliness_score, :parent_contacted, :weekly_score,
                       :bt1, :t1, :t2, :t3, :c1, :c2, :c3, :average_score, :average_comment, :academic_score)";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':teacher_id', $data->teacher_id);
            $stmt->bindParam(':year', $data->year);
            $stmt->bindParam(':term', $data->term);
            $stmt->bindParam(':class', $data->class);
            $stmt->bindParam(':stream', $data->stream);
            $stmt->bindParam(':week', $data->week);
            $stmt->bindParam(':roll_call_score', $data->roll_call_score);
            $stmt->bindParam(':mentorship_score', $data->mentorship_score);
            $stmt->bindParam(':devotion_score', $data->devotion_score);
            $stmt->bindParam(':cleanliness_score', $data->cleanliness_score);
            $stmt->bindParam(':parent_contacted', $data->parent_contacted);
            $stmt->bindParam(':weekly_score', $data->weekly_score);
            $stmt->bindParam(':bt1', $data->bt1);
            $stmt->bindParam(':t1', $data->t1);
            $stmt->bindParam(':t2', $data->t2);
            $stmt->bindParam(':t3', $data->t3);
            $stmt->bindParam(':c1', $data->c1);
            $stmt->bindParam(':c2', $data->c2);
            $stmt->bindParam(':c3', $data->c3);
            $stmt->bindParam(':average_score', $data->average_score);
            $stmt->bindParam(':average_comment', $data->average_comment);
            $stmt->bindParam(':academic_score', $data->academic_score);

            try {
                if ($stmt->execute()) {
                    // Calculate cumulative weekly average for all records up to this week
                    $teacherId = $data->teacher_id;
                    $year = $data->year;
                    $term = $data->term;
                    $insertId = $db->lastInsertId();
                    
                    // Get all weekly scores for this teacher, year, and term
                    $scoresQuery = "SELECT id, week, weekly_score FROM class_teacher_performance
                                    WHERE teacher_id = :teacher_id AND year = :year AND term = :term AND weekly_score IS NOT NULL
                                    ORDER BY week ASC";
                    $scoresStmt = $db->prepare($scoresQuery);
                    $scoresStmt->bindParam(':teacher_id', $teacherId);
                    $scoresStmt->bindParam(':year', $year);
                    $scoresStmt->bindParam(':term', $term);
                    $scoresStmt->execute();
                    $scores = $scoresStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Calculate cumulative average for each week
                    $cumulativeSum = 0;
                    foreach ($scores as $score) {
                        $cumulativeSum += $score['weekly_score'];
                        $weeklyAvg = round($cumulativeSum / $score['week'], 2);
                        
                        // Update each record with its cumulative average
                        $updateAvgQuery = "UPDATE class_teacher_performance SET weekly_average_score = :weekly_avg
                                           WHERE id = :id";
                        $updateAvgStmt = $db->prepare($updateAvgQuery);
                        $updateAvgStmt->bindParam(':weekly_avg', $weeklyAvg);
                        $updateAvgStmt->bindParam(':id', $score['id']);
                        $updateAvgStmt->execute();
                    }
                    
                    http_response_code(201);
                    echo json_encode(['success' => true, 'message' => 'Class teacher performance created']);
                } else {
                    $errorInfo = $stmt->errorInfo();
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Failed to create: ' . $errorInfo[2]]);
                }
            } catch (PDOException $e) {
                $errorInfo = $e->errorInfo;
                if (strpos($errorInfo[2], 'Duplicate entry') !== false) {
                    http_response_code(409);
                    echo json_encode(['success' => false, 'message' => 'A record for this teacher, class, stream, year, term, and week already exists.']);
                } else {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Failed to create: ' . $errorInfo[2]]);
                }
            }
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Required fields missing']);
        }
        break;
        
    case 'PUT':
        $data = json_decode(file_get_contents("php://input"));
        
        if (!empty($data->id)) {
            $query = "UPDATE class_teacher_performance SET
                      teacher_id = :teacher_id, year = :year, term = :term,
                      class = :class, stream = :stream, week = :week,
                      roll_call_score = :roll_call_score, mentorship_score = :mentorship_score, devotion_score = :devotion_score, cleanliness_score = :cleanliness_score,
                      parent_contacted = :parent_contacted, weekly_score = :weekly_score,
                      bt1 = :bt1, t1 = :t1, t2 = :t2, t3 = :t3,
                      c1 = :c1, c2 = :c2, c3 = :c3, average_score = :average_score, average_comment = :average_comment, academic_score = :academic_score
                      WHERE id = :id";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $data->id);
            $stmt->bindParam(':teacher_id', $data->teacher_id);
            $stmt->bindParam(':year', $data->year);
            $stmt->bindParam(':term', $data->term);
            $stmt->bindParam(':class', $data->class);
            $stmt->bindParam(':stream', $data->stream);
            $stmt->bindParam(':week', $data->week);
            $stmt->bindParam(':roll_call_score', $data->roll_call_score);
            $stmt->bindParam(':mentorship_score', $data->mentorship_score);
            $stmt->bindParam(':devotion_score', $data->devotion_score);
            $stmt->bindParam(':cleanliness_score', $data->cleanliness_score);
            $stmt->bindParam(':parent_contacted', $data->parent_contacted);
            $stmt->bindParam(':weekly_score', $data->weekly_score);
            $stmt->bindParam(':bt1', $data->bt1);
            $stmt->bindParam(':t1', $data->t1);
            $stmt->bindParam(':t2', $data->t2);
            $stmt->bindParam(':t3', $data->t3);
            $stmt->bindParam(':c1', $data->c1);
            $stmt->bindParam(':c2', $data->c2);
            $stmt->bindParam(':c3', $data->c3);
            $stmt->bindParam(':average_score', $data->average_score);
            $stmt->bindParam(':average_comment', $data->average_comment);
            $stmt->bindParam(':academic_score', $data->academic_score);

            if ($stmt->execute()) {
                // Calculate cumulative weekly average for all records up to this week
                $teacherId = $data->teacher_id;
                $year = $data->year;
                $term = $data->term;
                
                // Get all weekly scores for this teacher, year, and term
                $scoresQuery = "SELECT id, week, weekly_score FROM class_teacher_performance
                                WHERE teacher_id = :teacher_id AND year = :year AND term = :term AND weekly_score IS NOT NULL
                                ORDER BY week ASC";
                $scoresStmt = $db->prepare($scoresQuery);
                $scoresStmt->bindParam(':teacher_id', $teacherId);
                $scoresStmt->bindParam(':year', $year);
                $scoresStmt->bindParam(':term', $term);
                $scoresStmt->execute();
                $scores = $scoresStmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Calculate cumulative average for each week
                $cumulativeSum = 0;
                foreach ($scores as $score) {
                    $cumulativeSum += $score['weekly_score'];
                    $weeklyAvg = round($cumulativeSum / $score['week'], 2);
                    
                    // Update each record with its cumulative average
                    $updateAvgQuery = "UPDATE class_teacher_performance SET weekly_average_score = :weekly_avg
                                       WHERE id = :id";
                    $updateAvgStmt = $db->prepare($updateAvgQuery);
                    $updateAvgStmt->bindParam(':weekly_avg', $weeklyAvg);
                    $updateAvgStmt->bindParam(':id', $score['id']);
                    $updateAvgStmt->execute();
                }
                
                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'Class teacher performance updated']);
            } else {
                $errorInfo = $stmt->errorInfo();
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to update: ' . $errorInfo[2]]);
            }
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID is required']);
        }
        break;
        
    case 'DELETE':
        $data = json_decode(file_get_contents("php://input"));
        $id = $_GET['id'] ?? ($data->id ?? 0);

        if ($id) {
            try {
                $query = "DELETE FROM class_teacher_performance WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $id);

                if ($stmt->execute()) {
                    http_response_code(200);
                    echo json_encode(['success' => true, 'message' => 'Record deleted']);
                } else {
                    $errorInfo = $stmt->errorInfo();
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Failed to delete: ' . $errorInfo[2]]);
                }
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            }
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID is required']);
        }
        break;
}
?>
