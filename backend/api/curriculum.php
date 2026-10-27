<?php
/**
 * Curriculum setup.
 *
 * GET  [?subject_id&class_level&term&q]        topics with outcomes (any signed-in staff or teacher)
 * GET  ?action=summary                          topic counts per subject, class level and term
 * GET  ?action=for-class&class_id&subject_id[&term]   topics for a teacher's class (its level), for lesson plans
 * POST {id?, subject_id, class_level, term, theme, topic, competence, week_from, week_to, outcomes[]}  (academic staff)
 * POST ?action=import                           load the eSpace seed again (adds only what is missing)
 * DELETE ?id=…
 */
declare(strict_types=1);

require_once '../config/Database.php';
require_once '../config/HrHelpers.php';
require_once '../config/CurriculumService.php';

hr_cors_headers();
$db = (new Database())->getConnection();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    tt_ensure_schema($db);
    cu_ensure_schema($db);
    $action = (string)($_GET['action'] ?? '');

    if ($method === 'GET') {
        hr_require_auth(array_merge(SF_ACADEMIC_ROLES, [SF_TEACHER_ROLE]));
        if ($action === 'summary') {
            $rows = $db->query('SELECT t.subject_id, s.subject_name, t.class_level, t.term, COUNT(*) AS topics
                FROM curriculum_topics t JOIN subjects_new s ON s.id = t.subject_id GROUP BY t.subject_id, t.class_level, t.term ORDER BY s.subject_name, t.class_level, t.term')->fetchAll(PDO::FETCH_ASSOC);
            hr_respond(true, 'Curriculum summary', [
                'rows' => $rows,
                'totals' => [
                    'topics' => (int)$db->query('SELECT COUNT(*) FROM curriculum_topics')->fetchColumn(),
                    'outcomes' => (int)$db->query('SELECT COUNT(*) FROM curriculum_outcomes')->fetchColumn(),
                ],
                'subjects' => $db->query('SELECT id, subject_name, subject_code FROM subjects_new ORDER BY subject_name')->fetchAll(PDO::FETCH_ASSOC),
            ]);
        }
        if ($action === 'for-class') {
            $class = sf_class($db, (int)($_GET['class_id'] ?? 0)) ?? throw new TtException('Class not found', 404);
            $level = ac_class_number((string)$class['class_name']) ?? 0;
            hr_respond(true, 'Curriculum for the class', [
                'class_level' => $level,
                'topics' => cu_topics($db, ['subject_id' => (int)($_GET['subject_id'] ?? 0), 'class_level' => $level, 'term' => $_GET['term'] ?? null]),
            ]);
        }
        hr_respond(true, 'Curriculum topics', cu_topics($db, $_GET));
    }

    hr_require_auth(SF_ACADEMIC_ROLES);

    if ($method === 'POST' && $action === 'import') {
        $res = cu_import_seed($db);
        hr_respond(true, "Added {$res['topics_added']} topic(s) from the eSpace curriculum", $res);
    }
    if ($method === 'POST') {
        $topic = cu_save_topic($db, hr_request_data());
        hr_respond(true, 'Topic saved', $topic);
    }
    if ($method === 'DELETE') {
        $stmt = $db->prepare('DELETE FROM curriculum_topics WHERE id = :id');
        $stmt->execute([':id' => (int)($_GET['id'] ?? 0)]);
        if (!$stmt->rowCount()) {
            throw new TtException('Topic not found', 404);
        }
        $db->prepare('UPDATE lesson_plans SET curriculum_topic_id = NULL WHERE curriculum_topic_id = :id')->execute([':id' => (int)$_GET['id']]);
        hr_respond(true, 'Topic deleted');
    }
    hr_respond(false, 'Unknown request', null, 400);
} catch (TtException $e) {
    hr_respond(false, $e->getMessage(), $e->data, $e->status);
} catch (Throwable $e) {
    hr_respond(false, $e->getMessage(), null, 500);
}
