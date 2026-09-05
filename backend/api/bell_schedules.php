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
        
        if ($action === 'periods') {
            getSchedulePeriods($db);
        } elseif ($action === 'active') {
            getActiveSchedule($db);
        } else {
            getSchedules($db);
        }
    }
    
    if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
        hr_require_auth(['admin', 'academic_office']);
    }
    
    if ($method === 'POST') {
        $data = hr_request_data();
        $action = $_GET['action'] ?? '';
        
        if ($action === 'periods') {
            addPeriod($db, $data);
        } elseif ($action === 'bulk-periods') {
            bulkUpdatePeriods($db, $data);
        } else {
            createSchedule($db, $data);
        }
    }
    
    if ($method === 'PUT') {
        $data = hr_request_data();
        updateSchedule($db, $data);
    }
    
    if ($method === 'DELETE') {
        $id = (int)($_GET['id'] ?? hr_request_data()['id'] ?? 0);
        $action = $_GET['action'] ?? '';
        
        if ($action === 'period') {
            deletePeriod($db, $id);
        } else {
            deleteSchedule($db, $id);
        }
    }
    
    hr_respond(false, 'Method not allowed', null, 405);
} catch (Throwable $e) {
    hr_respond(false, $e->getMessage(), null, 500);
}

function getSchedules(PDO $db): void
{
    $sql = "
        SELECT 
            bs.*,
            COUNT(bsp.id) as period_count
        FROM bell_schedules bs
        LEFT JOIN bell_schedule_periods bsp ON bs.id = bsp.bell_schedule_id
        GROUP BY bs.id
        ORDER BY bs.is_active DESC, bs.created_at DESC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    hr_respond(true, 'Bell schedules loaded', $schedules);
}

function getActiveSchedule(PDO $db): void
{
    $sql = "
        SELECT 
            bs.*
        FROM bell_schedules bs
        WHERE bs.is_active = TRUE
        LIMIT 1
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $schedule = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$schedule) {
        hr_respond(false, 'No active bell schedule found', null, 404);
    }
    
    hr_respond(true, 'Active bell schedule loaded', $schedule);
}

function getSchedulePeriods(PDO $db): void
{
    $schedule_id = (int)($_GET['schedule_id'] ?? 0);
    
    if ($schedule_id <= 0) {
        hr_respond(false, 'Schedule ID is required', null, 400);
    }
    
    $sql = "
        SELECT 
            id,
            bell_schedule_id,
            day_of_week,
            period_number,
            period_name,
            start_time,
            end_time,
            period_type,
            is_active
        FROM bell_schedule_periods
        WHERE bell_schedule_id = :schedule_id
        ORDER BY 
            FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
            period_number
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([':schedule_id' => $schedule_id]);
    $periods = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    hr_respond(true, 'Bell schedule periods loaded', $periods);
}

function createSchedule(PDO $db, array $data): void
{
    $schedule_name = trim($data['schedule_name'] ?? '');
    $schedule_type = $data['schedule_type'] ?? 'weekly';
    $day_pattern = $data['day_pattern'] ?? 'uniform';
    
    if (!$schedule_name) {
        hr_respond(false, 'Schedule name is required', null, 400);
    }
    
    $valid_types = ['weekly', 'fortnightly', 'custom', 'rotation'];
    if (!in_array($schedule_type, $valid_types, true)) {
        hr_respond(false, 'Invalid schedule type', null, 400);
    }
    
    $valid_patterns = ['uniform', 'custom'];
    if (!in_array($day_pattern, $valid_patterns, true)) {
        hr_respond(false, 'Invalid day pattern', null, 400);
    }
    
    // If this is set as active, deactivate other active schedules
    if (!empty($data['is_active'])) {
        $db->prepare("UPDATE bell_schedules SET is_active = FALSE")->execute();
    }
    
    $sql = "
        INSERT INTO bell_schedules (
            schedule_name, schedule_type, day_pattern, is_active, academic_session_id
        ) VALUES (
            :schedule_name, :schedule_type, :day_pattern, :is_active, :academic_session_id
        )
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':schedule_name' => $schedule_name,
        ':schedule_type' => $schedule_type,
        ':day_pattern' => $day_pattern,
        ':is_active' => !empty($data['is_active']),
        ':academic_session_id' => !empty($data['academic_session_id']) ? (int)$data['academic_session_id'] : null
    ]);
    
    hr_respond(true, 'Bell schedule created', ['id' => (int)$db->lastInsertId()], 201);
}

function updateSchedule(PDO $db, array $data): void
{
    $id = (int)($data['id'] ?? 0);
    
    if ($id <= 0) {
        hr_respond(false, 'Schedule ID is required', null, 400);
    }
    
    // If activating this schedule, deactivate others
    if (!empty($data['is_active'])) {
        $db->prepare("UPDATE bell_schedules SET is_active = FALSE WHERE id != :id")->execute([':id' => $id]);
    }
    
    $sql = "
        UPDATE bell_schedules SET
            schedule_name = :schedule_name,
            schedule_type = :schedule_type,
            day_pattern = :day_pattern,
            is_active = :is_active,
            academic_session_id = :academic_session_id,
            updated_at = NOW()
        WHERE id = :id
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':id' => $id,
        ':schedule_name' => trim($data['schedule_name'] ?? ''),
        ':schedule_type' => $data['schedule_type'] ?? 'weekly',
        ':day_pattern' => $data['day_pattern'] ?? 'uniform',
        ':is_active' => !empty($data['is_active']),
        ':academic_session_id' => !empty($data['academic_session_id']) ? (int)$data['academic_session_id'] : null
    ]);
    
    hr_respond(true, 'Bell schedule updated');
}

function deleteSchedule(PDO $db, int $id): void
{
    if ($id <= 0) {
        hr_respond(false, 'Schedule ID is required', null, 400);
    }
    
    $db->prepare("DELETE FROM bell_schedule_periods WHERE bell_schedule_id = :id")->execute([':id' => $id]);
    $db->prepare("DELETE FROM bell_schedules WHERE id = :id")->execute([':id' => $id]);
    
    hr_respond(true, 'Bell schedule deleted');
}

function addPeriod(PDO $db, array $data): void
{
    $bell_schedule_id = (int)($data['bell_schedule_id'] ?? 0);
    $day_of_week = $data['day_of_week'] ?? '';
    $period_number = (int)($data['period_number'] ?? 0);
    $start_time = $data['start_time'] ?? '';
    $end_time = $data['end_time'] ?? '';
    
    if ($bell_schedule_id <= 0 || !$day_of_week || $period_number <= 0 || !$start_time || !$end_time) {
        hr_respond(false, 'Schedule ID, day, period number, start time, and end time are required', null, 400);
    }
    
    $valid_days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    if (!in_array($day_of_week, $valid_days, true)) {
        hr_respond(false, 'Invalid day of week', null, 400);
    }
    
    $valid_types = ['lesson', 'devotion', 'breakfast', 'break', 'lunch', 'mentorship', 'games', 'prep', 'supper', 'assembly', 'other'];
    $period_type = $data['period_type'] ?? 'lesson';
    if (!in_array($period_type, $valid_types, true)) {
        hr_respond(false, 'Invalid period type', null, 400);
    }
    
    $sql = "
        INSERT INTO bell_schedule_periods (
            bell_schedule_id, day_of_week, period_number, period_name,
            start_time, end_time, period_type, is_active
        ) VALUES (
            :bell_schedule_id, :day_of_week, :period_number, :period_name,
            :start_time, :end_time, :period_type, :is_active
        )
        ON DUPLICATE KEY UPDATE
            period_name = VALUES(period_name),
            start_time = VALUES(start_time),
            end_time = VALUES(end_time),
            period_type = VALUES(period_type),
            is_active = VALUES(is_active)
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':bell_schedule_id' => $bell_schedule_id,
        ':day_of_week' => $day_of_week,
        ':period_number' => $period_number,
        ':period_name' => $data['period_name'] ?? null,
        ':start_time' => $start_time,
        ':end_time' => $end_time,
        ':period_type' => $period_type,
        ':is_active' => !empty($data['is_active'])
    ]);
    
    hr_respond(true, 'Period added', ['id' => (int)$db->lastInsertId()], 201);
}

function deletePeriod(PDO $db, int $id): void
{
    if ($id <= 0) {
        hr_respond(false, 'Period ID is required', null, 400);
    }
    
    $db->prepare("DELETE FROM bell_schedule_periods WHERE id = :id")->execute([':id' => $id]);
    
    hr_respond(true, 'Period deleted');
}

function bulkUpdatePeriods(PDO $db, array $data): void
{
    $bell_schedule_id = (int)($data['bell_schedule_id'] ?? 0);
    $periods = $data['periods'] ?? [];
    
    if ($bell_schedule_id <= 0 || !is_array($periods)) {
        hr_respond(false, 'Schedule ID and periods array are required', null, 400);
    }
    
    $db->beginTransaction();
    
    try {
        // Delete existing periods for this schedule
        $delete = $db->prepare("DELETE FROM bell_schedule_periods WHERE bell_schedule_id = :schedule_id");
        $delete->execute([':schedule_id' => $bell_schedule_id]);
        
        // Insert new periods
        $insert = $db->prepare("
            INSERT INTO bell_schedule_periods (
                bell_schedule_id, day_of_week, period_number, period_name,
                start_time, end_time, period_type, is_active
            ) VALUES (
                :bell_schedule_id, :day_of_week, :period_number, :period_name,
                :start_time, :end_time, :period_type, :is_active
            )
        ");
        
        $valid_types = ['lesson', 'devotion', 'breakfast', 'break', 'lunch', 'mentorship', 'games', 'prep', 'supper', 'assembly', 'other'];
        $valid_days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        
        foreach ($periods as $period) {
            if (!in_array($period['day_of_week'], $valid_days, true)) {
                throw new Exception("Invalid day of week: {$period['day_of_week']}");
            }
            
            if (!in_array($period['period_type'], $valid_types, true)) {
                throw new Exception("Invalid period type: {$period['period_type']}");
            }
            
            $insert->execute([
                ':bell_schedule_id' => $bell_schedule_id,
                ':day_of_week' => $period['day_of_week'],
                ':period_number' => (int)$period['period_number'],
                ':period_name' => $period['period_name'] ?? null,
                ':start_time' => $period['start_time'],
                ':end_time' => $period['end_time'],
                ':period_type' => $period['period_type'] ?? 'lesson',
                ':is_active' => !empty($period['is_active'])
            ]);
        }
        
        $db->commit();
        
        hr_respond(true, 'Periods bulk updated', ['count' => count($periods)]);
    } catch (Exception $e) {
        $db->rollBack();
        hr_respond(false, 'Bulk update failed: ' . $e->getMessage(), null, 500);
    }
}
