<?php
declare(strict_types=1);

require_once '../config/Database.php';
require_once '../config/HrHelpers.php';

hr_cors_headers();

$db = (new Database())->getConnection();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        $id = (int)($_GET['id'] ?? 0);
        $year = (int)($_GET['academic_year'] ?? 0);
        $history = ($_GET['history'] ?? '0') === '1';
        $activeOnly = $history ? false : (($_GET['active_only'] ?? '1') === '1');
        $teacherId = (int)($_GET['teacher_id'] ?? 0);
        $classId = (int)($_GET['class_id'] ?? 0);

        if ($id > 0) {
            $stmt = $db->prepare("
                SELECT cta.*, t.full_name AS teacher_name, t.teacher_code, c.class_name
                FROM class_teacher_assignments cta
                LEFT JOIN teachers t ON t.id = cta.teacher_id
                LEFT JOIN classes c ON c.id = cta.class_id
                WHERE cta.id = :id LIMIT 1
            ");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                hr_respond(false, 'Assignment not found', null, 404);
            }
            hr_respond(true, 'Assignment loaded', $row);
        }

        $sql = "
            SELECT cta.*, t.full_name AS teacher_name, t.teacher_code, c.class_name
            FROM class_teacher_assignments cta
            LEFT JOIN teachers t ON t.id = cta.teacher_id
            LEFT JOIN classes c ON c.id = cta.class_id
            WHERE 1=1
        ";
        $params = [];

        if ($year > 0) {
            $sql .= ' AND cta.academic_year = :year';
            $params[':year'] = $year;
        }
        if ($activeOnly && !$history) {
            $sql .= ' AND cta.is_active = 1';
        }
        if ($teacherId > 0) {
            $sql .= ' AND cta.teacher_id = :teacher_id';
            $params[':teacher_id'] = $teacherId;
        }
        if ($classId > 0) {
            $sql .= ' AND cta.class_id = :class_id';
            $params[':class_id'] = $classId;
        }

        $sql .= ' ORDER BY cta.is_active DESC, cta.academic_year DESC, c.class_name ASC';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        hr_respond(true, 'Assignments loaded', $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        hr_require_auth(['admin', 'hr_manager', 'academic_office']);
    }

    if ($method === 'POST') {
        $data = hr_request_data();
        $teacherId = (int)($data['teacher_id'] ?? 0);
        $classId = (int)($data['class_id'] ?? 0);
        $year = (int)($data['academic_year'] ?? date('Y'));

        if ($teacherId <= 0 || $classId <= 0) {
            hr_respond(false, 'Teacher and class are required', null, 400);
        }

        $db->prepare("
            UPDATE class_teacher_assignments SET is_active = 0, end_date = CURDATE()
            WHERE class_id = :class_id AND academic_year = :year AND is_active = 1
              AND (stream IS NULL OR stream = :stream OR :stream = '')
        ")->execute([
            ':class_id' => $classId,
            ':year' => $year,
            ':stream' => trim((string)($data['stream'] ?? '')),
        ]);

        $stmt = $db->prepare("
            INSERT INTO class_teacher_assignments
            (teacher_id, class_id, stream, academic_year, term, start_date, is_active)
            VALUES (:teacher_id, :class_id, :stream, :year, :term, CURDATE(), 1)
        ");
        $stmt->execute([
            ':teacher_id' => $teacherId,
            ':class_id' => $classId,
            ':stream' => trim((string)($data['stream'] ?? '')) ?: null,
            ':year' => $year,
            ':term' => isset($data['term']) ? (int)$data['term'] : null,
        ]);

        hr_respond(true, 'Class teacher assigned', ['id' => (int)$db->lastInsertId()], 201);
    }

    if ($method === 'PATCH') {
        $data = hr_request_data();
        $id = (int)($data['id'] ?? 0);
        $newTeacherId = (int)($data['teacher_id'] ?? 0);

        if ($id <= 0 || $newTeacherId <= 0) {
            hr_respond(false, 'Assignment ID and new teacher required', null, 400);
        }

        $cur = $db->prepare('SELECT * FROM class_teacher_assignments WHERE id = :id LIMIT 1');
        $cur->execute([':id' => $id]);
        $row = $cur->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            hr_respond(false, 'Assignment not found', null, 404);
        }

        $db->prepare('UPDATE class_teacher_assignments SET is_active = 0, end_date = CURDATE() WHERE id = :id')
            ->execute([':id' => $id]);

        $stmt = $db->prepare("
            INSERT INTO class_teacher_assignments
            (teacher_id, class_id, stream, academic_year, term, start_date, is_active)
            VALUES (:teacher_id, :class_id, :stream, :year, :term, CURDATE(), 1)
        ");
        $stmt->execute([
            ':teacher_id' => $newTeacherId,
            ':class_id' => $row['class_id'],
            ':stream' => $row['stream'],
            ':year' => $row['academic_year'],
            ':term' => $row['term'],
        ]);

        hr_respond(true, 'Class teacher reassigned', ['id' => (int)$db->lastInsertId()]);
    }

    if ($method === 'DELETE') {
        $id = (int)($_GET['id'] ?? hr_request_data()['id'] ?? 0);
        $db->prepare('UPDATE class_teacher_assignments SET is_active = 0, end_date = CURDATE() WHERE id = :id')
            ->execute([':id' => $id]);
        hr_respond(true, 'Assignment ended');
    }

    hr_respond(false, 'Method not allowed', null, 405);
} catch (Throwable $e) {
    hr_respond(false, $e->getMessage(), null, 500);
}
