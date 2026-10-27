<?php
/**
 * Teacher portal logins (administrators).
 *
 * GET                                   every teacher with their login status
 * POST ?action=create {teacher_ids[] | all:true}   create logins (temporary passwords returned once)
 * POST ?action=reset  {teacher_id}      new temporary password
 * POST ?action=active {teacher_id, active}          suspend / re-activate a login
 */
declare(strict_types=1);

require_once '../config/Database.php';
require_once '../config/HrHelpers.php';
require_once '../config/SchoolService.php';

hr_cors_headers();
$db = (new Database())->getConnection();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    tt_ensure_schema($db);
    sf_ensure_schema($db);
    hr_require_auth(SF_ACADEMIC_ROLES);
    $action = (string)($_GET['action'] ?? '');

    if ($method === 'GET') {
        $rows = $db->query("SELECT t.id, t.full_name, t.teacher_code, t.email, t.subject, t.department, t.is_active AS teacher_active,
                u.id AS user_id, u.email AS login, u.is_active AS login_active, u.must_change_password, u.last_login
            FROM teachers t LEFT JOIN users u ON u.teacher_id = t.id
            ORDER BY t.full_name")->fetchAll(PDO::FETCH_ASSOC);
        hr_respond(true, 'Teacher logins', [
            'teachers' => $rows,
            'with_login' => count(array_filter($rows, fn($r) => $r['user_id'])),
        ]);
    }

    if ($method !== 'POST') {
        hr_respond(false, 'Method not allowed', null, 405);
    }
    $data = hr_request_data();

    if ($action === 'create') {
        $ids = !empty($data['all'])
            ? array_map('intval', $db->query('SELECT t.id FROM teachers t LEFT JOIN users u ON u.teacher_id = t.id
                WHERE u.id IS NULL AND (t.is_active = 1 OR t.is_active IS NULL)')->fetchAll(PDO::FETCH_COLUMN))
            : array_values(array_unique(array_filter(array_map('intval', (array)($data['teacher_ids'] ?? [])))));
        if (!$ids) {
            hr_respond(true, 'Every active teacher already has a login', ['created' => [], 'failed' => []]);
        }
        $created = [];
        $failed = [];
        foreach ($ids as $tid) {
            try {
                $r = sf_issue_teacher_login($db, $tid, false);
                if (isset($r['password'])) {
                    $created[] = $r;
                }
            } catch (TtException $e) {
                $failed[] = ['teacher_id' => $tid, 'reason' => $e->getMessage()];
            }
        }
        hr_respond(true, 'Created ' . count($created) . ' login(s)' . ($failed ? ', ' . count($failed) . ' could not be created' : ''),
            ['created' => $created, 'failed' => $failed]);
    }

    if ($action === 'reset') {
        $r = sf_issue_teacher_login($db, (int)($data['teacher_id'] ?? 0), true);
        hr_respond(true, "New temporary password for {$r['name']}", $r);
    }

    if ($action === 'active') {
        $stmt = $db->prepare('UPDATE users SET is_active = :a WHERE teacher_id = :t');
        $stmt->execute([':a' => !empty($data['active']) ? 1 : 0, ':t' => (int)($data['teacher_id'] ?? 0)]);
        if (!$stmt->rowCount()) {
            hr_respond(false, 'This teacher has no login yet', null, 404);
        }
        hr_respond(true, !empty($data['active']) ? 'Login re-activated' : 'Login suspended');
    }

    hr_respond(false, 'Unknown action', null, 400);
} catch (TtException $e) {
    hr_respond(false, $e->getMessage(), $e->data, $e->status);
} catch (Throwable $e) {
    hr_respond(false, $e->getMessage(), null, 500);
}
