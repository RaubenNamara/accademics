<?php
declare(strict_types=1);

require_once '../config/Database.php';
require_once '../config/HrHelpers.php';

hr_cors_headers();

$db = (new Database())->getConnection();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

error_log('Events API called with method: ' . $method);

try {
    if ($method === 'GET') {
        $id = (int)($_GET['id'] ?? 0);
        
        if ($id > 0) {
            // Get single event
            $stmt = $db->prepare("SELECT * FROM school_events WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $event = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$event) {
                hr_respond(false, 'Event not found', null, 404);
            }
            
            hr_respond(true, 'Event loaded', $event);
        } else {
            // Get all events
            $stmt = $db->prepare("SELECT * FROM school_events WHERE is_active = 1 ORDER BY event_name ASC");
            $stmt->execute();
            $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            hr_respond(true, 'Events loaded', $events);
        }
    }
    
    if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
        // hr_require_auth(['teacher', 'academic_office']);
    }
    
    if ($method === 'POST') {
        $data = hr_request_data();
        
        // Debug logging
        error_log('POST data received: ' . json_encode($data));
        
        $required = ['event_name', 'event_type'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
                hr_respond(false, "{$field} is required", null, 400);
            }
        }
        
        $event_name = trim((string)$data['event_name']);
        $event_type = trim((string)$data['event_type']);
        $event_color = trim((string)($data['event_color'] ?? '#FF6B6B'));
        $description = $data['description'] ?? null;
        $duration_minutes = (int)($data['duration_minutes'] ?? 40);
        
        $stmt = $db->prepare("
            INSERT INTO school_events (event_name, event_type, event_color, description, duration_minutes, is_active)
            VALUES (:event_name, :event_type, :event_color, :description, :duration_minutes, 1)
        ");
        
        try {
            $stmt->execute([
                ':event_name' => $event_name,
                ':event_type' => $event_type,
                ':event_color' => $event_color,
                ':description' => $description,
                ':duration_minutes' => $duration_minutes
            ]);
        } catch (PDOException $e) {
            error_log('Database error: ' . $e->getMessage());
            hr_respond(false, 'Database error: ' . $e->getMessage(), null, 500);
        }
        
        $eventId = (int)$db->lastInsertId();
        
        hr_respond(true, 'Event created', ['id' => $eventId], 201);
    }
    
    if ($method === 'PUT') {
        $data = hr_request_data();
        $id = (int)($data['id'] ?? 0);
        
        if ($id <= 0) {
            hr_respond(false, 'ID required', null, 400);
        }
        
        $required = ['event_name', 'event_type'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
                hr_respond(false, "{$field} is required", null, 400);
            }
        }
        
        $event_name = trim((string)$data['event_name']);
        $event_type = trim((string)$data['event_type']);
        $event_color = trim((string)($data['event_color'] ?? '#FF6B6B'));
        $description = $data['description'] ?? null;
        $duration_minutes = (int)($data['duration_minutes'] ?? 40);
        $is_active = isset($data['is_active']) ? (int)$data['is_active'] : 1;
        
        $stmt = $db->prepare("
            UPDATE school_events SET
                event_name = :event_name,
                event_type = :event_type,
                event_color = :event_color,
                description = :description,
                duration_minutes = :duration_minutes,
                is_active = :is_active
            WHERE id = :id
        ");
        $stmt->execute([
            ':id' => $id,
            ':event_name' => $event_name,
            ':event_type' => $event_type,
            ':event_color' => $event_color,
            ':description' => $description,
            ':duration_minutes' => $duration_minutes,
            ':is_active' => $is_active
        ]);
        
        hr_respond(true, 'Event updated');
    }
    
    if ($method === 'DELETE') {
        $id = (int)($_GET['id'] ?? hr_request_data()['id'] ?? 0);
        
        if ($id <= 0) {
            hr_respond(false, 'ID required', null, 400);
        }
        
        // Soft delete by setting is_active to 0
        $stmt = $db->prepare("UPDATE school_events SET is_active = 0 WHERE id = :id");
        $stmt->execute([':id' => $id]);
        
        hr_respond(true, 'Event deleted');
    }
    
    hr_respond(false, 'Method not allowed', null, 405);
    
} catch (PDOException $e) {
    hr_respond(false, 'Database error: ' . $e->getMessage(), null, 500);
} catch (Throwable $e) {
    hr_respond(false, $e->getMessage(), null, 500);
}
