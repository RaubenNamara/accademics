<?php
/**
 * Academic calendar: which term is open, closing a term (and a year), compulsory subjects.
 *
 * GET                                   years with their terms (open / closed / upcoming) and the next term
 * GET ?action=preview                   what closing the open term will do (exams, promotion counts)
 * POST ?action=close  {start_date, end_date, copy_requirements, confirm}   close the open term, open the next
 * POST ?action=open   {session_id}      open a term when none is open (first setup)
 * POST ?action=reopen {session_id}      open a closed term again for data entry; the current term stays open
 * POST ?action=close_reopened {session_id}   close a reopened term again
 * GET  ?action=compulsory               subjects offered and compulsory per level
 * POST ?action=compulsory {offered: {"O-Level": [ids], "A-Level": [ids]}, compulsory: {…}}
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
    $action = (string)($_GET['action'] ?? '');

    if ($method === 'GET') {
        hr_require_auth(SF_ACADEMIC_ROLES);
        if ($action === 'preview') {
            hr_respond(true, 'Closing preview', ac_close_preview($db));
        }
        if ($action === 'compulsory') {
            hr_respond(true, 'Compulsory subjects', [
                'offered' => ac_offered($db),
                'compulsory' => ac_compulsory($db),
                'subjects' => ac_all_subjects($db),
            ]);
        }
        hr_respond(true, 'Academic calendar', ac_calendar($db));
    }

    if ($method !== 'POST') {
        hr_respond(false, 'Method not allowed', null, 405);
    }
    $data = hr_request_data();

    if ($action === 'compulsory') {
        hr_require_auth(SF_ACADEMIC_ROLES);
        hr_respond(true, 'Subjects by level saved', ac_save_level_subjects($db, $data));
    }

    // Opening and closing terms is for administrators.
    $payload = hr_require_auth(['admin']);
    $by = (string)($payload['full_name'] ?? $payload['email'] ?? 'Administrator');

    if ($action === 'close') {
        $res = ac_close_term($db, $data, $by);
        $msg = "Term {$res['closed']['term']} {$res['closed']['academic_year']} closed. Term {$res['opened']['term']} {$res['opened']['academic_year']} is now open.";
        if ($res['promotion']) {
            $msg .= " {$res['promotion']['promoted']} students moved up a class.";
        }
        hr_respond(true, $msg, $res);
    }

    if ($action === 'reopen') {
        $res = ac_reopen_term($db, (int)($data['session_id'] ?? 0), $by);
        hr_respond(true, "Term {$res['term']} {$res['academic_year']} is reopened for data entry. The current term stays open.", $res);
    }

    if ($action === 'close_reopened') {
        $res = ac_close_reopened($db, (int)($data['session_id'] ?? 0), $by);
        hr_respond(true, "Term {$res['term']} {$res['academic_year']} is closed again.", $res);
    }

    if ($action === 'open') {
        if (ac_open_session($db)) {
            throw new TtException('A term is already open. Close it to move to the next term.', 409);
        }
        $stmt = $db->prepare('UPDATE academic_sessions SET is_active = 1, is_archived = 0 WHERE id = :id');
        $stmt->execute([':id' => (int)($data['session_id'] ?? 0)]);
        if (!$stmt->rowCount()) {
            throw new TtException('Term not found', 404);
        }
        hr_respond(true, 'Term opened');
    }

    hr_respond(false, 'Unknown action', null, 400);
} catch (TtException $e) {
    hr_respond(false, $e->getMessage(), $e->data, $e->status);
} catch (Throwable $e) {
    hr_respond(false, $e->getMessage(), null, 500);
}
