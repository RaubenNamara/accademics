<?php
/**
 * Migration 41: curriculum setup (curriculum_topics, curriculum_outcomes, lesson_plans.curriculum_topic_id)
 * and the first content, copied from eSpace's eNotes curriculum (backend/data/curriculum_seed.json).
 * Safe to run more than once: topics already present are left alone.
 *   php backend/migrations/41_curriculum.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/CurriculumService.php';

$db = (new Database())->getConnection();
tt_ensure_schema($db);
sf_ensure_schema($db);
cu_run_migration($db, fn(string $m) => print("  $m\n"));
$res = cu_import_seed($db);
echo "  {$res['topics_added']} topics and {$res['outcomes_added']} learning outcomes added"
    . ($res['unknown_subjects'] ? '; unknown subject codes skipped: ' . implode(', ', $res['unknown_subjects']) : '') . "\n";
echo '  ' . cu_normalise_competences($db) . " competence(s) rewritten to start with \"The learner\"\n";
echo "Migration 41 complete.\n";
