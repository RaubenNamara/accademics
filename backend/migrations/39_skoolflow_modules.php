<?php
/**
 * Migration 39: skoolFlow teacher portal, lesson plans, examinations (AOI etc.), marks and report settings.
 * Additive and idempotent — no existing row is changed:
 *  - users: teacher_id (link to a teacher), must_change_password, role value 'subject_teacher'
 *  - new tables: report_settings, exams, exam_marks, lesson_plans
 * The APIs also run this automatically on first use.
 * Usage: php backend/migrations/39_skoolflow_modules.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/SchoolService.php';

$db = (new Database())->getConnection();
sf_run_migration($db, static function (string $m): void {
    echo $m . PHP_EOL;
});
echo 'Migration 39 complete.' . PHP_EOL;
