<?php
/**
 * Multi-school (SaaS) support: one database per school, listed in a small registry
 * database (`schools` table). Every request is served from the database of its school:
 *
 *   1. the `school` claim of a correctly signed login token,
 *   2. `?school=<code>` on requests made before signing in (the sign-in form),
 *   3. the request's host name, when a school has its own domain,
 *   4. otherwise the home school (the original database).
 *
 * Without a registry database the app runs as a single school, exactly as before.
 * Command-line scripts pick a school with the SKOOL_SCHOOL environment variable.
 *
 * Settings (optional) in config/platform.local.php:
 *   <?php return ['registry_db' => 'skool_platform', 'db_prefix' => 'sm_'];
 */
require_once __DIR__ . '/JWT.php';

final class Tenant
{
    /** @var array|null the school serving this request */
    private static $current = null;
    /** @var callable|null fn(string $dbName): PDO */
    private static $connect = null;
    private static $defaultDb = null;
    /** @var PDO|null|false false = not looked up yet */
    private static $registry = false;
    private const HOME_MARK = '@home';

    public static function settings(): array
    {
        static $settings = null;
        if ($settings === null) {
            $file = __DIR__ . '/platform.local.php';
            $local = is_file($file) ? include $file : [];
            $settings = (is_array($local) ? $local : []) + ['registry_db' => 'skool_platform', 'db_prefix' => 'sm_'];
        }
        return $settings;
    }

    /** Database name for this request. $connect opens a PDO to a named database. */
    public static function databaseFor(string $defaultDb, callable $connect): string
    {
        self::$connect = $connect;
        self::$defaultDb = $defaultDb;
        $school = self::current();
        if ($school['status'] !== 'active') {
            self::stop(403, 'This school\'s account is suspended. Please contact Skool Monitor support.');
        }
        return $school['db_name'];
    }

    /** ['code','name','db_name','status','is_home','domain'] of the school serving this request. */
    public static function current(): array
    {
        if (self::$current !== null) {
            return self::$current;
        }
        $home = ['code' => null, 'name' => '', 'db_name' => self::$defaultDb ?? 'accademics', 'status' => 'active', 'is_home' => true, 'domain' => null];
        $registry = self::registry();
        if (!$registry) {
            return self::$current = $home;
        }

        $wanted = self::tokenSchool();
        $explicit = $wanted !== null;
        if ($wanted === null && PHP_SAPI === 'cli' && getenv('SKOOL_SCHOOL')) {
            [$wanted, $explicit] = [strtolower((string)getenv('SKOOL_SCHOOL')), true];
        }
        if ($wanted === null && isset($_GET['school']) && trim((string)$_GET['school']) !== '') {
            [$wanted, $explicit] = [strtolower(trim((string)$_GET['school'])), true];
        }

        $row = null;
        if ($wanted === self::HOME_MARK) {
            $row = $registry->query('SELECT * FROM schools WHERE is_home = 1 LIMIT 1')->fetch(PDO::FETCH_ASSOC) ?: null;
            $wanted = null;
            $explicit = false;
        }
        if ($wanted !== null) {
            $stmt = $registry->prepare('SELECT * FROM schools WHERE code = :c LIMIT 1');
            $stmt->execute([':c' => $wanted]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if (!$row && $explicit) {
                self::stop(404, 'We could not find a school with the code "' . $wanted . '". Check the code and try again.');
            }
        }
        if (!$row) {
            $host = strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
            if ($host !== '') {
                $stmt = $registry->prepare('SELECT * FROM schools WHERE domain = :h LIMIT 1');
                $stmt->execute([':h' => $host]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            }
        }
        if (!$row) {
            $row = $registry->query('SELECT * FROM schools WHERE is_home = 1 LIMIT 1')->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        if (!$row) {
            return self::$current = $home;
        }
        return self::$current = [
            'code' => $row['code'],
            'name' => $row['name'],
            'db_name' => $row['db_name'],
            'status' => $row['status'],
            'is_home' => (int)$row['is_home'] === 1,
            'domain' => $row['domain'],
        ];
    }

    /** PDO to the registry, or null when this install has none (single school). */
    public static function registry(): ?PDO
    {
        if (self::$registry !== false) {
            return self::$registry;
        }
        self::$registry = null;
        if (!self::$connect) {
            return null;
        }
        try {
            $candidate = (self::$connect)(self::settings()['registry_db']);
            $candidate->query('SELECT 1 FROM schools LIMIT 1');
            self::$registry = $candidate;
        } catch (Throwable $e) {
            self::$registry = null;
        }
        return self::$registry;
    }

    /** Look the registry up again (after it has just been created). */
    public static function refreshRegistry(): ?PDO
    {
        self::$registry = false;
        return self::registry();
    }

    /** Open a PDO to any database with the app's credentials (used when creating schools). */
    public static function connectTo(string $dbName): PDO
    {
        if (!self::$connect) {
            throw new RuntimeException('Database not initialised');
        }
        return (self::$connect)($dbName);
    }

    public static function defaultDb(): string
    {
        return self::$defaultDb ?? 'accademics';
    }

    /** School code from a correctly signed, unexpired token, if the request carries one. */
    private static function tokenSchool(): ?string
    {
        $header = '';
        foreach ([$_SERVER['HTTP_AUTHORIZATION'] ?? '', $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''] as $value) {
            if (is_string($value) && trim($value) !== '') {
                $header = $value;
                break;
            }
        }
        if ($header === '' && function_exists('getallheaders')) {
            foreach ((array)getallheaders() as $name => $value) {
                if (strcasecmp((string)$name, 'Authorization') === 0) {
                    $header = (string)$value;
                }
            }
        }
        if ($header === '') {
            return null;
        }
        $payload = JWT::decode(preg_replace('/^Bearer\s+/i', '', trim($header)));
        if (!$payload) {
            return null;
        }
        // A signed-in request always stays in its token's school; tokens issued before
        // schools existed belong to the home school. Never fall through to ?school= or the host.
        return isset($payload['school']) && $payload['school'] !== '' ? strtolower((string)$payload['school']) : self::HOME_MARK;
    }

    private static function stop(int $status, string $message): void
    {
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, $message . PHP_EOL);
            exit(1);
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['success' => false, 'message' => $message, 'school_error' => true]);
        exit;
    }
}
