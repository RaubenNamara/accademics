<?php
/**
 * Student sync with SMIS (see config/StudentSync.php). Administrators only.
 *
 * GET              the last runs and whether one is running
 * GET ?id=…        one run with its lists (added, changed class, left, back in school)
 * POST             run a sync now
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/HrHelpers.php';
require_once __DIR__ . '/../config/TimetableService.php';
require_once __DIR__ . '/../config/StudentSync.php';

hr_cors_headers();
$db = (new Database())->getConnection();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    $payload = hr_require_auth();
    if (!in_array((string)($payload['role'] ?? ''), ['admin', 'super_admin'], true)) {
        hr_respond(false, 'Only an administrator can sync students', null, 403);
    }

    // The SMIS roster is St Mark's own list: other schools on the platform must never pull it.
    $home = !class_exists('Tenant') || Tenant::current()['is_home'];
    if (!$home) {
        if ($method === 'GET') {
            hr_respond(true, 'Student sync', ['available' => false, 'running' => false, 'runs' => []]);
        }
        hr_respond(false, "SMIS sync is only set up for St Mark's. Add learners under Student E-files.", null, 403);
    }

    if ($method === 'GET' && isset($_GET['id'])) {
        $stmt = $db->prepare('SELECT details FROM student_sync_runs WHERE id = :id');
        $stmt->execute([':id' => (int)$_GET['id']]);
        $details = $stmt->fetchColumn();
        hr_respond(true, 'Sync details', ['details' => $details ? json_decode((string)$details, true) : null]);
    }
    if ($method === 'GET') {
        hr_respond(true, 'Student sync', ['available' => true] + ss_status($db));
    }
    if ($method === 'POST') {
        @set_time_limit(300);
        $res = ss_run($db, isset($payload['id']) ? (int)$payload['id'] : null);
        hr_respond($res['ok'], $res['message'], ['run' => $res['run']] + ss_status($db), $res['ok'] ? 200 : 502);
    }
    hr_respond(false, 'Unknown request', null, 400);
} catch (Throwable $e) {
    error_log('student_sync.php: ' . $e->getMessage());
    hr_respond(false, 'Student sync failed: ' . $e->getMessage(), null, 500);
}
