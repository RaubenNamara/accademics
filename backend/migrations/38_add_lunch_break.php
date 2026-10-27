<?php
/**
 * Migration 38: add a 13:00–14:00 lunch break after the lesson period that ends at 13:00 (Period 7).
 *
 * Lessons reference periods by period_number, so every later period moves down
 * one number and 60 minutes later. In one transaction this renumbers:
 *  - timetable_periods (and allows the 'lunch' / 'assembly' period types),
 *  - timetable rows (period_number, start_time, end_time) and timetable_slots,
 *  - teacher_availability,
 *  - lesson_requirements preferred_periods / avoid_periods,
 *  - published snapshots in timetable_versions (so restores stay correct).
 * A lesson that would now run across lunch (e.g. a Period 7–8 double) is kept
 * and reported; validation flags it until someone moves it.
 *
 * Idempotent: does nothing if a lunch period already exists.
 * Usage: php backend/migrations/38_add_lunch_break.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/TimetableService.php';

const LUNCH_START = '13:00:00';
const LUNCH_END = '14:00:00';

/**
 * Returns ['log' => string[], 'across' => int[] lesson ids now spanning lunch].
 * Throws on failure; the data changes are rolled back.
 */
function tt_add_lunch_break(PDO $db): array
{
    $log = [];
    tt_ensure_schema($db);

    $type = (string)$db->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'timetable_periods' AND COLUMN_NAME = 'period_type'")->fetchColumn();
    if (strpos($type, "'lunch'") === false) {
        $db->exec("ALTER TABLE timetable_periods MODIFY COLUMN period_type ENUM('lesson','break','lunch','assembly') NOT NULL DEFAULT 'lesson'");
        $log[] = 'period_type now allows lunch and assembly';
    }

    if ((int)$db->query("SELECT COUNT(*) FROM timetable_periods WHERE period_type = 'lunch' OR label LIKE '%lunch%'")->fetchColumn() > 0) {
        $log[] = 'A lunch period already exists — nothing to do.';
        return ['log' => $log, 'across' => []];
    }

    $stmt = $db->prepare("SELECT period_number, label FROM timetable_periods WHERE period_type = 'lesson' AND end_time = :t ORDER BY period_number LIMIT 1");
    $stmt->execute([':t' => LUNCH_START]);
    $anchor = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$anchor) {
        throw new RuntimeException('No lesson period ends at ' . LUNCH_START . '; check timetable_periods before adding lunch.');
    }
    $after = (int)$anchor['period_number'];
    $lunch = $after + 1;
    $hour = strtotime(LUNCH_END) - strtotime(LUNCH_START);
    $shiftTime = static fn(?string $t): ?string => $t === null || $t === '' ? $t : date('H:i:s', strtotime($t) + $hour);
    $shiftList = static function ($raw) use ($after) {
        if ($raw === null || $raw === '') {
            return $raw;
        }
        $list = json_decode((string)$raw, true);
        if (!is_array($list)) {
            return $raw;
        }
        return json_encode(array_map(fn($p) => is_numeric($p) && (int)$p > $after ? (int)$p + 1 : $p, $list));
    };
    $shift = gmdate('H:i:s', $hour);

    $db->beginTransaction();
    try {
        // Periods: move later ones down (highest first so the unique key never collides), then insert lunch.
        $later = $db->prepare('SELECT id, period_number, start_time, end_time FROM timetable_periods WHERE period_number > :a ORDER BY period_number DESC');
        $later->execute([':a' => $after]);
        $upd = $db->prepare('UPDATE timetable_periods SET period_number = :n, start_time = :s, end_time = :e WHERE id = :id');
        foreach ($later->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $upd->execute([':n' => (int)$p['period_number'] + 1, ':s' => $shiftTime($p['start_time']), ':e' => $shiftTime($p['end_time']), ':id' => $p['id']]);
        }
        $db->prepare("INSERT INTO timetable_periods (period_number, label, period_type, start_time, end_time, is_active) VALUES (:n, 'Lunch', 'lunch', :s, :e, 1)")
            ->execute([':n' => $lunch, ':s' => LUNCH_START, ':e' => LUNCH_END]);

        // Lessons that start at or before the anchor but run past it now cover lunch: they end an hour later.
        $across = array_map('intval', $db->query("SELECT id FROM timetable
            WHERE period_number <= $after AND period_number + GREATEST(spans_periods, 1) - 1 > $after")->fetchAll(PDO::FETCH_COLUMN));
        if ($across) {
            $db->exec("UPDATE timetable SET end_time = ADDTIME(end_time, '$shift') WHERE id IN (" . implode(',', $across) . ')');
        }
        // Lessons starting after the anchor move down a period and an hour later (highest first: unique keys).
        $moved = (int)$db->exec("UPDATE timetable SET period_number = period_number + 1,
                start_time = ADDTIME(start_time, '$shift'), end_time = ADDTIME(end_time, '$shift')
            WHERE period_number > $after ORDER BY period_number DESC");
        $db->exec("UPDATE timetable_slots SET period_number = period_number + 1 WHERE period_number > $after ORDER BY period_number DESC");
        $avail = (int)$db->exec("UPDATE teacher_availability SET period_number = period_number + 1 WHERE period_number > $after ORDER BY period_number DESC");

        $reqs = $db->query('SELECT id, preferred_periods, avoid_periods FROM lesson_requirements WHERE preferred_periods IS NOT NULL OR avoid_periods IS NOT NULL')->fetchAll(PDO::FETCH_ASSOC);
        $updReq = $db->prepare('UPDATE lesson_requirements SET preferred_periods = :p, avoid_periods = :a WHERE id = :id');
        foreach ($reqs as $r) {
            $updReq->execute([':p' => $shiftList($r['preferred_periods']), ':a' => $shiftList($r['avoid_periods']), ':id' => $r['id']]);
        }

        $versions = $db->query('SELECT id, snapshot FROM timetable_versions WHERE snapshot IS NOT NULL')->fetchAll(PDO::FETCH_ASSOC);
        $updVer = $db->prepare('UPDATE timetable_versions SET snapshot = :s WHERE id = :id');
        foreach ($versions as $v) {
            $rows = json_decode((string)$v['snapshot'], true) ?: [];
            foreach ($rows as &$row) {
                $start = (int)$row['period_number'];
                $end = $start + max(1, (int)($row['spans_periods'] ?? 1)) - 1;
                if ($start > $after) {
                    $row['period_number'] = $start + 1;
                    $row['start_time'] = $shiftTime($row['start_time'] ?? null);
                    $row['end_time'] = $shiftTime($row['end_time'] ?? null);
                } elseif ($end > $after) {
                    $row['end_time'] = $shiftTime($row['end_time'] ?? null);
                }
            }
            unset($row);
            $updVer->execute([':s' => json_encode($rows), ':id' => $v['id']]);
        }

        foreach ($db->query('SELECT academic_session_id FROM timetable_session_state')->fetchAll(PDO::FETCH_COLUMN) as $sid) {
            tt_bump_revision($db, (int)$sid); // every timetable must be validated again
        }
        tt_audit($db, null, null, 'periods',
            'Added Lunch ' . substr(LUNCH_START, 0, 5) . '–' . substr(LUNCH_END, 0, 5) . " after {$anchor['label']}; later periods moved one hour later",
            null, ['lunch_period' => $lunch, 'lessons_moved' => $moved, 'lessons_across_lunch' => $across],
            ['id' => null, 'name' => 'Migration 38', 'role' => 'system']);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }

    $log[] = "Lunch added as period $lunch (" . LUNCH_START . '–' . LUNCH_END . ") after {$anchor['label']}.";
    $log[] = "Lessons moved: $moved; availability rows moved: $avail; requirements updated: " . count($reqs) . '; snapshots updated: ' . count($versions);
    if ($across) {
        $log[] = count($across) . ' lesson(s) now run across lunch (ids ' . implode(', ', $across) . ') — validation will flag them to be moved.';
    }
    return ['log' => $log, 'across' => $across];
}

if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    try {
        $result = tt_add_lunch_break((new Database())->getConnection());
        echo implode(PHP_EOL, $result['log']) . PHP_EOL . 'Migration 38 complete.' . PHP_EOL;
    } catch (Throwable $e) {
        fwrite(STDERR, 'Migration 38 failed, nothing changed: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
}
