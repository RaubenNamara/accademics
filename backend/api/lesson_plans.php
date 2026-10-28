<?php
/**
 * Lesson plans (NCDC competency-based template).
 * Teachers write, edit and submit their own plans; academic staff review them.
 *
 * GET  [?id=…][&status=…&teacher_id=…&class_id=…&academic_session_id=…]
 * POST {…plan, submit?:bool}            create / update (teacher, own plans, while draft or returned)
 * POST ?action=review {id, decision: approve|return, comment}   (academic staff)
 * POST ?action=seen {id}               a reviewer opened the plan: it is no longer "new" for its department
 * GET  ?view=new_count                 submitted plans no reviewer has opened yet (sidebar badge)
 * DELETE ?id=…                          own plan, any status (staff: any)
 *
 * A lesson is 1–2 periods at O-Level (single, double) and 1–4 at A-Level (up to quadruple); a period is
 * 40 minutes. The learning procedure is a list of phases {minutes, teacher, learners} that must add up
 * to the lesson's length before the plan can be submitted.
 */
declare(strict_types=1);

require_once '../config/Database.php';
require_once '../config/HrHelpers.php';
require_once '../config/CurriculumService.php';

hr_cors_headers();
$db = (new Database())->getConnection();
require_once __DIR__ . '/../config/TermLock.php';
term_guard($db, 'lesson_plans', ['session' => 'academic_session_id']); // a closed term is read-only
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

const LP_PERIOD_MINUTES = 40;
const LP_MAX_SPAN = ['O-Level' => 2, 'A-Level' => 4];
const LP_TEXT_FIELDS = [
    'theme' => 255, 'competences' => 2000, 'generic_skills' => 3000, 'values_text' => 2000, 'cross_cutting' => 2000,
    'objectives' => 3000, 'prerequisite' => 2000, 'content' => 20000, 'materials' => 2000, 'references_text' => 1000, 'reflection' => 3000,
    // Earlier free-text layout, kept so older plans still open and save.
    'introduction' => 3000, 'development' => 6000, 'conclusion' => 3000, 'assessment' => 2000,
];

function lp_fetch(PDO $db, string $where, array $params, string $order = 'COALESCE(p.lesson_date, DATE(p.created_at)) DESC, p.id DESC'): array
{
    $stmt = $db->prepare("SELECT p.*, t.full_name AS teacher_name, t.teacher_code, c.full_class_name, c.class_name, s.subject_name, s.subject_code,
            a.academic_year, a.term, a.start_date AS term_start
        FROM lesson_plans p JOIN teachers t ON t.id = p.teacher_id JOIN classes c ON c.id = p.class_id JOIN subjects_new s ON s.id = p.subject_id
        LEFT JOIN academic_sessions a ON a.id = p.academic_session_id
        WHERE $where ORDER BY $order LIMIT 500");
    $stmt->execute($params);
    return array_map(static function (array $p): array {
        $p['procedure'] = json_decode((string)($p['procedure_json'] ?? ''), true) ?: [];
        unset($p['procedure_json']);
        $p['level'] = tt_class_level((string)$p['class_name']);
        $p['is_new'] = $p['status'] === 'submitted' && !$p['staff_seen_at'];
        return $p;
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

/** Week of term for a date (1 = the week the term starts). */
function lp_week(?string $termStart, string $date): ?int
{
    if (!$termStart || !$date) {
        return null;
    }
    $days = (int)floor((strtotime($date) - strtotime($termStart)) / 86400);
    return $days < 0 ? null : intdiv($days, 7) + 1;
}

try {
    tt_ensure_schema($db);
    cu_ensure_schema($db);
    sf_ensure_schema($db);
    $payload = hr_require_auth();
    $role = (string)($payload['role'] ?? '');
    $isStaff = sf_is_staff_role($role);
    $teacher = null;
    if (!$isStaff) {
        if ($role !== SF_TEACHER_ROLE) {
            hr_respond(false, 'Forbidden', null, 403);
        }
        $teacher = sf_require_teacher($db)['teacher'];
    }
    $action = (string)($_GET['action'] ?? '');

    if ($method === 'GET' && ($_GET['view'] ?? '') === 'new_count') {
        $n = $isStaff ? (int)$db->query("SELECT COUNT(*) FROM lesson_plans WHERE status = 'submitted' AND staff_seen_at IS NULL")->fetchColumn() : 0;
        hr_respond(true, 'New lesson plans', ['new' => $n]);
    }

    if ($method === 'GET') {
        $where = ['1 = 1'];
        $params = [];
        if ($teacher) {
            $where[] = 'p.teacher_id = :me';
            $params[':me'] = (int)$teacher['id'];
        } elseif (!empty($_GET['teacher_id'])) {
            $where[] = 'p.teacher_id = :t';
            $params[':t'] = (int)$_GET['teacher_id'];
        }
        foreach (['class_id' => 'p.class_id', 'academic_session_id' => 'p.academic_session_id'] as $k => $col) {
            if (!empty($_GET[$k])) {
                $where[] = "$col = :$k";
                $params[":$k"] = (int)$_GET[$k];
            }
        }
        if (!empty($_GET['status']) && in_array($_GET['status'], ['draft', 'submitted', 'approved', 'returned'], true)) {
            $where[] = 'p.status = :status';
            $params[':status'] = $_GET['status'];
        }
        if (!empty($_GET['id'])) {
            $where[] = 'p.id = :id';
            $params[':id'] = (int)$_GET['id'];
        }
        if (!$teacher && empty($_GET['status']) && empty($_GET['id'])) {
            $where[] = "p.status <> 'draft'"; // staff see plans once they are submitted
        }
        // Reviewers see the latest submissions first; teachers see their plans by lesson date.
        $order = $teacher ? 'COALESCE(p.lesson_date, DATE(p.created_at)) DESC, p.id DESC' : 'COALESCE(p.submitted_at, p.updated_at) DESC, p.id DESC';
        hr_respond(true, 'Lesson plans', lp_fetch($db, implode(' AND ', $where), $params, $order));
    }

    if ($method === 'POST' && $action === 'seen') {
        if (!$isStaff) {
            hr_respond(false, 'Forbidden', null, 403);
        }
        $id = (int)(hr_request_data()['id'] ?? 0);
        // updated_at is kept as it was: opening a plan does not change it.
        $db->prepare('UPDATE lesson_plans SET staff_seen_at = NOW(), updated_at = updated_at WHERE id = :id AND staff_seen_at IS NULL')->execute([':id' => $id]);
        hr_respond(true, 'Seen');
    }

    if ($method === 'POST' && $action === 'review') {
        if (!$isStaff) {
            hr_respond(false, 'Only academic staff can review lesson plans', null, 403);
        }
        $data = hr_request_data();
        $plan = lp_fetch($db, 'p.id = :id', [':id' => (int)($data['id'] ?? 0)])[0] ?? throw new TtException('Lesson plan not found', 404);
        $decision = (string)($data['decision'] ?? '');
        $comment = mb_substr(trim((string)($data['comment'] ?? '')), 0, 1000);
        if (!in_array($decision, ['approve', 'return'], true)) {
            throw new TtException('Choose approve or return', 422);
        }
        if ($decision === 'return' && $comment === '') {
            throw new TtException('Say what needs changing when returning a plan', 422, ['errors' => ['comment' => 'Add a comment for the teacher']]);
        }
        if ($plan['status'] === 'draft') {
            throw new TtException('This plan has not been submitted yet', 409);
        }
        $db->prepare('UPDATE lesson_plans SET status = :st, review_comment = :c, reviewed_by = :by, reviewed_at = NOW(), staff_seen_at = COALESCE(staff_seen_at, NOW()) WHERE id = :id')
            ->execute([':st' => $decision === 'approve' ? 'approved' : 'returned', ':c' => $comment ?: null, ':by' => (string)($payload['full_name'] ?? 'Reviewer'), ':id' => $plan['id']]);
        hr_respond(true, $decision === 'approve' ? 'Lesson plan approved' : 'Lesson plan returned to the teacher');
    }

    if ($method === 'POST') {
        if (!$teacher) {
            hr_respond(false, 'Lesson plans are written by teachers from their own login', null, 403);
        }
        $data = hr_request_data();
        $id = (int)($data['id'] ?? 0);
        if ($id) {
            $existing = lp_fetch($db, 'p.id = :id AND p.teacher_id = :t', [':id' => $id, ':t' => (int)$teacher['id']])[0] ?? null;
            if (!$existing) {
                throw new TtException('Lesson plan not found', 404);
            }
            if (!in_array($existing['status'], ['draft', 'returned'], true)) {
                throw new TtException("This plan is {$existing['status']} and can no longer be edited", 409);
            }
        }
        [$sid] = tt_resolve_session($db, $data);
        $classId = (int)($data['class_id'] ?? 0);
        $subjectId = (int)($data['subject_id'] ?? 0);
        $topic = mb_substr(trim((string)($data['topic'] ?? '')), 0, 255);
        $submit = !empty($data['submit']);
        $errors = [];
        $class = $classId ? sf_class($db, $classId) : null;
        if (!$class) {
            $errors['class_id'] = 'Choose a class';
        }
        if (!$subjectId) {
            $errors['subject_id'] = 'Choose a subject';
        }
        if ($topic === '') {
            $errors['topic'] = 'Enter the topic';
        }
        $date = trim((string)($data['lesson_date'] ?? ''));
        if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $errors['lesson_date'] = 'Enter a valid date';
        }
        $start = trim((string)($data['start_time'] ?? ''));
        if ($start !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d(:00)?$/', $start)) {
            $errors['start_time'] = 'Enter a valid start time';
        }
        $level = $class ? $class['level'] : 'O-Level';
        $span = (int)($data['lesson_span'] ?? 1);
        if ($span < 1 || $span > LP_MAX_SPAN[$level]) {
            $errors['lesson_span'] = $level === 'O-Level' ? 'O-Level lessons are single or double' : 'A-Level lessons are single, double, triple or quadruple';
        }
        $duration = $span * LP_PERIOD_MINUTES;
        $week = $data['week_number'] ?? null;
        if ($week !== null && $week !== '' && (!is_numeric($week) || (int)$week < 1 || (int)$week > 20)) {
            $errors['week_number'] = 'Week must be between 1 and 20';
        }
        $learners = $data['learners'] ?? null;
        if ($learners !== null && $learners !== '' && (!is_numeric($learners) || (int)$learners < 0 || (int)$learners > 1000)) {
            $errors['learners'] = 'Enter the number of learners';
        }

        // Learning procedure: phases with minutes, teacher activity and learners' activity.
        $procedure = [];
        foreach (array_slice((array)($data['procedure'] ?? []), 0, 15) as $row) {
            $row = (array)$row;
            $minutes = (int)($row['minutes'] ?? 0);
            $teacherAct = mb_substr(trim((string)($row['teacher'] ?? '')), 0, 3000);
            $learnerAct = mb_substr(trim((string)($row['learners'] ?? '')), 0, 3000);
            if ($minutes === 0 && $teacherAct === '' && $learnerAct === '') {
                continue;
            }
            $procedure[] = ['minutes' => max(0, min(400, $minutes)), 'teacher' => $teacherAct, 'learners' => $learnerAct];
        }

        if ($classId && $subjectId && $sid && !isset($errors['class_id'])) {
            $teaches = sf_teaches($db, (int)$teacher['id'], $sid, $classId, $subjectId);
            $inDept = $db->prepare('SELECT COUNT(*) FROM teacher_subjects WHERE teacher_id = :t AND subject_id = :s');
            $inDept->execute([':t' => $teacher['id'], ':s' => $subjectId]);
            if (!$teaches && !(int)$inDept->fetchColumn()) {
                $errors['subject_id'] = 'You do not teach this subject (not in your timetable, requirements or department)';
            }
        }
        if ($submit) {
            foreach (['lesson_date' => 'Choose the lesson date', 'competences' => 'Write the competency', 'objectives' => 'Write the key learning outcome(s)'] as $f => $msg) {
                if (trim((string)($data[$f] ?? '')) === '') {
                    $errors[$f] = $msg . ' before submitting';
                }
            }
            $legacy = trim((string)($data['development'] ?? '')) !== '';
            if (!$procedure && !$legacy) {
                $errors['procedure'] = 'Add the learning procedure before submitting';
            } elseif ($procedure) {
                $total = array_sum(array_column($procedure, 'minutes'));
                if ($total !== $duration) {
                    $errors['procedure'] = "The procedure adds up to $total minutes but the lesson is $duration minutes";
                }
                foreach ($procedure as $i => $p) {
                    if ($p['teacher'] === '' || $p['learners'] === '' || $p['minutes'] < 1) {
                        $errors['procedure'] = 'Phase ' . ($i + 1) . ': give the minutes, teacher activity and learners\' activity';
                        break;
                    }
                }
            }
        }
        // A plan may come from a curriculum topic of the same subject.
        $curriculumId = (int)($data['curriculum_topic_id'] ?? 0) ?: null;
        if ($curriculumId && (int)$db->query("SELECT subject_id FROM curriculum_topics WHERE id = $curriculumId")->fetchColumn() !== $subjectId) {
            $curriculumId = null;
        }
        if ($errors) {
            throw new TtException((string)reset($errors), 422, ['errors' => $errors]);
        }

        if (($week === null || $week === '') && $date !== '' && $sid) {
            $week = lp_week((string)$db->query("SELECT start_date FROM academic_sessions WHERE id = $sid")->fetchColumn(), $date);
        }
        if ($learners === null || $learners === '') {
            $learners = count(sf_class_students($db, $classId));
        }
        $values = [
            ':sid' => $sid, ':c' => $classId, ':s' => $subjectId, ':d' => $date ?: null, ':dur' => $duration, ':span' => $span,
            ':st' => $start !== '' ? substr($start, 0, 5) : null, ':wk' => $week !== null && $week !== '' ? (int)$week : null, ':ln' => (int)$learners,
            ':topic' => $topic, ':sub' => mb_substr(trim((string)($data['subtopic'] ?? '')), 0, 255) ?: null,
            ':proc' => $procedure ? json_encode($procedure, JSON_UNESCAPED_UNICODE) : null, ':status' => $submit ? 'submitted' : 'draft', ':cu' => $curriculumId,
        ];
        $data['competences'] = cu_learner_sentence($data['competences'] ?? ''); // always "The learner …"
        foreach (LP_TEXT_FIELDS as $f => $max) {
            $values[":$f"] = mb_substr(trim((string)($data[$f] ?? '')), 0, $max) ?: null;
        }
        $set = 'academic_session_id = :sid, class_id = :c, subject_id = :s, lesson_date = :d, duration_minutes = :dur, lesson_span = :span,
            start_time = :st, week_number = :wk, learners = :ln, topic = :topic, subtopic = :sub, procedure_json = :proc, status = :status, curriculum_topic_id = :cu, '
            . implode(', ', array_map(fn($f) => "$f = :$f", array_keys(LP_TEXT_FIELDS)))
            // Each submission goes to the top of its department's queue and is new to reviewers again.
            . ($submit ? ', submitted_at = NOW(), staff_seen_at = NULL' : '');
        if ($id) {
            $db->prepare("UPDATE lesson_plans SET $set WHERE id = :id")->execute($values + [':id' => $id]);
        } else {
            $db->prepare("INSERT INTO lesson_plans SET teacher_id = :t, $set")->execute($values + [':t' => (int)$teacher['id']]);
            $id = (int)$db->lastInsertId();
        }
        hr_respond(true, $submit ? 'Lesson plan submitted for review' : 'Lesson plan saved as a draft', ['id' => $id], 200);
    }

    if ($method === 'DELETE') {
        $id = (int)($_GET['id'] ?? 0);
        $plan = lp_fetch($db, 'p.id = :id', [':id' => $id])[0] ?? throw new TtException('Lesson plan not found', 404);
        // Teachers may delete any of their own plans (the page asks first); staff may delete any plan.
        if ($teacher && (int)$plan['teacher_id'] !== (int)$teacher['id']) {
            throw new TtException('You can only delete your own lesson plans', 403);
        }
        $db->prepare('DELETE FROM lesson_plans WHERE id = :id')->execute([':id' => $id]);
        hr_respond(true, 'Lesson plan deleted');
    }

    hr_respond(false, 'Unknown request', null, 400);
} catch (TtException $e) {
    hr_respond(false, $e->getMessage(), $e->data, $e->status);
} catch (Throwable $e) {
    hr_respond(false, $e->getMessage(), null, 500);
}
