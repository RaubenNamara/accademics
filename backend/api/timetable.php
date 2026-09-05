<?php
declare(strict_types=1);

require_once '../config/Database.php';
require_once '../config/HrHelpers.php';

hr_cors_headers();

$db = (new Database())->getConnection();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function tt_detect_conflicts(PDO $db, array $slot, ?int $excludeId = null): array
{
    $conflicts = [];

    $exclude = $excludeId ? ' AND id <> :exclude_id' : '';

    // Check teacher conflict
    $teacherParams = [
        ':year' => (int)$slot['academic_year'],
        ':term' => (int)$slot['term'],
        ':day' => $slot['day_of_week'],
        ':period' => (int)$slot['period_number'],
        ':teacher_id' => (int)$slot['teacher_id'],
    ];
    if ($excludeId) {
        $teacherParams[':exclude_id'] = $excludeId;
    }

    $teacherSql = "SELECT id FROM timetable WHERE academic_year = :year AND term = :term
        AND day_of_week = :day AND period_number = :period AND teacher_id = :teacher_id $exclude LIMIT 1";
    $stmt = $db->prepare($teacherSql);
    $stmt->execute($teacherParams);
    if ($stmt->fetch()) {
        $conflicts[] = 'Teacher already scheduled in this period';
    }

    // Check class conflict
    $classParams = [
        ':year' => (int)$slot['academic_year'],
        ':term' => (int)$slot['term'],
        ':day' => $slot['day_of_week'],
        ':period' => (int)$slot['period_number'],
        ':class_id' => (int)$slot['class_id'],
    ];
    if ($excludeId) {
        $classParams[':exclude_id'] = $excludeId;
    }

    $classSql = "SELECT id FROM timetable WHERE academic_year = :year AND term = :term
        AND day_of_week = :day AND period_number = :period AND class_id = :class_id $exclude LIMIT 1";
    $stmt = $db->prepare($classSql);
    $stmt->execute($classParams);
    if ($stmt->fetch()) {
        $conflicts[] = 'Class already has a lesson in this period';
    }

    return $conflicts;
}

function tt_generate_timetable(PDO $db): void
{
    $data = hr_request_data();
    $year = (int)($data['academic_year'] ?? date('Y'));
    $term = (int)($data['term'] ?? 1);
    $classId = (int)($data['class_id'] ?? 0);
    
    if ($classId <= 0) {
        hr_respond(false, 'Class ID is required for generation', null, 400);
        return;
    }
    
    // Get class information
    $classStmt = $db->prepare("SELECT * FROM classes WHERE id = :class_id");
    $classStmt->execute([':class_id' => $classId]);
    $class = $classStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$class) {
        hr_respond(false, 'Class not found', null, 404);
        return;
    }
    
    // Get subjects assigned to this class
    $subjectsStmt = $db->prepare("
        SELECT cs.*, s.subject_name 
        FROM class_subjects cs
        JOIN subjects_new s ON s.id = cs.subject_id
        WHERE cs.class_id = :class_id
    ");
    $subjectsStmt->execute([':class_id' => $classId]);
    $subjects = $subjectsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($subjects)) {
        hr_respond(false, 'No subjects assigned to this class', null, 400);
        return;
    }
    
    // Get available teachers for each subject
    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    $periods = [1, 2, 3, 4, 5, 6, 7, 8];
    
    $generated = 0;
    $conflicts = [];
    
    // Clear existing timetable for this class/year/term
    $deleteStmt = $db->prepare("DELETE FROM timetable WHERE class_id = :class_id AND academic_year = :year AND term = :term");
    $deleteStmt->execute([':class_id' => $classId, ':year' => $year, ':term' => $term]);
    
    // Get period times
    $periodTimesStmt = $db->prepare("SELECT * FROM timetable_periods WHERE is_active = 1 ORDER BY period_number");
    $periodTimesStmt->execute();
    $periodTimes = $periodTimesStmt->fetchAll(PDO::FETCH_ASSOC);
    $periodTimesMap = [];
    foreach ($periodTimes as $pt) {
        $periodTimesMap[$pt['period_number']] = [
            'start' => $pt['start_time'],
            'end' => $pt['end_time']
        ];
    }
    
    // Simple round-robin assignment
    $subjectIndex = 0;
    $totalSubjects = count($subjects);
    
    foreach ($days as $dayIndex => $day) {
        foreach ($periods as $period) {
            if ($subjectIndex >= $totalSubjects) {
                $subjectIndex = 0;
            }
            
            $subject = $subjects[$subjectIndex];
            
            // Find a teacher for this subject
            $teacherStmt = $db->prepare("
                SELECT t.id, t.full_name 
                FROM teachers t
                WHERE t.is_active = 1
                LIMIT 1
            ");
            $teacherStmt->execute();
            $teacher = $teacherStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$teacher) {
                $conflicts[] = "No active teacher found for subject: {$subject['subject_name']}";
                $subjectIndex++;
                continue;
            }
            
            // Check for teacher conflict
            $conflictCheck = tt_detect_conflicts($db, [
                'academic_year' => $year,
                'term' => $term,
                'day_of_week' => $day,
                'period_number' => $period,
                'teacher_id' => $teacher['id'],
                'class_id' => $classId
            ]);
            
            if (!empty($conflictCheck)) {
                $conflicts[] = "Conflict at $day P$period: " . implode(', ', $conflictCheck);
                $subjectIndex++;
                continue;
            }
            
            // Get period times
            $times = $periodTimesMap[$period] ?? ['start' => '08:00:00', 'end' => '08:40:00'];
            
            // Insert timetable entry
            $insertStmt = $db->prepare("
                INSERT INTO timetable (academic_year, term, day_of_week, period_number, start_time, end_time,
                    class_id, stream, subject_id, teacher_id)
                VALUES (:year, :term, :day, :period, :start, :end, :class_id, :stream, :subject_id, :teacher_id)
            ");
            $insertStmt->execute([
                ':year' => $year,
                ':term' => $term,
                ':day' => $day,
                ':period' => $period,
                ':start' => $times['start'],
                ':end' => $times['end'],
                ':class_id' => $classId,
                ':stream' => $class['stream_name'] ?: null,
                ':subject_id' => $subject['subject_id'],
                ':teacher_id' => $teacher['id'],
            ]);
            
            $generated++;
            $subjectIndex++;
        }
    }
    
    hr_respond(true, "Generated $generated timetable entries", [
        'generated' => $generated,
        'conflicts' => $conflicts
    ]);
}

try {
    if ($method === 'GET') {
        $view = trim((string)($_GET['view'] ?? 'all'));
        $year = (int)($_GET['academic_year'] ?? date('Y'));
        $term = (int)($_GET['term'] ?? 1);
        $teacherId = (int)($_GET['teacher_id'] ?? 0);
        $classId = (int)($_GET['class_id'] ?? 0);

        try {
            $sql = "
                SELECT tt.*,
                       t.full_name AS teacher_name, t.teacher_code,
                       c.class_name, s.subject_name,
                       e.event_name AS event_display_name, e.event_color, e.event_type
                FROM timetable tt
                LEFT JOIN teachers t ON t.id = tt.teacher_id
                LEFT JOIN classes c ON c.id = tt.class_id
                LEFT JOIN subjects_new s ON s.id = tt.subject_id
                LEFT JOIN school_events e ON e.id = tt.event_id
                WHERE tt.academic_year = :year AND tt.term = :term
            ";
            $params = [':year' => $year, ':term' => $term];

            if ($teacherId > 0) {
                $sql .= ' AND tt.teacher_id = :teacher_id';
                $params[':teacher_id'] = $teacherId;
            }
            if ($classId > 0) {
                $sql .= ' AND tt.class_id = :class_id';
                $params[':class_id'] = $classId;
            }

            $sql .= ' ORDER BY tt.day_of_week, tt.period_number';

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            hr_respond(false, 'Query failed: ' . $e->getMessage(), null, 500);
        }

        if ($view === 'periods') {
            try {
                $periods = $db->query('SELECT * FROM timetable_periods WHERE is_active = 1 ORDER BY period_number')->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable $e) {
                $periods = [];
            }
            hr_respond(true, 'Timetable data', ['entries' => $rows ?: [], 'periods' => $periods]);
        }

        hr_respond(true, 'Timetable loaded', $rows ?: []);
    }

    if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
        hr_require_auth(['teacher', 'academic_office']);
    }

    if ($method === 'POST') {
        $action = $_GET['action'] ?? '';
        
        if ($action === 'generate') {
            tt_generate_timetable($db);
            return;
        }
        
        $data = hr_request_data();
        $entry_type = trim((string)($data['entry_type'] ?? 'lesson'));
        
        // Validate entry_type
        if (!in_array($entry_type, ['lesson', 'event'], true)) {
            hr_respond(false, 'Invalid entry_type. Must be "lesson" or "event"', null, 400);
            return;
        }
        
        $slot = [
            'academic_year' => (int)($data['academic_year'] ?? $data['year'] ?? date('Y')),
            'term' => (int)($data['term'] ?? 1),
            'day_of_week' => trim((string)($data['day_of_week'] ?? $data['day'] ?? 'Monday')),
            'period_number' => (int)($data['period_number'] ?? $data['period'] ?? 1),
            'start_time' => $data['start_time'] ?? '08:00:00',
            'end_time' => $data['end_time'] ?? '08:40:00',
            'class_id' => isset($data['class_id']) && $data['class_id'] > 0 ? (int)$data['class_id'] : null,
            'stream' => trim((string)($data['stream'] ?? '')) ?: null,
            'entry_type' => $entry_type,
        ];

        // Handle lesson entries
        if ($entry_type === 'lesson') {
            $slot['subject_id'] = isset($data['subject_id']) && $data['subject_id'] > 0 ? (int)$data['subject_id'] : null;
            $slot['teacher_id'] = isset($data['teacher_id']) && $data['teacher_id'] > 0 ? (int)$data['teacher_id'] : null;
            $slot['event_id'] = null;
            $slot['event_name'] = null;
            
            if ($slot['class_id'] === null || $slot['subject_id'] === null || $slot['teacher_id'] === null) {
                hr_respond(false, 'Class, subject, and teacher are required for lesson entries', null, 400);
                return;
            }
        }
        // Handle event entries
        elseif ($entry_type === 'event') {
            $slot['event_id'] = isset($data['event_id']) && $data['event_id'] > 0 ? (int)$data['event_id'] : null;
            $slot['event_name'] = trim((string)($data['event_name'] ?? '')) ?: null;
            $slot['event_color'] = trim((string)($data['event_color'] ?? '#FF6B6B'));
            $slot['event_type'] = trim((string)($data['event_type'] ?? '')) ?: null;
            $slot['event_description'] = $data['event_description'] ?? null;
            $slot['duration_minutes'] = (int)($data['duration_minutes'] ?? 40);
            $slot['spans_periods'] = (int)($data['spans_periods'] ?? 1);
            $slot['subject_id'] = null;
            $slot['teacher_id'] = null;

            // Events can be scheduled for all classes (class_id = null) or specific classes
            // No class_id requirement for events

            if ($slot['event_id'] === null && $slot['event_name'] === null) {
                hr_respond(false, 'Event ID or event name is required', null, 400);
                return;
            }
        }

        $conflicts = tt_detect_conflicts($db, $slot);
        if ($conflicts) {
            hr_respond(false, implode('; ', $conflicts), ['conflicts' => $conflicts], 409);
        }

        // Build INSERT statement based on entry type
        if ($entry_type === 'lesson') {
            $stmt = $db->prepare("
                INSERT INTO timetable (academic_year, term, day_of_week, period_number, start_time, end_time,
                    class_id, stream, subject_id, teacher_id, entry_type)
                VALUES (:year, :term, :day, :period, :start_time, :end_time, :class_id, :stream, :subject_id, :teacher_id, :entry_type)
            ");
            $executeParams = [
                ':year' => $slot['academic_year'],
                ':term' => $slot['term'],
                ':day' => $slot['day_of_week'],
                ':period' => $slot['period_number'],
                ':start_time' => $slot['start_time'],
                ':end_time' => $slot['end_time'],
                ':class_id' => $slot['class_id'],
                ':stream' => $slot['stream'],
                ':subject_id' => $slot['subject_id'],
                ':teacher_id' => $slot['teacher_id'],
                ':entry_type' => $slot['entry_type'],
            ];
        } else {
            $stmt = $db->prepare("
                INSERT INTO timetable (academic_year, term, day_of_week, period_number, start_time, end_time,
                    class_id, stream, entry_type, event_id, event_name, event_color, event_type, event_description, duration_minutes, spans_periods)
                VALUES (:year, :term, :day, :period, :start_time, :end_time, :class_id, :stream, :entry_type, :event_id, :event_name, :event_color, :event_type, :event_description, :duration_minutes, :spans_periods)
            ");
            $executeParams = [
                ':year' => $slot['academic_year'],
                ':term' => $slot['term'],
                ':day' => $slot['day_of_week'],
                ':period' => $slot['period_number'],
                ':start_time' => $slot['start_time'],
                ':end_time' => $slot['end_time'],
                ':class_id' => $slot['class_id'],
                ':stream' => $slot['stream'],
                ':entry_type' => $slot['entry_type'],
                ':event_id' => $slot['event_id'],
                ':event_name' => $slot['event_name'],
                ':event_color' => $slot['event_color'],
                ':event_type' => $slot['event_type'],
                ':event_description' => $slot['event_description'],
                ':duration_minutes' => $slot['duration_minutes'],
                ':spans_periods' => $slot['spans_periods'],
            ];
        }

        try {
            $stmt->execute($executeParams);
        } catch (PDOException $e) {
            hr_respond(false, 'Insert failed: ' . $e->getMessage(), null, 500);
        }

        hr_respond(true, 'Timetable entry created', ['id' => (int)$db->lastInsertId()], 201);
    }

    if ($method === 'PUT') {
        $data = hr_request_data();
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) {
            hr_respond(false, 'ID required', null, 400);
            return;
        }

        $entry_type = trim((string)($data['entry_type'] ?? 'lesson'));
        
        // Validate entry_type
        if (!in_array($entry_type, ['lesson', 'event'], true)) {
            hr_respond(false, 'Invalid entry_type. Must be "lesson" or "event"', null, 400);
            return;
        }
        
        $slot = [
            'academic_year' => (int)($data['academic_year'] ?? $data['year'] ?? date('Y')),
            'term' => (int)($data['term'] ?? 1),
            'day_of_week' => trim((string)($data['day_of_week'] ?? $data['day'] ?? 'Monday')),
            'period_number' => (int)($data['period_number'] ?? $data['period'] ?? 1),
            'class_id' => isset($data['class_id']) && $data['class_id'] > 0 ? (int)$data['class_id'] : null,
            'stream' => trim((string)($data['stream'] ?? '')) ?: null,
            'entry_type' => $entry_type,
        ];

        // Handle lesson entries
        if ($entry_type === 'lesson') {
            $slot['subject_id'] = isset($data['subject_id']) && $data['subject_id'] > 0 ? (int)$data['subject_id'] : null;
            $slot['teacher_id'] = isset($data['teacher_id']) && $data['teacher_id'] > 0 ? (int)$data['teacher_id'] : null;
            $slot['event_id'] = null;
            $slot['event_name'] = null;
            
            if ($slot['class_id'] === null || $slot['subject_id'] === null || $slot['teacher_id'] === null) {
                hr_respond(false, 'Class, subject, and teacher are required for lesson entries', null, 400);
                return;
            }
        }
        // Handle event entries
        elseif ($entry_type === 'event') {
            $slot['event_id'] = isset($data['event_id']) && $data['event_id'] > 0 ? (int)$data['event_id'] : null;
            $slot['event_name'] = trim((string)($data['event_name'] ?? '')) ?: null;
            $slot['event_color'] = trim((string)($data['event_color'] ?? '#FF6B6B'));
            $slot['event_type'] = trim((string)($data['event_type'] ?? '')) ?: null;
            $slot['event_description'] = $data['event_description'] ?? null;
            $slot['duration_minutes'] = (int)($data['duration_minutes'] ?? 40);
            $slot['spans_periods'] = (int)($data['spans_periods'] ?? 1);
            $slot['subject_id'] = null;
            $slot['teacher_id'] = null;

            if ($slot['event_id'] === null && $slot['event_name'] === null) {
                hr_respond(false, 'Event ID or event name is required', null, 400);
                return;
            }
        }

        $conflicts = tt_detect_conflicts($db, $slot, $id);
        if ($conflicts) {
            hr_respond(false, implode('; ', $conflicts), ['conflicts' => $conflicts], 409);
            return;
        }

        // Build UPDATE statement based on entry type
        if ($entry_type === 'lesson') {
            $stmt = $db->prepare("
                UPDATE timetable SET
                    academic_year = :year, term = :term, day_of_week = :day, period_number = :period,
                    start_time = :start_time, end_time = :end_time, class_id = :class_id, stream = :stream,
                    subject_id = :subject_id, teacher_id = :teacher_id, entry_type = :entry_type
                WHERE id = :id
            ");
            $executeParams = [
                ':id' => $id,
                ':year' => $slot['academic_year'],
                ':term' => $slot['term'],
                ':day' => $slot['day_of_week'],
                ':period' => $slot['period_number'],
                ':start_time' => $data['start_time'] ?? '08:00:00',
                ':end_time' => $data['end_time'] ?? '08:40:00',
                ':class_id' => $slot['class_id'],
                ':stream' => $slot['stream'],
                ':subject_id' => $slot['subject_id'],
                ':teacher_id' => $slot['teacher_id'],
                ':entry_type' => $slot['entry_type'],
            ];
        } else {
            $stmt = $db->prepare("
                UPDATE timetable SET
                    academic_year = :year, term = :term, day_of_week = :day, period_number = :period,
                    start_time = :start_time, end_time = :end_time, class_id = :class_id, stream = :stream,
                    entry_type = :entry_type, event_id = :event_id, event_name = :event_name,
                    event_color = :event_color, event_type = :event_type, event_description = :event_description,
                    duration_minutes = :duration_minutes, spans_periods = :spans_periods
                WHERE id = :id
            ");
            $executeParams = [
                ':id' => $id,
                ':year' => $slot['academic_year'],
                ':term' => $slot['term'],
                ':day' => $slot['day_of_week'],
                ':period' => $slot['period_number'],
                ':start_time' => $data['start_time'] ?? '08:00:00',
                ':end_time' => $data['end_time'] ?? '08:40:00',
                ':class_id' => $slot['class_id'],
                ':stream' => $slot['stream'],
                ':entry_type' => $slot['entry_type'],
                ':event_id' => $slot['event_id'],
                ':event_name' => $slot['event_name'],
                ':event_color' => $slot['event_color'],
                ':event_type' => $slot['event_type'],
                ':event_description' => $slot['event_description'],
                ':duration_minutes' => $slot['duration_minutes'],
                ':spans_periods' => $slot['spans_periods'],
            ];
        }

        $stmt->execute($executeParams);
        hr_respond(true, 'Timetable entry updated');
    }

    if ($method === 'DELETE') {
        $id = (int)($_GET['id'] ?? hr_request_data()['id'] ?? 0);
        if ($id <= 0) {
            hr_respond(false, 'ID required', null, 400);
        }
        $db->prepare('DELETE FROM timetable WHERE id = :id')->execute([':id' => $id]);
        hr_respond(true, 'Timetable entry deleted');
    }

    hr_respond(false, 'Method not allowed', null, 405);
} catch (Throwable $e) {
    hr_respond(false, $e->getMessage(), null, 500);
}
