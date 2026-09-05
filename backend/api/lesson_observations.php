<?php
declare(strict_types=1);

require_once '../config/Database.php';
require_once '../config/JWT.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function sendJson(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function getAuthorizationHeader(): string
{
    $headers = [];
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
    }
    if (!empty($headers['Authorization'])) {
        return trim((string)$headers['Authorization']);
    }
    if (!empty($headers['authorization'])) {
        return trim((string)$headers['authorization']);
    }
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        return trim((string)$_SERVER['HTTP_AUTHORIZATION']);
    }
    if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        return trim((string)$_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
    }
    return '';
}

function optionalAuth(): void
{
    $auth = getAuthorizationHeader();
    if ($auth === '') {
        return;
    }
    $token = preg_replace('/^Bearer\s+/i', '', $auth);
    $jwt = new JWT();
    $payload = $jwt->decode($token);
    if (!$payload) {
        sendJson(401, [
            'success' => false,
            'error' => 'Invalid or expired token'
        ]);
    }
}

function bindValues(PDOStatement $stmt, array $params): void
{
    foreach ($params as $key => $value) {
        $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stmt->bindValue($key, $value, $type);
    }
}

optionalAuth();

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'GET') {
    if ($action === 'list') {
        getLessonObservations($db);
    } elseif ($action === 'view') {
        viewLessonObservation($db);
    } elseif ($action === 'stats') {
        getObservationStats($db);
    } else {
        sendJson(400, ['success' => false, 'error' => 'Invalid action']);
    }
} elseif ($method === 'POST') {
    if ($action === 'create') {
        createLessonObservation($db);
    } elseif ($action === 'update') {
        updateLessonObservation($db);
    } else {
        sendJson(400, ['success' => false, 'error' => 'Invalid action']);
    }
} elseif ($method === 'DELETE') {
    if ($action === 'delete') {
        deleteLessonObservation($db);
    } else {
        sendJson(400, ['success' => false, 'error' => 'Invalid action']);
    }
} else {
    sendJson(405, ['success' => false, 'error' => 'Method not allowed']);
}

function getLessonObservations(PDO $db): void
{
    $teacherId = $_GET['teacher_id'] ?? '';
    $subjectId = $_GET['subject_id'] ?? '';
    $year = $_GET['year'] ?? '';
    $round = $_GET['round'] ?? '';
    $search = $_GET['search'] ?? '';
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 10;
    $offset = ($page - 1) * $limit;

    $query = "
        SELECT lo.*, 
               t.full_name as teacher_name,
               s.name as subject_name,
               c.name as class_name,
               st.name as stream_name
        FROM lesson_observations lo
        LEFT JOIN teachers t ON lo.teacher_id = t.id
        LEFT JOIN subjects s ON lo.subject_id = s.id
        LEFT JOIN classes c ON lo.class_id = c.id
        LEFT JOIN streams st ON lo.stream_id = st.id
        WHERE 1=1
    ";
    $countQuery = "
        SELECT COUNT(*) as total FROM lesson_observations lo
        LEFT JOIN teachers t ON lo.teacher_id = t.id
        WHERE 1=1
    ";
    $params = [];

    if ($teacherId !== '') {
        $query .= " AND lo.teacher_id = :teacher_id";
        $countQuery .= " AND lo.teacher_id = :teacher_id";
        $params[':teacher_id'] = (int)$teacherId;
    }

    if ($subjectId !== '') {
        $query .= " AND lo.subject_id = :subject_id";
        $countQuery .= " AND lo.subject_id = :subject_id";
        $params[':subject_id'] = (int)$subjectId;
    }

    if ($year !== '') {
        $query .= " AND lo.year = :year";
        $countQuery .= " AND lo.year = :year";
        $params[':year'] = (int)$year;
    }

    if ($round !== '') {
        $query .= " AND lo.round = :round";
        $countQuery .= " AND lo.round = :round";
        $params[':round'] = (int)$round;
    }

    if ($search !== '') {
        $query .= " AND (t.full_name LIKE :search OR s.name LIKE :search)";
        $countQuery .= " AND (t.full_name LIKE :search OR s.name LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }

    $query .= " ORDER BY lo.created_at DESC LIMIT :limit OFFSET :offset";

    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $observations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $countStmt = $db->prepare($countQuery);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $totalResult = $countStmt->fetch(PDO::FETCH_ASSOC);
    $total = (int)$totalResult['total'];
    $totalPages = (int)ceil($total / $limit);

    sendJson(200, [
        'success' => true,
        'observations' => $observations,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => $totalPages
        ]
    ]);
}

function viewLessonObservation(PDO $db): void
{
    $id = (int)($_GET['id'] ?? 0);

    if ($id <= 0) {
        sendJson(400, ['success' => false, 'error' => 'Observation ID is required']);
    }

    $stmt = $db->prepare("
        SELECT lo.*, 
               t.full_name as teacher_name,
               s.name as subject_name,
               c.name as class_name,
               st.name as stream_name
        FROM lesson_observations lo
        LEFT JOIN teachers t ON lo.teacher_id = t.id
        LEFT JOIN subjects s ON lo.subject_id = s.id
        LEFT JOIN classes c ON lo.class_id = c.id
        LEFT JOIN streams st ON lo.stream_id = st.id
        WHERE lo.id = :id
    ");
    $stmt->execute([':id' => $id]);
    $observation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$observation) {
        sendJson(404, ['success' => false, 'error' => 'Observation not found']);
    }

    sendJson(200, ['success' => true, 'observation' => $observation]);
}

function createLessonObservation(PDO $db): void
{
    $data = $_POST;

    $required = ['teacher_id', 'subject_id', 'class_id', 'stream_id', 'term', 'year', 'round', 'total_score'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
            sendJson(400, ['success' => false, 'error' => "{$field} is required"]);
        }
    }

    // Validate total score
    $totalScore = (float)$data['total_score'];
    if ($totalScore < 0 || $totalScore > 100) {
        sendJson(400, ['success' => false, 'error' => 'Total score must be between 0 and 100']);
    }

    // Validate round
    $round = (int)$data['round'];
    if ($round < 1 || $round > 4) {
        sendJson(400, ['success' => false, 'error' => 'Round must be between 1 and 4']);
    }

    // Calculate rating and category
    $calculatedRating = $totalScore / 25;
    
    if ($calculatedRating >= 3.5) {
        $category = 'Outstanding';
    } elseif ($calculatedRating >= 3.0) {
        $category = 'Very Good';
    } elseif ($calculatedRating >= 2.5) {
        $category = 'Good';
    } elseif ($calculatedRating >= 2.0) {
        $category = 'Fair';
    } else {
        $category = 'Below Expectation';
    }

    // Insert observation with simplified structure
    $query = "
        INSERT INTO lesson_observations (
            teacher_id, subject_id, class_id, stream_id, term, year, round,
            total_score, calculated_rating, performance_category,
            strengths_observed, general_comment, areas_for_improvement, created_by
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";

    $stmt = $db->prepare($query);
    $ok = $stmt->execute([
        (int)$data['teacher_id'],
        (int)$data['subject_id'],
        (int)$data['class_id'],
        (int)$data['stream_id'],
        $data['term'],
        (int)$data['year'],
        $round,
        $totalScore,
        $calculatedRating,
        $category,
        $data['strengths_observed'] ?? '',
        $data['general_comment'] ?? '',
        $data['areas_for_improvement'] ?? '',
        1 // created_by - should be from JWT token in production
    ]);

    if (!$ok) {
        sendJson(500, [
            'success' => false,
            'error' => 'Failed to create observation',
            'db_error' => $stmt->errorInfo()
        ]);
    }

    $observationId = (int)$db->lastInsertId();

    sendJson(201, [
        'success' => true,
        'message' => 'Observation created successfully',
        'observation_id' => $observationId
    ]);
}

function updateLessonObservation(PDO $db): void
{
    $data = $_POST;

    $id = (int)($data['id'] ?? 0);

    if ($id <= 0) {
        sendJson(400, ['success' => false, 'error' => 'Observation ID is required']);
    }

    $existingStmt = $db->prepare("SELECT * FROM lesson_observations WHERE id = ?");
    $existingStmt->execute([$id]);
    $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        sendJson(404, ['success' => false, 'error' => 'Observation not found']);
    }

    $required = ['teacher_id', 'subject_id', 'class_id', 'stream_id', 'term', 'year', 'round', 'total_score'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
            sendJson(400, ['success' => false, 'error' => "{$field} is required"]);
        }
    }

    // Validate total score
    $totalScore = (float)$data['total_score'];
    if ($totalScore < 0 || $totalScore > 100) {
        sendJson(400, ['success' => false, 'error' => 'Total score must be between 0 and 100']);
    }

    // Validate round
    $round = (int)$data['round'];
    if ($round < 1 || $round > 4) {
        sendJson(400, ['success' => false, 'error' => 'Round must be between 1 and 4']);
    }

    // Calculate rating and category
    $calculatedRating = $totalScore / 25;
    
    if ($calculatedRating >= 3.5) {
        $category = 'Outstanding';
    } elseif ($calculatedRating >= 3.0) {
        $category = 'Very Good';
    } elseif ($calculatedRating >= 2.5) {
        $category = 'Good';
    } elseif ($calculatedRating >= 2.0) {
        $category = 'Fair';
    } else {
        $category = 'Below Expectation';
    }

    // Update observation with simplified structure
    $query = "
        UPDATE lesson_observations SET
            teacher_id = ?,
            subject_id = ?,
            class_id = ?,
            stream_id = ?,
            term = ?,
            year = ?,
            round = ?,
            total_score = ?,
            calculated_rating = ?,
            performance_category = ?,
            strengths_observed = ?,
            general_comment = ?,
            areas_for_improvement = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ";

    $stmt = $db->prepare($query);
    $ok = $stmt->execute([
        (int)$data['teacher_id'],
        (int)$data['subject_id'],
        (int)$data['class_id'],
        (int)$data['stream_id'],
        $data['term'],
        (int)$data['year'],
        $round,
        $totalScore,
        $calculatedRating,
        $category,
        $data['strengths_observed'] ?? '',
        $data['general_comment'] ?? '',
        $data['areas_for_improvement'] ?? '',
        $id
    ]);

    if (!$ok) {
        sendJson(500, [
            'success' => false,
            'error' => 'Failed to update observation',
            'db_error' => $stmt->errorInfo()
        ]);
    }

    sendJson(200, [
        'success' => true,
        'message' => 'Observation updated successfully'
    ]);
}

function deleteLessonObservation(PDO $db): void
{
    $id = (int)($_GET['id'] ?? 0);

    if ($id <= 0) {
        sendJson(400, ['success' => false, 'error' => 'Observation ID is required']);
    }

    $stmt = $db->prepare("DELETE FROM lesson_observations WHERE id = ?");
    $ok = $stmt->execute([$id]);

    if (!$ok) {
        sendJson(500, ['success' => false, 'error' => 'Failed to delete observation']);
    }

    sendJson(200, ['success' => true, 'message' => 'Observation deleted successfully']);
}

function getObservationStats(PDO $db): void
{
    $teacherId = $_GET['teacher_id'] ?? '';
    $year = $_GET['year'] ?? '';

    $where = "WHERE 1=1";
    $params = [];

    if ($teacherId !== '') {
        $where .= " AND teacher_id = :teacher_id";
        $params[':teacher_id'] = (int)$teacherId;
    }

    if ($year !== '') {
        $where .= " AND year = :year";
        $params[':year'] = (int)$year;
    }

    $query = "
        SELECT 
            COUNT(*) as total_observations,
            AVG(calculated_rating) as avg_rating,
            COUNT(CASE WHEN performance_category = 'Outstanding' THEN 1 END) as outstanding_count,
            COUNT(CASE WHEN performance_category = 'Very Good' THEN 1 END) as very_good_count,
            COUNT(CASE WHEN performance_category = 'Good' THEN 1 END) as good_count,
            COUNT(CASE WHEN performance_category = 'Fair' THEN 1 END) as fair_count,
            COUNT(CASE WHEN performance_category = 'Below Expectation' THEN 1 END) as below_expectation_count
        FROM lesson_observations
        {$where}
    ";

    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    sendJson(200, ['success' => true, 'stats' => $stats]);
}
