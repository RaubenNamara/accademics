<?php
/**
 * Report settings: school name, motto, address, contacts, logo (shown on report cards),
 * head teacher, next term date and the grade scale.
 *
 * GET   any signed-in user (report cards need it)
 * POST  administrators and academic staff
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
    if ($method === 'GET') {
        hr_require_auth();
        hr_respond(true, 'Report settings', sf_settings($db));
    }
    if ($method === 'POST') {
        hr_require_auth(SF_ACADEMIC_ROLES);
        hr_respond(true, 'Report settings saved', sf_save_settings($db, hr_request_data()));
    }
    hr_respond(false, 'Method not allowed', null, 405);
} catch (TtException $e) {
    hr_respond(false, $e->getMessage(), $e->data, $e->status);
} catch (Throwable $e) {
    hr_respond(false, $e->getMessage(), null, 500);
}
