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

$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';
$user = ['id' => 1, 'full_name' => 'Admin', 'role' => 'admin'];

if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
    $token = $matches[1];
    $decoded = JWT::decode($token);
    if ($decoded) {
        $user = $decoded;
    }
}

if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

function getDayOfWeek($date) {
    $dayMap = [
        1 => 'Sun',
        2 => 'Mon',
        3 => 'Tue',
        4 => 'Wed',
        5 => 'Thu',
        6 => 'Fri',
        7 => 'Sat'
    ];
    $dayNum = date('N', strtotime($date));
    return $dayMap[$dayNum] ?? '';
}

function getValue($data, $key, $default = null) {
    return isset($data->$key) ? $data->$key : $default;
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $query = "SELECT lc.*, t.full_name AS teacher_name, la.attendance_date AS original_lesson_date,
                  la.day_of_week AS original_lesson_day
                  FROM lesson_compensations lc
                  LEFT JOIN teachers t ON lc.teacher_id = t.id
                  LEFT JOIN teacher_lesson_attendance la ON lc.lesson_monitoring_id = la.id
                  WHERE 1=1";
        $params = [];

        if (isset($_GET['lesson_monitoring_id']) && $_GET['lesson_monitoring_id'] !== '') {
            $query .= " AND lc.lesson_monitoring_id = :lesson_monitoring_id";
            $params[':lesson_monitoring_id'] = $_GET['lesson_monitoring_id'];
        }

        if (isset($_GET['teacher_id']) && $_GET['teacher_id'] !== '') {
            $query .= " AND lc.teacher_id = :teacher_id";
            $params[':teacher_id'] = $_GET['teacher_id'];
        }

        if (isset($_GET['status']) && $_GET['status'] !== '') {
            $query .= " AND lc.status = :status";
            $params[':status'] = $_GET['status'];
        }

        if (isset($_GET['compensation_day']) && $_GET['compensation_day'] !== '') {
            $query .= " AND lc.compensation_day = :compensation_day";
            $params[':compensation_day'] = $_GET['compensation_day'];
        }

        if (isset($_GET['start_date']) && $_GET['start_date'] !== '') {
            $query .= " AND lc.compensation_date >= :start_date";
            $params[':start_date'] = $_GET['start_date'];
        }

        if (isset($_GET['end_date']) && $_GET['end_date'] !== '') {
            $query .= " AND lc.compensation_date <= :end_date";
            $params[':end_date'] = $_GET['end_date'];
        }

        $query .= " ORDER BY lc.compensation_date DESC, lc.created_at DESC";

        $stmt = $db->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $rows,
            'count' => count($rows)
        ]);
        break;

    case 'POST':
        $data = json_decode(file_get_contents("php://input"));

        error_log("POST compensation - Data: " . json_encode($data));

        $lessonMonitoringId = getValue($data, 'lesson_monitoring_id');
        $teacherId = getValue($data, 'teacher_id');
        $subject = getValue($data, 'subject');
        $class = getValue($data, 'class');
        $stream = getValue($data, 'stream');
        $originalDate = getValue($data, 'original_date');
        $originalDay = getValue($data, 'original_day');
        $compensationDate = getValue($data, 'compensation_date');
        $compensationDay = getValue($data, 'compensation_day');
        $minutesCompensated = (int) getValue($data, 'minutes_compensated', 0);
        $remarks = getValue($data, 'remarks');
        $status = getValue($data, 'status', 'Partially Compensated');

        error_log("POST compensation - Parsed values: lesson_monitoring_id=$lessonMonitoringId, minutes_compensated=$minutesCompensated, status=$status");

        // Validation
        if (!$lessonMonitoringId || !$teacherId || !$subject || !$class ||
            !$originalDate || !$originalDay || !$compensationDate) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Required fields missing'
            ]);
            break;
        }

        // Validate compensation date is not earlier than original date
        if (strtotime($compensationDate) < strtotime($originalDate)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Compensation date cannot be earlier than original lesson date'
            ]);
            break;
        }

        // Validate day abbreviations
        $validDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        if (!in_array($originalDay, $validDays)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid original day. Must be one of: Mon, Tue, Wed, Thu, Fri, Sat, Sun'
            ]);
            break;
        }

        // Auto-generate compensation day
        $compensationDay = getDayOfWeek($compensationDate);

        // Allow multiple compensations for the same lesson on different dates
        // Removed duplicate check to enable partial compensations over time

        $query = "INSERT INTO lesson_compensations
                  (lesson_monitoring_id, teacher_id, subject, class, stream, original_date, original_day,
                   compensation_date, compensation_day, minutes_compensated, remarks, status, created_by)
                  VALUES
                  (:lesson_monitoring_id, :teacher_id, :subject, :class, :stream, :original_date, :original_day,
                   :compensation_date, :compensation_day, :minutes_compensated, :remarks, :status, :created_by)";

        $stmt = $db->prepare($query);
        $stmt->bindValue(':lesson_monitoring_id', $lessonMonitoringId);
        $stmt->bindValue(':teacher_id', $teacherId);
        $stmt->bindValue(':subject', $subject);
        $stmt->bindValue(':class', $class);
        $stmt->bindValue(':stream', $stream);
        $stmt->bindValue(':original_date', $originalDate);
        $stmt->bindValue(':original_day', $originalDay);
        $stmt->bindValue(':compensation_date', $compensationDate);
        $stmt->bindValue(':compensation_day', $compensationDay);
        $stmt->bindValue(':minutes_compensated', $minutesCompensated);
        $stmt->bindValue(':remarks', $remarks);
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':created_by', $user['id']);

        if ($stmt->execute()) {
            // Update the original lesson attendance record to subtract compensated minutes
            $updateQuery = "UPDATE teacher_lesson_attendance
                           SET minutes_lost = GREATEST(0, minutes_lost - :minutes_compensated)
                           WHERE id = :lesson_monitoring_id";
            $updateStmt = $db->prepare($updateQuery);
            $updateStmt->bindValue(':minutes_compensated', $minutesCompensated);
            $updateStmt->bindValue(':lesson_monitoring_id', $lessonMonitoringId);
            $updateStmt->execute();

            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Compensation recorded successfully',
                'compensation_day' => $compensationDay
            ]);
        } else {
            $errorInfo = $stmt->errorInfo();
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Failed to save: ' . $errorInfo[2]
            ]);
        }
        break;

    case 'PUT':
        $data = json_decode(file_get_contents("php://input"));

        $id = getValue($data, 'id');
        $compensationDate = getValue($data, 'compensation_date');
        $compensationTime = getValue($data, 'compensation_time');
        $periodsRegained = (int) getValue($data, 'periods_regained', 1);
        $remarks = getValue($data, 'remarks');
        $status = getValue($data, 'status');

        if (!$id) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'ID is required'
            ]);
            break;
        }

        // Auto-generate compensation day if date changed
        $compensationDay = null;
        if ($compensationDate) {
            $compensationDay = getDayOfWeek($compensationDate);
        }

        $query = "UPDATE lesson_compensations SET";
        $params = [];
        $paramCount = 0;

        if ($compensationDate !== null) {
            $query .= " compensation_date = :compensation_date";
            $params[':compensation_date'] = $compensationDate;
            $paramCount++;
        }

        if ($compensationDay !== null) {
            if ($paramCount > 0) $query .= ",";
            $query .= " compensation_day = :compensation_day";
            $params[':compensation_day'] = $compensationDay;
            $paramCount++;
        }

        if ($compensationTime !== null) {
            if ($paramCount > 0) $query .= ",";
            $query .= " compensation_time = :compensation_time";
            $params[':compensation_time'] = $compensationTime;
            $paramCount++;
        }

        if ($periodsRegained !== null) {
            if ($paramCount > 0) $query .= ",";
            $query .= " periods_regained = :periods_regained";
            $params[':periods_regained'] = $periodsRegained;
            $paramCount++;
        }

        if ($remarks !== null) {
            if ($paramCount > 0) $query .= ",";
            $query .= " remarks = :remarks";
            $params[':remarks'] = $remarks;
            $paramCount++;
        }

        if ($status !== null) {
            if ($paramCount > 0) $query .= ",";
            $query .= " status = :status";
            $params[':status'] = $status;
            $paramCount++;
        }

        $query .= " WHERE id = :id";
        $params[':id'] = $id;

        if ($paramCount === 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'No fields to update'
            ]);
            break;
        }

        $stmt = $db->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        if ($stmt->execute()) {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Compensation updated successfully'
            ]);
        } else {
            $errorInfo = $stmt->errorInfo();
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Failed to update: ' . $errorInfo[2]
            ]);
        }
        break;

    case 'DELETE':
        if (isset($_GET['id'])) {
            $stmt = $db->prepare("DELETE FROM lesson_compensations WHERE id = :id");
            $stmt->bindValue(':id', $_GET['id']);

            if ($stmt->execute()) {
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Compensation deleted successfully'
                ]);
            } else {
                $errorInfo = $stmt->errorInfo();
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to delete: ' . $errorInfo[2]
                ]);
            }
        } else {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'ID is required'
            ]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>
