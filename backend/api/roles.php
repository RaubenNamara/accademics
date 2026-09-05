<?php
declare(strict_types=1);

require_once '../config/Database.php';
require_once '../config/HrHelpers.php';

hr_cors_headers('GET, POST, OPTIONS');

try {
    $db = (new Database())->getConnection();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['name'])) {
            hr_respond(false, 'Role name is required', null, 400);
        }

        $sql = 'INSERT INTO roles (name, description, department_id) VALUES (:name, :description, :department_id)';
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':name', $input['name'], PDO::PARAM_STR);
        $stmt->bindValue(':description', $input['description'] ?? '', PDO::PARAM_STR);
        $stmt->bindValue(':department_id', $input['department_id'] ?? null, PDO::PARAM_INT);

        if ($stmt->execute()) {
            hr_respond(true, 'Role created successfully', ['id' => $db->lastInsertId()], 201);
        }

        hr_respond(false, 'Failed to create role', null, 500);
    }

    // GET request
    $deptId = (int)($_GET['department_id'] ?? 0);

    $sql = 'SELECT r.id, r.name, r.department_id, r.description, d.name AS department_name
            FROM roles r LEFT JOIN departments d ON d.id = r.department_id WHERE 1=1';
    $params = [];
    if ($deptId > 0) {
        $sql .= ' AND (r.department_id = :dept OR r.department_id IS NULL)';
        $params[':dept'] = $deptId;
    }
    $sql .= ' ORDER BY r.name ASC';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    hr_respond(true, 'Roles loaded', $stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Throwable $e) {
    hr_respond(false, $e->getMessage(), null, 500);
}
