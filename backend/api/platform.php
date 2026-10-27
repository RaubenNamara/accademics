<?php
/**
 * Skool Monitor platform: manage schools (SaaS). One database per school.
 *
 * GET  ?action=context            public: is this a multi-school install, and which school
 *                                 this address / ?school= points to (for the sign-in page)
 * GET                             schools with counts           (home-school super admin)
 * POST ?action=setup              create the school list and register this school
 * POST ?action=create             new school {name, code, admin_name, admin_email, admin_password?, domain?, db_name?, contact_phone?}
 * POST ?action=update             {code, name?, domain?, status?, contact_*?, notes?}
 * POST ?action=settings           {show_school_field}: show the School code box on the sign-in page
 */
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/HrHelpers.php';
require_once __DIR__ . '/../config/PlatformService.php';

$db = (new Database())->getConnection();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = (string)($_GET['action'] ?? '');

try {
    if ($method === 'GET' && $action === 'context') {
        $registry = Tenant::registry();
        $school = Tenant::current();
        $active = $registry ? (int)$registry->query("SELECT COUNT(*) FROM schools WHERE status = 'active'")->fetchColumn() : 1;
        hr_respond(true, 'Context', [
            'multi_school' => $active > 1,
            // When off, staff reach their school by its sign-in link, its own address, or a remembered code.
            'show_school_field' => $active > 1 && pf_setting('show_school_field', '0') === '1',
            'school' => ['code' => $school['code'], 'name' => $school['name'], 'is_home' => $school['is_home']],
        ]);
    }

    // Managing schools: only super admins signed in to the home school.
    $payload = hr_require_auth(['super_admin']);
    if (($payload['role'] ?? '') !== 'super_admin' || !Tenant::current()['is_home']) {
        hr_respond(false, 'Only the platform owner can manage schools', null, 403);
    }
    $by = (string)($payload['email'] ?? $payload['full_name'] ?? 'super admin');
    $data = $method === 'GET' ? $_GET : hr_request_data();

    if ($method === 'GET' && $action === '') {
        hr_respond(true, 'Schools', pf_list() + ['settings' => ['show_school_field' => pf_setting('show_school_field', '0') === '1']]);
    }
    if ($method === 'POST' && $action === 'settings') {
        if (array_key_exists('show_school_field', $data)) {
            pf_set_setting('show_school_field', !empty($data['show_school_field']) ? '1' : '0');
        }
        $show = pf_setting('show_school_field', '0') === '1';
        hr_respond(true, $show ? 'The school code now shows on the sign-in page' : 'The school code is now hidden on the sign-in page', ['settings' => ['show_school_field' => $show]]);
    }
    if ($method === 'POST' && $action === 'setup') {
        hr_respond(true, 'School list ready', pf_setup($db, trim((string)($data['home_name'] ?? ''))));
    }
    if ($method === 'POST' && $action === 'create') {
        $result = pf_create_school($db, $data, $by);
        hr_respond(true, "{$result['school']['name']} is ready", $result);
    }
    if ($method === 'POST' && $action === 'update') {
        $school = pf_update_school(strtolower(trim((string)($data['code'] ?? ''))), $data);
        hr_respond(true, "{$school['name']} updated", ['school' => $school]);
    }
    hr_respond(false, 'Unknown action', null, 400);
} catch (PlatformException $e) {
    hr_respond(false, $e->getMessage(), $e->errors ? ['errors' => $e->errors] : null, $e->getCode() ?: 422);
} catch (Throwable $e) {
    hr_respond(false, 'Something went wrong: ' . $e->getMessage(), null, 500);
}
