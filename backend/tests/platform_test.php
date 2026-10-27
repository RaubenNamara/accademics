<?php
/**
 * Multi-school (SaaS) checks: school list setup, creating a school, sign-in per school,
 * isolation between schools, suspension, and who may manage schools.
 *
 *   php backend/tests/platform_test.php
 *
 * Uses a throwaway school "pftest" and a temporary home admin; removes both afterwards and
 * removes the school list again if it did not exist before the run.
 */
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/PlatformService.php';

const BASE = 'http://localhost/accademics/backend/api/';
const TEST_CODE = 'pftest';
const HOME_ADMIN = 'pf-test-admin@local.test';

$db = (new Database())->getConnection();
$registryName = pf_registry_name();
$testDb = Tenant::settings()['db_prefix'] . TEST_CODE;
$hadRegistry = (bool)$db->query('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ' . $db->quote($registryName))->fetchColumn();

$passed = 0;
$failed = 0;
function check(string $label, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    $ok ? $passed++ : $failed++;
    echo ($ok ? '  PASS ' : '  FAIL ') . $label . ($ok || $detail === '' ? '' : " — $detail") . "\n";
}
function call(string $method, string $path, ?array $body = null, ?string $token = null): array
{
    $ch = curl_init(BASE . $path);
    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = "Authorization: Bearer $token";
    }
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 120]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $raw = (string)curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$status, json_decode($raw, true) ?: ['raw' => substr($raw, 0, 200)]];
}
$login = fn(string $email, string $password, ?string $school = null) =>
    call('POST', 'auth.php?action=login' . ($school ? '&school=' . $school : ''), ['email' => $email, 'password' => $password]);

$cleanup = function () use ($db, $testDb, $registryName, $hadRegistry): void {
    $db->exec("DELETE FROM users WHERE email = '" . HOME_ADMIN . "'");
    $db->exec('DROP DATABASE IF EXISTS `' . $testDb . '`');
    $exists = (bool)$db->query("SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '$registryName'")->fetchColumn();
    if ($exists) {
        if ($hadRegistry) {
            $db->exec("DELETE FROM `$registryName`.schools WHERE code = '" . TEST_CODE . "'");
        } else {
            $db->exec("DROP DATABASE `$registryName`");
        }
    }
};
$cleanup();
register_shutdown_function($cleanup);

$db->prepare("INSERT INTO users (full_name, email, password_hash, role, is_active) VALUES ('PF Test Admin', ?, ?, 'super_admin', 1)")
    ->execute([HOME_ADMIN, password_hash('HomeAdmin2026', PASSWORD_DEFAULT)]);
$homeStudents = (int)$db->query("SELECT COUNT(*) FROM students")->fetchColumn();

echo "Before the school list exists\n";
[, $ctx] = call('GET', 'platform.php?action=context');
check('the app runs as one school', ($ctx['data']['multi_school'] ?? null) === false || $hadRegistry, json_encode($ctx['data'] ?? $ctx));
[$st, $r] = $login(HOME_ADMIN, 'HomeAdmin2026');
$home = $r['token'] ?? null;
check('home admin signs in as before', $st === 200 && $home, json_encode($r));

echo "Setting up the school list\n";
[$st, $r] = call('POST', 'platform.php?action=setup', ['home_name' => "St Mark's College"], $home);
$homeRow = array_values(array_filter($r['data']['schools'] ?? [], fn($s) => $s['is_home']))[0] ?? null;
check('setup registers this school as the home school', $st === 200 && $homeRow, json_encode($r));
check('home school counts come from its own database', ($homeRow['counts']['students'] ?? -1) > 0, json_encode($homeRow['counts'] ?? null));

echo "Creating a school\n";
[$st, $r] = call('POST', 'platform.php?action=create', ['name' => 'PF', 'code' => 'Bad Code!', 'admin_email' => 'x'], $home);
check('bad details are refused with field messages', $st === 422 && isset($r['data']['errors']['name'], $r['data']['errors']['code'], $r['data']['errors']['admin_email']), json_encode($r));
[$st, $r] = call('POST', 'platform.php?action=create', [
    'name' => 'PF Test Secondary School', 'code' => TEST_CODE, 'admin_name' => 'PF Head', 'admin_email' => 'head@pftest.local',
], $home);
$signIn = $r['data']['sign_in'] ?? [];
check('a new school is created with its own database', $st === 200 && ($r['data']['school']['db_name'] ?? '') === $testDb, json_encode($r));
check('a password is generated for the school head', !empty($signIn['password']) && $signIn['password_generated'] === true);
[$st, $r] = call('POST', 'platform.php?action=create', ['name' => 'PF Again', 'code' => TEST_CODE, 'admin_name' => 'X', 'admin_email' => 'x@pftest.local'], $home);
check('the same code cannot be used twice', $st === 422 && isset($r['data']['errors']['code']), json_encode($r));

$new = Tenant::connectTo($testDb);
$count = fn(string $sql) => (int)$new->query($sql)->fetchColumn();
check('new school has every table', $count('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()') === (int)$db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'")->fetchColumn());
check('subjects and curriculum are copied', $count('SELECT COUNT(*) FROM subjects_new') > 0 && $count('SELECT COUNT(*) FROM curriculum_topics') > 0);
check('no students, teachers or marks are copied', $count('SELECT COUNT(*) FROM students') + $count('SELECT COUNT(*) FROM teachers') + $count('SELECT COUNT(*) FROM exam_marks') === 0);
check('the school has an open term', $count('SELECT COUNT(*) FROM academic_sessions WHERE is_active = 1') === 1);
check('report settings carry the school\'s own name', $new->query('SELECT school_name FROM report_settings WHERE id = 1')->fetchColumn() === 'PF Test Secondary School');

echo "Signing in per school\n";
[, $ctx] = call('GET', 'platform.php?action=context');
check('sign-in page now asks for a school code', ($ctx['data']['multi_school'] ?? false) === true, json_encode($ctx));
[$st, $ctx] = call('GET', 'platform.php?action=context&school=' . TEST_CODE);
check('the school code is recognised', $st === 200 && ($ctx['data']['school']['name'] ?? '') === 'PF Test Secondary School', json_encode($ctx));
[$st, $r] = call('GET', 'platform.php?action=context&school=no-such-school');
check('an unknown school code is refused clearly', $st === 404 && str_contains($r['message'] ?? '', 'could not find'), json_encode($r));
[$st, $r] = $login('head@pftest.local', (string)($signIn['password'] ?? ''));
check('the new head cannot sign in without the school code', $st !== 200 || empty($r['token']), json_encode($r));
[$st, $r] = $login('head@pftest.local', (string)($signIn['password'] ?? ''), TEST_CODE);
$school = $r['token'] ?? null;
check('the new head signs in with the school code', $st === 200 && $school && ($r['user']['school']['code'] ?? '') === TEST_CODE, json_encode($r));
check('first sign-in asks for a new password', ($r['user']['must_change_password'] ?? false) === true);
[$st, $r] = $login(HOME_ADMIN, 'HomeAdmin2026', TEST_CODE);
check('home accounts do not exist in the new school', empty($r['token']), json_encode($r));

echo "Isolation\n";
[$st, $r] = call('GET', 'students.php?limit=5', null, $school);
check('the new school sees only its own (no) students', $st === 200 && count($r['data'] ?? []) === 0, json_encode($r));
[$st, $r] = call('GET', 'students.php?limit=5&school=home', null, $school);
check('?school= cannot move a signed-in user to another school', $st === 200 && count($r['data'] ?? []) === 0, json_encode($r));
[$st, $r] = call('GET', 'students.php?limit=5&school=' . TEST_CODE, null, $home);
check('a home token stays in the home school', $st === 200 && count($r['data'] ?? []) > 0, (string)$st);
[$st] = call('GET', 'platform.php', null, $school);
check('a school head cannot manage schools', $st === 403, (string)$st);
[$st, $r] = call('POST', 'users.php', ['full_name' => 'PF Office', 'email' => 'office@pftest.local', 'password' => 'Office2026!', 'role' => 'admin'], $school);
check('the school head can add staff accounts to their school', in_array($st, [200, 201], true) && ($r['success'] ?? false), json_encode($r));
check('that account lives only in the new school', $count("SELECT COUNT(*) FROM users WHERE email = 'office@pftest.local'") === 1
    && (int)$db->query("SELECT COUNT(*) FROM users WHERE email = 'office@pftest.local'")->fetchColumn() === 0);
check('home students are untouched', (int)$db->query('SELECT COUNT(*) FROM students')->fetchColumn() === $homeStudents);

echo "Suspending a school\n";
[$st, $r] = call('POST', 'platform.php?action=update', ['code' => $homeRow['code'], 'status' => 'suspended'], $home);
check('the home school cannot be suspended', $st === 409, json_encode($r));
[$st] = call('POST', 'platform.php?action=update', ['code' => TEST_CODE, 'status' => 'suspended'], $home);
check('the platform owner suspends a school', $st === 200);
[$st, $r] = call('GET', 'students.php', null, $school);
check('a suspended school\'s users are stopped', $st === 403 && str_contains($r['message'] ?? '', 'suspended'), json_encode($r));
[$st, $r] = $login('head@pftest.local', (string)($signIn['password'] ?? ''), TEST_CODE);
check('a suspended school cannot sign in', $st === 403, json_encode($r));
[$st] = call('POST', 'platform.php?action=update', ['code' => TEST_CODE, 'status' => 'active', 'domain' => 'pftest.example.com'], $home);
[$st2] = call('GET', 'students.php', null, $school);
check('reactivating lets the school back in', $st === 200 && $st2 === 200, "$st, $st2");
[, $list] = call('GET', 'platform.php', null, $home);
$row = array_values(array_filter($list['data']['schools'] ?? [], fn($s) => $s['code'] === TEST_CODE))[0] ?? [];
check('the list shows the school with its address and counts', ($row['domain'] ?? '') === 'pftest.example.com' && ($row['counts']['users'] ?? 0) === 2, json_encode($row));

echo "\n$passed passed, $failed failed\n";
exit($failed ? 1 : 0);
