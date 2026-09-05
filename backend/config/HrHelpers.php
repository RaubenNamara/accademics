<?php
declare(strict_types=1);

require_once __DIR__ . '/JWT.php';

function hr_cors_headers(string $methods = 'GET, POST, PUT, PATCH, DELETE, OPTIONS'): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: ' . $methods);
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Allow-Credentials: true');

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

function hr_respond(bool $success, string $message = '', $data = null, int $status = 200): void
{
    http_response_code($status);
    $payload = ['success' => $success];
    if ($message !== '') {
        $payload['message'] = $message;
    }
    if ($data !== null) {
        $payload['data'] = $data;
    }
    echo json_encode($payload);
    exit;
}

function hr_json_input(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function hr_request_data(): array
{
    $json = hr_json_input();
    if (!empty($json)) {
        return $json;
    }
    return !empty($_POST) ? $_POST : [];
}

function hr_auth_header(): string
{
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (!empty($headers['Authorization'])) {
            return trim((string)$headers['Authorization']);
        }
        if (!empty($headers['authorization'])) {
            return trim((string)$headers['authorization']);
        }
    }
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        return trim((string)$_SERVER['HTTP_AUTHORIZATION']);
    }
    if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        return trim((string)$_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
    }
    return '';
}

function hr_require_auth(array $allowedRoles = []): array
{
    $auth = hr_auth_header();
    if ($auth === '') {
        hr_respond(false, 'Authorization required', null, 401);
    }

    $token = preg_replace('/^Bearer\s+/i', '', $auth);
    $payload = JWT::decode($token);

    if (!$payload) {
        hr_respond(false, 'Invalid or expired token', null, 401);
    }

    if (!empty($allowedRoles)) {
        $role = (string)($payload['role'] ?? '');
        $allowed = array_merge($allowedRoles, ['admin', 'super_admin', 'hr_manager']);
        if (!in_array($role, $allowed, true)) {
            hr_respond(false, 'Forbidden', null, 403);
        }
    }

    return $payload;
}

/**
 * Sequential HR code: TS-001, NTS-002, etc.
 */
function hr_generate_sequential_code(PDO $db, string $prefix): string
{
    $pattern = $prefix . '-%';
    $maxNum = 0;

    $queries = [
        "SELECT hr_code FROM employees WHERE hr_code LIKE :p ORDER BY id DESC LIMIT 200",
    ];

    if ($prefix === 'TS') {
        $queries[] = "SELECT teacher_code AS hr_code FROM teachers WHERE teacher_code LIKE :p ORDER BY id DESC LIMIT 200";
    }

    foreach ($queries as $sql) {
        try {
            $stmt = $db->prepare($sql);
            $stmt->execute([':p' => $pattern]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $code = (string)($row['hr_code'] ?? '');
                if (preg_match('/^' . preg_quote($prefix, '/') . '-(\d+)$/i', $code, $m)) {
                    $maxNum = max($maxNum, (int)$m[1]);
                }
            }
        } catch (Throwable $e) {
            // table/column may not exist yet
        }
    }

    $next = $maxNum + 1;
    return $prefix . '-' . str_pad((string)$next, 3, '0', STR_PAD_LEFT);
}

function hr_split_name(string $fullName): array
{
    $fullName = trim($fullName);
    if ($fullName === '') {
        return ['first' => 'Staff', 'last' => 'Member'];
    }
    $parts = preg_split('/\s+/', $fullName);
    $first = array_shift($parts);
    $last = $parts ? implode(' ', $parts) : $first;
    return ['first' => $first, 'last' => $last];
}

function hr_sync_employee_for_teacher(PDO $db, int $teacherId, array $teacherRow): ?int
{
    try {
        $check = $db->prepare('SELECT employee_id, teacher_code, full_name, email, contact, is_active FROM teachers WHERE id = :id LIMIT 1');
        $check->execute([':id' => $teacherId]);
        $t = $check->fetch(PDO::FETCH_ASSOC);
        if (!$t) {
            return null;
        }

        $names = hr_split_name((string)$t['full_name']);
        $hrCode = (string)($t['teacher_code'] ?? hr_generate_sequential_code($db, 'TS'));
        $status = ((int)($t['is_active'] ?? 1)) === 1 ? 'active' : 'inactive';

        if (!empty($t['employee_id'])) {
            $upd = $db->prepare("
                UPDATE employees SET
                    hr_code = :hr_code,
                    first_name = :first_name,
                    last_name = :last_name,
                    email = :email,
                    phone_number = :phone,
                    status = :status,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $upd->execute([
                ':hr_code' => $hrCode,
                ':first_name' => $names['first'],
                ':last_name' => $names['last'],
                ':email' => $t['email'],
                ':phone' => $t['contact'],
                ':status' => $status,
                ':id' => (int)$t['employee_id'],
            ]);
            return (int)$t['employee_id'];
        }

        $ins = $db->prepare("
            INSERT INTO employees (staff_type, hr_code, first_name, last_name, email, phone_number, status, created_at, updated_at)
            VALUES ('teaching', :hr_code, :first_name, :last_name, :email, :phone, :status, NOW(), NOW())
        ");
        $ins->execute([
            ':hr_code' => $hrCode,
            ':first_name' => $names['first'],
            ':last_name' => $names['last'],
            ':email' => $t['email'],
            ':phone' => $t['contact'],
            ':status' => $status,
        ]);
        $employeeId = (int)$db->lastInsertId();

        $link = $db->prepare('UPDATE teachers SET employee_id = :eid, teacher_code = :code WHERE id = :id');
        $link->execute([':eid' => $employeeId, ':code' => $hrCode, ':id' => $teacherId]);

        return $employeeId;
    } catch (Throwable $e) {
        return null;
    }
}
