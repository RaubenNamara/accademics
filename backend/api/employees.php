<?php
declare(strict_types=1);

require_once '../config/Database.php';
require_once '../config/JWT.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function emp_sendJson(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method !== 'GET') {
        emp_sendJson(405, [
            'success' => false,
            'error' => 'Method not allowed'
        ]);
    }

    $id          = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $staffType   = trim((string)($_GET['staff_type'] ?? ''));
    $department  = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
    $roleId      = isset($_GET['role_id']) ? (int)$_GET['role_id'] : 0;
    $status      = trim((string)($_GET['status'] ?? ''));
    $search      = trim((string)($_GET['search'] ?? ''));

    if ($id > 0) {
        $sql = "
            SELECT
                e.*,
                d.name  AS department_name,
                r.name  AS role_name,
                s.first_name AS supervisor_first_name,
                s.last_name  AS supervisor_last_name
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN roles r       ON e.role_id = r.id
            LEFT JOIN employees s   ON e.supervisor_id = s.id
            WHERE e.id = :id
            LIMIT 1
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $emp = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$emp) {
            emp_sendJson(404, [
                'success' => false,
                'error'   => 'Employee not found'
            ]);
        }

        if ($emp['staff_type'] === 'non_teaching') {
            $detailStmt = $db->prepare('SELECT * FROM non_teaching_staff WHERE employee_id = :eid LIMIT 1');
            $detailStmt->execute([':eid' => $emp['id']]);
            $emp['non_teaching'] = $detailStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        emp_sendJson(200, [
            'success' => true,
            'data'    => $emp
        ]);
    }

    $conditions = [];
    $params     = [];

    if ($staffType !== '') {
        $conditions[]          = 'e.staff_type = :staff_type';
        $params[':staff_type'] = $staffType;
    }

    if ($department > 0) {
        $conditions[]             = 'e.department_id = :department_id';
        $params[':department_id'] = $department;
    }

    if ($roleId > 0) {
        $conditions[]       = 'e.role_id = :role_id';
        $params[':role_id'] = $roleId;
    }

    if ($status !== '') {
        $conditions[]      = 'e.status = :status';
        $params[':status'] = $status;
    }

    if ($search !== '') {
        $conditions[] = '(
            e.hr_code      LIKE :search OR
            e.first_name   LIKE :search OR
            e.last_name    LIKE :search OR
            e.email        LIKE :search OR
            e.phone_number LIKE :search
        )';
        $params[':search'] = '%' . $search . '%';
    }

    $whereSql = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

    $sql = "
        SELECT DISTINCT
            e.id,
            e.staff_type,
            e.hr_code,
            e.first_name,
            e.last_name,
            e.gender,
            e.date_of_birth,
            e.phone_number,
            e.email,
            e.address,
            e.status,
            e.department_id,
            d.name AS department_name,
            e.role_id,
            r.name AS role_name,
            e.date_joined,
            e.salary,
            e.created_at
        FROM employees e
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN roles r       ON e.role_id = r.id
        $whereSql
        ORDER BY e.created_at DESC
    ";

    $stmt = $db->prepare($sql);
    foreach ($params as $key => $value) {
        $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stmt->bindValue($key, $value, $type);
    }
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    emp_sendJson(200, [
        'success' => true,
        'data'    => $rows
    ]);
} catch (PDOException $e) {
    emp_sendJson(500, [
        'success' => false,
        'error'   => 'Database error',
        'database_error' => $e->getMessage()
    ]);
} catch (Throwable $e) {
    emp_sendJson(500, [
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}
