<?php
/**
 * Skool Monitor platform: the list of schools (registry database) and setting up a new school.
 * Each school has its own database; see Tenant.php for how requests reach it.
 */
require_once __DIR__ . '/Tenant.php';

/** Reference data every new school starts with (structure is copied for every table). */
const PF_REFERENCE_TABLES = ['subjects_new', 'subjects', 'curriculum_topics', 'curriculum_outcomes', 'timetable_periods', 'departments', 'roles'];
const PF_CODE_PATTERN = '/^[a-z0-9][a-z0-9-]{1,29}$/';

class PlatformException extends RuntimeException
{
    public array $errors;

    public function __construct(string $message, int $status = 422, array $errors = [])
    {
        parent::__construct($message, $status);
        $this->errors = $errors;
    }
}

function pf_registry_name(): string
{
    return (string)Tenant::settings()['registry_db'];
}

function pf_quote_ident(string $name): string
{
    if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $name)) {
        throw new PlatformException("\"$name\" is not a valid database name (letters, numbers and _ only)");
    }
    return "`$name`";
}

function pf_slug(string $name): string
{
    $slug = trim(preg_replace('/[^a-z0-9]+/', '-', str_replace(["'", '’'], '', strtolower($name))), '-');
    return substr($slug !== '' ? $slug : 'main', 0, 30);
}

/**
 * Create the registry and register the current (home) school in it. Safe to run twice.
 * $db is a connection to the home school's database.
 */
function pf_setup(PDO $db, string $homeName = ''): array
{
    $registryName = pf_registry_name();
    $registry = Tenant::registry();
    if (!$registry) {
        try {
            $db->exec('CREATE DATABASE IF NOT EXISTS ' . pf_quote_ident($registryName) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        } catch (PDOException $e) {
            // cPanel hosts do not let applications create databases.
        }
        try {
            $registry = Tenant::connectTo($registryName);
        } catch (Throwable $e) {
            throw new PlatformException("Create an empty database called \"$registryName\" (in cPanel: MySQL Databases), give the app's database user full access to it, then try again. If cPanel adds a prefix, put the full name in backend/config/platform.local.php as 'registry_db'.", 409);
        }
        $registry->exec("CREATE TABLE IF NOT EXISTS schools (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(30) NOT NULL UNIQUE,
            name VARCHAR(150) NOT NULL,
            db_name VARCHAR(64) NOT NULL UNIQUE,
            domain VARCHAR(190) NULL UNIQUE,
            status ENUM('active','suspended') NOT NULL DEFAULT 'active',
            is_home TINYINT(1) NOT NULL DEFAULT 0,
            contact_name VARCHAR(150) NULL,
            contact_email VARCHAR(190) NULL,
            contact_phone VARCHAR(50) NULL,
            notes TEXT NULL,
            created_by VARCHAR(190) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        Tenant::refreshRegistry();
    }
    if (!(int)$registry->query('SELECT COUNT(*) FROM schools WHERE is_home = 1')->fetchColumn()) {
        if ($homeName === '') {
            $homeName = (string)($db->query('SELECT school_name FROM report_settings WHERE id = 1')->fetchColumn() ?: 'Home school');
        }
        $code = pf_slug($homeName);
        $registry->prepare('INSERT INTO schools (code, name, db_name, is_home, status) VALUES (?, ?, ?, 1, \'active\')')
            ->execute([$code, $homeName, Tenant::defaultDb()]);
    }
    return pf_list();
}

/** Every school with headline counts read from its own database. */
function pf_list(): array
{
    $registry = Tenant::registry();
    if (!$registry) {
        return ['ready' => false, 'schools' => []];
    }
    $schools = $registry->query('SELECT * FROM schools ORDER BY is_home DESC, name')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($schools as &$s) {
        $s['is_home'] = (int)$s['is_home'] === 1;
        $s['counts'] = null;
        try {
            $pdo = Tenant::connectTo($s['db_name']);
            $count = fn(string $sql) => (int)$pdo->query($sql)->fetchColumn();
            $s['counts'] = [
                'students' => $count("SELECT COUNT(*) FROM students WHERE status = 'active' OR status IS NULL"),
                'teachers' => $count('SELECT COUNT(*) FROM teachers WHERE is_active = 1 OR is_active IS NULL'),
                'users' => $count('SELECT COUNT(*) FROM users WHERE is_active = 1'),
            ];
            $s['last_sign_in'] = $pdo->query('SELECT MAX(last_login) FROM users')->fetchColumn() ?: null;
        } catch (Throwable $e) {
            $s['unreachable'] = true;
        }
    }
    unset($s);
    return ['ready' => true, 'schools' => $schools];
}

function pf_school(string $code): array
{
    $registry = Tenant::registry() ?? throw new PlatformException('Set up the school list first', 409);
    $stmt = $registry->prepare('SELECT * FROM schools WHERE code = ?');
    $stmt->execute([$code]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: throw new PlatformException('School not found', 404);
}

function pf_clean_domain(?string $domain): ?string
{
    $domain = strtolower(trim((string)$domain));
    $domain = preg_replace('#^https?://#', '', $domain);
    $domain = rtrim($domain, '/');
    return $domain === '' ? null : $domain;
}

/**
 * Create a school: its database (or an empty one made in cPanel), the full table structure,
 * shared reference data, a first open term and the school's head account.
 * Returns the new school and the head account's sign-in details.
 */
function pf_create_school(PDO $homeDb, array $in, string $createdBy): array
{
    $registry = Tenant::registry() ?? throw new PlatformException('Set up the school list first', 409);
    $name = trim((string)($in['name'] ?? ''));
    $code = strtolower(trim((string)($in['code'] ?? '')));
    $adminName = trim((string)($in['admin_name'] ?? ''));
    $adminEmail = strtolower(trim((string)($in['admin_email'] ?? '')));
    $password = (string)($in['admin_password'] ?? '');
    $domain = pf_clean_domain($in['domain'] ?? null);
    $dbName = trim((string)($in['db_name'] ?? '')) ?: Tenant::settings()['db_prefix'] . str_replace('-', '_', $code);

    $errors = [];
    if (mb_strlen($name) < 3 || mb_strlen($name) > 150) {
        $errors['name'] = 'Enter the school\'s name';
    }
    if (!preg_match(PF_CODE_PATTERN, $code)) {
        $errors['code'] = 'Use 2 to 30 lowercase letters, numbers or dashes, e.g. "stjohns"';
    }
    if ($adminName === '') {
        $errors['admin_name'] = 'Enter the name of the school\'s administrator';
    }
    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        $errors['admin_email'] = 'Enter a valid email address';
    }
    $generated = false;
    if ($password === '') {
        $password = 'Sm-' . bin2hex(random_bytes(4)) . '!' . random_int(10, 99);
        $generated = true;
    } elseif (strlen($password) < 8) {
        $errors['admin_password'] = 'Use at least 8 characters';
    }
    if ($domain !== null && !preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $domain)) {
        $errors['domain'] = 'Enter a web address like school.example.com, or leave it empty';
    }
    if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $dbName)) {
        $errors['db_name'] = 'Letters, numbers and _ only';
    }
    if (!$errors) {
        $taken = $registry->prepare('SELECT code, db_name, domain FROM schools WHERE code = ? OR db_name = ? OR (domain IS NOT NULL AND domain = ?)');
        $taken->execute([$code, $dbName, $domain]);
        foreach ($taken->fetchAll(PDO::FETCH_ASSOC) as $t) {
            if ($t['code'] === $code) {
                $errors['code'] = 'Another school already uses this code';
            }
            if ($t['db_name'] === $dbName) {
                $errors['db_name'] = 'Another school already uses this database';
            }
            if ($domain !== null && $t['domain'] === $domain) {
                $errors['domain'] = 'Another school already uses this address';
            }
        }
    }
    if ($errors) {
        throw new PlatformException('Please check the highlighted fields', 422, $errors);
    }

    $homeName = Tenant::defaultDb();
    $existed = (bool)$homeDb->query('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ' . $homeDb->quote($dbName))->fetchColumn();
    $createdDb = false;
    if (!$existed) {
        try {
            $homeDb->exec('CREATE DATABASE ' . pf_quote_ident($dbName) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $createdDb = true;
        } catch (PDOException $e) {
            throw new PlatformException("This server does not let the app create databases. In cPanel (MySQL Databases) create an empty database, give the app's database user full access, then enter its full name under \"Database\" and try again.", 409, ['db_name' => 'Create this database in cPanel first']);
        }
    }

    try {
        $school = Tenant::connectTo($dbName);
        if ((int)$school->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()')->fetchColumn() > 0) {
            throw new PlatformException("The database \"$dbName\" already has tables in it. Use an empty database for a new school.", 409, ['db_name' => 'Not empty']);
        }

        // Structure: every table exactly as in the home school, foreign keys included.
        $tables = $homeDb->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
        $school->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($tables as $table) {
            $create = $homeDb->query('SHOW CREATE TABLE ' . pf_quote_ident($table))->fetch(PDO::FETCH_NUM)[1];
            $school->exec(preg_replace('/\sAUTO_INCREMENT=\d+/', '', $create));
        }

        // Shared reference data: subjects, the national curriculum, periods, departments, job roles.
        foreach (PF_REFERENCE_TABLES as $table) {
            if (in_array($table, $tables, true)) {
                $school->exec('INSERT INTO ' . pf_quote_ident($table) . ' SELECT * FROM ' . pf_quote_ident($homeName) . '.' . pf_quote_ident($table));
            }
        }
        // Report settings: grading and subject rules carried over, the school's own name and nothing else.
        if (in_array('report_settings', $tables, true)) {
            $school->exec('INSERT INTO report_settings SELECT * FROM ' . pf_quote_ident($homeName) . '.report_settings WHERE id = 1');
            $school->prepare('UPDATE report_settings SET school_name = ?, motto = NULL, address = NULL, phone = NULL, email = NULL, website = NULL, logo = NULL, head_teacher = NULL, next_term_begins = NULL, footer_note = NULL WHERE id = 1')
                ->execute([$name]);
            $school->prepare('INSERT IGNORE INTO report_settings (id, school_name) VALUES (1, ?)')->execute([$name]);
        }
        $school->exec('SET FOREIGN_KEY_CHECKS = 1');

        // The current term, open, so the school can start straight away.
        $current = $homeDb->query('SELECT academic_year, term, start_date, end_date FROM academic_sessions WHERE is_active = 1 LIMIT 1')->fetch(PDO::FETCH_ASSOC)
            ?: ['academic_year' => (int)date('Y'), 'term' => 1, 'start_date' => date('Y-01-01'), 'end_date' => date('Y-04-30')];
        $school->prepare('INSERT INTO academic_sessions (session_name, academic_year, term, start_date, end_date, is_active) VALUES (?, ?, ?, ?, ?, 1)')
            ->execute(["{$current['academic_year']} Term {$current['term']}", $current['academic_year'], $current['term'], $current['start_date'], $current['end_date']]);

        // The school's head account: full control of this school only.
        $school->prepare('INSERT INTO users (full_name, email, password_hash, role, is_active, must_change_password) VALUES (?, ?, ?, \'super_admin\', 1, 1)')
            ->execute([$adminName, $adminEmail, password_hash($password, PASSWORD_DEFAULT)]);

        $registry->prepare('INSERT INTO schools (code, name, db_name, domain, status, contact_name, contact_email, contact_phone, created_by) VALUES (?, ?, ?, ?, \'active\', ?, ?, ?, ?)')
            ->execute([$code, $name, $dbName, $domain, $adminName, $adminEmail, trim((string)($in['contact_phone'] ?? '')) ?: null, $createdBy]);
    } catch (Throwable $e) {
        // Leave nothing half-made behind: drop a database this call created, or empty one made in cPanel.
        try {
            if ($createdDb) {
                $homeDb->exec('DROP DATABASE ' . pf_quote_ident($dbName));
            } elseif (isset($school) && !($e instanceof PlatformException && ($e->errors['db_name'] ?? '') === 'Not empty')) {
                $school->exec('SET FOREIGN_KEY_CHECKS = 0');
                foreach ($school->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {
                    $school->exec('DROP TABLE ' . pf_quote_ident($t));
                }
            }
        } catch (Throwable $ignored) {
        }
        if ($e instanceof PlatformException) {
            throw $e;
        }
        throw new PlatformException('The school could not be set up: ' . $e->getMessage(), 500);
    }

    return [
        'school' => pf_school($code),
        'sign_in' => ['school_code' => $code, 'email' => $adminEmail, 'password' => $generated ? $password : null, 'password_generated' => $generated],
    ];
}

/** Rename, change address or contact, suspend or reactivate a school. */
function pf_update_school(string $code, array $in): array
{
    $registry = Tenant::registry() ?? throw new PlatformException('Set up the school list first', 409);
    $school = pf_school($code);
    $sets = [];
    $params = [];
    $errors = [];
    if (array_key_exists('name', $in)) {
        $name = trim((string)$in['name']);
        if (mb_strlen($name) < 3 || mb_strlen($name) > 150) {
            $errors['name'] = 'Enter the school\'s name';
        }
        $sets[] = 'name = ?';
        $params[] = $name;
    }
    if (array_key_exists('domain', $in)) {
        $domain = pf_clean_domain($in['domain']);
        if ($domain !== null && !preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $domain)) {
            $errors['domain'] = 'Enter a web address like school.example.com, or leave it empty';
        } elseif ($domain !== null) {
            $dup = $registry->prepare('SELECT COUNT(*) FROM schools WHERE domain = ? AND code <> ?');
            $dup->execute([$domain, $code]);
            if ((int)$dup->fetchColumn()) {
                $errors['domain'] = 'Another school already uses this address';
            }
        }
        $sets[] = 'domain = ?';
        $params[] = $domain;
    }
    foreach (['contact_name', 'contact_email', 'contact_phone', 'notes'] as $field) {
        if (array_key_exists($field, $in)) {
            $sets[] = "$field = ?";
            $params[] = trim((string)$in[$field]) ?: null;
        }
    }
    if (array_key_exists('status', $in)) {
        $status = (string)$in['status'];
        if (!in_array($status, ['active', 'suspended'], true)) {
            $errors['status'] = 'Status must be active or suspended';
        } elseif ($status === 'suspended' && (int)$school['is_home'] === 1) {
            throw new PlatformException('The home school runs the platform and cannot be suspended', 409);
        }
        $sets[] = 'status = ?';
        $params[] = $status;
    }
    if ($errors) {
        throw new PlatformException('Please check the highlighted fields', 422, $errors);
    }
    if ($sets) {
        $params[] = $code;
        $registry->prepare('UPDATE schools SET ' . implode(', ', $sets) . ' WHERE code = ?')->execute($params);
    }
    return pf_school($code);
}

/** A platform-wide setting from the registry, or $default (also when there is no registry yet). */
function pf_setting(string $name, string $default = ''): string
{
    $registry = Tenant::registry();
    if (!$registry) {
        return $default;
    }
    try {
        $stmt = $registry->prepare('SELECT value FROM platform_settings WHERE name = ?');
        $stmt->execute([$name]);
        $value = $stmt->fetchColumn();
        return $value === false ? $default : (string)$value;
    } catch (PDOException $e) {
        return $default; // table not created yet
    }
}

function pf_set_setting(string $name, string $value): void
{
    $registry = Tenant::registry() ?? throw new PlatformException('Set up the school list first', 409);
    $registry->exec('CREATE TABLE IF NOT EXISTS platform_settings (
        name VARCHAR(64) PRIMARY KEY,
        value TEXT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $registry->prepare('INSERT INTO platform_settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)')->execute([$name, $value]);
}
