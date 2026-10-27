<?php
declare(strict_types=1);

require_once '../config/Database.php';
require_once '../config/HrHelpers.php';

hr_cors_headers();

$db = (new Database())->getConnection();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

const ROOM_TYPES = ['classroom', 'laboratory', 'library', 'hall', 'office', 'other'];
/** Automatic code prefixes: CR-001, LAB-001, … */
const ROOM_PREFIXES = ['classroom' => 'CR', 'laboratory' => 'LAB', 'library' => 'LIB', 'hall' => 'HALL', 'office' => 'OFF', 'other' => 'RM'];

try {
    if ($method === 'GET') {
        hr_require_auth();
        if (($_GET['action'] ?? '') === 'next-code') {
            $type = in_array($_GET['type'] ?? '', ROOM_TYPES, true) ? $_GET['type'] : 'classroom';
            hr_respond(true, 'Next room code', ['room_code' => nextRoomCode($db, $type)]);
        }
        getRooms($db);
    }

    // Rooms are school facilities: only administrators may change them.
    if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
        hr_require_auth(['admin']);
    }

    if ($method === 'POST') {
        if (($_GET['action'] ?? '') === 'class-rooms') {
            createClassRooms($db);
        }
        createRoom($db, hr_request_data());
    }

    if ($method === 'PUT') {
        updateRoom($db, hr_request_data());
    }

    if ($method === 'DELETE') {
        $id = (int)($_GET['id'] ?? hr_request_data()['id'] ?? 0);
        deleteRoom($db, $id);
    }

    hr_respond(false, 'Method not allowed', null, 405);
} catch (Throwable $e) {
    hr_respond(false, $e->getMessage(), null, 500);
}

function getRooms(PDO $db): void
{
    $rooms = $db->query('
        SELECT r.id, r.room_code, r.room_name, r.room_type, r.capacity, r.has_projector, r.has_computers, r.is_active,
               r.created_at, r.updated_at,
               (SELECT COUNT(*) FROM timetable t WHERE t.room_id = r.id) AS lesson_count,
               (SELECT COUNT(*) FROM lesson_requirements lr WHERE lr.room_id = r.id) AS requirement_count
        FROM rooms r
        ORDER BY r.room_type, r.room_code
    ')->fetchAll(PDO::FETCH_ASSOC);

    hr_respond(true, 'Rooms loaded', $rooms);
}

/**
 * Validates a room and returns the column values; responds 422/409 with a clear reason otherwise.
 * Codes are assigned by the system: a new room gets the next code for its type, an existing room keeps its code.
 */
function roomValues(PDO $db, array $data, ?int $id = null): array
{
    $name = trim((string)($data['room_name'] ?? ''));
    $type = (string)($data['room_type'] ?? 'classroom');
    if ($id) {
        $current = $db->prepare('SELECT room_code FROM rooms WHERE id = :id');
        $current->execute([':id' => $id]);
        $code = (string)$current->fetchColumn();
    } else {
        $code = nextRoomCode($db, in_array($type, ROOM_TYPES, true) ? $type : 'other');
    }
    $capacity = (int)($data['capacity'] ?? 40);
    $errors = [];

    if ($name === '') {
        $errors['room_name'] = 'Enter a room name, e.g. Chemistry Lab';
    } elseif (mb_strlen($name) > 100) {
        $errors['room_name'] = 'Keep the name under 100 characters';
    }
    if (!in_array($type, ROOM_TYPES, true)) {
        $errors['room_type'] = 'Choose a room type';
    }
    if ($capacity < 1 || $capacity > 2000) {
        $errors['capacity'] = 'Capacity must be between 1 and 2000';
    }
    if (!isset($errors['room_code'])) {
        $sql = 'SELECT id FROM rooms WHERE room_code = :code' . ($id ? ' AND id <> :id' : '');
        $check = $db->prepare($sql);
        $check->execute($id ? [':code' => $code, ':id' => $id] : [':code' => $code]);
        if ($check->fetch()) {
            $errors['room_code'] = "Another room already uses the code $code";
        }
    }
    if ($errors) {
        hr_respond(false, reset($errors), ['errors' => $errors], isset($errors['room_code']) && str_starts_with($errors['room_code'], 'Another') ? 409 : 422);
    }

    return [
        ':room_code' => $code,
        ':room_name' => $name,
        ':room_type' => $type,
        ':capacity' => $capacity,
        ':has_projector' => (int)!empty($data['has_projector']),
        ':has_computers' => (int)!empty($data['has_computers']),
        // New rooms are active unless explicitly switched off.
        ':is_active' => (int)(array_key_exists('is_active', $data) ? !empty($data['is_active']) : true),
    ];
}

/** Next free code for a type: CR-001, CR-002, … (numbers continue after the highest existing one). */
function nextRoomCode(PDO $db, string $type): string
{
    $prefix = ROOM_PREFIXES[$type] ?? 'RM';
    $stmt = $db->prepare('SELECT room_code FROM rooms WHERE room_code LIKE :p');
    $stmt->execute([':p' => $prefix . '-%']);
    $max = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $code) {
        if (preg_match('/^' . $prefix . '-(\d+)$/', (string)$code, $m)) {
            $max = max($max, (int)$m[1]);
        }
    }
    return sprintf('%s-%03d', $prefix, $max + 1);
}

/** "S1" + "A" -> "S.1 - A Class" */
function classRoomName(string $className, string $stream): string
{
    $level = preg_replace('/^S\.?\s*(\d+)$/i', 'S.$1', trim($className));
    return trim($stream) !== '' ? "$level - " . trim($stream) . ' Class' : "$level Class";
}

/**
 * Creates one classroom per class stream ("S.1 - A Class") that does not have one yet,
 * with an automatic code and seats taken from the stream's enrolment (at least 40).
 */
function createClassRooms(PDO $db): void
{
    $classes = $db->query('SELECT class_name, stream_name FROM classes ORDER BY class_name, stream_name')->fetchAll(PDO::FETCH_ASSOC);
    $existing = array_flip(array_map('strtolower', $db->query('SELECT room_name FROM rooms')->fetchAll(PDO::FETCH_COLUMN)));

    $enrolment = [];
    try {
        foreach ($db->query('SELECT class, stream, COUNT(*) AS n FROM students GROUP BY class, stream')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $enrolment[strtolower(trim((string)$row['class']) . '|' . trim((string)$row['stream']))] = (int)$row['n'];
        }
    } catch (Throwable $e) {
        // No student records: every room gets the default capacity.
    }

    $created = [];
    $skipped = 0;
    $insert = $db->prepare("INSERT INTO rooms (room_code, room_name, room_type, capacity, has_projector, has_computers, is_active)
        VALUES (:code, :name, 'classroom', :capacity, 0, 0, 1)");
    $db->beginTransaction();
    try {
        foreach ($classes as $c) {
            $name = classRoomName((string)$c['class_name'], (string)$c['stream_name']);
            if (isset($existing[strtolower($name)])) {
                $skipped++;
                continue;
            }
            $students = $enrolment[strtolower(trim((string)$c['class_name']) . '|' . trim((string)$c['stream_name']))] ?? 0;
            $code = nextRoomCode($db, 'classroom');
            $insert->execute([':code' => $code, ':name' => $name, ':capacity' => max(40, (int)(ceil($students / 5) * 5))]);
            $created[] = "$code $name";
            $existing[strtolower($name)] = true;
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }

    $msg = count($created) ? 'Added ' . count($created) . ' class room(s)' : 'Every class stream already has a room';
    hr_respond(true, $msg . ($skipped ? " ($skipped already existed)" : ''), ['created' => $created, 'skipped' => $skipped], count($created) ? 201 : 200);
}

function createRoom(PDO $db, array $data): void
{
    $values = roomValues($db, $data);
    $insert = $db->prepare('
        INSERT INTO rooms (room_code, room_name, room_type, capacity, has_projector, has_computers, is_active)
        VALUES (:room_code, :room_name, :room_type, :capacity, :has_projector, :has_computers, :is_active)
    ');
    // Two rooms added at the same moment could be offered the same code: take the next one.
    for ($attempt = 0; ; $attempt++) {
        try {
            $insert->execute($values);
            break;
        } catch (PDOException $e) {
            if ($attempt >= 4 || $e->getCode() !== '23000') {
                throw $e;
            }
            $values[':room_code'] = nextRoomCode($db, $values[':room_type']);
        }
    }

    hr_respond(true, "Room {$values[':room_code']} added", ['id' => (int)$db->lastInsertId()], 201);
}

function updateRoom(PDO $db, array $data): void
{
    $id = (int)($data['id'] ?? 0);
    $check = $db->prepare('SELECT id FROM rooms WHERE id = :id');
    $check->execute([':id' => $id]);
    if ($id <= 0 || !$check->fetch()) {
        hr_respond(false, 'This room no longer exists', null, 404);
    }

    $values = roomValues($db, $data, $id);
    $db->prepare('
        UPDATE rooms SET room_code = :room_code, room_name = :room_name, room_type = :room_type, capacity = :capacity,
            has_projector = :has_projector, has_computers = :has_computers, is_active = :is_active, updated_at = NOW()
        WHERE id = :id
    ')->execute($values + [':id' => $id]);

    hr_respond(true, "Room {$values[':room_code']} updated");
}

function deleteRoom(PDO $db, int $id): void
{
    if ($id <= 0) {
        hr_respond(false, 'Room ID is required', null, 400);
    }

    // A room still referenced by lessons or requirements is deactivated, not deleted.
    $count = static function (string $table) use ($db, $id): int {
        $stmt = $db->prepare("SELECT COUNT(*) FROM $table WHERE room_id = :id");
        $stmt->execute([':id' => $id]);
        return (int)$stmt->fetchColumn();
    };
    $lessonCount = $count('timetable');
    $requirementCount = $count('lesson_requirements');
    if ($lessonCount || $requirementCount) {
        $parts = array_filter([
            $lessonCount ? "$lessonCount timetable lesson(s)" : null,
            $requirementCount ? "$requirementCount lesson requirement(s)" : null,
        ]);
        hr_respond(false, 'This room is used by ' . implode(' and ', $parts) . '. Deactivate it instead, or move those lessons first.', null, 409);
    }

    $db->prepare('DELETE FROM rooms WHERE id = :id')->execute([':id' => $id]);
    hr_respond(true, 'Room deleted');
}
