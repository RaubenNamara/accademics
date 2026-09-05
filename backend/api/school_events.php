<?php
/**
 * School Events API
 * Manages school-wide events that can be scheduled in the timetable
 * Examples: Devotion, Assembly, Breakfast, Break, Lunch, Mentorship, Games, Clubs, Prep, Supper
 */

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../config/HrHelpers.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$db = (new Database())->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGet($db);
            break;
        case 'POST':
            handlePost($db);
            break;
        case 'PUT':
            handlePut($db);
            break;
        case 'DELETE':
            handleDelete($db);
            break;
        default:
            hr_respond(false, 'Method not allowed', null, 405);
    }
} catch (Exception $e) {
    hr_respond(false, 'Server error: ' . $e->getMessage(), null, 500);
}

function handleGet(PDO $db) {
    $action = $_GET['action'] ?? '';

    if ($action === 'by-type') {
        // Get events by type
        $type = $_GET['type'] ?? '';
        $stmt = $db->prepare("SELECT * FROM school_events WHERE event_type = ? AND is_active = 1 ORDER BY event_name");
        $stmt->execute([$type]);
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        hr_respond(true, 'Events retrieved', $events);
    } elseif ($action === 'active') {
        // Get only active events
        $stmt = $db->prepare("SELECT * FROM school_events WHERE is_active = 1 ORDER BY event_name");
        $stmt->execute();
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        hr_respond(true, 'Active events retrieved', $events);
    } else {
        // Get all events
        $stmt = $db->prepare("SELECT * FROM school_events ORDER BY event_name");
        $stmt->execute();
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        hr_respond(true, 'Events retrieved', $events);
    }
}

function handlePost(PDO $db) {
    $data = hr_request_data();

    // Validate required fields
    if (empty($data['event_name']) || empty($data['event_type'])) {
        hr_respond(false, 'Event name and type are required', null, 400);
        return;
    }

    // Check for duplicate event name
    $checkStmt = $db->prepare("SELECT id FROM school_events WHERE event_name = ?");
    $checkStmt->execute([$data['event_name']]);
    if ($checkStmt->fetch()) {
        hr_respond(false, 'Event with this name already exists', null, 409);
        return;
    }

    $stmt = $db->prepare("
        INSERT INTO school_events (event_name, event_type, event_color, event_description,
            is_mandatory, applies_to_all_classes, duration_minutes, spans_periods, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $result = $stmt->execute([
        $data['event_name'],
        $data['event_type'],
        $data['event_color'] ?? '#FF6B6B',
        $data['event_description'] ?? null,
        $data['is_mandatory'] ?? false,
        $data['applies_to_all_classes'] ?? true,
        $data['duration_minutes'] ?? 40,
        $data['spans_periods'] ?? 1,
        $data['is_active'] ?? true
    ]);

    if ($result) {
        hr_respond(true, 'Event created', ['id' => $db->lastInsertId()]);
    } else {
        hr_respond(false, 'Failed to create event', null, 500);
    }
}

function handlePut(PDO $db) {
    $data = hr_request_data();

    if (empty($data['id'])) {
        hr_respond(false, 'Event ID is required', null, 400);
        return;
    }

    // Check for duplicate event name (excluding current)
    $checkStmt = $db->prepare("SELECT id FROM school_events WHERE event_name = ? AND id <> ?");
    $checkStmt->execute([$data['event_name'], $data['id']]);
    if ($checkStmt->fetch()) {
        hr_respond(false, 'Event with this name already exists', null, 409);
        return;
    }

    $stmt = $db->prepare("
        UPDATE school_events
        SET event_name = ?, event_type = ?, event_color = ?, event_description = ?,
            is_mandatory = ?, applies_to_all_classes = ?, duration_minutes = ?,
            spans_periods = ?, is_active = ?
        WHERE id = ?
    ");

    $result = $stmt->execute([
        $data['event_name'],
        $data['event_type'],
        $data['event_color'] ?? '#FF6B6B',
        $data['event_description'] ?? null,
        $data['is_mandatory'] ?? false,
        $data['applies_to_all_classes'] ?? true,
        $data['duration_minutes'] ?? 40,
        $data['spans_periods'] ?? 1,
        $data['is_active'] ?? true,
        $data['id']
    ]);

    if ($result) {
        hr_respond(true, 'Event updated');
    } else {
        hr_respond(false, 'Failed to update event', null, 500);
    }
}

function handleDelete(PDO $db) {
    $id = $_GET['id'] ?? '';
    
    if (empty($id)) {
        hr_respond(false, 'Event ID is required', null, 400);
        return;
    }
    
    // Check if event is in use
    $checkStmt = $db->prepare("SELECT COUNT(*) FROM timetable WHERE event_id = ?");
    $checkStmt->execute([$id]);
    if ($checkStmt->fetchColumn() > 0) {
        hr_respond(false, 'Cannot delete event: it is in use in the timetable', null, 409);
        return;
    }
    
    $stmt = $db->prepare("DELETE FROM school_events WHERE id = ?");
    $result = $stmt->execute([$id]);
    
    if ($result) {
        hr_respond(true, 'Event deleted');
    } else {
        hr_respond(false, 'Failed to delete event', null, 500);
    }
}
