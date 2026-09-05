<?php
declare(strict_types=1);

require_once '../config/Database.php';
require_once '../config/HrHelpers.php';

hr_cors_headers('GET, OPTIONS');

$db = (new Database())->getConnection();
$year = (int)($_GET['year'] ?? date('Y'));
$term = (int)($_GET['term'] ?? 1);
$teacherId = (int)($_GET['teacher_id'] ?? 0);

try {
    $workload = [];
    $stmt = $db->query("
        SELECT id, teacher_code, full_name, subject, class, stream, obligation, is_active
        FROM teachers
        ORDER BY full_name ASC
    ");
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($teachers as $t) {
        if ($teacherId > 0 && (int)$t['id'] !== $teacherId) {
            continue;
        }
        $subjects = array_filter(array_map('trim', explode(',', (string)($t['subject'] ?? ''))));
        $classes = array_filter(array_map('trim', explode(',', (string)($t['class'] ?? ''))));
        $workload[] = [
            'teacher_id' => (int)$t['id'],
            'teacher_code' => $t['teacher_code'],
            'full_name' => $t['full_name'],
            'obligation' => $t['obligation'],
            'subjects_count' => count($subjects) ?: (empty($t['subject']) ? 0 : 1),
            'classes_count' => count($classes) ?: (empty($t['class']) ? 0 : 1),
            'weekly_load_estimate' => max(count($subjects), 1) * max(count($classes), 1),
            'is_active' => (int)$t['is_active'],
        ];
    }

    $attendance = [];
    try {
        $sql = "
            SELECT t.id AS teacher_id, t.full_name, t.teacher_code,
                   COUNT(a.id) AS lessons_recorded,
                   SUM(CASE WHEN a.time_in IS NOT NULL THEN 1 ELSE 0 END) AS lessons_attended
            FROM teachers t
            LEFT JOIN teacher_lesson_attendance a ON a.teacher_id = t.id AND a.year = :year AND a.term = :term
            WHERE 1=1
        ";
        $params = [':year' => $year, ':term' => $term];
        if ($teacherId > 0) {
            $sql .= ' AND t.id = :tid';
            $params[':tid'] = $teacherId;
        }
        $sql .= ' GROUP BY t.id, t.full_name, t.teacher_code ORDER BY lessons_recorded DESC';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        foreach ($teachers as $t) {
            if ($teacherId > 0 && (int)$t['id'] !== $teacherId) {
                continue;
            }
            $attendance[] = [
                'teacher_id' => (int)$t['id'],
                'full_name' => $t['full_name'],
                'teacher_code' => $t['teacher_code'],
                'lessons_recorded' => 0,
                'lessons_attended' => 0,
            ];
        }
    }

    $classAllocation = [];
    foreach ($teachers as $t) {
        if (empty($t['class'])) {
            continue;
        }
        $classAllocation[] = [
            'teacher' => $t['full_name'],
            'teacher_code' => $t['teacher_code'],
            'class' => $t['class'],
            'stream' => $t['stream'],
            'subject' => $t['subject'],
        ];
    }

    hr_respond(true, 'Teaching analytics', [
        'workload' => $workload,
        'attendance' => $attendance,
        'class_allocation' => $classAllocation,
        'summary' => [
            'total_teachers' => count($teachers),
            'active_teachers' => count(array_filter($teachers, fn($t) => (int)$t['is_active'] === 1)),
        ],
    ]);
} catch (Throwable $e) {
    hr_respond(false, $e->getMessage(), null, 500);
}
