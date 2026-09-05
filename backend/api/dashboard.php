<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/Database.php';

$database = new Database();
$db = $database->getConnection();

function respond($success, $message = '', $data = null, $status = 200) {
    http_response_code($status);
    $payload = ['success' => $success];
    if ($message !== '') $payload['message'] = $message;
    if ($data !== null) $payload['data'] = $data;
    echo json_encode($payload);
    exit();
}

$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$term = isset($_GET['term']) ? (int)$_GET['term'] : 1;
$classFilter = trim($_GET['class'] ?? '');

try {
    $teachersWhere = "WHERE 1=1";
    $teachersParams = [];

    if ($classFilter !== '') {
        $teachersWhere .= " AND class = :class";
        $teachersParams[':class'] = $classFilter;
    }

    $stmt = $db->prepare("SELECT COUNT(*) AS total_teachers FROM teachers $teachersWhere");
    foreach ($teachersParams as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $totalTeachers = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total_teachers'] ?? 0);

    $stmt = $db->prepare("SELECT COUNT(*) AS active_teachers FROM teachers $teachersWhere AND is_active = 1");
    foreach ($teachersParams as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $activeTeachers = (int)($stmt->fetch(PDO::FETCH_ASSOC)['active_teachers'] ?? 0);

    $inactiveTeachers = max(0, $totalTeachers - $activeTeachers);

    $classListStmt = $db->query("SELECT DISTINCT class FROM teachers WHERE class IS NOT NULL AND class <> '' ORDER BY class ASC");
    $availableClasses = $classListStmt ? $classListStmt->fetchAll(PDO::FETCH_COLUMN) : [];

    $totalObservations = 0;
    try {
        $obsWhere = "WHERE year = :year AND term = :term";
        $obsParams = [
            ':year' => $year,
            ':term' => $term
        ];
        if ($classFilter !== '') {
            $obsWhere .= " AND class = :class";
            $obsParams[':class'] = $classFilter;
        }

        $stmt = $db->prepare("SELECT COUNT(*) AS total_observations FROM lesson_observations $obsWhere");
        foreach ($obsParams as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $totalObservations = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total_observations'] ?? 0);
    } catch (Throwable $e) {
        $totalObservations = 0;
    }

    $teacherOfWeek = 'N/A';
    $teacherOfTerm = 'N/A';

    $topTeachers = [];
    $lowTeachers = [];

    try {
        $dutyWhere = "WHERE year = :year AND term = :term";
        $dutyParams = [
            ':year' => $year,
            ':term' => $term
        ];
        if ($classFilter !== '') {
            $dutyWhere .= " AND class = :class";
            $dutyParams[':class'] = $classFilter;
        }

        $stmt = $db->prepare("
            SELECT teacher_name, AVG(total_score) AS avg_score
            FROM duty_performance
            $dutyWhere
            GROUP BY teacher_name
            ORDER BY avg_score DESC
            LIMIT 1
        ");
        foreach ($dutyParams as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $teacherOfWeek = $row['teacher_name'] . ' (' . round((float)$row['avg_score'], 1) . '%)';
        }

        $stmt = $db->prepare("
            SELECT teacher_name, AVG(agp) AS avg_agp
            FROM subject_teacher_performance
            $dutyWhere
            GROUP BY teacher_name
            ORDER BY avg_agp DESC
            LIMIT 1
        ");
        foreach ($dutyParams as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $teacherOfTerm = $row['teacher_name'] . ' (' . round((float)$row['avg_agp'], 1) . ')';
        }

        $stmt = $db->prepare("
            SELECT teacher_name, AVG(total_score) AS avg_score
            FROM duty_performance
            $dutyWhere
            GROUP BY teacher_name
            ORDER BY avg_score DESC
            LIMIT 5
        ");
        foreach ($dutyParams as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $topTeachers = [];
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $topTeachers[] = [
                'name' => $r['teacher_name'],
                'score' => round((float)$r['avg_score'], 1)
            ];
        }

        $stmt = $db->prepare("
            SELECT teacher_name, AVG(total_score) AS avg_score
            FROM duty_performance
            $dutyWhere
            GROUP BY teacher_name
            HAVING AVG(total_score) < 60
            ORDER BY avg_score ASC
            LIMIT 5
        ");
        foreach ($dutyParams as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $lowTeachers = [];
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $lowTeachers[] = [
                'name' => $r['teacher_name'],
                'score' => round((float)$r['avg_score'], 1)
            ];
        }
    } catch (Throwable $e) {
        $teacherOfWeek = 'N/A';
        $teacherOfTerm = 'N/A';
        $topTeachers = [];
        $lowTeachers = [];
    }

    $dutyLabels = [];
    $dutyScores = [];
    try {
        $stmt = $db->prepare("
            SELECT teacher_name, AVG(total_score) AS avg_score
            FROM duty_performance
            WHERE year = :year AND term = :term
            GROUP BY teacher_name
            ORDER BY avg_score DESC
            LIMIT 10
        ");
        $stmt->execute([
            ':year' => $year,
            ':term' => $term
        ]);
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $dutyLabels[] = $r['teacher_name'];
            $dutyScores[] = round((float)$r['avg_score'], 1);
        }
    } catch (Throwable $e) {
        $dutyLabels = [];
        $dutyScores = [];
    }

    $trendLabels = [];
    $trendData = [];
    try {
        $stmt = $db->prepare("
            SELECT teacher_name, AVG(agp) AS avg_agp
            FROM subject_teacher_performance
            WHERE year = :year
            GROUP BY teacher_name
            ORDER BY avg_agp DESC
            LIMIT 10
        ");
        $stmt->execute([
            ':year' => $year
        ]);
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $trendLabels[] = $r['teacher_name'];
            $trendData[] = round((float)$r['avg_agp'], 1);
        }
    } catch (Throwable $e) {
        $trendLabels = [];
        $trendData = [];
    }

    $timeLabels = [];
    $timeData = [];
    try {
        $stmt = $db->prepare("
            SELECT teacher_name, SUM(total_time_lost) AS total_time
            FROM lesson_monitoring
            WHERE year = :year AND term = :term
            GROUP BY teacher_name
            ORDER BY total_time DESC
            LIMIT 10
        ");
        $stmt->execute([
            ':year' => $year,
            ':term' => $term
        ]);
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $timeLabels[] = $r['teacher_name'];
            $timeData[] = (int)$r['total_time'];
        }
    } catch (Throwable $e) {
        $timeLabels = [];
        $timeData = [];
    }

    respond(true, 'Dashboard loaded', [
        'stats' => [
            'year' => $year,
            'term' => $term,
            'class_filter' => $classFilter,
            'total_teachers' => $totalTeachers,
            'active_teachers' => $activeTeachers,
            'inactive_teachers' => $inactiveTeachers,
            'total_observations' => $totalObservations,
            'teacher_of_week' => $teacherOfWeek,
            'teacher_of_term' => $teacherOfTerm,
            'top_teachers' => $topTeachers,
            'low_teachers' => $lowTeachers,
            'available_classes' => $availableClasses
        ],
        'charts' => [
            'duty_labels' => $dutyLabels,
            'duty_scores' => $dutyScores,
            'trend_labels' => $trendLabels,
            'trend_data' => $trendData,
            'time_labels' => $timeLabels,
            'time_data' => $timeData
        ]
    ]);
} catch (Throwable $e) {
    respond(false, 'Failed to load dashboard', [
        'error' => $e->getMessage()
    ], 500);
}