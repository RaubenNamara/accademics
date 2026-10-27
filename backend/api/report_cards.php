<?php
/**
 * Report cards for a class stream (bulk) or one student, for a term.
 * GET ?academic_session_id=…&class_id=…[&student_id=…]
 * The PDF is drawn in the browser from this data and the report settings.
 */
declare(strict_types=1);

require_once '../config/Database.php';
require_once '../config/HrHelpers.php';
require_once '../config/SchoolService.php';

hr_cors_headers();
$db = (new Database())->getConnection();

try {
    tt_ensure_schema($db);
    sf_ensure_schema($db);
    hr_require_auth(SF_ACADEMIC_ROLES);
    [$sid] = tt_require_session($db, $_GET);
    $classId = (int)($_GET['class_id'] ?? 0);
    if ($classId <= 0) {
        throw new TtException('Choose a class', 422);
    }
    $studentId = (int)($_GET['student_id'] ?? 0) ?: null;
    hr_respond(true, 'Report cards', sf_report_cards($db, $sid, $classId, $studentId));
} catch (TtException $e) {
    hr_respond(false, $e->getMessage(), $e->data, $e->status);
} catch (Throwable $e) {
    hr_respond(false, $e->getMessage(), null, 500);
}
