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

function calcMinutes($timeIn, $timeOut) {
    $in = new DateTime($timeIn);
    $out = new DateTime($timeOut);

    if ($out <= $in) {
        $out->modify('+1 day');
    }

    $seconds = $out->getTimestamp() - $in->getTimestamp();
    return (int) round($seconds / 60);
}

function getDayOfWeek($date) {
    $dayMap = [
        1 => 'Mon',
        2 => 'Tue',
        3 => 'Wed',
        4 => 'Thu',
        5 => 'Fri',
        6 => 'Sat',
        7 => 'Sun'
    ];
    $dayNum = date('N', strtotime($date));
    return $dayMap[$dayNum] ?? '';
}

function getValue($data, $key, $default = null) {
    return isset($data->$key) ? $data->$key : $default;
}

$method = $_SERVER['REQUEST_METHOD'];

// Handle method override for PUT/DELETE via POST
if ($method === 'POST' && isset($_POST['_method'])) {
    $method = strtoupper($_POST['_method']);
}

// Also check JSON body for method override
if ($method === 'POST') {
    $input = json_decode(file_get_contents("php://input"), true);
    if (isset($input['_method'])) {
        $method = strtoupper($input['_method']);
    }
}

switch ($method) {
    case 'GET':
        $query = "SELECT a.*, t.full_name AS teacher_name,
                  (SELECT COUNT(*) FROM lesson_compensations lc WHERE lc.lesson_monitoring_id = a.id) as compensation_count,
                  (SELECT GROUP_CONCAT(CONCAT(lc.compensation_date, ' (', lc.status, ')') SEPARATOR '; ')
                   FROM lesson_compensations lc
                   WHERE lc.lesson_monitoring_id = a.id) as compensation_details,
                  (SELECT lc.status FROM lesson_compensations lc
                   WHERE lc.lesson_monitoring_id = a.id
                   ORDER BY lc.created_at DESC LIMIT 1) as compensation_status
                  FROM teacher_lesson_attendance a
                  LEFT JOIN teachers t ON a.teacher_id = t.id
                  WHERE 1=1";
        $params = [];

        if (isset($_GET['teacher_id']) && $_GET['teacher_id'] !== '') {
            $query .= " AND a.teacher_id = :teacher_id";
            $params[':teacher_id'] = $_GET['teacher_id'];
        }

        if (isset($_GET['year']) && $_GET['year'] !== '') {
            $query .= " AND a.year = :year";
            $params[':year'] = $_GET['year'];
        }

        if (isset($_GET['term']) && $_GET['term'] !== '') {
            $query .= " AND a.term = :term";
            $params[':term'] = $_GET['term'];
        }

        if (isset($_GET['week_number']) && $_GET['week_number'] !== '') {
            $query .= " AND a.week_number = :week_number";
            $params[':week_number'] = $_GET['week_number'];
        }

        $query .= " ORDER BY a.week_number ASC, a.attendance_date ASC, a.time_in ASC, t.full_name ASC, a.id ASC";

        $stmt = $db->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $summaryTotalLost = 0;
        foreach ($rows as &$row) {
            $summaryTotalLost += (int)$row['minutes_lost'];
            $row['cumulative_minutes_lost'] = $summaryTotalLost;
            $row['equivalent_single_lessons'] = (int) floor(((int)$row['minutes_lost']) / 40);
            $row['equivalent_double_lessons'] = (int) floor(((int)$row['minutes_lost']) / 80);
            $row['is_compensated'] = ($row['compensation_count'] ?? 0) > 0;
        }

        $summary = [
            'total_minutes_lost' => $summaryTotalLost,
            'equivalent_single_lessons' => (int) floor($summaryTotalLost / 40),
            'equivalent_double_lessons' => (int) floor($summaryTotalLost / 80),
            'records' => count($rows)
        ];

        echo json_encode([
            'success' => true,
            'data' => $rows,
            'summary' => $summary
        ]);
        break;

    case 'POST':
        $data = json_decode(file_get_contents("php://input"));

        $teacherId = getValue($data, 'teacher_id');
        $subject = getValue($data, 'subject');
        $class = getValue($data, 'class');
        $stream = getValue($data, 'stream');
        $weekNumber = getValue($data, 'week_number');
        $attendanceDate = getValue($data, 'attendance_date');
        $timeIn = getValue($data, 'time_in');
        $timeOut = getValue($data, 'time_out');
        $expectedMinutes = (int) getValue($data, 'expected_minutes', 0);
        $year = getValue($data, 'year');
        $term = getValue($data, 'term');

        if (
            $teacherId !== null && $subject && $class && $stream &&
            $weekNumber !== null && $attendanceDate && $timeIn && $timeOut &&
            $year !== null && $term !== null
        ) {
            $actualMinutes = calcMinutes($timeIn, $timeOut);
            $minutesLost = max(0, $expectedMinutes - $actualMinutes);
            $dayOfWeek = getDayOfWeek($attendanceDate);

            $query = "INSERT INTO teacher_lesson_attendance
                      (teacher_id, subject, class, stream, week_number, attendance_date, day_of_week, time_in, time_out,
                       expected_minutes, actual_minutes, minutes_lost, year, term)
                      VALUES
                      (:teacher_id, :subject, :class, :stream, :week_number, :attendance_date, :day_of_week, :time_in, :time_out,
                       :expected_minutes, :actual_minutes, :minutes_lost, :year, :term)";

            $stmt = $db->prepare($query);
            $stmt->bindValue(':teacher_id', $teacherId);
            $stmt->bindValue(':subject', $subject);
            $stmt->bindValue(':class', $class);
            $stmt->bindValue(':stream', $stream);
            $stmt->bindValue(':week_number', $weekNumber);
            $stmt->bindValue(':attendance_date', $attendanceDate);
            $stmt->bindValue(':day_of_week', $dayOfWeek);
            $stmt->bindValue(':time_in', $timeIn);
            $stmt->bindValue(':time_out', $timeOut);
            $stmt->bindValue(':expected_minutes', $expectedMinutes);
            $stmt->bindValue(':actual_minutes', $actualMinutes);
            $stmt->bindValue(':minutes_lost', $minutesLost);
            $stmt->bindValue(':year', $year);
            $stmt->bindValue(':term', $term);

            if ($stmt->execute()) {
                http_response_code(201);
                echo json_encode([
                    'success' => true,
                    'message' => 'Attendance saved successfully',
                    'day_of_week' => $dayOfWeek
                ]);
            } else {
                $errorInfo = $stmt->errorInfo();
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to save: ' . $errorInfo[2]
                ]);
            }
        } else {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Required fields missing'
            ]);
        }
        break;

    case 'PUT':
        $data = json_decode(file_get_contents("php://input"));

        error_log("PUT request - Query ID: " . ($_GET['id'] ?? 'none'));
        error_log("PUT request - Body ID: " . (isset($data->id) ? $data->id : 'none'));
        error_log("PUT request - Data: " . json_encode($data));

        $id = isset($_GET['id']) ? $_GET['id'] : getValue($data, 'id');
        $teacherId = getValue($data, 'teacher_id');
        $subject = getValue($data, 'subject');
        $class = getValue($data, 'class');
        $stream = getValue($data, 'stream');
        $weekNumber = getValue($data, 'week_number');
        $attendanceDate = getValue($data, 'attendance_date');
        $timeIn = getValue($data, 'time_in');
        $timeOut = getValue($data, 'time_out');
        $expectedMinutes = (int) getValue($data, 'expected_minutes', 0);
        $year = getValue($data, 'year');
        $term = getValue($data, 'term');

        error_log("PUT request - Final ID: $id");

        if ($id !== null) {
            $actualMinutes = calcMinutes($timeIn, $timeOut);
            $minutesLost = max(0, $expectedMinutes - $actualMinutes);
            $dayOfWeek = getDayOfWeek($attendanceDate);

            $query = "UPDATE teacher_lesson_attendance SET
                      teacher_id = :teacher_id,
                      subject = :subject,
                      class = :class,
                      stream = :stream,
                      week_number = :week_number,
                      attendance_date = :attendance_date,
                      day_of_week = :day_of_week,
                      time_in = :time_in,
                      time_out = :time_out,
                      expected_minutes = :expected_minutes,
                      actual_minutes = :actual_minutes,
                      minutes_lost = :minutes_lost,
                      year = :year,
                      term = :term
                      WHERE id = :id";

            $stmt = $db->prepare($query);
            $stmt->bindValue(':id', $id);
            $stmt->bindValue(':teacher_id', $teacherId);
            $stmt->bindValue(':subject', $subject);
            $stmt->bindValue(':class', $class);
            $stmt->bindValue(':stream', $stream);
            $stmt->bindValue(':week_number', $weekNumber);
            $stmt->bindValue(':attendance_date', $attendanceDate);
            $stmt->bindValue(':day_of_week', $dayOfWeek);
            $stmt->bindValue(':time_in', $timeIn);
            $stmt->bindValue(':time_out', $timeOut);
            $stmt->bindValue(':expected_minutes', $expectedMinutes);
            $stmt->bindValue(':actual_minutes', $actualMinutes);
            $stmt->bindValue(':minutes_lost', $minutesLost);
            $stmt->bindValue(':year', $year);
            $stmt->bindValue(':term', $term);

            if ($stmt->execute()) {
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Attendance updated successfully',
                    'day_of_week' => $dayOfWeek
                ]);
            } else {
                $errorInfo = $stmt->errorInfo();
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to update: ' . $errorInfo[2]
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

    case 'DELETE':
        if (isset($_GET['id'])) {
            $stmt = $db->prepare("DELETE FROM teacher_lesson_attendance WHERE id = :id");
            $stmt->bindValue(':id', $_GET['id']);

            if ($stmt->execute()) {
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Attendance deleted successfully'
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