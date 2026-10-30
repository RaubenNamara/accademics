<?php
/**
 * Student sync with the school's SMIS roster (the St Mark's student API). Started by the
 * admin's "Sync students" switch on Student E-files; one run:
 *
 *  - New in SMIS: the learner is added in their class and stream (the class stream is created
 *    if it is new).
 *  - In both: their class, stream and level follow SMIS. Names and everything else in the e-file
 *    are left alone. A learner who completed S.4 and now shows in S.5/S.6 becomes a current
 *    A-Level student again.
 *  - Here but no longer in SMIS (changed school): marked "Left" with the date. Nothing is
 *    deleted, so parents, contacts, results, marks, subjects and documents stay attached to the
 *    learner. If they come back in SMIS they are restored to current. Learners an admin marked
 *    "Left" by hand are never brought back by the sync.
 *
 * One transaction: a failed run changes nothing. A run is refused when the SMIS answer looks wrong
 * (empty, malformed, or far smaller than the school), so an SMIS outage can never mark the whole
 * school as left.
 */
declare(strict_types=1);

const SS_DEFAULT_URL = 'https://smisug.com/STMARKLIBRARYAPI/SLISLIBRARYAPI/api/student';
/** A roster smaller than this share of the current students is treated as a broken answer. */
const SS_MIN_ROSTER_SHARE = 0.6;

function ss_ensure_schema(PDO $db): void
{
    $db->exec("CREATE TABLE IF NOT EXISTS student_sync_runs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        started_by INT NULL,
        started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        finished_at TIMESTAMP NULL DEFAULT NULL,
        status ENUM('running','success','failed') NOT NULL DEFAULT 'running',
        fetched INT NOT NULL DEFAULT 0,
        added INT NOT NULL DEFAULT 0,
        moved INT NOT NULL DEFAULT 0,
        left_school INT NOT NULL DEFAULT 0,
        returned INT NOT NULL DEFAULT 0,
        message VARCHAR(1000) NULL,
        details LONGTEXT NULL,
        KEY idx_student_sync_runs (status, started_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    // Who marked a learner as left, and when: 'sync' (not in SMIS any more) or an admin (NULL).
    if (!tt_column_exists($db, 'students', 'left_at')) {
        $db->exec('ALTER TABLE students ADD COLUMN left_at DATETIME NULL, ADD COLUMN left_by_sync TINYINT(1) NOT NULL DEFAULT 0');
    }
}

/** Level from the class name: S1–S4 O-Level, S5–S6 A-Level, P1–P7 Primary. */
function ss_level(string $class): ?string
{
    $c = strtoupper(str_replace([' ', '.', '-'], '', $class));
    if (preg_match('/^S([1-6])$/', $c, $m)) {
        return (int)$m[1] >= 5 ? 'A-Level' : 'O-Level';
    }
    if (preg_match('/^P[1-7]$/', $c)) {
        return 'Primary';
    }
    return null;
}

/** The SMIS roster as [{reg, name, class, stream}], or a RuntimeException with a plain message. */
function ss_fetch_roster(string $url = SS_DEFAULT_URL): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 90,
        CURLOPT_CONNECTTIMEOUT => 20, CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $body = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($body === false) {
        throw new RuntimeException('Could not reach SMIS: ' . $err);
    }
    if ($code !== 200) {
        throw new RuntimeException("SMIS answered with an error (HTTP $code). Nothing was changed.");
    }
    $data = json_decode((string)$body, true);
    if (!is_array($data)) {
        throw new RuntimeException('SMIS sent something that is not a student list. Nothing was changed.');
    }
    if (!array_is_list($data)) {
        $data = $data['data'] ?? $data['students'] ?? $data['items'] ?? [];
    }
    $out = [];
    foreach ((array)$data as $s) {
        if (!is_array($s)) {
            continue;
        }
        $reg = trim((string)($s['RegNo'] ?? ''));
        $name = trim((string)($s['Name'] ?? ''));
        $class = trim((string)($s['Class'] ?? ''));
        $stream = trim((string)($s['Stream'] ?? ''));
        if ($reg !== '' && $name !== '' && $class !== '' && $stream !== '') {
            $out[$reg] = ['reg' => $reg, 'name' => $name, 'class' => $class, 'stream' => $stream];
        }
    }
    return array_values($out);
}

/** The running run (if any) and the last few, for the switch card. */
function ss_status(PDO $db): array
{
    ss_ensure_schema($db);
    // A run that never finished (server restarted mid-way) stops blocking after 15 minutes.
    $db->exec("UPDATE student_sync_runs SET status = 'failed', finished_at = NOW(), message = 'Stopped before it finished'
        WHERE status = 'running' AND started_at < NOW() - INTERVAL 15 MINUTE");
    $runs = $db->query("SELECT r.id, r.started_at, r.finished_at, r.status, r.fetched, r.added, r.moved, r.left_school, r.returned, r.message,
            COALESCE(u.full_name, u.email, '') AS started_by_name
        FROM student_sync_runs r LEFT JOIN users u ON u.id = r.started_by ORDER BY r.id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($runs as &$r) {
        foreach (['id', 'fetched', 'added', 'moved', 'left_school', 'returned'] as $k) {
            $r[$k] = (int)$r[$k];
        }
    }
    unset($r);
    return ['running' => isset($runs[0]) && $runs[0]['status'] === 'running', 'runs' => $runs];
}

/**
 * Runs one sync. $roster replaces the SMIS call (tests).
 * @return array{ok: bool, message: string, run: ?array}
 */
function ss_run(PDO $db, ?int $startedBy, ?array $roster = null): array
{
    ss_ensure_schema($db);
    if ((int)$db->query("SELECT GET_LOCK('accademics_student_sync', 0)")->fetchColumn() !== 1) {
        return ['ok' => false, 'message' => 'A sync is already running. Wait for it to finish.', 'run' => null];
    }
    $db->prepare('INSERT INTO student_sync_runs (started_by) VALUES (?)')->execute([$startedBy]);
    $runId = (int)$db->lastInsertId();
    try {
        $result = ss_apply($db, $roster ?? ss_fetch_roster());
        ss_finish($db, $runId, 'success', $result['counts'], $result['message'], $result['details']);
        return ['ok' => true, 'message' => $result['message'], 'run' => ss_run_row($db, $runId)];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('Student sync failed: ' . $e->getMessage());
        $message = $e instanceof RuntimeException ? $e->getMessage() : 'The sync failed and nothing was changed.';
        ss_finish($db, $runId, 'failed', [], $message, null);
        return ['ok' => false, 'message' => $message, 'run' => ss_run_row($db, $runId)];
    } finally {
        $db->query("SELECT RELEASE_LOCK('accademics_student_sync')");
    }
}

function ss_apply(PDO $db, array $roster): array
{
    $current = (int)$db->query("SELECT COUNT(*) FROM students WHERE status = 'active'")->fetchColumn();
    if (!$roster) {
        throw new RuntimeException('SMIS returned no students. Nothing was changed.');
    }
    if ($current > 0 && count($roster) < $current * SS_MIN_ROSTER_SHARE) {
        throw new RuntimeException('SMIS returned only ' . count($roster) . " students for a school of $current, so the answer looks incomplete. Nothing was changed.");
    }

    $existing = [];
    foreach ($db->query('SELECT id, admission_number, full_name, `class`, stream, level, status, left_by_sync FROM students') as $s) {
        $existing[strtolower(trim((string)$s['admission_number']))] = $s;
    }
    $classIds = [];
    foreach ($db->query('SELECT id, class_name, stream_name FROM classes') as $c) {
        $classIds[strtolower(trim($c['class_name'])) . '|' . strtolower(trim($c['stream_name']))] = (int)$c['id'];
    }

    $addClass = $db->prepare('INSERT INTO classes (class_name, stream_name, full_class_name) VALUES (:c, :s, :f)');
    $insert = $db->prepare("INSERT INTO students (admission_number, full_name, `class`, stream, level, enrollment_date, is_active, status)
        VALUES (:reg, :name, :class, :stream, :level, CURDATE(), 1, 'active')");
    $move = $db->prepare('UPDATE students SET `class` = :class, stream = :stream, level = COALESCE(:level, level) WHERE id = :id');
    $restore = $db->prepare("UPDATE students SET status = 'active', is_active = 1, left_at = NULL, left_by_sync = 0 WHERE id = :id");
    $leave = $db->prepare("UPDATE students SET status = 'left', left_at = NOW(), left_by_sync = 1 WHERE id = :id");

    $counts = ['fetched' => count($roster), 'added' => 0, 'moved' => 0, 'left_school' => 0, 'returned' => 0];
    $details = ['added' => [], 'moved' => [], 'left' => [], 'returned' => [], 'classes' => []];
    $seen = [];

    $db->beginTransaction();
    foreach ($roster as $r) {
        $key = strtolower($r['reg']);
        $seen[$key] = true;
        $level = ss_level($r['class']);
        $classKey = strtolower($r['class']) . '|' . strtolower($r['stream']);
        if (!isset($classIds[$classKey])) {
            $addClass->execute([':c' => $r['class'], ':s' => $r['stream'], ':f' => trim($r['class'] . ' ' . $r['stream'])]);
            $classIds[$classKey] = (int)$db->lastInsertId();
            $details['classes'][] = trim($r['class'] . ' ' . $r['stream']);
        }

        $s = $existing[$key] ?? null;
        if (!$s) {
            $insert->execute([':reg' => $r['reg'], ':name' => $r['name'], ':class' => $r['class'], ':stream' => $r['stream'], ':level' => $level]);
            $counts['added']++;
            $details['added'][] = "{$r['reg']} {$r['name']} ({$r['class']} {$r['stream']})";
            continue;
        }

        $status = (string)$s['status'];
        if ($status === 'left') {
            if ((int)$s['left_by_sync'] !== 1) {
                continue; // an admin marked them as left: the sync leaves that decision alone
            }
            $restore->execute([':id' => $s['id']]);
            $counts['returned']++;
            $details['returned'][] = "{$r['reg']} {$s['full_name']}";
        } elseif ($status === 'completed_o_level' && $level === 'A-Level') {
            $restore->execute([':id' => $s['id']]); // back for A-Level
            $counts['returned']++;
            $details['returned'][] = "{$r['reg']} {$s['full_name']} (A-Level)";
        } elseif ($status !== 'active') {
            continue; // completed learners keep their final class
        }

        $sameClass = strcasecmp(trim((string)$s['class']), $r['class']) === 0 && strcasecmp(trim((string)$s['stream']), $r['stream']) === 0;
        if (!$sameClass) {
            $move->execute([':class' => $r['class'], ':stream' => $r['stream'], ':level' => $level, ':id' => $s['id']]);
            $counts['moved']++;
            $details['moved'][] = "{$r['reg']} {$s['full_name']}: {$s['class']} {$s['stream']} -> {$r['class']} {$r['stream']}";
        }
    }

    // Current learners SMIS no longer lists have changed school: kept, marked as left.
    foreach ($existing as $key => $s) {
        if (!isset($seen[$key]) && $s['status'] === 'active') {
            $leave->execute([':id' => $s['id']]);
            $counts['left_school']++;
            $details['left'][] = "{$s['admission_number']} {$s['full_name']} ({$s['class']} {$s['stream']})";
        }
    }
    $db->commit();

    $parts = [];
    foreach (['added' => 'added', 'moved' => 'changed class', 'left_school' => 'marked as left', 'returned' => 'back in school'] as $k => $label) {
        if ($counts[$k]) {
            $parts[] = "{$counts[$k]} $label";
        }
    }
    $message = 'Synced ' . number_format($counts['fetched']) . ' students from SMIS' . ($parts ? ': ' . implode(', ', $parts) : '. Everything was already up to date.');
    return ['counts' => $counts, 'message' => $message, 'details' => $details];
}

function ss_finish(PDO $db, int $runId, string $status, array $counts, string $message, ?array $details): void
{
    $db->prepare('UPDATE student_sync_runs SET status = :st, finished_at = NOW(), fetched = :f, added = :a, moved = :m, left_school = :l, returned = :r,
            message = :msg, details = :d WHERE id = :id')
        ->execute([':st' => $status, ':f' => $counts['fetched'] ?? 0, ':a' => $counts['added'] ?? 0, ':m' => $counts['moved'] ?? 0,
            ':l' => $counts['left_school'] ?? 0, ':r' => $counts['returned'] ?? 0, ':msg' => mb_substr($message, 0, 1000),
            ':d' => $details ? json_encode($details) : null, ':id' => $runId]);
}

function ss_run_row(PDO $db, int $runId): ?array
{
    foreach (ss_status($db)['runs'] as $r) {
        if ($r['id'] === $runId) {
            return $r;
        }
    }
    return null;
}
