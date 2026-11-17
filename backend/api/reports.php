<?php
require_once __DIR__ . '/../config/ApiGuard.php';
api_guard();
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
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
    exit();
}

// Handle both GET and POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Try JSON body first
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input) {
        $reportType = isset($input['type']) ? $input['type'] : 'summary';
        $year = isset($input['year']) ? $input['year'] : date('Y');
        $term = isset($input['term']) ? $input['term'] : null;
    } else {
        // Try form-encoded data
        $reportType = isset($_POST['type']) ? $_POST['type'] : 'summary';
        $year = isset($_POST['year']) ? $_POST['year'] : date('Y');
        $term = isset($_POST['term']) ? $_POST['term'] : null;
    }
} else {
    $reportType = isset($_GET['type']) ? $_GET['type'] : 'summary';
    $year = isset($_GET['year']) ? $_GET['year'] : date('Y');
    $term = isset($_GET['term']) ? $_GET['term'] : null;
}

$data = [];

switch($reportType) {
    case 'weekly':
        // Weekly lesson monitoring report
        $stmt = $db->prepare("SELECT lm.*, t.full_name as teacher_name 
                              FROM lesson_monitoring lm
                              JOIN teachers t ON lm.teacher_id = t.id
                              WHERE lm.year = ? AND (? IS NULL OR lm.term = ?)
                              ORDER BY t.full_name");
        $stmt->execute([$year, $term, $term]);
        $data['lesson_monitoring'] = $stmt->fetchAll();
        break;
        
    case 'termly':
        // Termly comprehensive report
        $stmt = $db->prepare("SELECT 
            t.full_name,
            sp.subject, sp.class, sp.stream, sp.bot1, sp.eot1, sp.tc1, sp.eot2, sp.tc2, sp.eot3, sp.tc3, sp.agp,
            lo.round_1_score, lo.round_2_score, lo.average_score,
            dp.punctuality, dp.supervision, dp.cleanliness, dp.time_keeping, dp.participation, dp.percentage,
            lm.total_minutes_lost, lm.equivalent_single_lessons, lm.equivalent_double_lessons
        FROM teachers t
        LEFT JOIN subject_teacher_performance sp ON t.id = sp.teacher_id AND sp.year = ? AND sp.term = ?
        LEFT JOIN lesson_observations lo ON t.id = lo.teacher_id AND lo.year = ? AND lo.term = ?
        LEFT JOIN duty_performance dp ON t.id = dp.teacher_id AND dp.year = ? AND dp.term = ?
        LEFT JOIN lesson_monitoring lm ON t.id = lm.teacher_id AND lm.year = ? AND lm.term = ?
        WHERE t.is_active = 1
        ORDER BY t.full_name");
        $stmt->execute([$year, $term, $year, $term, $year, $term, $year, $term]);
        $data['termly_report'] = $stmt->fetchAll();
        break;
        
    case 'yearly':
        // Yearly summary report
        for ($t = 1; $t <= 3; $t++) {
            $stmt = $db->prepare("SELECT 
                t.full_name,
                AVG(sp.agp) as avg_agp,
                AVG(dp.percentage) as avg_duty,
                SUM(lm.total_minutes_lost) as total_time_lost
            FROM teachers t
            LEFT JOIN subject_teacher_performance sp ON t.id = sp.teacher_id AND sp.year = ? AND sp.term = ?
            LEFT JOIN duty_performance dp ON t.id = dp.teacher_id AND dp.year = ? AND dp.term = ?
            LEFT JOIN lesson_monitoring lm ON t.id = lm.teacher_id AND lm.year = ? AND lm.term = ?
            WHERE t.is_active = 1
            GROUP BY t.id
            ORDER BY t.full_name");
            $stmt->execute([$year, $t, $year, $t, $year, $t]);
            $data["term_$t"] = $stmt->fetchAll();
        }
        break;
        
    case 'best-teachers':
        // Best teacher awards
        $awardType = isset($_GET['award_type']) ? $_GET['award_type'] : 'week';
        
        $stmt = $db->prepare("SELECT ta.*, t.full_name as teacher_name, t.subject, t.class
                              FROM teacher_awards ta
                              JOIN teachers t ON ta.teacher_id = t.id
                              WHERE ta.year = ? AND ta.award_type = ?
                              ORDER BY ta.awarded_at DESC");
        $stmt->execute([$year, $awardType]);
        $data['awards'] = $stmt->fetchAll();
        
        // Calculate best performers for auto-award
        $stmt = $db->prepare("SELECT dp.*, t.full_name, t.subject, t.class
                             FROM duty_performance dp
                             JOIN teachers t ON dp.teacher_id = t.id
                             WHERE dp.year = ? AND (? IS NULL OR dp.term = ?)
                             ORDER BY dp.percentage DESC
                             LIMIT 10");
        $stmt->execute([$year, $term, $term]);
        $data['top_candidates'] = $stmt->fetchAll();
        break;
        
    case 'printable':
        // Single teacher printable report
        if (isset($_GET['teacher_id'])) {
            $teacherId = $_GET['teacher_id'];
            
            // Teacher info
            $stmt = $db->prepare("SELECT * FROM teachers WHERE id = ?");
            $stmt->execute([$teacherId]);
            $data['teacher'] = $stmt->fetch();
            
            // Subject performance
            $stmt = $db->prepare("SELECT * FROM subject_teacher_performance 
                                  WHERE teacher_id = ? AND year = ? AND (? IS NULL OR term = ?)
                                  ORDER BY term");
            $stmt->execute([$teacherId, $year, $term, $term]);
            $data['subject_performance'] = $stmt->fetchAll();
            
            // Duty performance
            $stmt = $db->prepare("SELECT * FROM duty_performance 
                                  WHERE teacher_id = ? AND year = ? AND (? IS NULL OR term = ?)");
            $stmt->execute([$teacherId, $year, $term, $term]);
            $data['duty_performance'] = $stmt->fetchAll();
            
            // Lesson observations
            $stmt = $db->prepare("SELECT * FROM lesson_observations 
                                  WHERE teacher_id = ? AND year = ? AND (? IS NULL OR term = ?)");
            $stmt->execute([$teacherId, $year, $term, $term]);
            $data['observations'] = $stmt->fetchAll();
            
            // Class teacher performance
            $stmt = $db->prepare("SELECT * FROM class_teacher_performance 
                                  WHERE teacher_id = ? AND year = ? AND (? IS NULL OR term = ?)");
            $stmt->execute([$teacherId, $year, $term, $term]);
            $data['class_performance'] = $stmt->fetchAll();
            
            // Awards
            $stmt = $db->prepare("SELECT * FROM teacher_awards 
                                  WHERE teacher_id = ? AND year = ?
                                  ORDER BY awarded_at DESC");
            $stmt->execute([$teacherId, $year]);
            $data['awards'] = $stmt->fetchAll();
        }
        break;
        
    case 'top20':
    case 'top-teachers-lost':
    case 'top-teachers':
        // Top 20 teachers with highest minutes lost
        error_log("Generating top-teachers-lost report for year: $year, term: $term");

        // First, check if there's any data in the table for the selected year
        $checkStmt = $db->prepare("SELECT COUNT(*) as count FROM teacher_lesson_attendance WHERE year = ?");
        $checkStmt->execute([$year]);
        $checkResult = $checkStmt->fetch();
        error_log("Total records in teacher_lesson_attendance for year $year: " . $checkResult['count']);

        // Check if teachers table has data
        $teachersStmt = $db->query("SELECT COUNT(*) as count FROM teachers");
        $teachersCount = $teachersStmt->fetch();
        error_log("Total teachers: " . $teachersCount['count']);

        // Try without JOIN first to see if we get data
        $noJoinStmt = $db->prepare("
            SELECT
                teacher_id,
                SUM(minutes_lost) as total_minutes_lost,
                COUNT(id) as total_lessons
            FROM teacher_lesson_attendance
            WHERE year = ? AND (? IS NULL OR term = ?)
            GROUP BY teacher_id
            LIMIT 5
        ");
        $noJoinStmt->execute([$year, $term, $term]);
        $noJoinResult = $noJoinStmt->fetchAll();
        error_log("No-join result: " . json_encode($noJoinResult));

        $stmt = $db->prepare("
            SELECT
                a.teacher_id,
                t.full_name as teacher_name,
                SUM(a.minutes_lost) as total_minutes_lost,
                FLOOR(SUM(a.minutes_lost) / 40) as equivalent_single_lessons,
                FLOOR(SUM(a.minutes_lost) / 80) as equivalent_double_lessons,
                COUNT(a.id) as total_lessons,
                COUNT(DISTINCT CONCAT(a.class, '-', a.stream)) as classes_count
            FROM teacher_lesson_attendance a
            LEFT JOIN teachers t ON a.teacher_id = t.id
            WHERE a.year = ? AND (? IS NULL OR a.term = ?)
            GROUP BY a.teacher_id, t.full_name
            ORDER BY total_minutes_lost DESC
            LIMIT 20
        ");
        $stmt->execute([$year, $term, $term]);
        $data['top_teachers'] = $stmt->fetchAll();
        error_log("Top teachers count: " . count($data['top_teachers']));
        error_log("Top teachers data: " . json_encode($data['top_teachers']));
        break;

    case 'few':
    case 'teachers-few-lost':
    case 'few-teachers':
        // Teachers with few minutes lost (best performers)
        $stmt = $db->prepare("
            SELECT
                t.id as teacher_id,
                t.full_name as teacher_name,
                SUM(a.minutes_lost) as total_minutes_lost,
                FLOOR(SUM(a.minutes_lost) / 40) as equivalent_single_lessons,
                FLOOR(SUM(a.minutes_lost) / 80) as equivalent_double_lessons,
                COUNT(a.id) as total_lessons,
                CASE
                    WHEN COUNT(a.id) > 0
                    THEN ROUND((1 - (SUM(a.minutes_lost) / (COUNT(a.id) * 40))) * 100, 2)
                    ELSE 100
                END as performance_percentage
            FROM teacher_lesson_attendance a
            LEFT JOIN teachers t ON a.teacher_id = t.id
            WHERE a.year = ? AND (? IS NULL OR a.term = ?)
            GROUP BY a.teacher_id, t.full_name
            HAVING total_lessons > 0
            ORDER BY total_minutes_lost ASC
            LIMIT 20
        ");
        $stmt->execute([$year, $term, $term]);
        $data['few_teachers'] = $stmt->fetchAll();
        break;

    case 'class-top':
    case 'classes-most-lost':
    case 'top-classes':
        // Classes with most minutes lost
        $stmt = $db->prepare("
            SELECT
                CONCAT(a.class, '-', COALESCE(a.stream, '')) as class_key,
                a.class,
                a.stream,
                SUM(a.minutes_lost) as total_minutes_lost,
                FLOOR(SUM(a.minutes_lost) / 40) as equivalent_single_lessons,
                FLOOR(SUM(a.minutes_lost) / 80) as equivalent_double_lessons,
                COUNT(DISTINCT a.teacher_id) as teachers_count
            FROM teacher_lesson_attendance a
            WHERE a.year = ? AND (? IS NULL OR a.term = ?)
            GROUP BY a.class, a.stream
            ORDER BY total_minutes_lost DESC
            LIMIT 20
        ");
        $stmt->execute([$year, $term, $term]);
        $data['most_classes'] = $stmt->fetchAll();
        break;

    case 'class-few':
    case 'classes-few-lost':
    case 'few-classes':
        // Classes with few minutes lost
        $stmt = $db->prepare("
            SELECT
                CONCAT(a.class, '-', COALESCE(a.stream, '')) as class_key,
                a.class,
                a.stream,
                SUM(a.minutes_lost) as total_minutes_lost,
                FLOOR(SUM(a.minutes_lost) / 40) as equivalent_single_lessons,
                FLOOR(SUM(a.minutes_lost) / 80) as equivalent_double_lessons,
                COUNT(DISTINCT a.teacher_id) as teachers_count
            FROM teacher_lesson_attendance a
            WHERE a.year = ? AND (? IS NULL OR a.term = ?)
            GROUP BY a.class, a.stream
            HAVING COUNT(a.id) > 0
            ORDER BY total_minutes_lost ASC
            LIMIT 20
        ");
        $stmt->execute([$year, $term, $term]);
        $data['few_classes'] = $stmt->fetchAll();
        break;
        
    case 'combined-best':
        // How much each area counts (observation, time kept, duty). Default equal: the plain average.
        // Any numbers work (1/1/1, 2/1/1, 40/30/30); they are scaled to percentages.
        $w = [
            'observation' => max(0, (float)($_GET['w_obs'] ?? 1)),
            'monitoring' => max(0, (float)($_GET['w_mon'] ?? 1)),
            'duty' => max(0, (float)($_GET['w_duty'] ?? 1)),
        ];
        $sum = array_sum($w);
        $w = $sum > 0 ? array_map(fn($v) => $v * 100 / $sum, $w) : ['observation' => 100 / 3, 'monitoring' => 100 / 3, 'duty' => 100 / 3];
        $data = combined_best_teachers($db, (int)$year, ($term === null || $term === '' || $term === 'all') ? null : (int)$term, $w);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid report type']);
        exit();
}

/**
 * Best teachers over Lesson Observations, Lesson Monitoring and Duty Performance for a period
 * (one term, or the whole year). Kept simple so any teacher can check it by hand:
 *
 * 1. Enough evidence: ranked only with MIN_LESSONS lessons recorded (10 in a term, 30 in a year), MIN_OBS observation round(s)
 *    and MIN_DUTY duty record(s). Everyone else is listed as "not ranked yet", with the reason.
 * 2. Marks out of 100: observation = average of all rounds; duty = average duty percentage;
 *    time kept = (minutes recorded - minutes lost) / minutes recorded x 100.
 * 3. Total = weighted average of the three marks (default equal: the plain average).
 * 4. Highest total first; equal totals (to one decimal) share the position. Bands by position.
 */
function combined_best_teachers(PDO $db, int $year, ?int $term, array $weights): array
{
    // Lessons needed to be ranked: 10 in one term, 30 over the whole year.
    $MIN_LESSONS = $term === null ? 30 : 10;
    $MIN_OBS = 1;
    $MIN_DUTY = 1;

    $termSql = $term === null ? '' : ' AND term = :term';
    $params = $term === null ? [':year' => $year] : [':year' => $year, ':term' => $term];
    $fetch = function (string $sql) use ($db, $params) {
        $s = $db->prepare($sql);
        $s->execute($params);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    };

    $teachers = [];
    foreach ($db->query("SELECT id, full_name, subject, department, is_active FROM teachers")->fetchAll(PDO::FETCH_ASSOC) as $t) {
        $teachers[(int)$t['id']] = $t;
    }
    $rows = [];
    $row = function (int $id) use (&$rows, $teachers) {
        if (!isset($rows[$id])) {
            $t = $teachers[$id] ?? null;
            $rows[$id] = [
                'teacher_id' => $id,
                'teacher_name' => $t['full_name'] ?? "Teacher #$id",
                'subject' => $t['subject'] ?? '',
                'department' => $t['department'] ?? '',
                'observation' => null, 'obs_n' => 0,
                'duty' => null, 'duty_n' => 0,
                'lessons' => 0, 'minutes_recorded' => 0, 'minutes_lost' => 0, 'time_kept' => null,
            ];
        }
    };

    foreach ($fetch("SELECT teacher_id, AVG(total_score) a, COUNT(*) n FROM lesson_observations WHERE year = :year{$termSql} AND total_score IS NOT NULL GROUP BY teacher_id") as $r) {
        $id = (int)$r['teacher_id'];
        $row($id);
        $rows[$id]['observation'] = (float)$r['a'];
        $rows[$id]['obs_n'] = (int)$r['n'];
    }
    foreach ($fetch("SELECT teacher_id, AVG(percentage) a, COUNT(*) n FROM duty_performance WHERE year = :year{$termSql} GROUP BY teacher_id") as $r) {
        $id = (int)$r['teacher_id'];
        $row($id);
        $rows[$id]['duty'] = (float)$r['a'];
        $rows[$id]['duty_n'] = (int)$r['n'];
    }
    // A lesson's recorded minutes: its planned length, or the time taught when no length was saved.
    foreach ($fetch("SELECT teacher_id, COUNT(*) lessons, COALESCE(SUM(minutes_lost), 0) lost,
                            COALESCE(SUM(CASE WHEN expected_minutes > 0 THEN expected_minutes ELSE actual_minutes + minutes_lost END), 0) recorded
                     FROM teacher_lesson_attendance WHERE year = :year{$termSql} GROUP BY teacher_id") as $r) {
        $id = (int)$r['teacher_id'];
        $row($id);
        $rec = (int)$r['recorded'];
        $lost = (int)$r['lost'];
        $rows[$id]['lessons'] = (int)$r['lessons'];
        $rows[$id]['minutes_recorded'] = $rec;
        $rows[$id]['minutes_lost'] = $lost;
        $rows[$id]['time_kept'] = $rec > 0 ? max(0, min(100, 100 * ($rec - $lost) / $rec)) : null;
    }

    // Leave out staff no longer active.
    $rows = array_filter($rows, fn($r) => !isset($teachers[$r['teacher_id']]) || (int)$teachers[$r['teacher_id']]['is_active'] === 1);

    $w = $weights;
    $ranked = [];
    $notRanked = [];
    foreach ($rows as $r) {
        $missing = [];
        if ($r['lessons'] < $MIN_LESSONS) {
            $missing[] = $r['lessons'] ? "only {$r['lessons']} lessons recorded (needs {$MIN_LESSONS})" : 'no lessons recorded';
        } elseif ($r['time_kept'] === null) {
            $missing[] = 'no lesson minutes recorded';
        }
        if ($r['obs_n'] < $MIN_OBS) $missing[] = 'no lesson observation';
        if ($r['duty_n'] < $MIN_DUTY) $missing[] = 'no duty record';

        if ($missing) {
            $r['missing'] = $missing;
            $notRanked[] = $r;
            continue;
        }
        $r['total'] = ($w['observation'] * $r['observation'] + $w['monitoring'] * $r['time_kept'] + $w['duty'] * $r['duty']) / 100;
        $ranked[] = $r;
    }

    // Highest total first; on equal totals, the higher observation, then fewer minutes lost.
    usort($ranked, fn($a, $b) => [round($b['total'], 1), $b['observation'], -$b['minutes_lost']] <=> [round($a['total'], 1), $a['observation'], -$a['minutes_lost']]
        ?: strcmp($a['teacher_name'], $b['teacher_name']));

    $N = count($ranked);
    $band = function (int $pos) use ($N) {
        if ($pos <= max(1, (int)ceil($N * 0.10))) return 'Top 10%';
        if ($pos <= max(1, (int)ceil($N * 0.25))) return 'Top 25%';
        if ($pos <= (int)ceil($N * 0.50)) return 'Above middle';
        return 'Below middle';
    };
    foreach ($ranked as $i => &$r) {
        // Equal totals (to one decimal) share the position.
        $r['position'] = ($i > 0 && round($ranked[$i - 1]['total'], 1) === round($r['total'], 1)) ? $ranked[$i - 1]['position'] : $i + 1;
        $r['band'] = $band($r['position']);
        foreach (['observation', 'duty', 'time_kept', 'total'] as $k) $r[$k] = round($r[$k], 1);
    }
    unset($r);
    foreach ($notRanked as &$r) {
        foreach (['observation', 'duty', 'time_kept'] as $k) $r[$k] = $r[$k] === null ? null : round($r[$k], 1);
    }
    unset($r);
    usort($notRanked, fn($a, $b) => count($a['missing']) <=> count($b['missing']) ?: strcmp($a['teacher_name'], $b['teacher_name']));

    return [
        'teachers' => $ranked,
        'not_ranked' => $notRanked,
        'settings' => [
            'weights' => $w,
            'min_lessons' => $MIN_LESSONS,
            'min_observations' => $MIN_OBS,
            'min_duty' => $MIN_DUTY,
        ],
        'counts' => ['ranked' => $N, 'not_ranked' => count($notRanked)],
    ];
}

http_response_code(200);
echo json_encode(['success' => true, 'report_type' => $reportType, 'data' => $data, 'year' => $year, 'term' => $term]);
?>
