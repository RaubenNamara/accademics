<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/Database.php';
require_once '../config/JWT.php';

$database = new Database();
$db = $database->getConnection();

function respond($success, $message = '', $data = null, $status = 200) {
    http_response_code($status);

    $payload = ['success' => $success];

    if ($message !== '') {
        $payload['message'] = $message;
    }

    if ($data !== null) {
        $payload['data'] = $data;
    }

    echo json_encode($payload);
    exit();
}

function getJsonInput() {
    $raw = file_get_contents("php://input");
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function userExistsByEmail(PDO $db, string $email, int $exceptId = 0): bool {
    if ($exceptId > 0) {
        $stmt = $db->prepare("
            SELECT id
            FROM users
            WHERE LOWER(TRIM(email)) = LOWER(TRIM(:email)) AND id <> :id
            LIMIT 1
        ");
        $stmt->execute([
            ':email' => $email,
            ':id' => $exceptId
        ]);
    } else {
        $stmt = $db->prepare("
            SELECT id
            FROM users
            WHERE LOWER(TRIM(email)) = LOWER(TRIM(:email))
            LIMIT 1
        ");
        $stmt->execute([
            ':email' => $email
        ]);
    }

    return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}

// Verify JWT token
function verifyAuth() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

    if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        return null;
    }

    $token = $matches[1];
    $jwt = new JWT();
    $decoded = $jwt->decode($token);

    if (!$decoded || !isset($decoded['id'])) {
        return null;
    }

    return $decoded;
}

$method = $_SERVER['REQUEST_METHOD'];
$input = getJsonInput();

// GET - Fetch users
if ($method === 'GET') {
    $auth = verifyAuth();
    if (!$auth) {
        respond(false, 'Unauthorized', null, 401);
    }

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $role = isset($_GET['role']) ? trim($_GET['role']) : '';
    $is_active = isset($_GET['is_active']) ? trim($_GET['is_active']) : '';

    // Get single user
    if ($id > 0) {
        $stmt = $db->prepare("
            SELECT id, full_name, email, role, is_active, last_login, created_at, updated_at
            FROM users
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            respond(true, '', $user);
        } else {
            respond(false, 'User not found', null, 404);
        }
    }

    // Get users with filters
    $query = "
        SELECT id, full_name, email, role, is_active, last_login, created_at, updated_at
        FROM users
        WHERE 1=1
    ";
    $params = [];

    if ($search !== '') {
        $query .= " AND (full_name LIKE :search OR email LIKE :search)";
        $params[':search'] = "%$search%";
    }

    if ($role !== '') {
        $query .= " AND role = :role";
        $params[':role'] = $role;
    }

    if ($is_active !== '') {
        $query .= " AND is_active = :is_active";
        $params[':is_active'] = $is_active;
    }

    $query .= " ORDER BY created_at DESC";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    respond(true, '', $users);
}

// POST - Create user
if ($method === 'POST') {
    $auth = verifyAuth();
    if (!$auth) {
        respond(false, 'Unauthorized', null, 401);
    }

    $full_name = trim($input['full_name'] ?? '');
    $email = trim($input['email'] ?? '');
    $password = trim($input['password'] ?? '');
    $role = trim($input['role'] ?? '');
    $is_active = isset($input['is_active']) ? (int)$input['is_active'] : 1;

    // Validation
    if ($full_name === '') {
        respond(false, 'Full name is required', null, 400);
    }

    if ($email === '') {
        respond(false, 'Email is required', null, 400);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        respond(false, 'Invalid email format', null, 400);
    }

    if ($password === '') {
        respond(false, 'Password is required', null, 400);
    }

    if (strlen($password) < 6) {
        respond(false, 'Password must be at least 6 characters', null, 400);
    }

    $allowedRoles = ['super_admin', 'admin', 'teacher', 'staff', 'academic_office', 'hr_manager'];
    if (!in_array($role, $allowedRoles)) {
        respond(false, 'Invalid role', null, 400);
    }

    // Check if email already exists
    if (userExistsByEmail($db, $email)) {
        respond(false, 'Email already exists', null, 409);
    }

    // Hash password
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // Insert user
    $stmt = $db->prepare("
        INSERT INTO users (full_name, email, password_hash, role, is_active)
        VALUES (:full_name, :email, :password_hash, :role, :is_active)
    ");

    try {
        $stmt->execute([
            ':full_name' => $full_name,
            ':email' => strtolower($email),
            ':password_hash' => $password_hash,
            ':role' => $role,
            ':is_active' => $is_active
        ]);

        $userId = $db->lastInsertId();

        // Fetch created user
        $userStmt = $db->prepare("
            SELECT id, full_name, email, role, is_active, last_login, created_at, updated_at
            FROM users
            WHERE id = :id
            LIMIT 1
        ");
        $userStmt->execute([':id' => $userId]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);

        respond(true, 'User created successfully', $user, 201);
    } catch (PDOException $e) {
        respond(false, 'Failed to create user: ' . $e->getMessage(), null, 500);
    }
}

// PUT - Update user
if ($method === 'PUT') {
    $auth = verifyAuth();
    if (!$auth) {
        respond(false, 'Unauthorized', null, 401);
    }

    $id = isset($input['id']) ? (int)$input['id'] : 0;
    $full_name = trim($input['full_name'] ?? '');
    $email = trim($input['email'] ?? '');
    $password = trim($input['password'] ?? '');
    $role = trim($input['role'] ?? '');
    $is_active = isset($input['is_active']) ? (int)$input['is_active'] : 1;

    if ($id <= 0) {
        respond(false, 'User ID is required', null, 400);
    }

    if ($full_name === '') {
        respond(false, 'Full name is required', null, 400);
    }

    if ($email === '') {
        respond(false, 'Email is required', null, 400);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        respond(false, 'Invalid email format', null, 400);
    }

    $allowedRoles = ['super_admin', 'admin', 'teacher', 'staff', 'academic_office', 'hr_manager'];
    if (!in_array($role, $allowedRoles)) {
        respond(false, 'Invalid role', null, 400);
    }

    // Check if email already exists for another user
    if (userExistsByEmail($db, $email, $id)) {
        respond(false, 'Email already exists', null, 409);
    }

    // Build update query
    $updateFields = [
        'full_name = :full_name',
        'email = :email',
        'role = :role',
        'is_active = :is_active'
    ];

    $params = [
        ':id' => $id,
        ':full_name' => $full_name,
        ':email' => strtolower($email),
        ':role' => $role,
        ':is_active' => $is_active
    ];

    // Add password if provided
    if ($password !== '') {
        if (strlen($password) < 6) {
            respond(false, 'Password must be at least 6 characters', null, 400);
        }
        $updateFields[] = 'password_hash = :password_hash';
        $params[':password_hash'] = password_hash($password, PASSWORD_DEFAULT);
    }

    $query = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = :id";

    try {
        $stmt = $db->prepare($query);
        $stmt->execute($params);

        if ($stmt->rowCount() === 0) {
            respond(false, 'User not found or no changes made', null, 404);
        }

        // Fetch updated user
        $userStmt = $db->prepare("
            SELECT id, full_name, email, role, is_active, last_login, created_at, updated_at
            FROM users
            WHERE id = :id
            LIMIT 1
        ");
        $userStmt->execute([':id' => $id]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);

        respond(true, 'User updated successfully', $user);
    } catch (PDOException $e) {
        respond(false, 'Failed to update user: ' . $e->getMessage(), null, 500);
    }
}

// PATCH - Toggle user status
if ($method === 'PATCH') {
    $auth = verifyAuth();
    if (!$auth) {
        respond(false, 'Unauthorized', null, 401);
    }

    $id = isset($input['id']) ? (int)$input['id'] : 0;
    $is_active = isset($input['is_active']) ? (int)$input['is_active'] : null;

    if ($id <= 0) {
        respond(false, 'User ID is required', null, 400);
    }

    if ($is_active === null) {
        respond(false, 'is_active status is required', null, 400);
    }

    try {
        $stmt = $db->prepare("
            UPDATE users
            SET is_active = :is_active
            WHERE id = :id
        ");
        $stmt->execute([
            ':is_active' => $is_active,
            ':id' => $id
        ]);

        if ($stmt->rowCount() === 0) {
            respond(false, 'User not found', null, 404);
        }

        respond(true, 'User status updated successfully');
    } catch (PDOException $e) {
        respond(false, 'Failed to update user status: ' . $e->getMessage(), null, 500);
    }
}

// DELETE - Delete user (soft delete by deactivating)
if ($method === 'DELETE') {
    $auth = verifyAuth();
    if (!$auth) {
        respond(false, 'Unauthorized', null, 401);
    }

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($id <= 0) {
        respond(false, 'User ID is required', null, 400);
    }

    try {
        // Soft delete by setting is_active to 0
        $stmt = $db->prepare("
            UPDATE users
            SET is_active = 0
            WHERE id = :id
        ");
        $stmt->execute([':id' => $id]);

        if ($stmt->rowCount() === 0) {
            respond(false, 'User not found', null, 404);
        }

        respond(true, 'User deleted successfully');
    } catch (PDOException $e) {
        respond(false, 'Failed to delete user: ' . $e->getMessage(), null, 500);
    }
}

// Method not allowed
respond(false, 'Method not allowed', null, 405);
