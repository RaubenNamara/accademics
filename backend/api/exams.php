<?php
/**
 * Examinations (incl. AOI) and marks entry.
 *
 * GET  ?academic_session_id=…                      exams of a term, with how many marks are in
 * GET  ?action=sheet&exam_id&class_id&subject_id   mark sheet (teachers: only what they teach)
 * GET  ?action=progress&exam_id                    marks entered per class & subject
 * POST                                             create / update an exam (academic staff)
 * POST ?action=status   {id, status}               draft → open → closed
 * POST ?action=marks    {exam_id, class_id, subject_id, rows:[{student_id, marks, comment}]}
 * DELETE ?id=…[&confirm=1]                         remove an exam (asks first if it has marks)
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
    $payload = hr_require_auth();
    $role = (string)($payload['role'] ?? '');
    $isStaff = sf_is_staff_role($role);
    $teacherId = null;
    if (!$isStaff) {
        if ($role !== SF_TEACHER_ROLE) {
            hr_respond(false, 'Forbidden', null, 403);
        }
        $teacherId = (int)sf_require_teacher($db)['teacher']['id'];
    }
    $action = (string)($_GET['action'] ?? '');
    $data = $method === 'GET' ? $_GET : hr_request_data();

    if ($method === 'GET' && $action === '') {
        [$sid] = tt_require_session($db, $_GET);
        $stmt = $db->prepare("SELECT e.*, (SELECT COUNT(*) FROM exam_marks m WHERE m.exam_id = e.id AND m.marks IS NOT NULL) AS marks_entered
            FROM exams e WHERE e.academic_session_id = :s " . ($isStaff ? '' : "AND e.status <> 'draft'") . "
            ORDER BY FIELD(e.exam_type,'BOT','AOI','MOT','EOT','OTHER'), e.id");
        $stmt->execute([':s' => $sid]);
        $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
        hr_respond(true, 'Exams', ['exams' => $exams, 'academic_session_id' => $sid, 'scheme' => sf_scheme($db)]);
    }

    if ($method === 'GET' && $action === 'sheet') {
        $exam = sf_exam($db, (int)($data['exam_id'] ?? 0)) ?? throw new TtException('Exam not found', 404);
        $classId = (int)($data['class_id'] ?? 0);
        $subjectId = (int)($data['subject_id'] ?? 0);
        if ($teacherId && !sf_teaches($db, $teacherId, (int)$exam['academic_session_id'], $classId, $subjectId)) {
            throw new TtException('You can only enter marks for the classes assigned to you this term', 403);
        }
        $scheme = sf_scheme($db);
        $scheme['counts'] = sf_counts_to_final((string)$exam['exam_type'], $scheme['exam_basis'], $scheme['school_type'] === 'primary');
        hr_respond(true, 'Mark sheet', ['exam' => $exam, 'scheme' => $scheme] + sf_mark_sheet($db, $exam, $classId, $subjectId));
    }

    if ($method === 'GET' && $action === 'progress') {
        if (!$isStaff) {
            hr_respond(false, 'Forbidden', null, 403);
        }
        $exam = sf_exam($db, (int)($data['exam_id'] ?? 0)) ?? throw new TtException('Exam not found', 404);
        $stmt = $db->prepare("SELECT lr.class_id, lr.subject_id, c.full_class_name, c.class_name, s.subject_name,
                GROUP_CONCAT(DISTINCT t.full_name SEPARATOR ', ') AS teachers,
                (SELECT COUNT(*) FROM exam_marks m WHERE m.exam_id = :e AND m.class_id = lr.class_id AND m.subject_id = lr.subject_id AND m.marks IS NOT NULL) AS entered
            FROM lesson_requirements lr JOIN classes c ON c.id = lr.class_id JOIN subjects_new s ON s.id = lr.subject_id JOIN teachers t ON t.id = lr.teacher_id
            WHERE lr.academic_session_id = :s GROUP BY lr.class_id, lr.subject_id ORDER BY c.class_name, c.stream_name, s.subject_name");
        $stmt->execute([':e' => $exam['id'], ':s' => $exam['academic_session_id']]);
        $rows = array_values(array_filter($stmt->fetchAll(PDO::FETCH_ASSOC), fn($r) => sf_exam_applies($exam, tt_class_level((string)$r['class_name']))));
        $sizes = [];
        foreach ($rows as &$r) {
            $sizes[$r['class_id']] ??= count(sf_class_students($db, (int)$r['class_id']));
            $r['students'] = $sizes[$r['class_id']];
            $r['entered'] = (int)$r['entered'];
        }
        unset($r);
        hr_respond(true, 'Progress', ['exam' => $exam, 'sheets' => $rows]);
    }

    if ($method === 'POST' && $action === 'marks') {
        $exam = sf_exam($db, (int)($data['exam_id'] ?? 0)) ?? throw new TtException('Exam not found', 404);
        $classId = (int)($data['class_id'] ?? 0);
        $subjectId = (int)($data['subject_id'] ?? 0);
        if ($teacherId && !sf_teaches($db, $teacherId, (int)$exam['academic_session_id'], $classId, $subjectId)) {
            throw new TtException('You can only enter marks for the classes assigned to you this term', 403);
        }
        $res = sf_save_marks($db, $exam, $classId, $subjectId, (array)($data['rows'] ?? []), isset($payload['id']) ? (int)$payload['id'] : null);
        hr_respond(true, "Saved {$res['saved']} mark(s)" . ($res['cleared'] ? ", cleared {$res['cleared']}" : ''), $res);
    }

    // Everything below manages exams: academic staff and administrators only.
    if (!$isStaff || !in_array($role, ['admin', 'super_admin', 'hr_manager', 'teacher', 'academic_office'], true)) {
        hr_respond(false, 'Only academic staff can set up exams', null, 403);
    }

    // Where the 80 comes from: mid term, end of term or both. Administrators only.
    if ($method === 'POST' && $action === 'scheme') {
        if (!in_array($role, ['admin', 'super_admin', 'academic_office'], true)) {
            hr_respond(false, 'Only an administrator can change the marking scheme', null, 403);
        }
        $basis = sf_save_exam_basis($db, (string)($data['exam_basis'] ?? ''));
        $label = ['MOT' => 'the mid term', 'EOT' => 'the end of term', 'both' => 'the mid term and end of term averaged'][$basis];
        hr_respond(true, (sf_is_primary($db) ? 'The final mark' : 'The 80') . " now comes from $label", ['scheme' => sf_scheme($db)]);
    }

    if ($method === 'POST' && $action === 'status') {
        $exam = sf_exam($db, (int)($data['id'] ?? 0)) ?? throw new TtException('Exam not found', 404);
        $status = (string)($data['status'] ?? '');
        if (!in_array($status, ['draft', 'open', 'closed'], true)) {
            throw new TtException('Status must be draft, open or closed', 422);
        }
        $db->prepare('UPDATE exams SET status = :st WHERE id = :id')->execute([':st' => $status, ':id' => $exam['id']]);
        $label = ['draft' => 'moved back to draft', 'open' => 'opened for marks entry', 'closed' => 'closed, marks are locked'][$status];
        hr_respond(true, "{$exam['name']} $label");
    }

    if ($method === 'POST' && $action === '') {
        [$sid] = tt_require_session($db, $data);
        if ((int)$db->query("SELECT is_archived FROM academic_sessions WHERE id = $sid")->fetchColumn() === 1) {
            throw new TtException('That term is closed. Set up exams in the open term or an upcoming one.', 409);
        }
        $id = (int)($data['id'] ?? 0);
        $name = mb_substr(trim((string)($data['name'] ?? '')), 0, 150);
        $type = (string)($data['exam_type'] ?? 'EOT');
        $max = $data['max_marks'] ?? null;
        // The share in the final mark comes from the type: each AOI out of 10, exams out of 80.
        $primary = sf_is_primary($db);
        $weight = in_array($type, SF_EXAM_TYPES, true) ? sf_component_out_of($type, $primary) : 0;
        $level = (string)($data['class_level'] ?? 'all');
        $errors = [];
        if ($name === '') {
            $errors['name'] = 'Give the exam a name, e.g. "AOI 1" or "End of Term"';
        }
        if (!in_array($type, SF_EXAM_TYPES, true)) {
            $errors['exam_type'] = 'Choose a type';
        } elseif ($primary && $type === 'AOI') {
            $errors['exam_type'] = 'Primary schools have no AOIs. Choose mid term or end of term.';
        }
        if (!is_numeric($max) || (float)$max <= 0 || (float)$max > 1000) {
            $errors['max_marks'] = 'Out of must be between 1 and 1000';
        }
        if (!in_array($level, ['all', 'O-Level', 'A-Level'], true)) {
            $errors['class_level'] = 'Choose who sits it';
        }
        $dup = $db->prepare('SELECT id FROM exams WHERE academic_session_id = :s AND LOWER(name) = LOWER(:n) AND id <> :id');
        $dup->execute([':s' => $sid, ':n' => $name, ':id' => $id]);
        if ($name !== '' && $dup->fetch()) {
            $errors['name'] = 'This term already has an exam with that name';
        }
        if ($id) {
            $old = sf_exam($db, $id) ?? throw new TtException('Exam not found', 404);
            $hasMarks = (int)$db->query("SELECT COUNT(*) FROM exam_marks WHERE exam_id = $id AND marks IS NOT NULL")->fetchColumn();
            if ($hasMarks && (float)$max < (float)$old['max_marks']) {
                $over = (int)$db->query("SELECT COUNT(*) FROM exam_marks WHERE exam_id = $id AND marks > " . (float)$max)->fetchColumn();
                if ($over) {
                    $errors['max_marks'] = "$over mark(s) already entered are above $max";
                }
            }
        }
        if ($errors) {
            throw new TtException((string)reset($errors), 422, ['errors' => $errors]);
        }
        $params = [':s' => $sid, ':n' => $name, ':t' => $type, ':m' => (float)$max, ':w' => (float)$weight, ':l' => $level,
            ':notes' => mb_substr(trim((string)($data['notes'] ?? '')), 0, 500) ?: null];
        if ($id) {
            $db->prepare('UPDATE exams SET academic_session_id = :s, name = :n, exam_type = :t, max_marks = :m, weight = :w, class_level = :l, notes = :notes WHERE id = :id')
                ->execute($params + [':id' => $id]);
            hr_respond(true, "$name updated", ['id' => $id]);
        }
        $db->prepare('INSERT INTO exams (academic_session_id, name, exam_type, max_marks, weight, class_level, notes, created_by) VALUES (:s, :n, :t, :m, :w, :l, :notes, :by)')
            ->execute($params + [':by' => isset($payload['id']) ? (int)$payload['id'] : null]);
        hr_respond(true, "$name created", ['id' => (int)$db->lastInsertId()], 201);
    }

    if ($method === 'DELETE') {
        $exam = sf_exam($db, (int)($_GET['id'] ?? 0)) ?? throw new TtException('Exam not found', 404);
        $count = (int)$db->query('SELECT COUNT(*) FROM exam_marks WHERE exam_id = ' . (int)$exam['id'])->fetchColumn();
        if ($count && empty($_GET['confirm'])) {
            hr_respond(false, "{$exam['name']} has $count mark(s). Deleting it removes them too.", ['needs_confirmation' => true, 'marks' => $count], 409);
        }
        $db->prepare('DELETE FROM exams WHERE id = :id')->execute([':id' => $exam['id']]);
        hr_respond(true, "{$exam['name']} deleted");
    }

    hr_respond(false, 'Unknown request', null, 400);
} catch (TtException $e) {
    hr_respond(false, $e->getMessage(), $e->data, $e->status);
} catch (Throwable $e) {
    error_log('exams.php: ' . $e->getMessage());
    hr_respond(false, 'Exam request failed: ' . $e->getMessage(), null, 500);
}
