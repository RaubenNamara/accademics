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
        
        if ($action === 'by-session') {
            getBySession($db);
        } elseif ($action === 'latest') {
            getLatest($db);
        } else {
            getVersions($db);
        }
    }
    
    if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
        hr_require_auth(['admin', 'academic_office']);
    }
    
    if ($method === 'POST') {
        $data = hr_request_data();
        $action = $_GET['action'] ?? '';
        
        if ($action === 'publish') {
            publishVersion($db, $data);
        } elseif ($action === 'archive') {
            archiveVersion($db, $data);
        } elseif ($action === 'restore') {
            restoreVersion($db, $data);
        } else {
            createVersion($db, $data);
        }
    }
    
    if ($method === 'PUT') {
        $data = hr_request_data();
        updateVersion($db, $data);
    }
    
    if ($method === 'DELETE') {
        $id = (int)($_GET['id'] ?? hr_request_data()['id'] ?? 0);
        deleteVersion($db, $id);
    }
    
    hr_respond(false, 'Method not allowed', null, 405);
} catch (Throwable $e) {
    hr_respond(false, $e->getMessage(), null, 500);
}

function getVersions(PDO $db): void
{
    $session_id = (int)($_GET['academic_session_id'] ?? 0);
    
    $sql = "
        SELECT 
            tv.*,
            t.full_name AS generated_by_name,
            COUNT(tt.id) as entry_count
        FROM timetable_versions tv
        LEFT JOIN teachers t ON tv.generated_by = t.id
        LEFT JOIN timetable tt ON tt.timetable_version_id = tv.id
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($session_id > 0) {
        $sql .= ' AND tv.academic_session_id = :session_id';
        $params[':session_id'] = $session_id;
    }
    
    $sql .= ' GROUP BY tv.id ORDER BY tv.version_number DESC';
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $versions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    hr_respond(true, 'Timetable versions loaded', $versions);
}

function getBySession(PDO $db): void
{
    $session_id = (int)($_GET['academic_session_id'] ?? 0);
    
    if ($session_id <= 0) {
        hr_respond(false, 'Academic session ID is required', null, 400);
    }
    
    $sql = "
        SELECT 
            tv.*,
            t.full_name AS generated_by_name,
            COUNT(tt.id) as entry_count
        FROM timetable_versions tv
        LEFT JOIN teachers t ON tv.generated_by = t.id
        LEFT JOIN timetable tt ON tt.timetable_version_id = tv.id
        WHERE tv.academic_session_id = :session_id
        GROUP BY tv.id
        ORDER BY tv.version_number DESC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([':session_id' => $session_id]);
    $versions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    hr_respond(true, 'Timetable versions loaded', $versions);
}

function getLatest(PDO $db): void
{
    $session_id = (int)($_GET['academic_session_id'] ?? 0);
    
    if ($session_id <= 0) {
        hr_respond(false, 'Academic session ID is required', null, 400);
    }
    
    $sql = "
        SELECT 
            tv.*,
            t.full_name AS generated_by_name,
            COUNT(tt.id) as entry_count
        FROM timetable_versions tv
        LEFT JOIN teachers t ON tv.generated_by = t.id
        LEFT JOIN timetable tt ON tt.timetable_version_id = tv.id
        WHERE tv.academic_session_id = :session_id AND tv.status = 'published'
        GROUP BY tv.id
        ORDER BY tv.version_number DESC
        LIMIT 1
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([':session_id' => $session_id]);
    $version = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$version) {
        hr_respond(false, 'No published version found', null, 404);
    }
    
    hr_respond(true, 'Latest published version loaded', $version);
}

function createVersion(PDO $db, array $data): void
{
    $academic_session_id = (int)($data['academic_session_id'] ?? 0);
    $version_name = trim($data['version_name'] ?? '');
    $generated_by = (int)($data['generated_by'] ?? 0);
    
    if ($academic_session_id <= 0 || !$version_name) {
        hr_respond(false, 'Session ID and version name are required', null, 400);
    }
    
    // Get next version number
    $maxStmt = $db->prepare("SELECT MAX(version_number) as max_num FROM timetable_versions WHERE academic_session_id = :session_id");
    $maxStmt->execute([':session_id' => $academic_session_id]);
    $maxResult = $maxStmt->fetch(PDO::FETCH_ASSOC);
    $next_version = ($maxResult['max_num'] ?? 0) + 1;
    
    $sql = "
        INSERT INTO timetable_versions (
            academic_session_id, version_name, version_number, status, generated_by, notes
        ) VALUES (
            :academic_session_id, :version_name, :version_number, 'draft', :generated_by, :notes
        )
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':academic_session_id' => $academic_session_id,
        ':version_name' => $version_name,
        ':version_number' => $next_version,
        ':generated_by' => $generated_by > 0 ? $generated_by : null,
        ':notes' => $data['notes'] ?? null
    ]);
    
    $version_id = (int)$db->lastInsertId();
    
    // Link current timetable entries to this version
    if (!empty($data['link_current'])) {
        $sessionInfo = $db->prepare("SELECT academic_year, term FROM academic_sessions WHERE id = :session_id");
        $sessionInfo->execute([':session_id' => $academic_session_id]);
        $session = $sessionInfo->fetch(PDO::FETCH_ASSOC);
        
        if ($session) {
            $update = $db->prepare("
                UPDATE timetable 
                SET timetable_version_id = :version_id
                WHERE academic_year = :year AND term = :term AND timetable_version_id IS NULL
            ");
            $update->execute([
                ':version_id' => $version_id,
                ':year' => $session['academic_year'],
                ':term' => $session['term']
            ]);
        }
    }
    
    hr_respond(true, 'Timetable version created', ['id' => $version_id, 'version_number' => $next_version], 201);
}

function updateVersion(PDO $db, array $data): void
{
    $id = (int)($data['id'] ?? 0);
    
    if ($id <= 0) {
        hr_respond(false, 'Version ID is required', null, 400);
    }
    
    $sql = "
        UPDATE timetable_versions SET
            version_name = :version_name,
            notes = :notes,
            updated_at = NOW()
        WHERE id = :id
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':id' => $id,
        ':version_name' => trim($data['version_name'] ?? ''),
        ':notes' => $data['notes'] ?? null
    ]);
    
    hr_respond(true, 'Timetable version updated');
}

function publishVersion(PDO $db, array $data): void
{
    $id = (int)($data['id'] ?? 0);
    
    if ($id <= 0) {
        hr_respond(false, 'Version ID is required', null, 400);
    }
    
    // Archive other published versions for this session
    $archiveStmt = $db->prepare("
        UPDATE timetable_versions 
        SET status = 'archived', archived_at = NOW()
        WHERE academic_session_id = (SELECT academic_session_id FROM timetable_versions WHERE id = :id)
        AND status = 'published'
    ");
    $archiveStmt->execute([':id' => $id]);
    
    // Publish this version
    $publishStmt = $db->prepare("
        UPDATE timetable_versions 
        SET status = 'published', published_at = NOW()
        WHERE id = :id
    ");
    $publishStmt->execute([':id' => $id]);
    
    hr_respond(true, 'Timetable version published');
}

function archiveVersion(PDO $db, array $data): void
{
    $id = (int)($data['id'] ?? 0);
    
    if ($id <= 0) {
        hr_respond(false, 'Version ID is required', null, 400);
    }
    
    $stmt = $db->prepare("
        UPDATE timetable_versions 
        SET status = 'archived', archived_at = NOW()
        WHERE id = :id
    ");
    $stmt->execute([':id' => $id]);
    
    hr_respond(true, 'Timetable version archived');
}

function restoreVersion(PDO $db, array $data): void
{
    $id = (int)($data['id'] ?? 0);
    
    if ($id <= 0) {
        hr_respond(false, 'Version ID is required', null, 400);
    }
    
    // Get version info
    $versionStmt = $db->prepare("SELECT * FROM timetable_versions WHERE id = :id");
    $versionStmt->execute([':id' => $id]);
    $version = $versionStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$version) {
        hr_respond(false, 'Version not found', null, 404);
    }
    
    // Get session info
    $sessionStmt = $db->prepare("SELECT academic_year, term FROM academic_sessions WHERE id = :session_id");
    $sessionStmt->execute([':session_id' => $version['academic_session_id']]);
    $session = $sessionStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
        hr_respond(false, 'Session not found', null, 404);
    }
    
    $db->beginTransaction();
    
    try {
        // Delete current timetable entries for this session
        $delete = $db->prepare("
            DELETE FROM timetable 
            WHERE academic_year = :year AND term = :term
        ");
        $delete->execute([
            ':year' => $session['academic_year'],
            ':term' => $session['term']
        ]);
        
        // Copy entries from the archived version
        $copy = $db->prepare("
            INSERT INTO timetable (
                academic_year, term, day_of_week, period_number, start_time, end_time,
                class_id, stream, subject_id, teacher_id, room_id, entry_type,
                event_id, event_name, event_color, event_type, event_description,
                duration_minutes, spans_periods, timetable_version_id, is_locked
            )
            SELECT 
                :year, :term, day_of_week, period_number, start_time, end_time,
                class_id, stream, subject_id, teacher_id, room_id, entry_type,
                event_id, event_name, event_color, event_type, event_description,
                duration_minutes, spans_periods, :version_id, FALSE
            FROM timetable
            WHERE timetable_version_id = :old_version_id
        ");
        $copy->execute([
            ':year' => $session['academic_year'],
            ':term' => $session['term'],
            ':version_id' => $id,
            ':old_version_id' => $id
        ]);
        
        // Set version status to published
        $updateVersion = $db->prepare("
            UPDATE timetable_versions 
            SET status = 'published', published_at = NOW()
            WHERE id = :id
        ");
        $updateVersion->execute([':id' => $id]);
        
        $db->commit();
        
        hr_respond(true, 'Timetable version restored');
    } catch (Exception $e) {
        $db->rollBack();
        hr_respond(false, 'Restore failed: ' . $e->getMessage(), null, 500);
    }
}

function deleteVersion(PDO $db, int $id): void
{
    if ($id <= 0) {
        hr_respond(false, 'Version ID is required', null, 400);
    }
    
    // Check if version is published
    $check = $db->prepare("SELECT status FROM timetable_versions WHERE id = :id");
    $check->execute([':id' => $id]);
    $version = $check->fetch(PDO::FETCH_ASSOC);
    
    if ($version && $version['status'] === 'published') {
        hr_respond(false, 'Cannot delete published version. Archive it first.', null, 400);
    }
    
    // Unlink timetable entries
    $unlink = $db->prepare("UPDATE timetable SET timetable_version_id = NULL WHERE timetable_version_id = :id");
    $unlink->execute([':id' => $id]);
    
    // Delete version
    $db->prepare("DELETE FROM timetable_versions WHERE id = :id")->execute([':id' => $id]);
    
    hr_respond(true, 'Timetable version deleted');
}
