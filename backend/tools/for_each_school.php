<?php
/**
 * Run a backend script (usually a migration) once for every school.
 *
 *   php backend/tools/for_each_school.php backend/migrations/41_curriculum.php
 *   php backend/tools/for_each_school.php backend/migrations/41_curriculum.php --only=stjohns
 *
 * Each run gets SKOOL_SCHOOL=<code>, so Database connects to that school's database.
 * Without a school list (a single-school install) the script simply runs once.
 * Suspended schools are included: their data still needs the same structure.
 */
if (PHP_SAPI !== 'cli') {
    exit("Run this from the command line.\n");
}
$script = $argv[1] ?? '';
if ($script === '' || !is_file($script)) {
    fwrite(STDERR, "Usage: php backend/tools/for_each_school.php <script.php> [--only=code]\n");
    exit(2);
}
$only = null;
foreach (array_slice($argv, 2) as $arg) {
    if (str_starts_with($arg, '--only=')) {
        $only = strtolower(substr($arg, 7));
    }
}

require_once __DIR__ . '/../config/Database.php';
(new Database())->getConnection(); // initialises the school list
$registry = Tenant::registry();
$schools = $registry
    ? $registry->query('SELECT code, name FROM schools ORDER BY is_home DESC, name')->fetchAll(PDO::FETCH_ASSOC)
    : [['code' => null, 'name' => 'this school']];

$php = PHP_BINARY;
$failed = [];
foreach ($schools as $school) {
    if ($only !== null && $school['code'] !== $only) {
        continue;
    }
    echo "\n=== {$school['name']}" . ($school['code'] ? " ({$school['code']})" : '') . " ===\n";
    $env = getenv();
    if ($school['code']) {
        $env['SKOOL_SCHOOL'] = $school['code'];
    }
    $proc = proc_open([$php, $script], [STDIN, STDOUT, STDERR], $pipes, null, $env);
    $code = is_resource($proc) ? proc_close($proc) : 1;
    if ($code !== 0) {
        $failed[] = $school['name'];
    }
}
echo $failed ? "\nFailed for: " . implode(', ', $failed) . "\n" : "\nDone for every school.\n";
exit($failed ? 1 : 0);
