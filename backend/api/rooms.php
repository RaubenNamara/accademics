<?php
declare(strict_types=1);

require_once '../config/Database.php';
require_once '../config/HrHelpers.php';

hr_cors_headers();

$db = (new Database())->getConnection();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        getRooms($db);
    }
    
    if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
        hr_require_auth(['admin', 'academic_office']);
    }
    
    if ($method === 'POST') {
        $data = hr_request_data();
        createRoom($db, $data);
    }
    
    if ($method === 'PUT') {
        $data = hr_request_data();
        updateRoom($db, $data);
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
    $sql = "
        SELECT 
            id,
            room_code,
            room_name,
            room_type,
            capacity,
            has_projector,
            has_computers,
            is_active,
            created_at,
            updated_at
        FROM rooms
        ORDER BY room_type, room_code
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    hr_respond(true, 'Rooms loaded', $rooms);
}

function createRoom(PDO $db, array $data): void
{
    $room_code = trim($data['room_code'] ?? '');
    $room_name = trim($data['room_name'] ?? '');
    $room_type = $data['room_type'] ?? 'classroom';
    
    if (!$room_code || !$room_name) {
        hr_respond(false, 'Room code and room name are required', null, 400);
    }
    
    $valid_types = ['classroom', 'laboratory', 'library', 'hall', 'office', 'other'];
    if (!in_array($room_type, $valid_types, true)) {
        hr_respond(false, 'Invalid room type', null, 400);
    }
    
    // Check for duplicate room code
    $check = $db->prepare("SELECT id FROM rooms WHERE room_code = :room_code");
    $check->execute([':room_code' => $room_code]);
    
    if ($check->fetch()) {
        hr_respond(false, 'Room code already exists', null, 409);
    }
    
    $sql = "
        INSERT INTO rooms (
            room_code, room_name, room_type, capacity, has_projector, has_computers, is_active
        ) VALUES (
            :room_code, :room_name, :room_type, :capacity, :has_projector, :has_computers, :is_active
        )
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':room_code' => $room_code,
        ':room_name' => $room_name,
        ':room_type' => $room_type,
        ':capacity' => (int)($data['capacity'] ?? 40),
        ':has_projector' => !empty($data['has_projector']),
        ':has_computers' => !empty($data['has_computers']),
        ':is_active' => !empty($data['is_active'])
    ]);
    
    hr_respond(true, 'Room created', ['id' => (int)$db->lastInsertId()], 201);
}

function updateRoom(PDO $db, array $data): void
{
    $id = (int)($data['id'] ?? 0);
    
    if ($id <= 0) {
        hr_respond(false, 'Room ID is required', null, 400);
    }
    
    // Check if room exists
    $check = $db->prepare("SELECT id FROM rooms WHERE id = :id");
    $check->execute([':id' => $id]);
    
    if (!$check->fetch()) {
        hr_respond(false, 'Room not found', null, 404);
    }
    
    $sql = "
        UPDATE rooms SET
            room_code = :room_code,
            room_name = :room_name,
            room_type = :room_type,
            capacity = :capacity,
            has_projector = :has_projector,
            has_computers = :has_computers,
            is_active = :is_active,
            updated_at = NOW()
        WHERE id = :id
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':id' => $id,
        ':room_code' => trim($data['room_code'] ?? ''),
        ':room_name' => trim($data['room_name'] ?? ''),
        ':room_type' => $data['room_type'] ?? 'classroom',
        ':capacity' => (int)($data['capacity'] ?? 40),
        ':has_projector' => !empty($data['has_projector']),
        ':has_computers' => !empty($data['has_computers']),
        ':is_active' => !empty($data['is_active'])
    ]);
    
    hr_respond(true, 'Room updated');
}

function deleteRoom(PDO $db, int $id): void
{
    if ($id <= 0) {
        hr_respond(false, 'Room ID is required', null, 400);
    }
    
    // Check if room is used in timetable
    $check = $db->prepare("SELECT COUNT(*) FROM timetable WHERE room_id = :id");
    $check->execute([':id' => $id]);
    $count = $check->fetchColumn();
    
    if ($count > 0) {
        hr_respond(false, 'Cannot delete room that is used in timetable. Deactivate it instead.', null, 400);
    }
    
    $db->prepare("DELETE FROM rooms WHERE id = :id")->execute([':id' => $id]);
    
    hr_respond(true, 'Room deleted');
}
