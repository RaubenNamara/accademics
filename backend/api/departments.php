<?php
declare(strict_types=1);

require_once '../config/Database.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function dept_sendJson(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

try {
    $database = new Database();
    $db       = $database->getConnection();

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    switch ($method) {
        case 'GET':
            $search = trim((string)($_GET['search'] ?? ''));
            $includeRoles = ($_GET['include_roles'] ?? 'false') === 'true';
            
            $sql    = 'SELECT id, name, description, is_active FROM departments';
            $params = [];

            if ($search !== '') {
                $sql      .= ' WHERE name LIKE :search OR description LIKE :search';
                $params[':search'] = '%' . $search . '%';
            }

            $sql .= ' ORDER BY name ASC';

            $stmt = $db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
            $stmt->execute();

            $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Fetch roles for each department if requested
            if ($includeRoles) {
                foreach ($departments as &$dept) {
                    $roleStmt = $db->prepare('SELECT id, name, description FROM roles WHERE department_id = :dept_id ORDER BY name ASC');
                    $roleStmt->bindValue(':dept_id', $dept['id'], PDO::PARAM_INT);
                    $roleStmt->execute();
                    $dept['roles'] = $roleStmt->fetchAll(PDO::FETCH_ASSOC);
                }
            }

            dept_sendJson(200, [
                'success' => true,
                'data'    => $departments
            ]);
            break;

        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);

            if (empty($input['name'])) {
                dept_sendJson(400, [
                    'success' => false,
                    'error'   => 'Department name is required'
                ]);
            }

            $db->beginTransaction();
            
            try {
                $sql = 'INSERT INTO departments (name, description, is_active) VALUES (:name, :description, :is_active)';
                $stmt = $db->prepare($sql);
                $stmt->bindValue(':name', $input['name'], PDO::PARAM_STR);
                $stmt->bindValue(':description', $input['description'] ?? '', PDO::PARAM_STR);
                $stmt->bindValue(':is_active', $input['is_active'] ?? true, PDO::PARAM_BOOL);
                $stmt->execute();
                
                $departmentId = $db->lastInsertId();
                
                // Assign roles to department if provided
                if (!empty($input['role_ids']) && is_array($input['role_ids'])) {
                    $roleSql = 'UPDATE roles SET department_id = :dept_id WHERE id IN (' . implode(',', array_map('intval', $input['role_ids'])) . ')';
                    $roleStmt = $db->prepare($roleSql);
                    $roleStmt->bindValue(':dept_id', $departmentId, PDO::PARAM_INT);
                    $roleStmt->execute();
                }
                
                $db->commit();
                
                dept_sendJson(201, [
                    'success' => true,
                    'message' => 'Department created successfully',
                    'id' => $departmentId
                ]);
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }

        case 'PUT':
            $input = json_decode(file_get_contents('php://input'), true);

            if (empty($input['id']) || empty($input['name'])) {
                dept_sendJson(400, [
                    'success' => false,
                    'error'   => 'Department ID and name are required'
                ]);
            }

            $db->beginTransaction();
            
            try {
                $sql = 'UPDATE departments SET name = :name, description = :description, is_active = :is_active WHERE id = :id';
                $stmt = $db->prepare($sql);
                $stmt->bindValue(':id', $input['id'], PDO::PARAM_INT);
                $stmt->bindValue(':name', $input['name'], PDO::PARAM_STR);
                $stmt->bindValue(':description', $input['description'] ?? '', PDO::PARAM_STR);
                $stmt->bindValue(':is_active', $input['is_active'] ?? true, PDO::PARAM_BOOL);
                $stmt->execute();
                
                // Update role assignments
                // First, remove department_id from roles that were previously assigned to this department
                $clearSql = 'UPDATE roles SET department_id = NULL WHERE department_id = :dept_id';
                $clearStmt = $db->prepare($clearSql);
                $clearStmt->bindValue(':dept_id', $input['id'], PDO::PARAM_INT);
                $clearStmt->execute();
                
                // Then assign new roles to department if provided
                if (!empty($input['role_ids']) && is_array($input['role_ids'])) {
                    $roleSql = 'UPDATE roles SET department_id = :dept_id WHERE id IN (' . implode(',', array_map('intval', $input['role_ids'])) . ')';
                    $roleStmt = $db->prepare($roleSql);
                    $roleStmt->bindValue(':dept_id', $input['id'], PDO::PARAM_INT);
                    $roleStmt->execute();
                }
                
                $db->commit();
                
                dept_sendJson(200, [
                    'success' => true,
                    'message' => 'Department updated successfully'
                ]);
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }

        case 'DELETE':
            $id = $_GET['id'] ?? null;

            if (empty($id)) {
                dept_sendJson(400, [
                    'success' => false,
                    'error'   => 'Department ID is required'
                ]);
            }

            $sql = 'DELETE FROM departments WHERE id = :id';
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                dept_sendJson(200, [
                    'success' => true,
                    'message' => 'Department deleted successfully'
                ]);
            }

            dept_sendJson(500, [
                'success' => false,
                'error'   => 'Failed to delete department'
            ]);

        default:
            dept_sendJson(405, [
                'success' => false,
                'error'   => 'Method not allowed'
            ]);
    }
} catch (PDOException $e) {
    dept_sendJson(500, [
        'success' => false,
        'error'   => 'Database error',
        'database_error' => $e->getMessage()
    ]);
} catch (Throwable $e) {
    dept_sendJson(500, [
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}
