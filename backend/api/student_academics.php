<?php
/**
 * The subjects a student offers, and A-Level entry for students who completed S.4.
 *
 * GET  ?action=subjects&id=…                         compulsory + chosen subjects
 * POST ?action=subjects {student_id, subject_ids[]}  set the optional subjects
 * GET  ?action=awaiting[&q=…]                        students who completed S.4 (not yet in S.5)
 * POST ?action=admit-a-level {student_ids[], class_id, subject_ids[]}
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
    $payload = hr_require_auth(SF_ACADEMIC_ROLES);
    $by = (string)($payload['full_name'] ?? $payload['email'] ?? 'Staff');
    $action = (string)($_GET['action'] ?? '');

    if ($method === 'GET' && $action === 'subjects') {
        hr_respond(true, 'Student subjects', ac_student_subjects($db, (int)($_GET['id'] ?? 0)));
    }

    if ($method === 'GET' && $action === 'awaiting') {
        $q = trim((string)($_GET['q'] ?? ''));
        $sql = "SELECT s.id, s.admission_number, s.full_name, s.gender, s.class, s.stream, s.aggregate, s.division,
                (SELECT MAX(p.academic_year) FROM student_promotions p WHERE p.student_id = s.id AND p.to_status = 'completed_o_level') AS completed_year
            FROM students s WHERE s.status = 'completed_o_level'";
        $params = [];
        if ($q !== '') {
            $sql .= ' AND (s.admission_number LIKE :q OR s.full_name LIKE :q2)';
            $params = [':q' => "%$q%", ':q2' => "%$q%"];
        }
        $stmt = $db->prepare($sql . ' ORDER BY s.admission_number LIMIT 500');
        $stmt->execute($params);
        hr_respond(true, 'Completed S.4', [
            'students' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'streams' => $db->query("SELECT id, class_name, stream_name, full_class_name FROM classes WHERE class_name REGEXP '^S\\\\.? ?5' ORDER BY stream_name")->fetchAll(PDO::FETCH_ASSOC),
            'compulsory' => ac_compulsory($db)['A-Level'],
            'subjects' => ac_offered($db)['A-Level'],
        ]);
    }

    if ($method === 'POST') {
        $data = hr_request_data();
        if ($action === 'subjects') {
            hr_respond(true, 'Subjects saved', ac_set_student_subjects($db, (int)($data['student_id'] ?? 0), (array)($data['subject_ids'] ?? [])));
        }
        if ($action === 'admit-a-level') {
            $res = ac_admit_a_level($db, (array)($data['student_ids'] ?? []), (int)($data['class_id'] ?? 0), (array)($data['subject_ids'] ?? []), $by);
            hr_respond(true, "{$res['admitted']} student(s) admitted to {$res['class']}", $res);
        }
    }

    hr_respond(false, 'Unknown request', null, 400);
} catch (TtException $e) {
    hr_respond(false, $e->getMessage(), $e->data, $e->status);
} catch (Throwable $e) {
    hr_respond(false, $e->getMessage(), null, 500);
}
