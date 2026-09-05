<?php
declare(strict_types=1);

require_once '../config/Database.php';
require_once '../config/HrHelpers.php';

hr_cors_headers();

$db = (new Database())->getConnection();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

/**
 * Resolves an academic_session_id into [id, academic_year, term]. Accepts an
 * explicit academic_session_id, or explicit academic_year/term, or falls
 * back to the active session, or finally today's year + term 1. Used so the
 * client only ever needs to send a session id and the year/term columns
 * (still queried directly by timetable_analytics.php/timetable_pdf.php)
 * stay in sync automatically.
 */
function tt_resolve_session(PDO $db, array $data): array
{
    $sessionId = isset($data['academic_session_id']) && (int)$data['academic_session_id'] > 0
        ? (int)$data['academic_session_id']
        : null;

    if ($sessionId) {
        $stmt = $db->prepare('SELECT id, academic_year, term FROM academic_sessions WHERE id = :id');
        $stmt->execute([':id' => $sessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($session) {
            return [(int)$session['id'], (int)$session['academic_year'], (int)$session['term']];
        }
    }

    if (isset($data['academic_year']) || isset($data['year']) || isset($data['term'])) {
        $year = (int)($data['academic_year'] ?? $data['year'] ?? date('Y'));
        $term = (int)($data['term'] ?? 1);
        $stmt = $db->prepare('SELECT id FROM academic_sessions WHERE academic_year = :y AND term = :t LIMIT 1');
        $stmt->execute([':y' => $year, ':t' => $term]);
        $found = $stmt->fetch(PDO::FETCH_ASSOC);
        return [$found ? (int)$found['id'] : null, $year, $term];
    }

    $active = $db->query("SELECT id, academic_year, term FROM academic_sessions WHERE is_active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($active) {
        return [(int)$active['id'], (int)$active['academic_year'], (int)$active['term']];
    }

    return [null, (int)date('Y'), 1];
}

/**
 * Validates that [startPeriod .. startPeriod+span-1] are all real, time-
 * contiguous lesson slots in timetable_periods (rejects spans that fall on a
 * break, run past the last period, or bridge an unmodeled gap like lunch).
 * Returns ['error' => string] or ['start_time','end_time','duration_minutes'].
 */
function tt_validate_period_span(PDO $db, int $startPeriod, int $span): array
{
    $endPeriod = $startPeriod + $span - 1;
    $stmt = $db->prepare("
        SELECT period_number, label, period_type, start_time, end_time
        FROM timetable_periods
        WHERE period_number BETWEEN :start AND :end
        ORDER BY period_number
    ");
    $stmt->execute([':start' => $startPeriod, ':end' => $endPeriod]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($rows) !== $span) {
        return ['error' => 'Lesson extends beyond the last period of the day'];
    }

    foreach ($rows as $i => $row) {
        if ($row['period_type'] !== 'lesson') {
            return ['error' => "Period {$row['period_number']} ({$row['label']}) is not a lesson slot"];
        }
        if ($i > 0 && $rows[$i - 1]['end_time'] !== $row['start_time']) {
            return ['error' => 'Lesson would run across a break or gap in the schedule'];
        }
    }

    $start = $rows[0]['start_time'];
    $end = $rows[count($rows) - 1]['end_time'];

    return [
        'start_time' => $start,
        'end_time' => $end,
        'duration_minutes' => (int)((strtotime($end) - strtotime($start)) / 60),
    ];
}

/**
 * Overlap-range conflict check (not exact-match): a slot spanning
 * [period_number .. period_number+spans_periods-1] conflicts with any
 * existing lesson row whose own span overlaps that range, for the same
 * teacher, class, or room.
 */
function tt_detect_conflicts(PDO $db, array $slot, ?int $excludeId = null): array
{
    $conflicts = [];
    $exclude = $excludeId ? ' AND id <> :exclude_id' : '';
    $span = max(1, (int)($slot['spans_periods'] ?? 1));
    $startPeriod = (int)$slot['period_number'];
    $endPeriod = $startPeriod + $span - 1;

    $baseParams = [
        ':year' => (int)$slot['academic_year'],
        ':term' => (int)$slot['term'],
        ':day' => $slot['day_of_week'],
        ':start' => $startPeriod,
        ':end' => $endPeriod,
    ];
    if ($excludeId) {
        $baseParams[':exclude_id'] = $excludeId;
    }

    $overlapWhere = "academic_year = :year AND term = :term AND day_of_week = :day
        AND entry_type = 'lesson'
        AND period_number <= :end
        AND (period_number + spans_periods - 1) >= :start
        $exclude";

    if (!empty($slot['teacher_id'])) {
        $stmt = $db->prepare("SELECT id FROM timetable WHERE $overlapWhere AND teacher_id = :teacher_id LIMIT 1");
        $stmt->execute($baseParams + [':teacher_id' => (int)$slot['teacher_id']]);
        if ($stmt->fetch()) {
            $conflicts[] = 'Teacher already has an overlapping lesson in this period range';
        }
    }

    if (!empty($slot['class_id'])) {
        $stmt = $db->prepare("SELECT id FROM timetable WHERE $overlapWhere AND class_id = :class_id LIMIT 1");
        $stmt->execute($baseParams + [':class_id' => (int)$slot['class_id']]);
        if ($stmt->fetch()) {
            $conflicts[] = 'Class already has an overlapping lesson in this period range';
        }
    }

    if (!empty($slot['room_id'])) {
        $stmt = $db->prepare("SELECT id FROM timetable WHERE $overlapWhere AND room_id = :room_id LIMIT 1");
        $stmt->execute($baseParams + [':room_id' => (int)$slot['room_id']]);
        if ($stmt->fetch()) {
            $conflicts[] = 'Room already booked for an overlapping period range';
        }
    }

    return $conflicts;
}

/**
 * Greedy, requirement-aware generator (not a perfect solver): for each
 * lesson_requirements row, places periods_per_week periods in blocks of the
 * requirement's preferred span (clamped to level-appropriate lengths),
 * skipping any day/slot where the class, teacher, or room already has an
 * overlapping booking or the teacher is marked unavailable. At most one
 * block per subject per day. Reports anything it couldn't fully place.
 */
function tt_generate_timetable(PDO $db): void
{
    $data = hr_request_data();
    [$sessionId, $year, $term] = tt_resolve_session($db, $data);
    if (!$sessionId) {
        hr_respond(false, 'Select (or create) an academic session before generating', null, 400);
        return;
    }

    $classId = (int)($data['class_id'] ?? 0);
    $classSql = 'SELECT * FROM classes' . ($classId > 0 ? ' WHERE id = :id' : '');
    $classStmt = $db->prepare($classSql);
    $classStmt->execute($classId > 0 ? [':id' => $classId] : []);
    $classes = $classStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($classes)) {
        hr_respond(false, 'No matching class found', null, 404);
        return;
    }

    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    $lessonPeriods = array_map('intval', array_column(
        $db->query("SELECT period_number FROM timetable_periods WHERE period_type = 'lesson' ORDER BY period_number")->fetchAll(PDO::FETCH_ASSOC),
        'period_number'
    ));

    $generated = 0;
    $unplaced = [];

    foreach ($classes as $class) {
        $classId = (int)$class['id'];
        $level = tt_class_level((string)$class['class_name']);
        $spanOptions = tt_span_options($level);

        $db->prepare("DELETE FROM timetable WHERE class_id = :class_id AND academic_session_id = :sid AND entry_type = 'lesson'")
            ->execute([':class_id' => $classId, ':sid' => $sessionId]);

        $reqStmt = $db->prepare("
            SELECT lr.*, s.subject_name
            FROM lesson_requirements lr
            JOIN subjects_new s ON s.id = lr.subject_id
            WHERE lr.academic_session_id = :sid AND lr.class_id = :class_id
        ");
        $reqStmt->execute([':sid' => $sessionId, ':class_id' => $classId]);
        $requirements = $reqStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($requirements)) {
            $unplaced[] = "{$class['full_class_name']}: no lesson requirements configured";
            continue;
        }

        foreach ($requirements as $req) {
            $span = (int)($req['preferred_span'] ?? 0);
            if ($span < 1) {
                $span = (!empty($req['double_lesson_required']) || !empty($req['double_lesson_allowed'])) ? 2 : 1;
            }
            if (!isset($spanOptions[$span])) {
                $span = 1;
            }

            $remaining = (int)$req['periods_per_week'];
            $target = $remaining;
            $teacherId = (int)$req['teacher_id'];
            $roomId = $req['room_id'] ? (int)$req['room_id'] : null;

            foreach ($days as $day) {
                if ($remaining <= 0) {
                    break;
                }

                foreach ($lessonPeriods as $startPeriod) {
                    if ($remaining <= 0) {
                        break;
                    }

                    $trySpan = min($span, $remaining);
                    $periodInfo = tt_validate_period_span($db, $startPeriod, $trySpan);
                    if (isset($periodInfo['error'])) {
                        continue;
                    }

                    $conflicts = tt_detect_conflicts($db, [
                        'academic_year' => $year,
                        'term' => $term,
                        'day_of_week' => $day,
                        'period_number' => $startPeriod,
                        'spans_periods' => $trySpan,
                        'teacher_id' => $teacherId,
                        'class_id' => $classId,
                        'room_id' => $roomId,
                    ]);
                    if (!empty($conflicts)) {
                        continue;
                    }

                    $availStmt = $db->prepare("
                        SELECT COUNT(*) FROM teacher_availability
                        WHERE teacher_id = :tid AND academic_session_id = :sid AND day_of_week = :day
                        AND period_number BETWEEN :start AND :end AND is_available = 0
                    ");
                    $availStmt->execute([
                        ':tid' => $teacherId, ':sid' => $sessionId, ':day' => $day,
                        ':start' => $startPeriod, ':end' => $startPeriod + $trySpan - 1,
                    ]);
                    if ((int)$availStmt->fetchColumn() > 0) {
                        continue;
                    }

                    $insertStmt = $db->prepare("
                        INSERT INTO timetable (academic_session_id, academic_year, term, day_of_week, period_number,
                            spans_periods, duration_minutes, start_time, end_time, class_id, stream, subject_id, teacher_id, room_id, entry_type)
                        VALUES (:sid, :year, :term, :day, :period, :span, :duration, :start_time, :end_time, :class_id, :stream, :subject_id, :teacher_id, :room_id, 'lesson')
                    ");
                    $insertStmt->execute([
                        ':sid' => $sessionId, ':year' => $year, ':term' => $term, ':day' => $day,
                        ':period' => $startPeriod, ':span' => $trySpan,
                        ':duration' => $periodInfo['duration_minutes'],
                        ':start_time' => $periodInfo['start_time'], ':end_time' => $periodInfo['end_time'],
                        ':class_id' => $classId, ':stream' => $class['stream_name'] ?: null,
                        ':subject_id' => (int)$req['subject_id'], ':teacher_id' => $teacherId, ':room_id' => $roomId,
                    ]);

                    $generated++;
                    $remaining -= $trySpan;
                    break; // one block of this subject per day
                }
            }

            if ($remaining > 0) {
                $unplaced[] = "{$class['full_class_name']} - {$req['subject_name']}: placed " . ($target - $remaining) . "/{$target} periods";
            }
        }
    }

    hr_respond(true, "Generated $generated timetable entries", [
        'generated' => $generated,
        'unplaced' => $unplaced,
    ]);
}

try {
    if ($method === 'GET') {
        $view = trim((string)($_GET['view'] ?? 'all'));
        [, $year, $term] = tt_resolve_session($db, $_GET);
        $teacherId = (int)($_GET['teacher_id'] ?? 0);
        $classId = (int)($_GET['class_id'] ?? 0);
        $roomId = (int)($_GET['room_id'] ?? 0);

        try {
            $sql = "
                SELECT tt.*,
                       t.full_name AS teacher_name, t.teacher_code,
                       c.class_name, c.stream_name, s.subject_name, s.subject_code,
                       r.room_code, r.room_name
                FROM timetable tt
                LEFT JOIN teachers t ON t.id = tt.teacher_id
                LEFT JOIN classes c ON c.id = tt.class_id
                LEFT JOIN subjects_new s ON s.id = tt.subject_id
                LEFT JOIN rooms r ON r.id = tt.room_id
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
            if ($roomId > 0) {
                $sql .= ' AND tt.room_id = :room_id';
                $params[':room_id'] = $roomId;
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

        if (!in_array($entry_type, ['lesson', 'event'], true)) {
            hr_respond(false, 'Invalid entry_type. Must be "lesson" or "event"', null, 400);
            return;
        }

        [$sessionId, $year, $term] = tt_resolve_session($db, $data);

        $slot = [
            'academic_session_id' => $sessionId,
            'academic_year' => $year,
            'term' => $term,
            'day_of_week' => trim((string)($data['day_of_week'] ?? $data['day'] ?? 'Monday')),
            'period_number' => (int)($data['period_number'] ?? $data['period'] ?? 1),
            'class_id' => isset($data['class_id']) && $data['class_id'] > 0 ? (int)$data['class_id'] : null,
            'stream' => trim((string)($data['stream'] ?? '')) ?: null,
            'entry_type' => $entry_type,
        ];

        if ($entry_type === 'lesson') {
            $slot['subject_id'] = isset($data['subject_id']) && $data['subject_id'] > 0 ? (int)$data['subject_id'] : null;
            $slot['teacher_id'] = isset($data['teacher_id']) && $data['teacher_id'] > 0 ? (int)$data['teacher_id'] : null;
            $slot['room_id'] = isset($data['room_id']) && $data['room_id'] > 0 ? (int)$data['room_id'] : null;
            $slot['spans_periods'] = max(1, (int)($data['spans_periods'] ?? 1));
            $slot['event_id'] = null;
            $slot['event_name'] = null;

            if ($slot['class_id'] === null || $slot['subject_id'] === null || $slot['teacher_id'] === null) {
                hr_respond(false, 'Class, subject, and teacher are required for lesson entries', null, 400);
                return;
            }

            $classStmt = $db->prepare('SELECT class_name, full_class_name FROM classes WHERE id = :id');
            $classStmt->execute([':id' => $slot['class_id']]);
            $classRow = $classStmt->fetch(PDO::FETCH_ASSOC);
            if (!$classRow) {
                hr_respond(false, 'Class not found', null, 404);
                return;
            }

            $level = tt_class_level((string)$classRow['class_name']);
            $spanOptions = tt_span_options($level);
            if (!isset($spanOptions[$slot['spans_periods']])) {
                $allowed = implode(', ', array_map(
                    fn($span, $o) => "{$o['label']} ({$o['minutes']}min)",
                    array_keys($spanOptions), $spanOptions
                ));
                hr_respond(false, "{$classRow['full_class_name']} is $level: lessons can only be $allowed", null, 400);
                return;
            }

            $periodInfo = tt_validate_period_span($db, $slot['period_number'], $slot['spans_periods']);
            if (isset($periodInfo['error'])) {
                hr_respond(false, $periodInfo['error'], null, 400);
                return;
            }
            $slot['start_time'] = $periodInfo['start_time'];
            $slot['end_time'] = $periodInfo['end_time'];
            $slot['duration_minutes'] = $periodInfo['duration_minutes'];
        } elseif ($entry_type === 'event') {
            $slot['event_id'] = isset($data['event_id']) && $data['event_id'] > 0 ? (int)$data['event_id'] : null;
            $slot['event_name'] = trim((string)($data['event_name'] ?? '')) ?: null;
            $slot['event_color'] = trim((string)($data['event_color'] ?? '#FF6B6B'));
            $slot['event_type'] = trim((string)($data['event_type'] ?? '')) ?: null;
            $slot['event_description'] = $data['event_description'] ?? null;
            $slot['duration_minutes'] = (int)($data['duration_minutes'] ?? 40);
            $slot['spans_periods'] = (int)($data['spans_periods'] ?? 1);
            $slot['start_time'] = $data['start_time'] ?? '08:00:00';
            $slot['end_time'] = $data['end_time'] ?? '08:40:00';
            $slot['subject_id'] = null;
            $slot['teacher_id'] = null;
            $slot['room_id'] = null;

            if ($slot['event_id'] === null && $slot['event_name'] === null) {
                hr_respond(false, 'Event ID or event name is required', null, 400);
                return;
            }
        }

        $conflicts = tt_detect_conflicts($db, $slot);
        if ($conflicts) {
            hr_respond(false, implode('; ', $conflicts), ['conflicts' => $conflicts], 409);
            return;
        }

        if ($entry_type === 'lesson') {
            $stmt = $db->prepare("
                INSERT INTO timetable (academic_session_id, academic_year, term, day_of_week, period_number,
                    spans_periods, duration_minutes, start_time, end_time, class_id, stream, subject_id, teacher_id, room_id, entry_type)
                VALUES (:sid, :year, :term, :day, :period, :span, :duration, :start_time, :end_time, :class_id, :stream, :subject_id, :teacher_id, :room_id, :entry_type)
            ");
            $executeParams = [
                ':sid' => $slot['academic_session_id'],
                ':year' => $slot['academic_year'],
                ':term' => $slot['term'],
                ':day' => $slot['day_of_week'],
                ':period' => $slot['period_number'],
                ':span' => $slot['spans_periods'],
                ':duration' => $slot['duration_minutes'],
                ':start_time' => $slot['start_time'],
                ':end_time' => $slot['end_time'],
                ':class_id' => $slot['class_id'],
                ':stream' => $slot['stream'],
                ':subject_id' => $slot['subject_id'],
                ':teacher_id' => $slot['teacher_id'],
                ':room_id' => $slot['room_id'],
                ':entry_type' => $slot['entry_type'],
            ];
        } else {
            $stmt = $db->prepare("
                INSERT INTO timetable (academic_session_id, academic_year, term, day_of_week, period_number, start_time, end_time,
                    class_id, stream, entry_type, event_id, event_name, event_color, event_type, event_description, duration_minutes, spans_periods)
                VALUES (:sid, :year, :term, :day, :period, :start_time, :end_time, :class_id, :stream, :entry_type, :event_id, :event_name, :event_color, :event_type, :event_description, :duration_minutes, :spans_periods)
            ");
            $executeParams = [
                ':sid' => $slot['academic_session_id'],
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
            return;
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
        if (!in_array($entry_type, ['lesson', 'event'], true)) {
            hr_respond(false, 'Invalid entry_type. Must be "lesson" or "event"', null, 400);
            return;
        }

        [$sessionId, $year, $term] = tt_resolve_session($db, $data);

        $slot = [
            'academic_session_id' => $sessionId,
            'academic_year' => $year,
            'term' => $term,
            'day_of_week' => trim((string)($data['day_of_week'] ?? $data['day'] ?? 'Monday')),
            'period_number' => (int)($data['period_number'] ?? $data['period'] ?? 1),
            'class_id' => isset($data['class_id']) && $data['class_id'] > 0 ? (int)$data['class_id'] : null,
            'stream' => trim((string)($data['stream'] ?? '')) ?: null,
            'entry_type' => $entry_type,
        ];

        if ($entry_type === 'lesson') {
            $slot['subject_id'] = isset($data['subject_id']) && $data['subject_id'] > 0 ? (int)$data['subject_id'] : null;
            $slot['teacher_id'] = isset($data['teacher_id']) && $data['teacher_id'] > 0 ? (int)$data['teacher_id'] : null;
            $slot['room_id'] = isset($data['room_id']) && $data['room_id'] > 0 ? (int)$data['room_id'] : null;
            $slot['spans_periods'] = max(1, (int)($data['spans_periods'] ?? 1));
            $slot['event_id'] = null;
            $slot['event_name'] = null;

            if ($slot['class_id'] === null || $slot['subject_id'] === null || $slot['teacher_id'] === null) {
                hr_respond(false, 'Class, subject, and teacher are required for lesson entries', null, 400);
                return;
            }

            $classStmt = $db->prepare('SELECT class_name, full_class_name FROM classes WHERE id = :id');
            $classStmt->execute([':id' => $slot['class_id']]);
            $classRow = $classStmt->fetch(PDO::FETCH_ASSOC);
            if (!$classRow) {
                hr_respond(false, 'Class not found', null, 404);
                return;
            }

            $level = tt_class_level((string)$classRow['class_name']);
            $spanOptions = tt_span_options($level);
            if (!isset($spanOptions[$slot['spans_periods']])) {
                $allowed = implode(', ', array_map(
                    fn($span, $o) => "{$o['label']} ({$o['minutes']}min)",
                    array_keys($spanOptions), $spanOptions
                ));
                hr_respond(false, "{$classRow['full_class_name']} is $level: lessons can only be $allowed", null, 400);
                return;
            }

            $periodInfo = tt_validate_period_span($db, $slot['period_number'], $slot['spans_periods']);
            if (isset($periodInfo['error'])) {
                hr_respond(false, $periodInfo['error'], null, 400);
                return;
            }
            $slot['start_time'] = $periodInfo['start_time'];
            $slot['end_time'] = $periodInfo['end_time'];
            $slot['duration_minutes'] = $periodInfo['duration_minutes'];
        } elseif ($entry_type === 'event') {
            $slot['event_id'] = isset($data['event_id']) && $data['event_id'] > 0 ? (int)$data['event_id'] : null;
            $slot['event_name'] = trim((string)($data['event_name'] ?? '')) ?: null;
            $slot['event_color'] = trim((string)($data['event_color'] ?? '#FF6B6B'));
            $slot['event_type'] = trim((string)($data['event_type'] ?? '')) ?: null;
            $slot['event_description'] = $data['event_description'] ?? null;
            $slot['duration_minutes'] = (int)($data['duration_minutes'] ?? 40);
            $slot['spans_periods'] = (int)($data['spans_periods'] ?? 1);
            $slot['start_time'] = $data['start_time'] ?? '08:00:00';
            $slot['end_time'] = $data['end_time'] ?? '08:40:00';
            $slot['subject_id'] = null;
            $slot['teacher_id'] = null;
            $slot['room_id'] = null;

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

        if ($entry_type === 'lesson') {
            $stmt = $db->prepare("
                UPDATE timetable SET
                    academic_session_id = :sid, academic_year = :year, term = :term, day_of_week = :day, period_number = :period,
                    spans_periods = :span, duration_minutes = :duration, start_time = :start_time, end_time = :end_time,
                    class_id = :class_id, stream = :stream, subject_id = :subject_id, teacher_id = :teacher_id, room_id = :room_id,
                    entry_type = :entry_type
                WHERE id = :id
            ");
            $executeParams = [
                ':id' => $id,
                ':sid' => $slot['academic_session_id'],
                ':year' => $slot['academic_year'],
                ':term' => $slot['term'],
                ':day' => $slot['day_of_week'],
                ':period' => $slot['period_number'],
                ':span' => $slot['spans_periods'],
                ':duration' => $slot['duration_minutes'],
                ':start_time' => $slot['start_time'],
                ':end_time' => $slot['end_time'],
                ':class_id' => $slot['class_id'],
                ':stream' => $slot['stream'],
                ':subject_id' => $slot['subject_id'],
                ':teacher_id' => $slot['teacher_id'],
                ':room_id' => $slot['room_id'],
                ':entry_type' => $slot['entry_type'],
            ];
        } else {
            $stmt = $db->prepare("
                UPDATE timetable SET
                    academic_session_id = :sid, academic_year = :year, term = :term, day_of_week = :day, period_number = :period,
                    start_time = :start_time, end_time = :end_time, class_id = :class_id, stream = :stream,
                    entry_type = :entry_type, event_id = :event_id, event_name = :event_name,
                    event_color = :event_color, event_type = :event_type, event_description = :event_description,
                    duration_minutes = :duration_minutes, spans_periods = :spans_periods
                WHERE id = :id
            ");
            $executeParams = [
                ':id' => $id,
                ':sid' => $slot['academic_session_id'],
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
