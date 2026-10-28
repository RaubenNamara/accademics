<?php
/**
 * Closed terms are read-only in every module.
 *
 * A term is closed when its academic_sessions row has is_archived = 1. The current term and a term
 * reopened under Terms & Years have is_archived = 0, so they stay editable; a term with no row yet is
 * not locked either. Call term_guard() once in an endpoint, after connecting: on any add, change or
 * delete it checks the term the request names and the term of the record being changed, and answers
 * 423 when either is closed.
 *
 *   term_guard($db, 'duty_performance');                                   rows with year + term
 *   term_guard($db, 'class_teacher_assignments', ['year' => 'academic_year']);
 *   term_guard($db, 'lesson_plans', ['session' => 'academic_session_id']); rows tied to a term by id
 *   term_guard($db, 'lesson_compensations', ['via' => ['teacher_lesson_attendance', 'lesson_monitoring_id']]);
 */
declare(strict_types=1);

/** The closed term [academic_year, term] matching a year+term or a session id, or null if editable. */
function term_closed(PDO $db, $year, $term, $sessionId = null): ?array
{
    try {
        if ($sessionId) {
            $q = $db->prepare('SELECT academic_year, term, is_archived FROM academic_sessions WHERE id = :id');
            $q->execute([':id' => (int)$sessionId]);
        } elseif ($year && $term) {
            $q = $db->prepare('SELECT academic_year, term, is_archived FROM academic_sessions WHERE academic_year = :y AND term = :t LIMIT 1');
            $q->execute([':y' => (int)$year, ':t' => (int)$term]);
        } else {
            return null;
        }
        $row = $q->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return null; // no terms table yet: nothing to lock
    }
    return $row && (int)$row['is_archived'] === 1 ? $row : null;
}

function term_deny(array $s): void
{
    http_response_code(423);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => "Term {$s['term']} {$s['academic_year']} is closed, so its records cannot be added, changed or deleted. Reopen it under Terms & Years to make changes.",
        'data' => ['term_closed' => true, 'academic_year' => (int)$s['academic_year'], 'term' => (int)$s['term']],
    ]);
    exit;
}

function term_guard(PDO $db, string $table, array $opt = []): void
{
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
        return;
    }
    $yearCol = $opt['year'] ?? 'year';
    $termCol = $opt['term'] ?? 'term';
    $sessionCol = $opt['session'] ?? null;
    $via = $opt['via'] ?? null; // [parent table, foreign key column] when the row's term sits on its parent

    static $body = null;
    if ($body === null) {
        $raw = file_get_contents('php://input') ?: '';
        $json = json_decode($raw, true);
        $body = (is_array($json) ? $json : []) + $_POST;
    }

    // 1) The term the request writes into.
    if ($via) {
        [$parent, $fk] = $via;
        if (!empty($body[$fk])) {
            term_guard_row($db, $parent, (int)$body[$fk], 'year', 'term', null);
        }
    } elseif ($sessionCol) {
        if (!empty($body[$sessionCol]) && ($s = term_closed($db, null, null, $body[$sessionCol]))) {
            term_deny($s);
        }
    } else {
        $year = $body[$yearCol] ?? $body['academic_year'] ?? $body['year'] ?? null;
        $term = $body[$termCol] ?? $body['term'] ?? null;
        if ($year && $term && ($s = term_closed($db, $year, $term))) {
            term_deny($s);
        }
    }

    // 2) The record being changed or deleted keeps the term it was saved in.
    $id = (int)($_GET['id'] ?? $body['id'] ?? 0);
    if (!$id) {
        return;
    }
    if ($via) {
        try {
            $q = $db->prepare("SELECT `{$via[1]}` FROM `$table` WHERE id = :id");
            $q->execute([':id' => $id]);
            $parentId = (int)$q->fetchColumn();
        } catch (Throwable $e) {
            return;
        }
        if ($parentId) {
            term_guard_row($db, $via[0], $parentId, 'year', 'term', null);
        }
        return;
    }
    term_guard_row($db, $table, $id, $yearCol, $termCol, $sessionCol);
}

function term_guard_row(PDO $db, string $table, int $id, string $yearCol, string $termCol, ?string $sessionCol): void
{
    try {
        $cols = $sessionCol ? "`$sessionCol` AS sid" : "`$yearCol` AS y, `$termCol` AS t";
        $q = $db->prepare("SELECT $cols FROM `$table` WHERE id = :id");
        $q->execute([':id' => $id]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return;
    }
    if (!$row) {
        return;
    }
    $s = $sessionCol ? term_closed($db, null, null, $row['sid']) : term_closed($db, $row['y'], $row['t']);
    if ($s) {
        term_deny($s);
    }
}
