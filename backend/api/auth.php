<?php

$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: $origin");

header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/Database.php';
require_once '../config/JWT.php';
require_once '../config/SchoolService.php';

try {

    $database = new Database();
    $db = $database->getConnection();
    tt_ensure_schema($db);
    sf_ensure_schema($db);

    // Log request for debugging
    error_log("Auth request - Origin: " . ($_SERVER['HTTP_ORIGIN'] ?? 'none'));
    error_log("Auth request - Action: " . ($_GET['action'] ?? 'none'));
    error_log("Auth request - Method: " . ($_SERVER['REQUEST_METHOD'] ?? 'none'));

    $action = $_GET['action'] ?? '';

    $rawInput = file_get_contents("php://input");
    $data = json_decode($rawInput);

    if ($action === 'login') {

        if (
            !$data ||
            empty($data->email) ||
            !isset($data->password)
        ) {
            echo json_encode([
                'success' => false,
                'message' => 'Enter your email (or teacher code) and password'
            ]);
            exit;
        }

        $email = strtolower(trim($data->email));
        $password = (string)$data->password;

        // Teachers may sign in with their email or their teacher code (e.g. TS-012).
        $stmt = $db->prepare("
            SELECT
                id,
                full_name,
                email,
                password_hash,
                role,
                is_active,
                teacher_id,
                must_change_password
            FROM users
            WHERE LOWER(TRIM(email)) = :email
               OR teacher_id = (SELECT id FROM teachers WHERE LOWER(TRIM(teacher_code)) = :code LIMIT 1)
            ORDER BY (LOWER(TRIM(email)) = :email2) DESC
            LIMIT 1
        ");

        $stmt->execute([
            ':email' => $email,
            ':code' => $email,
            ':email2' => $email
        ]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            // Same answer as a wrong password, so the form does not reveal which accounts exist.
            echo json_encode([
                'success' => false,
                'message' => 'Email or password is incorrect'
            ]);
            exit;
        }

        if ((int)$user['is_active'] !== 1) {
            echo json_encode([
                'success' => false,
                'message' => 'Account inactive'
            ]);
            exit;
        }

        if (
            empty($user['password_hash']) ||
            !password_verify($password, $user['password_hash'])
        ) {
            echo json_encode([
                'success' => false,
                'message' => 'Email or password is incorrect'
            ]);
            exit;
        }

        $update = $db->prepare("
            UPDATE users
            SET last_login = NOW()
            WHERE id = :id
        ");

        $update->execute([
            ':id' => $user['id']
        ]);

        // The school is part of the token, so the session can never reach another school.
        // A single-school install has no code; a token without one belongs to the home school.
        $school = Tenant::current();
        $token = JWT::encode(($school['code'] !== null ? ['school' => $school['code']] : []) + [
            'id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role'],
            'full_name' => $user['full_name'],
            'teacher_id' => $user['teacher_id'] ? (int)$user['teacher_id'] : null
        ]);

        echo json_encode([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'full_name' => $user['full_name'],
                'email' => $user['email'],
                'role' => $user['role'],
                'teacher_id' => $user['teacher_id'] ? (int)$user['teacher_id'] : null,
                'must_change_password' => (int)$user['must_change_password'] === 1,
                'school' => ['code' => $school['code'], 'name' => $school['name'], 'is_home' => $school['is_home']]
            ]
        ]);

        exit;
    }

    if ($action === 'change-password') {
        $payload = hr_require_auth();
        $current = (string)($data->current_password ?? '');
        $new = (string)($data->new_password ?? '');
        $row = $db->prepare('SELECT password_hash FROM users WHERE id = :id AND is_active = 1');
        $row->execute([':id' => (int)$payload['id']]);
        $hash = $row->fetchColumn();
        if (!$hash || !password_verify($current, (string)$hash)) {
            hr_respond(false, 'Your current password is not correct', ['errors' => ['current_password' => 'Not correct']], 422);
        }
        if (strlen($new) < 8 || !preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new)) {
            hr_respond(false, 'Use at least 8 characters with letters and numbers', ['errors' => ['new_password' => 'At least 8 characters, letters and numbers']], 422);
        }
        if (password_verify($new, (string)$hash)) {
            hr_respond(false, 'Choose a password different from the current one', ['errors' => ['new_password' => 'Must be different']], 422);
        }
        $db->prepare('UPDATE users SET password_hash = :h, must_change_password = 0 WHERE id = :id')
            ->execute([':h' => password_hash($new, PASSWORD_DEFAULT), ':id' => (int)$payload['id']]);
        hr_respond(true, 'Password changed');
    }

    if ($action === 'verify') {

        $headers = getallheaders();

        $authHeader =
            $headers['Authorization']
            ?? $headers['authorization']
            ?? '';

        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            http_response_code(401);

            echo json_encode([
                'success' => false,
                'message' => 'No token provided'
            ]);

            exit;
        }

        $payload = JWT::decode($matches[1]);

        if (!$payload) {
            http_response_code(401);

            echo json_encode([
                'success' => false,
                'message' => 'Invalid token'
            ]);

            exit;
        }

        echo json_encode([
            'success' => true,
            'user' => $payload
        ]);

        exit;
    }

    echo json_encode([
        'success' => false,
        'message' => 'Invalid action'
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}