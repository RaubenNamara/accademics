<?php
/**
 * Curriculum setup: per subject + class level (S.1–S.6) + term, the themes, topics, competences,
 * learning outcomes and suggested teaching weeks that teachers pick from when writing lesson plans.
 * The first content comes from eSpace's eNotes curriculum (backend/data/curriculum_seed.json).
 */
declare(strict_types=1);

require_once __DIR__ . '/SchoolService.php';

const CU_SEED_FILE = __DIR__ . '/../data/curriculum_seed.json';
const CU_TERM_WEEKS = 12; // weeks topics are spread over when no week is given

/** Third person singular of a verb: install → installs, apply → applies, discuss → discusses. */
function cu_third_person(string $verb): string
{
    $fixes = ['interprete' => 'interprets', 'distinguishe' => 'distinguishes', 'establishe' => 'establishes', 'have' => 'has', 'be' => 'is'];
    $lower = strtolower($verb);
    if (isset($fixes[$lower])) {
        return $fixes[$lower];
    }
    // Already "-s" form (understands, analyses) but not a base ending in -ss/-us (discuss, focus).
    if (preg_match('/[^su]s$/', $lower)) {
        return $verb;
    }
    if (preg_match('/[^aeiou]y$/', $lower)) {
        return substr($verb, 0, -1) . 'ies';
    }
    if (preg_match('/(s|sh|ch|x|z|o)$/', $lower)) {
        return $verb . 'es';
    }
    return $verb . 's';
}

/**
 * Competences read "The learner …": "install and maintain software" → "The learner installs and
 * maintains software"; "should be able to …" → "The learner should be able to …". Text that already
 * starts with "The learner" or "Learners" is left as it is.
 */
function cu_learner_sentence(?string $text): string
{
    $t = trim((string)$text);
    if ($t === '' || preg_match('/^(the\s+learners?|learners?)\b/i', $t)) {
        return $t;
    }
    $t = lcfirst($t);
    if (preg_match('/^(should|can|will|must|may|is|are|has)\b/i', $t)) {
        return "The learner $t";
    }
    // The leading verbs: "talk", "talk and write", "critique, write and present".
    if (preg_match('/^([a-z]+(?:,\s*[a-z]+)*(?:,?\s+(?:and|or)\s+[a-z]+)?)(\b.*)$/s', $t, $m)) {
        $lead = preg_replace_callback('/[a-z]+/', static fn($w) => in_array($w[0], ['and', 'or'], true) ? $w[0] : cu_third_person($w[0]), $m[1]);
        // Only the first word is surely a verb; a coordinated word joins in when it is joined by and/or/commas.
        if (!preg_match('/(,|\s(and|or)\s)/', $m[1])) {
            $lead = cu_third_person($m[1]);
        }
        return 'The learner ' . $lead . $m[2];
    }
    return "The learner $t";
}

function cu_ensure_schema(PDO $db): void
{
    static $done = false;
    if ($done) {
        return;
    }
    sf_ensure_schema($db);
    if (!tt_table_exists($db, 'curriculum_outcomes') || !tt_column_exists($db, 'lesson_plans', 'curriculum_topic_id')) {
        cu_run_migration($db);
    }
    $done = true;
}

function cu_run_migration(PDO $db, ?callable $log = null): void
{
    $log = $log ?? static function (string $m): void {};
    $db->exec("CREATE TABLE IF NOT EXISTS curriculum_topics (
        id INT AUTO_INCREMENT PRIMARY KEY,
        subject_id INT NOT NULL,
        class_level TINYINT NOT NULL,
        term TINYINT NOT NULL,
        theme VARCHAR(255) NOT NULL,
        topic VARCHAR(255) NOT NULL,
        competence TEXT NULL,
        week_from TINYINT NULL,
        week_to TINYINT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        source VARCHAR(30) NULL,
        source_id INT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_cu_lookup (subject_id, class_level, term, sort_order),
        CONSTRAINT fk_cu_subject FOREIGN KEY (subject_id) REFERENCES subjects_new (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    $db->exec("CREATE TABLE IF NOT EXISTS curriculum_outcomes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        topic_id INT NOT NULL,
        outcome TEXT NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        KEY idx_cuo_topic (topic_id, sort_order),
        CONSTRAINT fk_cuo_topic FOREIGN KEY (topic_id) REFERENCES curriculum_topics (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    if (!tt_column_exists($db, 'lesson_plans', 'curriculum_topic_id')) {
        $db->exec('ALTER TABLE lesson_plans ADD COLUMN curriculum_topic_id INT NULL, ADD KEY idx_plans_curriculum (curriculum_topic_id)');
        $log('lesson_plans.curriculum_topic_id added');
    }
    $log('curriculum_topics and curriculum_outcomes ready');
}

/**
 * Loads the seed file. Topics already present (same subject, level, term, theme and topic) are left
 * alone, so it is safe to run again. Weeks are suggested by spreading each term's topics, in
 * curriculum order, over CU_TERM_WEEKS weeks.
 */
function cu_import_seed(PDO $db, string $file = CU_SEED_FILE): array
{
    cu_ensure_schema($db);
    $seed = json_decode((string)@file_get_contents($file), true);
    if (!is_array($seed) || !isset($seed['topics'])) {
        throw new TtException('The curriculum seed file is missing or unreadable', 500);
    }
    $codes = array_column($db->query('SELECT id, LOWER(TRIM(subject_code)) AS code FROM subjects_new')->fetchAll(PDO::FETCH_ASSOC), 'id', 'code');
    $groups = [];
    $missing = [];
    foreach ($seed['topics'] as $t) {
        $sid = $codes[strtolower(trim((string)$t['subject_code']))] ?? null;
        if (!$sid) {
            $missing[$t['subject_code']] = true;
            continue;
        }
        $groups["$sid|{$t['class_level']}|{$t['term']}"][] = $t + ['subject_id' => (int)$sid];
    }
    $exists = $db->prepare('SELECT id FROM curriculum_topics WHERE subject_id = :s AND class_level = :l AND term = :t AND LOWER(theme) = LOWER(:th) AND LOWER(topic) = LOWER(:tp) LIMIT 1');
    $insert = $db->prepare('INSERT INTO curriculum_topics (subject_id, class_level, term, theme, topic, competence, week_from, week_to, sort_order, source, source_id)
        VALUES (:s, :l, :t, :th, :tp, :c, :wf, :wt, :o, \'espace\', :src)');
    $outcome = $db->prepare('INSERT INTO curriculum_outcomes (topic_id, outcome, sort_order) VALUES (:t, :o, :n)');
    $added = 0;
    $outcomes = 0;
    $db->beginTransaction();
    try {
        foreach ($groups as $list) {
            usort($list, fn($a, $b) => $a['order'] <=> $b['order']);
            $n = count($list);
            foreach ($list as $i => $t) {
                $exists->execute([':s' => $t['subject_id'], ':l' => $t['class_level'], ':t' => $t['term'], ':th' => $t['theme'], ':tp' => $t['topic']]);
                if ($exists->fetchColumn()) {
                    continue;
                }
                $from = intdiv($i * CU_TERM_WEEKS, $n) + 1;
                $to = max($from, intdiv(($i + 1) * CU_TERM_WEEKS, $n));
                $insert->execute([':s' => $t['subject_id'], ':l' => $t['class_level'], ':t' => $t['term'], ':th' => mb_substr($t['theme'], 0, 255),
                    ':tp' => mb_substr($t['topic'], 0, 255), ':c' => cu_learner_sentence($t['competence'] ?? '') ?: null, ':wf' => $from, ':wt' => $to, ':o' => ($i + 1) * 10, ':src' => $t['source_id'] ?? null]);
                $topicId = (int)$db->lastInsertId();
                foreach (array_values($t['outcomes'] ?? []) as $k => $o) {
                    $outcome->execute([':t' => $topicId, ':o' => $o, ':n' => $k + 1]);
                    $outcomes++;
                }
                $added++;
            }
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
    return ['topics_added' => $added, 'outcomes_added' => $outcomes, 'unknown_subjects' => array_keys($missing)];
}

/** Topics with their outcomes. Filters: subject_id, class_level, term, q (search). */
/** Rewrites stored competences as "The learner …" (already-converted ones are unchanged). */
function cu_normalise_competences(PDO $db): int
{
    $up = $db->prepare('UPDATE curriculum_topics SET competence = :c WHERE id = :id');
    $n = 0;
    foreach ($db->query("SELECT id, competence FROM curriculum_topics WHERE competence IS NOT NULL AND competence <> ''")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $new = cu_learner_sentence($r['competence']);
        if ($new !== $r['competence']) {
            $up->execute([':c' => $new, ':id' => $r['id']]);
            $n++;
        }
    }
    return $n;
}

function cu_topics(PDO $db, array $f): array
{
    $where = ['1 = 1'];
    $params = [];
    foreach (['subject_id' => 't.subject_id', 'class_level' => 't.class_level', 'term' => 't.term', 'id' => 't.id'] as $k => $col) {
        if (isset($f[$k]) && $f[$k] !== '' && $f[$k] !== null) {
            $where[] = "$col = :$k";
            $params[":$k"] = (int)$f[$k];
        }
    }
    if (!empty($f['q'])) {
        $where[] = '(t.topic LIKE :q OR t.theme LIKE :q2 OR t.competence LIKE :q3)';
        $params += [':q' => "%{$f['q']}%", ':q2' => "%{$f['q']}%", ':q3' => "%{$f['q']}%"];
    }
    $stmt = $db->prepare('SELECT t.*, s.subject_name, s.subject_code FROM curriculum_topics t JOIN subjects_new s ON s.id = t.subject_id
        WHERE ' . implode(' AND ', $where) . ' ORDER BY s.subject_name, t.class_level, t.term, t.sort_order, t.id LIMIT 2000');
    $stmt->execute($params);
    $topics = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$topics) {
        return [];
    }
    $ids = implode(',', array_map(fn($t) => (int)$t['id'], $topics));
    $byTopic = [];
    foreach ($db->query("SELECT topic_id, outcome FROM curriculum_outcomes WHERE topic_id IN ($ids) ORDER BY topic_id, sort_order, id")->fetchAll(PDO::FETCH_ASSOC) as $o) {
        $byTopic[(int)$o['topic_id']][] = $o['outcome'];
    }
    return array_map(static function (array $t) use ($byTopic): array {
        foreach (['id', 'subject_id', 'class_level', 'term', 'sort_order'] as $k) {
            $t[$k] = (int)$t[$k];
        }
        $t['week_from'] = $t['week_from'] !== null ? (int)$t['week_from'] : null;
        $t['week_to'] = $t['week_to'] !== null ? (int)$t['week_to'] : null;
        $t['outcomes'] = $byTopic[$t['id']] ?? [];
        return $t;
    }, $topics);
}

function cu_save_topic(PDO $db, array $d): array
{
    $id = (int)($d['id'] ?? 0);
    $errors = [];
    $subjectId = (int)($d['subject_id'] ?? 0);
    $level = (int)($d['class_level'] ?? 0);
    $term = (int)($d['term'] ?? 0);
    $theme = mb_substr(trim((string)($d['theme'] ?? '')), 0, 255);
    $topic = mb_substr(trim((string)($d['topic'] ?? '')), 0, 255);
    $competence = cu_learner_sentence($d['competence'] ?? '');
    $wf = $d['week_from'] ?? null;
    $wt = $d['week_to'] ?? null;
    if (!$subjectId || !$db->query("SELECT COUNT(*) FROM subjects_new WHERE id = $subjectId")->fetchColumn()) {
        $errors['subject_id'] = 'Choose the subject';
    }
    if ($level < 1 || $level > 6) {
        $errors['class_level'] = 'Choose the class (S.1–S.6)';
    }
    if ($term < 1 || $term > 3) {
        $errors['term'] = 'Choose the term';
    }
    if ($theme === '') {
        $errors['theme'] = 'Enter the theme / chapter';
    }
    if ($topic === '') {
        $errors['topic'] = 'Enter the topic';
    }
    $wf = ($wf === '' || $wf === null) ? null : (int)$wf;
    $wt = ($wt === '' || $wt === null) ? $wf : (int)$wt;
    if (($wf !== null && ($wf < 1 || $wf > 20)) || ($wt !== null && ($wt < 1 || $wt > 20)) || ($wf !== null && $wt !== null && $wt < $wf)) {
        $errors['week_from'] = 'Weeks run from 1 to 20, and the last week cannot be before the first';
    }
    $outcomes = array_values(array_filter(array_map(fn($o) => mb_substr(trim((string)$o), 0, 2000), (array)($d['outcomes'] ?? [])), fn($o) => $o !== ''));
    if ($errors) {
        throw new TtException((string)reset($errors), 422, ['errors' => $errors]);
    }
    $db->beginTransaction();
    try {
        $values = [':s' => $subjectId, ':l' => $level, ':t' => $term, ':th' => $theme, ':tp' => $topic, ':c' => $competence ?: null, ':wf' => $wf, ':wt' => $wt];
        if ($id) {
            $stmt = $db->prepare('UPDATE curriculum_topics SET subject_id = :s, class_level = :l, term = :t, theme = :th, topic = :tp, competence = :c, week_from = :wf, week_to = :wt WHERE id = :id');
            $stmt->execute($values + [':id' => $id]);
            if (!$db->query("SELECT COUNT(*) FROM curriculum_topics WHERE id = $id")->fetchColumn()) {
                throw new TtException('Topic not found', 404);
            }
        } else {
            $next = (int)$db->query("SELECT COALESCE(MAX(sort_order), 0) + 10 FROM curriculum_topics WHERE subject_id = $subjectId AND class_level = $level AND term = $term")->fetchColumn();
            $db->prepare('INSERT INTO curriculum_topics (subject_id, class_level, term, theme, topic, competence, week_from, week_to, sort_order, source)
                VALUES (:s, :l, :t, :th, :tp, :c, :wf, :wt, :o, \'manual\')')->execute($values + [':o' => $next]);
            $id = (int)$db->lastInsertId();
        }
        $db->prepare('DELETE FROM curriculum_outcomes WHERE topic_id = :t')->execute([':t' => $id]);
        $ins = $db->prepare('INSERT INTO curriculum_outcomes (topic_id, outcome, sort_order) VALUES (:t, :o, :n)');
        foreach ($outcomes as $i => $o) {
            $ins->execute([':t' => $id, ':o' => $o, ':n' => $i + 1]);
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
    return cu_topics($db, ['id' => $id])[0];
}
