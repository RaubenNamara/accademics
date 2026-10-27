<?php
/**
 * Migration 37: timetable integrity, publishing and audit trail.
 *
 * Additive and idempotent — safe to run repeatedly, keeps every existing row:
 *  - timetable_slots: one row per period per teacher/class/room; its UNIQUE key
 *    makes double-booking impossible, including under concurrent requests.
 *    Existing lessons are backfilled; rows that already clash are left intact
 *    and reported by validation.
 *  - timetable_session_state: draft / validated / published status per term.
 *  - timetable_audit_log: who changed what, with before/after data.
 *  - timetable: client_request_id (double-submit guard), lesson_requirement_id,
 *    notes, created_by, updated_by, plus a slot lookup index.
 *  - timetable_versions: snapshot, entry_count, effective_date, created_by_*.
 *
 * The timetable API also runs this automatically on first use.
 * Usage: php backend/migrations/37_timetable_integrity.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/TimetableService.php';

$db = (new Database())->getConnection();
tt_run_migration($db, static function (string $message): void {
    echo $message . PHP_EOL;
});
echo 'Migration 37 complete.' . PHP_EOL;
