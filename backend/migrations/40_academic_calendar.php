<?php
/**
 * Migration 40: academic calendar and student progression.
 *   students.status (active / completed_o_level / completed_a_level / left), academic_sessions.closed_at/closed_by,
 *   report_settings.compulsory_subjects, student_subjects, student_promotions.
 * Safe to run more than once.  php backend/migrations/40_academic_calendar.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/SchoolService.php';

$db = (new Database())->getConnection();
tt_ensure_schema($db);
sf_run_migration($db, fn(string $m) => print("  $m\n"));
ac_run_migration($db, fn(string $m) => print("  $m\n"));
echo "Migration 40 complete.\n";
