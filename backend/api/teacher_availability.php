<?php
declare(strict_types=1);

require_once '../config/Database.php';
require_once '../config/HrHelpers.php';

hr_cors_headers();

$db = (new Database())->getConnection();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        $action = $_GET['action'] ?? '';
        
        if ($action === 'by-teacher') {
            getByTeacher($db);
        } else {
            getAvailability($db);
        }
    }
    
    if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
        hr_require_auth(['admin', 'academic_office']);
    }
    
    if ($method === 'POST') {
        $data = hr_request_data();
        $action = $_GET['action'] ?? '';
        
        if ($action === 'bulk') {
            bulkUpdate($db, $data);
        } else {
            setAvailability($db, $data);
        }
    }
    
    if ($method === 'PUT') {
        $data = hr_request_data();
        updateAvailability($db, $data);
    }
    
    if ($method === 'DELETE') {
        $id = (int)($_GET['id'] ?? hr_request_data()['id'] ?? 0);
        deleteAvailability($db, $id);
    }
    
    hr_respond(false, 'Method not allowed', null, 405);
} catch (Throwable $e) {
    hr_respond(false, $e->getMessage(), null, 500);
}

function getAvailability(PDO $db): void
{
    $session_id = (int)($_GET['academic_session_id'] ?? 0);
    $teacher_id = (int)($_GET['teacher_id'] ?? 0);
    
    $sql = "
        SELECT 
            ta.*,
            t.full_name AS teacher_name,
            t.teacher_code
        FROM teacher_availability ta
        LEFT JOIN teachers t ON ta.teacher_id = t.id
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($session_id > 0) {
        $sql .= ' AND ta.academic_session_id = :session_id';
        $params[':session_id'] = $session_id;
    }
    
    if ($teacher_id > 0) {
        $sql .= ' AND ta.teacher_id = :teacher_id';
        $params[':teacher_id'] = $teacher_id;
    }
    
    $sql .= ' ORDER BY ta.teacher_id, FIELD(ta.day_of_week, "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"), ta.period_number';
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $availability = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    hr_respond(true, 'Teacher availability loaded', $availability);
}

function getByTeacher(PDO $db): void
{
    $session_id = (int)($_GET['academic_session_id'] ?? 0);
    $teacher_id = (int)($_GET['teacher_id'] ?? 0);
    
    if ($session_id <= 0 || $teacher_id <= 0) {
        hr_respond(false, 'Session ID and teacher ID are required', null, 400);
    }
    
    $sql = "
        SELECT 
            day_of_week,
            period_number,
            is_available,
            reason
        FROM teacher_availability
        WHERE academic_session_id = :session_id AND teacher_id = :teacher_id
        ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), period_number
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':session_id' => $session_id,
        ':teacher_id' => $teacher_id
    ]);
    $availability = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    hr_respond(true, 'Teacher availability loaded', $availability);
}

function setAvailability(PDO $db, array $data): void
{
    $teacher_id = (int)($data['teacher_id'] ?? 0);
    $academic_session_id = (int)($data['academic_session_id'] ?? 0);
    $day_of_week = $data['day_of_week'] ?? '';
    $period_number = (int)($data['period_number'] ?? 0);
    
    if ($teacher_id <= 0 || $academic_session_id <= 0 || !$day_of_week || $period_number <= 0) {
        hr_respond(false, 'Teacher, session, day, and period are required', null, 400);
    }
    
    $valid_days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    if (!in_array($day_of_week, $valid_days, true)) {
        hr_respond(false, 'Invalid day of week', null, 400);
    }
    
    $sql = "
        INSERT INTO teacher_availability (
            teacher_id, academic_session_id, day_of_week, period_number, is_available, reason
        ) VALUES (
            :teacher_id, :academic_session_id, :day_of_week, :period_number, :is_available, :reason
        )
        ON DUPLICATE KEY UPDATE
            is_available = VALUES(is_available),
            reason = VALUES(reason),
            updated_at = NOW()
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':teacher_id' => $teacher_id,
        ':academic_session_id' => $academic_session_id,
        ':day_of_week' => $day_of_week,
        ':period_number' => $period_number,
        ':is_available' => !empty($data['is_available']),
        ':reason' => $data['reason'] ?? null
    ]);
    
    hr_respond(true, 'Teacher availability set', ['id' => (int)$db->lastInsertId()], 201);
}

function updateAvailability(PDO $db, array $data): void
{
    $id = (int)($data['id'] ?? 0);
    
    if ($id <= 0) {
        hr_respond(false, 'Availability ID is required', null, 400);
    }
    
    $sql = "
        UPDATE teacher_availability SET
            is_available = :is_available,
            reason = :reason,
            updated_at = NOW()
        WHERE id = :id
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':id' => $id,
        ':is_available' => !empty($data['is_available']),
        ':reason' => $data['reason'] ?? null
    ]);
    
    hr_respond(true, 'Teacher availability updated');
}

function deleteAvailability(PDO $db, int $id): void
{
    if ($id <= 0) {
        hr_respond(false, 'Availability ID is required', null, 400);
    }
    
    $db->prepare("DELETE FROM teacher_availability WHERE id = :id")->execute([':id' => $id]);
    
    hr_respond(true, 'Teacher availability deleted');
}

function bulkUpdate(PDO $db, array $data): void
{
    $teacher_id = (int)($data['teacher_id'] ?? 0);
    $academic_session_id = (int)($data['academic_session_id'] ?? 0);
    $availability = $data['availability'] ?? [];
    
    if ($teacher_id <= 0 || $academic_session_id <= 0 || !is_array($availability)) {
        hr_respond(false, 'Teacher ID, session ID, and availability array are required', null, 400);
    }
    
    $db->beginTransaction();
    
    try {
        // Delete existing availability for this teacher/session
        $delete = $db->prepare("
            DELETE FROM teacher_availability 
            WHERE teacher_id = :teacher_id AND academic_session_id = :session_id
        ");
        $delete->execute([
            ':teacher_id' => $teacher_id,
            ':session_id' => $academic_session_id
        ]);
        
        // Insert new availability
        $insert = $db->prepare("
            INSERT INTO teacher_availability (
                teacher_id, academic_session_id, day_of_week, period_number, is_available, reason
            ) VALUES (
                :teacher_id, :academic_session_id, :day_of_week, :period_number, :is_available, :reason
            )
        ");
        
        foreach ($availability as $avail) {
            if (empty($avail['is_available'])) {
                continue; // Skip available slots (default is available)
            }
            
            $insert->execute([
                ':teacher_id' => $teacher_id,
                ':academic_session_id' => $academic_session_id,
                ':day_of_week' => $avail['day_of_week'],
                ':period_number' => (int)$avail['period_number'],
                ':is_available' => false,
                ':reason' => $avail['reason'] ?? null
            ]);
        }
        
        $db->commit();
        
        hr_respond(true, 'Teacher availability bulk updated');
    } catch (Exception $e) {
        $db->rollBack();
        hr_respond(false, 'Bulk update failed: ' . $e->getMessage(), null, 500);
    }
}
