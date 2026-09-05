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
    } elseif ($action === 'teacher-observations') {
        getTeacherObservations($db);
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
    $term = $_GET['term'] ?? '';
    $round = $_GET['round'] ?? '';
    $search = $_GET['search'] ?? '';
    $classFilter = $_GET['class'] ?? '';
    $streamFilter = $_GET['stream'] ?? '';
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 10;
    $offset = ($page - 1) * $limit;

    $baseFrom = "
        FROM lesson_observations lo
        LEFT JOIN teachers t ON lo.teacher_id = t.id
        LEFT JOIN subjects s ON lo.subject_id = s.id
        LEFT JOIN classes c ON lo.stream_id = c.id
        WHERE 1=1
    ";

    $query = "
        SELECT lo.id,
               lo.teacher_id,
               lo.subject_id,
               lo.class_id,
               lo.stream_id,
               lo.term,
               lo.year,
               lo.round,
               lo.total_score,
               lo.calculated_rating,
               lo.performance_category,
               lo.strengths_observed,
               lo.general_comment,
               lo.areas_for_improvement,
               lo.created_by,
               lo.created_at,
               lo.updated_at,
               t.full_name AS teacher_name,
               s.subject_name AS subject,
               (SELECT class_name FROM classes WHERE id = lo.class_id) AS class,
               (SELECT stream_name FROM classes WHERE id = lo.stream_id) AS stream
        {$baseFrom}
    ";

    $countQuery = "
        SELECT COUNT(*) AS total
        {$baseFrom}
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

    if ($term !== '' && $term !== 'all') {
        $query .= " AND lo.term = :term";
        $countQuery .= " AND lo.term = :term";
        $params[':term'] = (int)$term;
    }

    if ($round !== '') {
        $query .= " AND lo.round = :round";
        $countQuery .= " AND lo.round = :round";
        $params[':round'] = (int)$round;
    }

    if ($classFilter !== '') {
        $query .= " AND c.class_name = :class";
        $countQuery .= " AND c.class_name = :class";
        $params[':class'] = $classFilter;
    }

    if ($streamFilter !== '') {
        $query .= " AND lo.stream_id = :stream";
        $countQuery .= " AND lo.stream_id = :stream";
        $params[':stream'] = $streamFilter;
    }

    if ($search !== '') {
        $query .= " AND (
            t.full_name LIKE :search
            OR s.subject_name LIKE :search
            OR c.class_name LIKE :search
            OR lo.stream_id LIKE :search
            OR (SELECT stream_name FROM classes WHERE id = lo.stream_id) LIKE :search
        )";
        $countQuery .= " AND (
            t.full_name LIKE :search
            OR s.subject_name LIKE :search
            OR c.class_name LIKE :search
            OR lo.stream_id LIKE :search
            OR (SELECT stream_name FROM classes WHERE id = lo.stream_id) LIKE :search
        )";
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
    $total = (int)($totalResult['total'] ?? 0);
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
        SELECT lo.id,
               lo.teacher_id,
               lo.subject_id,
               lo.class_id,
               lo.stream_id,
               lo.term,
               lo.year,
               lo.round,
               lo.total_score,
               lo.calculated_rating,
               lo.performance_category,
               lo.strengths_observed,
               lo.general_comment,
               lo.areas_for_improvement,
               lo.created_by,
               lo.created_at,
               lo.updated_at,
               t.full_name AS teacher_name,
               s.subject_name AS subject,
               (SELECT class_name FROM classes WHERE id = lo.class_id) AS class,
               (SELECT stream_name FROM classes WHERE id = lo.stream_id) AS stream
        FROM lesson_observations lo
        LEFT JOIN teachers t ON lo.teacher_id = t.id
        LEFT JOIN subjects s ON lo.subject_id = s.id
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
    $input = file_get_contents('php://input');
    $data = json_decode($input, true) ?? $_POST;

    $required = ['teacher_id', 'subject_id', 'class_id', 'term', 'year', 'round', 'total_score'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
            sendJson(400, ['success' => false, 'error' => "{$field} is required"]);
        }
    }

    $totalScore = (float)$data['total_score'];
    if ($totalScore < 0 || $totalScore > 100) {
        sendJson(400, ['success' => false, 'error' => 'Total score must be between 0 and 100']);
    }

    $round = (int)$data['round'];
    if ($round < 1 || $round > 4) {
        sendJson(400, ['success' => false, 'error' => 'Round must be between 1 and 4']);
    }

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

    // Note: Same teacher can be added with different class/stream combinations
    // Each unique combination of teacher_id, subject_id, class_id, stream_id, term, year, round creates a new row
    $query = "
        INSERT INTO lesson_observations (
            teacher_id, subject_id, class_id, stream_id, term, year, round,
            total_score, calculated_rating, performance_category,
            strengths_observed, general_comment, areas_for_improvement, created_by
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";

    $streamId = isset($data['stream_id']) && $data['stream_id'] !== '' && $data['stream_id'] !== '0'
        ? (int)$data['stream_id']
        : null;

    $stmt = $db->prepare($query);
    $ok = $stmt->execute([
        (int)$data['teacher_id'],
        (int)$data['subject_id'],
        (int)$data['class_id'],
        $streamId,
        $data['term'],
        (int)$data['year'],
        $round,
        $totalScore,
        $calculatedRating,
        $category,
        $data['strengths_observed'] ?? '',
        $data['general_comment'] ?? '',
        $data['areas_for_improvement'] ?? '',
        1
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
    $input = file_get_contents('php://input');
    $data = json_decode($input, true) ?? $_POST;
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

    $required = ['teacher_id', 'subject_id', 'class_id', 'term', 'year', 'round', 'total_score'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
            sendJson(400, ['success' => false, 'error' => "{$field} is required"]);
        }
    }

    $totalScore = (float)$data['total_score'];
    if ($totalScore < 0 || $totalScore > 100) {
        sendJson(400, ['success' => false, 'error' => 'Total score must be between 0 and 100']);
    }

    $round = (int)$data['round'];
    if ($round < 1 || $round > 4) {
        sendJson(400, ['success' => false, 'error' => 'Round must be between 1 and 4']);
    }

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

    $streamId = isset($data['stream_id']) && $data['stream_id'] !== '' && $data['stream_id'] !== '0'
        ? (int)$data['stream_id']
        : null;

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
        $streamId,
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
            COUNT(*) AS total_observations,
            AVG(calculated_rating) AS avg_rating,
            COUNT(CASE WHEN performance_category = 'Outstanding' THEN 1 END) AS outstanding_count,
            COUNT(CASE WHEN performance_category = 'Very Good' THEN 1 END) AS very_good_count,
            COUNT(CASE WHEN performance_category = 'Good' THEN 1 END) AS good_count,
            COUNT(CASE WHEN performance_category = 'Fair' THEN 1 END) AS fair_count,
            COUNT(CASE WHEN performance_category = 'Below Expectation' THEN 1 END) AS below_expectation_count
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

function getTeacherObservations(PDO $db): void
{
    $teacherId = (int)($_GET['teacher_id'] ?? 0);

    if ($teacherId <= 0) {
        sendJson(400, ['success' => false, 'error' => 'Teacher ID is required']);
    }

    $query = "
        SELECT 
            lo.id,
            lo.teacher_id,
            lo.subject_id,
            lo.class_id,
            lo.stream_id,
            lo.term,
            lo.year,
            lo.round,
            lo.total_score,
            lo.calculated_rating,
            lo.performance_category,
            lo.strengths_observed,
            lo.general_comment,
            lo.areas_for_improvement,
            lo.created_at,
            t.full_name AS teacher_name,
            s.subject_name AS subject,
            (SELECT class_name FROM classes WHERE id = lo.class_id) AS class,
            (SELECT stream_name FROM classes WHERE id = lo.stream_id) AS stream
        FROM lesson_observations lo
        LEFT JOIN teachers t ON lo.teacher_id = t.id
        LEFT JOIN subjects s ON lo.subject_id = s.id
        WHERE lo.teacher_id = :teacher_id
        ORDER BY lo.year DESC, lo.term DESC, lo.round ASC
    ";

    $stmt = $db->prepare($query);
    $stmt->execute([':teacher_id' => $teacherId]);
    $observations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($observations)) {
        sendJson(200, [
            'success' => true,
            'teacher_name' => '',
            'observations' => [],
            'summary' => [
                'total_observations' => 0,
                'average_score' => 0,
                'average_rating' => 0,
                'highest_score' => 0,
                'lowest_score' => 0,
                'best_performance_category' => 'N/A'
            ]
        ]);
    }

    // Calculate summary statistics
    $totalObservations = count($observations);
    $totalScore = array_sum(array_column($observations, 'total_score'));
    $averageScore = $totalScore / $totalObservations;
    $totalRating = array_sum(array_column($observations, 'calculated_rating'));
    $averageRating = $totalRating / $totalObservations;
    $highestScore = max(array_column($observations, 'total_score'));
    $lowestScore = min(array_column($observations, 'total_score'));

    // Determine best performance category
    $categoryOrder = ['Outstanding' => 5, 'Very Good' => 4, 'Good' => 3, 'Fair' => 2, 'Below Expectation' => 1];
    $bestCategory = 'Below Expectation';
    $bestScore = 0;
    foreach ($observations as $obs) {
        $catScore = $categoryOrder[$obs['performance_category']] ?? 0;
        if ($catScore > $bestScore) {
            $bestScore = $catScore;
            $bestCategory = $obs['performance_category'];
        }
    }

    sendJson(200, [
        'success' => true,
        'teacher_name' => $observations[0]['teacher_name'] ?? '',
        'observations' => $observations,
        'summary' => [
            'total_observations' => $totalObservations,
            'average_score' => round($averageScore, 2),
            'average_rating' => round($averageRating, 2),
            'highest_score' => $highestScore,
            'lowest_score' => $lowestScore,
            'best_performance_category' => $bestCategory
        ]
    ]);
}