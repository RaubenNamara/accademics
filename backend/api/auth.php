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

try {

    $database = new Database();
    $db = $database->getConnection();

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
                'message' => 'Email and password required'
            ]);
            exit;
        }

        $email = strtolower(trim($data->email));
        $password = (string)$data->password;

        $stmt = $db->prepare("
            SELECT
                id,
                full_name,
                email,
                password_hash,
                role,
                is_active
            FROM users
            WHERE LOWER(TRIM(email)) = :email
            LIMIT 1
        ");

        $stmt->execute([
            ':email' => $email
        ]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            echo json_encode([
                'success' => false,
                'message' => 'User not found'
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
                'message' => 'Invalid credentials'
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

        $token = JWT::encode([
            'id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role'],
            'full_name' => $user['full_name']
        ]);

        echo json_encode([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'full_name' => $user['full_name'],
                'email' => $user['email'],
                'role' => $user['role']
            ]
        ]);

        exit;
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