<?php
/**
 * Login check for API endpoints that predate hr_require_auth().
 *
 *   require_once __DIR__ . '/../config/ApiGuard.php';
 *   api_guard();                       // any staff login (not teacher-portal accounts)
 *   api_guard(['super_admin', 'admin']); // only these roles
 *
 * Stops the request with 401/403 JSON when the token is missing, forged,
 * expired or the role is not allowed. Returns the token payload otherwise.
 * CORS preflight (OPTIONS) is let through untouched.
 */
require_once __DIR__ . '/JWT.php';

const API_GUARD_PORTAL_ROLES = ['subject_teacher'];

function api_guard_header(): string
{
    $candidates = [
        $_SERVER['HTTP_AUTHORIZATION'] ?? '',
        $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '',
    ];
    // PHP-FPM (the live server) only exposes the header through getallheaders().
    foreach (['getallheaders', 'apache_request_headers'] as $fn) {
        if (function_exists($fn)) {
            foreach ((array)$fn() as $name => $value) {
                if (strcasecmp((string)$name, 'Authorization') === 0) {
                    $candidates[] = $value;
                }
            }
        }
    }
    foreach ($candidates as $value) {
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }
    }
    return '';
}

function api_guard_fail(int $status, string $message): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

/**
 * Classes, subjects and the curriculum are set up by the academic office and the super admin:
 * an admin (or HR) may read them but not change them.
 */
function api_guard_academic_setup_write(array $payload): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true) && in_array((string)($payload['role'] ?? ''), ['admin', 'hr_manager'], true)) {
        api_guard_fail(403, 'Classes and subjects are managed by the academic office');
    }
}

function api_guard(array $roles = []): array
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        return [];
    }
    $header = api_guard_header();
    if ($header === '') {
        api_guard_fail(401, 'Please sign in');
    }
    $payload = JWT::decode(preg_replace('/^Bearer\s+/i', '', $header));
    if (!$payload) {
        api_guard_fail(401, 'Your session has expired. Please sign in again.');
    }
    $role = (string)($payload['role'] ?? '');
    if ($roles) {
        if (!in_array($role, $roles, true)) {
            api_guard_fail(403, 'You do not have access to this');
        }
    } elseif (in_array($role, API_GUARD_PORTAL_ROLES, true)) {
        api_guard_fail(403, 'You do not have access to this');
    }
    return $payload;
}
