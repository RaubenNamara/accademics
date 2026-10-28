<?php
declare(strict_types=1);

require_once '../config/Database.php';
require_once '../config/HrHelpers.php';
require_once '../config/TimetableService.php';

hr_cors_headers();

$db = (new Database())->getConnection();
require_once __DIR__ . '/../config/TermLock.php';
term_guard($db, 'timetable', ['session' => 'academic_session_id']); // a closed term is read-only
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    tt_ensure_schema($db);

    if ($method === 'GET') {
        hr_require_auth();
        $view = trim((string)($_GET['view'] ?? 'all'));

        if ($view === 'meta') {
            hr_respond(true, 'Timetable lookups', tt_meta($db));
        }

        if ($view === 'setup') {
            tt_ensure_home_rooms($db);
            [$sid] = tt_resolve_session($db, $_GET);
            $homes = $db->query('SELECT c.id AS class_id, c.full_class_name, h.room_id, r.room_code, r.room_name
                FROM classes c LEFT JOIN class_home_rooms h ON h.class_id = c.id LEFT JOIN rooms r ON r.id = h.room_id
                ORDER BY c.class_name, c.stream_name')->fetchAll(PDO::FETCH_ASSOC);
            $students = tt_class_enrolment($db);
            foreach ($homes as &$h) {
                $h['students'] = $students[(int)$h['class_id']] ?? 0;
            }
            unset($h);
            hr_respond(true, 'Timetable setup', [
                'status' => tt_setup_status($db, $sid),
                'periods' => array_values(tt_load_periods($db)),
                'home_rooms' => $homes,
            ]);
        }

        [$sessionId, $year, $term] = tt_resolve_session($db, $_GET);

        if ($view === 'overview' || $view === 'validation') {
            if (!$sessionId) {
                hr_respond(false, 'No academic session exists for this year and term', null, 404);
            }
            $validation = tt_validate($db, $sessionId, $year, $term);
            hr_respond(true, 'Timetable overview', $validation + ['state' => tt_session_state($db, $sessionId, $validation['summary']['errors'])]);
        }

        if ($view === 'history') {
            if (!$sessionId) {
                hr_respond(true, 'No history', ['versions' => [], 'audit' => []]);
            }
            hr_respond(true, 'Timetable history', [
                'versions' => tt_versions($db, $sessionId),
                'audit' => tt_audit_log($db, $sessionId, (int)($_GET['limit'] ?? 100)),
            ]);
        }

        if ($view === 'published') {
            $rows = $sessionId ? tt_published_entries($db, $sessionId) : null;
            hr_respond(true, $rows === null ? 'Nothing has been published for this term yet' : 'Published timetable', [
                'published' => $rows !== null,
                'entries' => $rows ?? [],
            ]);
        }

        $rows = tt_fetch_entries($db, $year, $term, $_GET);
        if ($view === 'periods') {
            hr_respond(true, 'Timetable data', ['entries' => $rows, 'periods' => array_values(tt_load_periods($db))]);
        }
        hr_respond(true, 'Timetable loaded', $rows);
    }

    if (!in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
        hr_respond(false, 'Method not allowed', null, 405);
    }

    $actor = tt_actor(hr_require_auth(TT_EDITOR_ROLES));
    $data = hr_request_data();
    $action = (string)($_GET['action'] ?? '');
    $isAdmin = in_array($actor['role'], ['admin', 'super_admin', 'hr_manager'], true);
    if (in_array($action, ['save-periods', 'home-room'], true) && !$isAdmin) {
        hr_respond(false, 'Only administrators can change the school day and class rooms', null, 403);
    }

    if ($method === 'POST') {
        switch ($action) {
            case 'generate':
                $result = tt_generate($db, $data, $actor);
                $message = $result['dry_run']
                    ? "Preview: {$result['placed_periods']} period(s) could be placed"
                    : "Placed {$result['placed_periods']} period(s) in {$result['generated']} lesson(s)";
                hr_respond(true, $message, $result);
                // no break: hr_respond exits

            case 'check':
                // Live conflict check for the lesson form; never writes.
                $periods = tt_load_periods($db);
                [$slot, $errors] = tt_build_entry($db, $data, $periods);
                $conflicts = $errors ? [] : tt_find_conflicts($db, $slot, (int)($data['id'] ?? 0) ?: null, $periods);
                hr_respond(true, 'Checked', ['errors' => (object)$errors, 'conflicts' => $conflicts]);

            case 'validate':
                [$sessionId, $year, $term] = tt_require_session($db, $data);
                $validation = tt_validate($db, $sessionId, $year, $term);
                if ($validation['summary']['errors'] === 0) {
                    tt_mark_validated($db, $sessionId, $actor);
                }
                tt_audit($db, $sessionId, null, 'validate',
                    "Validated: {$validation['summary']['errors']} blocking, {$validation['summary']['warnings']} warning(s)", null, null, $actor);
                hr_respond(true, 'Validation complete', $validation + ['state' => tt_session_state($db, $sessionId, $validation['summary']['errors'])]);

            case 'publish':
                $result = tt_publish($db, $data, $actor);
                hr_respond(true, "Published version {$result['version_number']}", $result);

            case 'restore':
                $result = tt_restore($db, (int)($data['version_id'] ?? 0), $actor);
                hr_respond(true, "Restored {$result['restored']} entr(ies)", $result);

            case 'save-periods':
                $result = tt_save_periods($db, (array)($data['periods'] ?? []), $actor);
                hr_respond(true, 'School day saved' . ($result['lessons_retimed'] ? "; {$result['lessons_retimed']} lesson(s) given the new times" : ''), $result);

            case 'save-rules':
                [$sid] = tt_require_session($db, $data);
                $max = isset($data['max_lessons_per_day']) && $data['max_lessons_per_day'] !== '' && $data['max_lessons_per_day'] !== null
                    ? (int)$data['max_lessons_per_day'] : null;
                tt_save_daily_limit($db, $sid, $max, $actor);
                hr_respond(true, $max ? "Teachers can teach at most $max periods a day" : 'Daily limit removed');

            case 'home-room':
                tt_ensure_home_rooms($db);
                $classId = (int)($data['class_id'] ?? 0);
                $roomId = (int)($data['room_id'] ?? 0);
                if ($roomId) {
                    $taken = $db->prepare('SELECT c.full_class_name FROM class_home_rooms h JOIN classes c ON c.id = h.class_id WHERE h.room_id = :r AND h.class_id <> :c');
                    $taken->execute([':r' => $roomId, ':c' => $classId]);
                    if ($other = $taken->fetchColumn()) {
                        hr_respond(false, "That room is already the home room of $other", null, 409);
                    }
                    $db->prepare('INSERT INTO class_home_rooms (class_id, room_id) VALUES (:c, :r) ON DUPLICATE KEY UPDATE room_id = VALUES(room_id)')
                        ->execute([':c' => $classId, ':r' => $roomId]);
                } else {
                    $db->prepare('DELETE FROM class_home_rooms WHERE class_id = :c')->execute([':c' => $classId]);
                }
                hr_respond(true, $roomId ? 'Home room saved' : 'Home room cleared');

            case 'link-home-rooms':
                if (!$isAdmin) {
                    hr_respond(false, 'Only administrators can change class rooms', null, 403);
                }
                tt_ensure_home_rooms($db);
                $n = tt_link_home_rooms_by_name($db);
                hr_respond(true, $n ? "Linked $n class(es) to their rooms" : 'No unlinked class rooms found', ['linked' => $n]);

            case 'lock':
            case 'unlock':
                tt_set_locked($db, (int)($data['id'] ?? 0), $action === 'lock', $actor);
                hr_respond(true, $action === 'lock' ? 'Lesson locked' : 'Lesson unlocked');

            case '':
                $result = tt_save_entry($db, $data, null, $actor);
                hr_respond(true, $result['duplicate'] ? 'Lesson already saved' : 'Timetable entry created', $result, $result['duplicate'] ? 200 : 201);

            default:
                hr_respond(false, 'Unknown action', null, 400);
        }
    }

    if ($method === 'PUT') {
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) {
            hr_respond(false, 'ID required', null, 400);
        }
        $result = tt_save_entry($db, $data, $id, $actor);
        hr_respond(true, 'Timetable entry updated', $result);
    }

    if ($method === 'DELETE') {
        $id = (int)($_GET['id'] ?? $data['id'] ?? 0);
        if ($id <= 0) {
            hr_respond(false, 'ID required', null, 400);
        }
        tt_delete_entry($db, $id, $actor);
        hr_respond(true, 'Timetable entry deleted');
    }
} catch (TtException $e) {
    hr_respond(false, $e->getMessage(), $e->data, $e->status);
} catch (Throwable $e) {
    error_log('timetable.php: ' . $e->getMessage());
    hr_respond(false, 'Timetable request failed: ' . $e->getMessage(), null, 500);
}
