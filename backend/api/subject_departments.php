<?php
/**
 * Subject departments: which teachers belong to which subject (Biology, Mathematics, …).
 * Used to offer only a subject's own teachers when timetabling it.
 *
 * GET                      , every subject with its teachers
 * POST ?action=add         , {subject_id, teacher_ids[]}
 * POST ?action=remove      , {subject_id, teacher_id}
 * POST ?action=sync        , add teachers from their HR records (subject, second subject, department); {dry_run}
 */
declare(strict_types=1);

require_once '../config/Database.php';
require_once '../config/HrHelpers.php';
require_once '../config/TimetableService.php';

hr_cors_headers();

$db = (new Database())->getConnection();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    tt_ensure_subject_departments($db);

    if ($method === 'GET') {
        hr_require_auth();
        $subjects = $db->query('SELECT id, subject_name, subject_code FROM subjects_new ORDER BY subject_name')->fetchAll(PDO::FETCH_ASSOC);
        $members = $db->query('SELECT ts.subject_id, t.id, t.full_name, t.teacher_code, t.is_active
            FROM teacher_subjects ts JOIN teachers t ON t.id = ts.teacher_id ORDER BY t.full_name')->fetchAll(PDO::FETCH_ASSOC);
        $bySubject = [];
        foreach ($members as $m) {
            $bySubject[(int)$m['subject_id']][] = ['id' => (int)$m['id'], 'full_name' => $m['full_name'], 'teacher_code' => $m['teacher_code'],
                'is_active' => $m['is_active'] === null ? 1 : (int)$m['is_active']];
        }
        $inAny = (int)$db->query('SELECT COUNT(DISTINCT teacher_id) FROM teacher_subjects')->fetchColumn();
        $active = (int)$db->query('SELECT COUNT(*) FROM teachers WHERE is_active = 1 OR is_active IS NULL')->fetchColumn();
        hr_respond(true, 'Subject departments', [
            'subjects' => array_map(fn($s) => ['id' => (int)$s['id'], 'subject_name' => $s['subject_name'], 'subject_code' => $s['subject_code'],
                'teachers' => $bySubject[(int)$s['id']] ?? []], $subjects),
            'teachers_in_a_department' => $inAny,
            'active_teachers' => $active,
            // Active teachers not in any department yet, with what their HR record says they teach.
            'unassigned' => $db->query('SELECT t.id, t.full_name, t.teacher_code, t.subject, t.second_subject FROM teachers t
                WHERE (t.is_active = 1 OR t.is_active IS NULL) AND NOT EXISTS (SELECT 1 FROM teacher_subjects ts WHERE ts.teacher_id = t.id)
                ORDER BY t.full_name')->fetchAll(PDO::FETCH_ASSOC),
        ]);
    }

    if ($method !== 'POST') {
        hr_respond(false, 'Method not allowed', null, 405);
    }
    hr_require_auth(TT_EDITOR_ROLES);
    $data = hr_request_data();
    $action = (string)($_GET['action'] ?? '');

    if ($action === 'sync') {
        $result = tt_sync_subject_departments($db, !empty($data['dry_run']));
        hr_respond(true, $result['added'] ? "Added {$result['added']} teacher–subject link(s) from teacher records" : 'No new links found in teacher records', $result);
    }

    $subjectId = (int)($data['subject_id'] ?? 0);
    $stmt = $db->prepare('SELECT subject_name FROM subjects_new WHERE id = :id');
    $stmt->execute([':id' => $subjectId]);
    $subject = $stmt->fetchColumn();
    if (!$subject) {
        hr_respond(false, 'Choose a subject', null, 422);
    }

    if ($action === 'add') {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array)($data['teacher_ids'] ?? [$data['teacher_id'] ?? 0])))));
        if (!$ids) {
            hr_respond(false, 'Choose at least one teacher', null, 422);
        }
        $ins = $db->prepare('INSERT IGNORE INTO teacher_subjects (teacher_id, subject_id) SELECT id, :s FROM teachers WHERE id = :t');
        $added = 0;
        foreach ($ids as $tid) {
            $ins->execute([':s' => $subjectId, ':t' => $tid]);
            $added += $ins->rowCount();
        }
        hr_respond(true, $added ? "Added $added teacher(s) to $subject" : "Already in $subject", ['added' => $added]);
    }

    if ($action === 'remove') {
        $tid = (int)($data['teacher_id'] ?? 0);
        // Warn instead of silently breaking requirements that still use this teacher for this subject.
        $used = $db->prepare('SELECT COUNT(*) FROM lesson_requirements WHERE subject_id = :s AND teacher_id = :t');
        $used->execute([':s' => $subjectId, ':t' => $tid]);
        $count = (int)$used->fetchColumn();
        if ($count && empty($data['confirm'])) {
            hr_respond(false, "This teacher still has $count $subject requirement(s). Remove anyway?", ['needs_confirmation' => true, 'requirements' => $count], 409);
        }
        $db->prepare('DELETE FROM teacher_subjects WHERE subject_id = :s AND teacher_id = :t')->execute([':s' => $subjectId, ':t' => $tid]);
        hr_respond(true, "Removed from $subject");
    }

    hr_respond(false, 'Unknown action', null, 400);
} catch (TtException $e) {
    hr_respond(false, $e->getMessage(), $e->data, $e->status);
} catch (Throwable $e) {
    hr_respond(false, $e->getMessage(), null, 500);
}
