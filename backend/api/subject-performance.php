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

switch ($method) {
    case 'GET':
        $query = "SELECT sp.*, t.full_name AS teacher_name
                  FROM subject_teacher_performance sp
                  LEFT JOIN teachers t ON sp.teacher_id = t.id
                  WHERE 1=1";
        $params = [];

        if (isset($_GET['teacher_id']) && $_GET['teacher_id'] !== '') {
            $query .= " AND sp.teacher_id = :teacher_id";
            $params[':teacher_id'] = $_GET['teacher_id'];
        }

        if (isset($_GET['year']) && $_GET['year'] !== '') {
            $query .= " AND sp.year = :year";
            $params[':year'] = $_GET['year'];
        }

        // If term is provided and not empty, filter by that term.
        // If term is missing or empty, return all terms for the selected year.
        if (isset($_GET['term']) && $_GET['term'] !== '') {
            $query .= " AND sp.term = :term";
            $params[':term'] = $_GET['term'];
        }

        $query .= " ORDER BY t.full_name ASC, sp.year DESC, sp.term DESC";

        $stmt = $db->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ]);
        break;

    case 'POST':
        $data = json_decode(file_get_contents("php://input"));

        if (!empty($data->teacher_id) && !empty($data->year) && !empty($data->term)) {
            $query = "INSERT INTO subject_teacher_performance
                      (teacher_id, year, term, subject, class, stream, bot1, eot1, tc1, eot2, tc2, eot3, tc3, agp, tc1_comment, tc2_comment, tc3_comment, agp_comment)
                      VALUES
                      (:teacher_id, :year, :term, :subject, :class, :stream, :bot1, :eot1, :tc1, :eot2, :tc2, :eot3, :tc3, :agp, :tc1_comment, :tc2_comment, :tc3_comment, :agp_comment)";

            $stmt = $db->prepare($query);
            $stmt->bindParam(':teacher_id', $data->teacher_id);
            $stmt->bindParam(':year', $data->year);
            $stmt->bindParam(':term', $data->term);
            $stmt->bindParam(':subject', $data->subject);
            $stmt->bindParam(':class', $data->class);
            $stmt->bindParam(':stream', $data->stream);
            $stmt->bindParam(':bot1', $data->bot1);
            $stmt->bindParam(':eot1', $data->eot1);
            $stmt->bindParam(':tc1', $data->tc1);
            $stmt->bindParam(':eot2', $data->eot2);
            $stmt->bindParam(':tc2', $data->tc2);
            $stmt->bindParam(':eot3', $data->eot3);
            $stmt->bindParam(':tc3', $data->tc3);
            $stmt->bindParam(':agp', $data->agp);
            $stmt->bindParam(':tc1_comment', $data->tc1_comment);
            $stmt->bindParam(':tc2_comment', $data->tc2_comment);
            $stmt->bindParam(':tc3_comment', $data->tc3_comment);
            $stmt->bindParam(':agp_comment', $data->agp_comment);

            if ($stmt->execute()) {
                http_response_code(201);
                echo json_encode(['success' => true, 'message' => 'Performance record created']);
            } else {
                $errorInfo = $stmt->errorInfo();
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to create: ' . ($errorInfo[2] ?? 'Unknown database error')]);
            }
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Required fields missing']);
        }
        break;

    case 'PUT':
        $data = json_decode(file_get_contents("php://input"));

        if (!empty($data->id)) {
            $query = "UPDATE subject_teacher_performance SET
                      teacher_id = :teacher_id,
                      year = :year,
                      term = :term,
                      subject = :subject,
                      class = :class,
                      stream = :stream,
                      bot1 = :bot1,
                      eot1 = :eot1,
                      tc1 = :tc1,
                      eot2 = :eot2,
                      tc2 = :tc2,
                      eot3 = :eot3,
                      tc3 = :tc3,
                      agp = :agp,
                      tc1_comment = :tc1_comment,
                      tc2_comment = :tc2_comment,
                      tc3_comment = :tc3_comment,
                      agp_comment = :agp_comment
                      WHERE id = :id";

            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $data->id);
            $stmt->bindParam(':teacher_id', $data->teacher_id);
            $stmt->bindParam(':year', $data->year);
            $stmt->bindParam(':term', $data->term);
            $stmt->bindParam(':subject', $data->subject);
            $stmt->bindParam(':class', $data->class);
            $stmt->bindParam(':stream', $data->stream);
            $stmt->bindParam(':bot1', $data->bot1);
            $stmt->bindParam(':eot1', $data->eot1);
            $stmt->bindParam(':tc1', $data->tc1);
            $stmt->bindParam(':eot2', $data->eot2);
            $stmt->bindParam(':tc2', $data->tc2);
            $stmt->bindParam(':eot3', $data->eot3);
            $stmt->bindParam(':tc3', $data->tc3);
            $stmt->bindParam(':agp', $data->agp);
            $stmt->bindParam(':tc1_comment', $data->tc1_comment);
            $stmt->bindParam(':tc2_comment', $data->tc2_comment);
            $stmt->bindParam(':tc3_comment', $data->tc3_comment);
            $stmt->bindParam(':agp_comment', $data->agp_comment);

            if ($stmt->execute()) {
                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'Performance record updated']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to update']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID is required']);
        }
        break;

    case 'DELETE':
        if (isset($_GET['id']) && $_GET['id'] !== '') {
            $query = "DELETE FROM subject_teacher_performance WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $_GET['id']);

            if ($stmt->execute()) {
                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'Record deleted']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to delete']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID is required']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        break;
}
?>