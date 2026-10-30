<?php
/**
 * End-to-end test of the teacher portal, lesson plans, examinations/marks, report settings and
 * report cards over the real HTTP API (CLI):
 *   php backend/tests/skoolflow_modules_test.php [base-url]
 *
 * Works in the OPEN term (marks can only be entered there) with exams named "SFTEST …", a temporary
 * admin, and a real teacher/class/subject (read only; the teacher's temporary login is removed
 * afterwards). Only rows this test created are removed.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/SchoolService.php';

$base = rtrim($argv[1] ?? 'http://localhost/accademics/backend/api/', '/') . '/';
$db = (new Database())->getConnection();
tt_ensure_schema($db);
sf_ensure_schema($db);
$pass = 0;
$fail = 0;

function check(string $name, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    echo ($ok ? '  PASS ' : '  FAIL ') . $name . ($ok || $detail === '' ? '' : " — $detail") . PHP_EOL;
}

function call(string $method, string $path, ?array $body = null, ?string $token = null): array
{
    global $base;
    $ch = curl_init($base . $path);
    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = "Authorization: Bearer $token";
    }
    curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 60]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $raw = (string)curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$status, json_decode($raw, true) ?: ['raw' => $raw]];
}

// A class with students, a subject and a teacher (in that subject's department) without a login yet.
$class = $db->query("SELECT c.id, c.full_class_name FROM classes c JOIN students s ON TRIM(s.class) COLLATE utf8mb4_unicode_ci = TRIM(c.class_name) COLLATE utf8mb4_unicode_ci AND TRIM(s.stream) COLLATE utf8mb4_unicode_ci = TRIM(c.stream_name) COLLATE utf8mb4_unicode_ci
    WHERE c.class_name REGEXP '^S ?[1-4]' GROUP BY c.id AND c.id NOT IN (SELECT class_id FROM exam_marks) HAVING COUNT(*) >= 5 ORDER BY c.id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
// Two temporary teachers in one O-Level subject's department, so no real teacher's login is touched.
$db->exec("DELETE FROM teachers WHERE teacher_code IN ('SFTEST-T1', 'SFTEST-T2')");
$subjectId = (int)($db->query("SELECT id FROM subjects_new WHERE subject_code IN ('MATH', 'MTC', 'ENG') ORDER BY id LIMIT 1")->fetchColumn()
    ?: $db->query('SELECT id FROM subjects_new ORDER BY id LIMIT 1')->fetchColumn());
$newTeacher = function (string $code, string $name) use ($db, $subjectId): int {
    $db->prepare('INSERT INTO teachers (teacher_code, full_name, email, is_active) VALUES (?, ?, ?, 1)')
        ->execute([$code, $name, strtolower($code) . '@local.test']);
    $id = (int)$db->lastInsertId();
    $db->prepare('INSERT INTO teacher_subjects (teacher_id, subject_id) VALUES (?, ?)')->execute([$id, $subjectId]);
    return $id;
};
$pick = ['teacher_id' => $newTeacher('SFTEST-T1', 'SFTEST Teacher One'), 'subject_id' => $subjectId, 'teacher_code' => 'SFTEST-T1'];
$tid = (int)$pick['teacher_id'];
$subject = (int)$pick['subject_id'];
$other = $newTeacher('SFTEST-T2', 'SFTEST Teacher Two');
// The temporary teachers go last; their subject links cascade.
register_shutdown_function(fn() => $db->exec("DELETE FROM teachers WHERE teacher_code IN ('SFTEST-T1', 'SFTEST-T2')"));

$sid = (int)$db->query('SELECT id FROM academic_sessions WHERE is_active = 1 LIMIT 1')->fetchColumn();
if (!$sid) {
    exit("No open term — open one first.
");
}
$reqId = 0;
$cleanup = function () use ($db, $tid, $other, $sid, &$reqId): void {
    $db->exec("DELETE FROM exams WHERE academic_session_id = $sid AND name LIKE 'SFTEST %'"); // cascades marks
    $db->exec("DELETE FROM lesson_plans WHERE topic LIKE 'SFTEST %'");
    // Only the temporary teachers' assignments (made directly or through the Teaching Assignments API).
    $db->exec("DELETE FROM lesson_requirements WHERE teacher_id IN ($tid, $other)");
    $db->exec("DELETE FROM users WHERE email LIKE 'sf-test-%@local.test' OR teacher_id IN ($tid" . ($other ? ", $other" : '') . ')');
};
$cleanup();
$settingsBefore = $db->query('SELECT * FROM report_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC) ?: null;

$db->prepare("INSERT INTO users (full_name, email, password_hash, role, is_active) VALUES ('SF Test Admin', 'sf-test-admin@local.test', ?, 'super_admin', 1)")
    ->execute([password_hash('Admin2026!', PASSWORD_DEFAULT)]);
$admin = call('POST', 'auth.php?action=login', ['email' => 'sf-test-admin@local.test', 'password' => 'Admin2026!'])[1]['token'] ?? null;
$db->prepare('INSERT INTO lesson_requirements (academic_session_id, class_id, subject_id, teacher_id, periods_per_week) VALUES (?, ?, ?, ?, 4)')
    ->execute([$sid, $class['id'], $subject, $tid]);
$reqId = (int)$db->lastInsertId();

try {
    echo "Teacher logins\n";
    [$st, $res] = call('POST', 'teacher_accounts.php?action=create', ['teacher_ids' => [$tid]], $admin);
    $cred = $res['data']['created'][0] ?? null;
    check('admin creates a teacher login with a temporary password', $st === 200 && $cred && strlen($cred['password']) >= 8, json_encode($res));
    [$st, $login] = call('POST', 'auth.php?action=login', ['email' => $pick['teacher_code'], 'password' => $cred['password']]);
    check('teacher signs in with their teacher code', ($login['success'] ?? false) && ($login['user']['role'] ?? '') === SF_TEACHER_ROLE, json_encode($login));
    check('first sign-in requires a password change', ($login['user']['must_change_password'] ?? false) === true);
    $teacherToken = $login['token'] ?? null;
    [$st] = call('POST', 'auth.php?action=change-password', ['current_password' => $cred['password'], 'new_password' => 'short'], $teacherToken);
    check('a weak new password is refused', $st === 422, (string)$st);
    [$st] = call('POST', 'auth.php?action=change-password', ['current_password' => $cred['password'], 'new_password' => 'NewPass2026'], $teacherToken);
    [, $again] = call('POST', 'auth.php?action=login', ['email' => $pick['teacher_code'], 'password' => 'NewPass2026']);
    check('password changed; the flag is cleared', $st === 200 && ($again['user']['must_change_password'] ?? true) === false);
    [, $wrong] = call('POST', 'auth.php?action=login', ['email' => 'nobody-here@local.test', 'password' => 'x']);
    check('an unknown login gets the same message as a wrong password', ($wrong['message'] ?? '') === 'Email or password is incorrect');

    echo "Teacher portal\n";
    [$st, $me] = call('GET', "teacher_portal.php?view=me&academic_session_id=$sid", null, $teacherToken);
    check('portal shows the signed-in teacher and what they teach', $st === 200 && ($me['data']['teacher']['id'] ?? 0) === $tid
        && count($me['data']['assignments'] ?? []) === 1, json_encode($me['data']['assignments'] ?? $me));
    foreach (['timetable', 'performance', 'attendance'] as $v) {
        [$st] = call('GET', "teacher_portal.php?view=$v&academic_session_id=$sid", null, $teacherToken);
        check("portal view “{$v}” loads", $st === 200, (string)$st);
    }
    [$st] = call('GET', 'teacher_portal.php?view=me', null, $admin);
    check('an admin account has no teacher portal', $st === 403, (string)$st);

    echo "Examinations\n";
    [$st] = call('POST', 'exams.php', ['academic_session_id' => $sid, 'name' => 'SFTEST Broken', 'exam_type' => 'AOI', 'max_marks' => 0], $admin);
    check('an exam with an invalid “out of” is refused', $st === 422, (string)$st);
    $ids = [];
    foreach ([['AOI 1', 'AOI', 3], ['AOI 2', 'AOI', 3], ['Mid Term', 'MOT', 100], ['End of Term', 'EOT', 100]] as [$n, $t, $m]) {
        [$st, $r] = call('POST', 'exams.php', ['academic_session_id' => $sid, 'name' => "SFTEST $n", 'exam_type' => $t, 'max_marks' => $m], $admin);
        $ids[$n] = $r['data']['id'] ?? 0;
    }
    check('admin sets up AOI 1, AOI 2, Mid Term and End of Term', count(array_filter($ids)) === 4);
    $shares = $db->query('SELECT exam_type, weight FROM exams WHERE id IN (' . implode(',', array_map('intval', $ids)) . ')')->fetchAll(PDO::FETCH_KEY_PAIR);
    check('each AOI counts out of 10 and exams out of 80, set from the type', (float)($shares['AOI'] ?? 0) === 10.0 && (float)($shares['EOT'] ?? 0) === 80.0 && (float)($shares['MOT'] ?? 0) === 80.0, json_encode($shares));
    [$st] = call('POST', 'exams.php', ['academic_session_id' => $sid, 'name' => 'SFTEST Hack', 'exam_type' => 'EOT', 'max_marks' => 100, 'weight' => 50], $teacherToken);
    check('a teacher cannot set up exams', $st === 403, (string)$st);
    [, $list] = call('GET', "exams.php?academic_session_id=$sid", null, $teacherToken);
    check('teachers do not see exams still in draft', !array_filter($list['data']['exams'] ?? [['name' => 'SFTEST']], fn($e) => str_starts_with($e['name'], 'SFTEST')));
    $sheetQ = fn($exam, $cls) => "exams.php?action=sheet&exam_id={$ids[$exam]}&class_id=$cls&subject_id=$subject";
    [, $sheet] = call('GET', $sheetQ('End of Term', $class['id']), null, $teacherToken);
    $students = $sheet['data']['students'] ?? [];
    $marksFor = fn(array $list) => array_map(fn($s, $i) => ['student_id' => $s['student_id'], 'marks' => $list[$i] ?? null], array_slice($students, 0, count($list)), array_keys(array_slice($students, 0, count($list))));
    [$st] = call('POST', 'exams.php?action=marks', ['exam_id' => $ids['End of Term'], 'class_id' => $class['id'], 'subject_id' => $subject, 'rows' => $marksFor([50])], $teacherToken);
    check('marks cannot be entered while an exam is a draft', $st === 423, (string)$st);
    foreach ($ids as $id) {
        call('POST', 'exams.php?action=status', ['id' => $id, 'status' => 'open'], $admin);
    }
    check('the class mark sheet lists the stream’s students', count($students) >= 5, (string)count($students));
    // Teachers mark only the classes assigned to them. Another O-Level class nobody teaches this subject to yet:
    $otherClass = (int)$db->query("SELECT c.id FROM classes c WHERE c.id <> {$class['id']} AND c.class_name REGEXP '^S ?[1-4]'
        AND NOT EXISTS (SELECT 1 FROM lesson_requirements lr WHERE lr.academic_session_id = $sid AND lr.class_id = c.id AND lr.subject_id = $subject)
        ORDER BY c.id LIMIT 1")->fetchColumn();
    [$st] = call('GET', $sheetQ('End of Term', $otherClass), null, $teacherToken);
    check('a teacher cannot open a class that is not assigned to them', $st === 403, (string)$st);
    $assign = fn($teacher, $token) => call('POST', 'teaching_assignments.php?action=assign', ['academic_session_id' => $sid, 'teacher_id' => $teacher, 'subject_id' => $subject, 'class_ids' => [$otherClass]], $token);
    [$st] = $assign($tid, $teacherToken);
    check('a teacher cannot assign classes', $st === 403, (string)$st);
    [$st] = $assign($other, $admin);
    [$st2, $r] = $assign($tid, $admin);
    $holders = $db->query("SELECT teacher_id FROM lesson_requirements WHERE academic_session_id = $sid AND class_id = $otherClass AND subject_id = $subject")->fetchAll(PDO::FETCH_COLUMN);
    check('assigning a class someone else has moves it: one teacher per class and subject', $st === 200 && $st2 === 200 && array_map('intval', $holders) === [$tid]
        && str_contains($r['message'] ?? '', 'moved'), json_encode([$holders, $r['message'] ?? $r]));
    $dept = array_values(array_filter($r['data']['departments'] ?? [], fn($d) => $d['subject_id'] === $subject))[0] ?? [];
    check('the overview groups teachers by department and counts covered classes', in_array($tid, array_column($dept['teachers'] ?? [], 'id'), true)
        && ($dept['covered'] ?? 0) >= 2 && ($r['data']['stats']['assigned_teachers'] ?? 0) >= 1, json_encode($r['data']['stats'] ?? null));
    [$st] = call('GET', $sheetQ('End of Term', $otherClass), null, $teacherToken);
    check('once assigned, the teacher can open that class', $st === 200, (string)$st);
    $newId = (int)$db->query("SELECT id FROM lesson_requirements WHERE academic_session_id = $sid AND class_id = $otherClass AND subject_id = $subject")->fetchColumn();
    [$st] = call('POST', 'teaching_assignments.php?action=unassign', ['id' => $newId], $admin);
    [$st2] = call('GET', $sheetQ('End of Term', $otherClass), null, $teacherToken);
    check('removing the assignment closes the class again', $st === 200 && $st2 === 403, "$st, $st2");
    $foreign = (int)$db->query("SELECT id FROM subjects_new WHERE id <> $subject ORDER BY id LIMIT 1")->fetchColumn();
    [$st] = call('GET', "exams.php?action=sheet&exam_id={$ids['End of Term']}&class_id={$class['id']}&subject_id=$foreign", null, $teacherToken);
    check('a teacher cannot open a sheet for another department’s subject', $st === 403, (string)$st);
    [$st, $r] = call('POST', 'exams.php?action=marks', ['exam_id' => $ids['AOI 1'], 'class_id' => $class['id'], 'subject_id' => $subject, 'rows' => $marksFor([2, 5])], $teacherToken);
    $stored = (int)$db->query("SELECT COUNT(*) FROM exam_marks WHERE exam_id = {$ids['AOI 1']}")->fetchColumn();
    check('a mark above “out of” is refused and nothing is saved', $st === 422 && $stored === 0, "$st, $stored stored");
    call('POST', 'exams.php?action=marks', ['exam_id' => $ids['AOI 1'], 'class_id' => $class['id'], 'subject_id' => $subject, 'rows' => $marksFor([3, 2, 1])], $teacherToken);
    call('POST', 'exams.php?action=marks', ['exam_id' => $ids['AOI 2'], 'class_id' => $class['id'], 'subject_id' => $subject, 'rows' => $marksFor([3, 1, 1])], $teacherToken);
    // Only the first learner sat the Mid Term; the fourth sat only End of Term (no AOIs).
    call('POST', 'exams.php?action=marks', ['exam_id' => $ids['Mid Term'], 'class_id' => $class['id'], 'subject_id' => $subject, 'rows' => $marksFor([70])], $teacherToken);
    [$st, $r] = call('POST', 'exams.php?action=marks', ['exam_id' => $ids['End of Term'], 'class_id' => $class['id'], 'subject_id' => $subject, 'rows' => $marksFor([90, 60, 30, 72])], $teacherToken);
    check('the teacher saves marks for their class', $st === 200 && ($r['data']['saved'] ?? 0) === 4, json_encode($r));
    [, $prog] = call('GET', "exams.php?action=progress&exam_id={$ids['End of Term']}", null, $admin);
    $mine = array_values(array_filter($prog['data']['sheets'] ?? [], fn($r) => (int)$r['class_id'] === (int)$class['id'] && (int)$r['subject_id'] === $subject));
    check('admin sees marks-entry progress', ($mine[0]['entered'] ?? 0) === 4, json_encode($mine));
    call('POST', 'exams.php?action=status', ['id' => $ids['End of Term'], 'status' => 'closed'], $admin);
    [$st] = call('POST', 'exams.php?action=marks', ['exam_id' => $ids['End of Term'], 'class_id' => $class['id'], 'subject_id' => $subject, 'rows' => $marksFor([10])], $teacherToken);
    check('marks are locked once an exam is closed', $st === 423, (string)$st);

    echo "Report settings\n";
    $png = 'data:image/png;base64,' . base64_encode(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));
    [$st] = call('POST', 'school_settings.php', ['school_name' => 'Test School', 'logo' => 'data:image/gif;base64,R0lGODlh'], $admin);
    check('a non PNG/JPG logo is refused', $st === 422, (string)$st);
    [$st, $set] = call('POST', 'school_settings.php', ['school_name' => "St Mark's College Namagoma", 'address' => 'P.O. Box 1, Mukono', 'logo' => $png,
        'grade_scale' => [['min' => 80, 'grade' => 'A', 'comment' => 'Exceptional'], ['min' => 60, 'grade' => 'B', 'comment' => 'Good'], ['min' => 0, 'grade' => 'C', 'comment' => 'Fair']]], $admin);
    check('admin saves the school logo, address and grade scale', $st === 200 && str_starts_with($set['data']['logo'] ?? '', 'data:image/png') && count($set['data']['grade_scale']) === 3);
    [$st] = call('POST', 'school_settings.php', ['school_name' => 'X'], $teacherToken);
    check('a teacher cannot change report settings', $st === 403, (string)$st);

    echo "Report cards\n";
    [$st, $rc] = call('GET', "report_cards.php?academic_session_id=$sid&class_id={$class['id']}", null, $admin);
    $byStudent = [];
    foreach ($rc['data']['students'] ?? [] as $c) {
        $byStudent[$c['student']['id']] = $c;
    }
    $s0 = $byStudent[$students[0]['student_id']] ?? null;
    $s1 = $byStudent[$students[1]['student_id']] ?? null;
    $s2 = $byStudent[$students[2]['student_id']] ?? null;
    $s3 = $byStudent[$students[3]['student_id']] ?? null;
    $row = fn($c) => $c['subjects'][0] ?? [];
    // Learner 1: AOIs 3/3, 3/3 -> 10, 10 (best two make 20); Mid Term 70 -> 56, End of Term 90 -> 72 (avg 64); final 20 + 64 = 84.
    check('each AOI is converted to 10 and each exam to 80', ($row($s0)['scores'][$ids['AOI 1']] ?? null) == 10 && ($row($s0)['scores'][$ids['Mid Term']] ?? null) == 56 && ($row($s0)['scores'][$ids['End of Term']] ?? null) == 72, json_encode($row($s0)));
    check('final = best two AOIs (/20) + exam average (/80)', ($row($s0)['aoi'] ?? null) == 20 && ($row($s0)['exam'] ?? null) == 64 && ($row($s0)['final'] ?? null) == 84, json_encode($row($s0)));
    // Learner 2: AOIs 2/3, 1/3 -> 6.7, 3.3 (20 x 15/30 = 10); End of Term 60 -> 48; final 58.
    check('a learner who missed the Mid Term is averaged on the exams they sat', ($row($s1)['final'] ?? null) == 58, json_encode($row($s1)));
    // Learner 4: End of Term 72 only -> 57.6 of 80 -> scaled to 72 out of 100.
    check('a learner with only one exam is scaled to 100', array_key_exists('aoi', $row($s3)) && $row($s3)['aoi'] === null && ($row($s3)['final'] ?? null) == 72, json_encode($row($s3)));
    check('grade and comment come from the school’s grade scale', ($s0['subjects'][0]['grade'] ?? '') === 'A' && ($s2['subjects'][0]['grade'] ?? '') === 'C');
    // The scheme itself: best two of three AOIs, and the admin's choice of where the 80 comes from.
    $three = sf_final_mark([10, 4, 8], [72]);
    check('three AOIs: the best two make the 20 (10 + 8 = 18)', $three['aoi'] == 18 && $three['final'] == 90, json_encode($three));
    $one = sf_final_mark([7], [40]);
    check('a single AOI so far counts double (7 -> 14)', $one['aoi'] == 14 && $one['final'] == 54, json_encode($one));
    check('basis both: MOT and EOT count, BOT does not', sf_counts_to_final('MOT', 'both') && sf_counts_to_final('EOT', 'both') && !sf_counts_to_final('BOT', 'both'));
    check('basis EOT: only the end of term counts', sf_counts_to_final('EOT', 'EOT') && !sf_counts_to_final('MOT', 'EOT') && sf_counts_to_final('AOI', 'EOT'));
    $oldBasis = sf_exam_basis($db);
    [$st] = call('POST', 'exams.php?action=scheme', ['exam_basis' => 'EOT'], $teacherToken);
    check('a teacher cannot change where the 80 comes from', $st === 403, "status $st");
    [$st, $r] = call('POST', 'exams.php?action=scheme', ['exam_basis' => 'EOT'], $admin);
    [, $rcE] = call('GET', "report_cards.php?" . http_build_query(['academic_session_id' => $sid, 'class_id' => $class['id']]), null, $admin);
    $s0e = array_values(array_filter($rcE['data']['students'] ?? [], fn($c) => $c['student']['id'] === $students[0]['student_id']))[0] ?? [];
    $rowE = $s0e['subjects'][0] ?? [];
    check('admin sets the 80 to come from the end of term only (20 + 72 = 92)', $st === 200 && ($rowE['exam'] ?? null) == 72 && ($rowE['final'] ?? null) == 92, json_encode([$st, $rowE]));
    sf_save_exam_basis($db, $oldBasis);

    // Primary school: no AOIs, the mid term and end of term make the whole 100, grades D1 to F9.
    $oldType = (string)$db->query('SELECT school_type FROM report_settings WHERE id = 1')->fetchColumn() ?: 'secondary';
    sf_save_exam_basis($db, 'both');
    $db->exec("UPDATE report_settings SET school_type = 'primary' WHERE id = 1");
    [$st] = call('POST', 'exams.php', ['academic_session_id' => $sid, 'name' => 'SFTEST Primary AOI', 'exam_type' => 'AOI', 'max_marks' => 100], $admin);
    check('a primary school cannot set up an AOI', $st === 422, "status $st");
    [, $rcP] = call('GET', "report_cards.php?" . http_build_query(['academic_session_id' => $sid, 'class_id' => $class['id']]), null, $admin);
    $s0p = array_values(array_filter($rcP['data']['students'] ?? [], fn($c) => $c['student']['id'] === $students[0]['student_id']))[0] ?? [];
    $rowP = $s0p['subjects'][0] ?? [];
    // Learner 1: Mid Term 70, End of Term 90 (each out of 100) -> 80; the AOIs are ignored.
    check('primary: AOIs ignored, MOT + EOT averaged out of 100 (80)', array_key_exists('aoi', $rowP) && $rowP['aoi'] === null && ($rowP['final'] ?? null) == 80 && ($rowP['scores'][$ids['Mid Term']] ?? null) == 70, json_encode($rowP));
    check('primary: the scheme says exams make 100', ($rcP['data']['scheme']['exam_out_of'] ?? 0) === 100 && ($rcP['data']['scheme']['school_type'] ?? '') === 'primary');
    $db->prepare('UPDATE report_settings SET school_type = :t WHERE id = 1')->execute([':t' => $oldType]);
    sf_save_exam_basis($db, $oldBasis);

    check('positions rank the class by average', ($s0['position'] ?? 0) === 1 && ($s3['position'] ?? 0) === 2 && ($s0['out_of'] ?? 0) === 4, json_encode([$s0['position'] ?? null, $s3['position'] ?? null, $s0['out_of'] ?? null]));
    [$st, $one] = call('GET', "report_cards.php?academic_session_id=$sid&class_id={$class['id']}&student_id={$students[1]['student_id']}", null, $admin);
    check('a single student’s report card can be generated', $st === 200 && count($one['data']['students'] ?? []) === 1);
    check('report data carries the school settings and logo', str_starts_with($rc['data']['settings']['logo'] ?? '', 'data:image/png'));
    [$st] = call('GET', "report_cards.php?academic_session_id=$sid&class_id={$class['id']}", null, $teacherToken);
    check('teachers cannot generate report cards', $st === 403, (string)$st);

    echo "Lesson plans\n";
    $termStart = (string)$db->query("SELECT start_date FROM academic_sessions WHERE id = $sid")->fetchColumn();
    $lessonDate = date('Y-m-d', strtotime($termStart . ' +15 days')); // third week of the term
    $plan = ['academic_session_id' => $sid, 'class_id' => $class['id'], 'subject_id' => $subject, 'topic' => 'SFTEST topic', 'lesson_date' => $lessonDate,
        'lesson_span' => 2, 'start_time' => '08:00', 'theme' => 'Theme', 'competences' => 'The learner …'];
    $steps = [['minutes' => 10, 'teacher' => 'Introduction: greets', 'learners' => 'Respond'], ['minutes' => 60, 'teacher' => 'Development: explains', 'learners' => 'Discuss'], ['minutes' => 10, 'teacher' => 'Conclusion: sums up', 'learners' => 'Note']];
    [$st, $r] = call('POST', 'lesson_plans.php', $plan + ['submit' => true, 'procedure' => $steps], $teacherToken);
    check('submitting without key learning outcomes is refused', $st === 422 && isset($r['data']['errors']['objectives']), json_encode($r));
    [$st, $r] = call('POST', 'lesson_plans.php', array_merge($plan, ['lesson_span' => 3, 'objectives' => 'x', 'procedure' => $steps]), $teacherToken);
    check('an O-Level lesson cannot be a triple', $st === 422 && isset($r['data']['errors']['lesson_span']), json_encode($r));
    $short = $steps;
    $short[1]['minutes'] = 40;
    [$st, $r] = call('POST', 'lesson_plans.php', $plan + ['objectives' => 'x', 'procedure' => $short, 'submit' => true], $teacherToken);
    check('the procedure must fill the lesson (80 min for a double)', $st === 422 && str_contains($r['data']['errors']['procedure'] ?? '', '60 minutes'), json_encode($r));
    [$st, $r] = call('POST', 'lesson_plans.php', $plan + ['objectives' => 'By the end learners should…', 'procedure' => $steps, 'generic_skills' => "Critical thinking: x\nCommunication: y", 'submit' => true], $teacherToken);
    $planId = $r['data']['id'] ?? 0;
    check('the teacher submits a complete lesson plan', $st === 200 && $planId > 0, json_encode($r));
    [, $mine] = call('GET', 'lesson_plans.php', null, $teacherToken);
    $saved = $mine['data'][0] ?? [];
    check('week, duration and number of learners are filled in', (int)$saved['week_number'] === 3 && (int)$saved['duration_minutes'] === 80 && (int)$saved['learners'] === count($students), json_encode([$saved['week_number'] ?? null, $saved['duration_minutes'] ?? null, $saved['learners'] ?? null]));
    check('the procedure comes back as phases', count($saved['procedure'] ?? []) === 3 && $saved['procedure'][1]['minutes'] === 60);
    [$st] = call('POST', 'lesson_plans.php', ['academic_session_id' => $sid, 'class_id' => $class['id'], 'subject_id' => $subject, 'topic' => 'SFTEST x', 'id' => $planId, 'objectives' => 'a'], $teacherToken);
    check('a submitted plan cannot be edited until it is returned', $st === 409, (string)$st);
    [$st] = call('POST', 'lesson_plans.php?action=review', ['id' => $planId, 'decision' => 'return'], $admin);
    check('returning a plan needs a comment', $st === 422, (string)$st);
    call('POST', 'lesson_plans.php?action=review', ['id' => $planId, 'decision' => 'return', 'comment' => 'Add an assessment'], $admin);
    [$st] = call('POST', 'lesson_plans.php', $plan + ['id' => $planId, 'objectives' => 'Obj', 'procedure' => $steps, 'reflection' => 'Went well', 'submit' => true], $teacherToken);
    [, $mine] = call('GET', 'lesson_plans.php', null, $teacherToken);
    check('the returned plan is fixed and resubmitted', $st === 200 && ($mine['data'][0]['status'] ?? '') === 'submitted');
    [$st] = call('POST', 'lesson_plans.php?action=review', ['id' => $planId, 'decision' => 'approve'], $admin);
    [, $mine] = call('GET', 'lesson_plans.php', null, $teacherToken);
    check('admin approves it', $st === 200 && ($mine['data'][0]['status'] ?? '') === 'approved');
    if ($other) {
        $cred2 = call('POST', 'teacher_accounts.php?action=create', ['teacher_ids' => [(int)$other]], $admin)[1]['data']['created'][0] ?? null;
        $t2 = $cred2 ? (call('POST', 'auth.php?action=login', ['email' => $cred2['login'], 'password' => $cred2['password']])[1]['token'] ?? null) : null;
        [, $theirs] = call('GET', 'lesson_plans.php', null, $t2);
        check('another teacher cannot see this teacher’s plans', $t2 && count($theirs['data'] ?? [1]) === 0);
        [$st] = call('POST', 'lesson_plans.php', $plan + ['objectives' => 'a', 'procedure' => $steps, 'id' => $planId], $t2);
        check('another teacher cannot edit it', $st === 404, (string)$st);
    }
} finally {
    $cleanup();
    if ($settingsBefore) {
        $cols = array_keys($settingsBefore);
        $db->prepare('REPLACE INTO report_settings (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')')->execute(array_values($settingsBefore));
    } else {
        $db->exec('DELETE FROM report_settings');
    }
    echo "\nCleanup: test term, exams, marks, plans and logins removed; report settings restored\n";
}

echo "\n$pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
