<?php
declare(strict_types=1);

/**
 * Skool Monitor: teacher portal, lesson plans, examinations (incl. AOI), marks entry,
 * report settings and report cards. Shared by the related API endpoints.
 *
 * Schema changes are additive (see sf_ensure_schema / migration 39) and never touch
 * existing rows: new tables, plus users.teacher_id / must_change_password and the
 * 'subject_teacher' role (a classroom teacher using the teacher portal).
 */

require_once __DIR__ . '/HrHelpers.php';
require_once __DIR__ . '/TimetableService.php';

const SF_TEACHER_ROLE = 'subject_teacher';
const SF_ACADEMIC_ROLES = ['teacher', 'academic_office']; // + admin, super_admin, hr_manager via hr_require_auth
/** Leave types a teacher can request (same list as HR leave). */
const SF_LEAVE_TYPES = ['annual', 'sick', 'maternity', 'paternity', 'compassionate', 'unpaid', 'other'];
const SF_EXAM_TYPES = ['AOI', 'BOT', 'MOT', 'EOT', 'OTHER'];
const SF_DEFAULT_SCALE = [
    ['min' => 80, 'grade' => 'A', 'comment' => 'Exceptional'],
    ['min' => 70, 'grade' => 'B', 'comment' => 'Outstanding'],
    ['min' => 60, 'grade' => 'C', 'comment' => 'Satisfactory'],
    ['min' => 50, 'grade' => 'D', 'comment' => 'Basic'],
    ['min' => 0, 'grade' => 'E', 'comment' => 'Elementary'],
];

/** Primary schools grade D1 to F9 (no AOI descriptors such as "Exceptional"). */
const SF_PRIMARY_SCALE = [
    ['min' => 90, 'grade' => 'D1', 'comment' => 'Distinction'],
    ['min' => 80, 'grade' => 'D2', 'comment' => 'Distinction'],
    ['min' => 70, 'grade' => 'C3', 'comment' => 'Credit'],
    ['min' => 60, 'grade' => 'C4', 'comment' => 'Credit'],
    ['min' => 55, 'grade' => 'C5', 'comment' => 'Credit'],
    ['min' => 50, 'grade' => 'C6', 'comment' => 'Credit'],
    ['min' => 45, 'grade' => 'P7', 'comment' => 'Pass'],
    ['min' => 40, 'grade' => 'P8', 'comment' => 'Pass'],
    ['min' => 0, 'grade' => 'F9', 'comment' => 'Fail'],
];
const SF_SCHOOL_TYPES = ['secondary', 'primary'];

/* ------------------------------------------------------------------ */
/* Schema                                                              */
/* ------------------------------------------------------------------ */

function sf_ensure_schema(PDO $db): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $ready = tt_table_exists($db, 'exam_marks') && tt_table_exists($db, 'lesson_plans') && tt_table_exists($db, 'report_settings')
        && tt_column_exists($db, 'users', 'teacher_id') && tt_column_exists($db, 'lesson_plans', 'procedure_json') && tt_column_exists($db, 'lesson_plans', 'content')
        && tt_column_exists($db, 'lesson_plans', 'staff_seen_at') && tt_column_exists($db, 'report_settings', 'exam_basis') && tt_column_exists($db, 'report_settings', 'school_type');
    if (!$ready) {
        sf_run_migration($db);
    }
    $done = true;
    ac_ensure_schema($db); // student status / subjects / promotions (CalendarService)
}

function sf_run_migration(PDO $db, ?callable $log = null): void
{
    $log = $log ?? static function (string $m): void {};

    if (!tt_column_exists($db, 'users', 'teacher_id')) {
        $db->exec('ALTER TABLE users ADD COLUMN teacher_id INT NULL, ADD UNIQUE KEY uq_users_teacher (teacher_id)');
        $log('users.teacher_id added');
    }
    if (!tt_column_exists($db, 'users', 'must_change_password')) {
        $db->exec('ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0');
        $log('users.must_change_password added');
    }
    $type = (string)$db->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'role'")->fetchColumn();
    if ($type !== '' && strpos($type, "'" . SF_TEACHER_ROLE . "'") === false) {
        $values = substr($type, 5, -1); // inside enum( … )
        $db->exec("ALTER TABLE users MODIFY COLUMN role ENUM($values,'" . SF_TEACHER_ROLE . "') NOT NULL DEFAULT 'admin'");
        $log('users.role allows ' . SF_TEACHER_ROLE);
    }

    $db->exec("CREATE TABLE IF NOT EXISTS report_settings (
        id TINYINT NOT NULL PRIMARY KEY DEFAULT 1,
        school_name VARCHAR(255) NULL,
        motto VARCHAR(255) NULL,
        address VARCHAR(500) NULL,
        phone VARCHAR(100) NULL,
        email VARCHAR(255) NULL,
        website VARCHAR(255) NULL,
        logo LONGTEXT NULL,
        head_teacher VARCHAR(255) NULL,
        next_term_begins VARCHAR(100) NULL,
        footer_note VARCHAR(500) NULL,
        grade_scale LONGTEXT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS exams (
        id INT AUTO_INCREMENT PRIMARY KEY,
        academic_session_id INT NOT NULL,
        name VARCHAR(150) NOT NULL,
        exam_type ENUM('AOI','BOT','MOT','EOT','OTHER') NOT NULL DEFAULT 'EOT',
        max_marks DECIMAL(6,2) NOT NULL DEFAULT 100,
        weight DECIMAL(5,2) NOT NULL DEFAULT 100,
        class_level ENUM('all','O-Level','A-Level') NOT NULL DEFAULT 'all',
        status ENUM('draft','open','closed') NOT NULL DEFAULT 'draft',
        notes VARCHAR(500) NULL,
        created_by INT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_exams_session (academic_session_id),
        CONSTRAINT fk_exams_session FOREIGN KEY (academic_session_id) REFERENCES academic_sessions (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS exam_marks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        exam_id INT NOT NULL,
        student_id INT NOT NULL,
        subject_id INT NOT NULL,
        class_id INT NOT NULL,
        marks DECIMAL(6,2) NULL,
        comment VARCHAR(255) NULL,
        entered_by INT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_exam_mark (exam_id, student_id, subject_id),
        KEY idx_mark_sheet (exam_id, class_id, subject_id),
        KEY idx_mark_student (student_id),
        CONSTRAINT fk_mark_exam FOREIGN KEY (exam_id) REFERENCES exams (id) ON DELETE CASCADE,
        CONSTRAINT fk_mark_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
        CONSTRAINT fk_mark_subject FOREIGN KEY (subject_id) REFERENCES subjects_new (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS lesson_plans (
        id INT AUTO_INCREMENT PRIMARY KEY,
        teacher_id INT NOT NULL,
        academic_session_id INT NULL,
        class_id INT NOT NULL,
        subject_id INT NOT NULL,
        lesson_date DATE NULL,
        duration_minutes INT NULL,
        topic VARCHAR(255) NOT NULL,
        subtopic VARCHAR(255) NULL,
        competences TEXT NULL,
        objectives TEXT NULL,
        materials TEXT NULL,
        introduction TEXT NULL,
        development TEXT NULL,
        conclusion TEXT NULL,
        assessment TEXT NULL,
        reflection TEXT NULL,
        status ENUM('draft','submitted','approved','returned') NOT NULL DEFAULT 'draft',
        review_comment VARCHAR(1000) NULL,
        reviewed_by VARCHAR(255) NULL,
        reviewed_at DATETIME NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_plans_teacher (teacher_id, lesson_date),
        KEY idx_plans_status (status),
        CONSTRAINT fk_plan_teacher FOREIGN KEY (teacher_id) REFERENCES teachers (id) ON DELETE CASCADE,
        CONSTRAINT fk_plan_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE CASCADE,
        CONSTRAINT fk_plan_subject FOREIGN KEY (subject_id) REFERENCES subjects_new (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    $log('report_settings, exams, exam_marks and lesson_plans ready');

    // Lesson plan template (NCDC competency-based): header details, sections and the procedure table.
    $planColumns = [
        'week_number' => 'TINYINT NULL', 'start_time' => 'TIME NULL', 'lesson_span' => 'TINYINT NULL', 'learners' => 'INT NULL',
        'theme' => 'VARCHAR(255) NULL', 'generic_skills' => 'TEXT NULL', 'values_text' => 'TEXT NULL', 'cross_cutting' => 'TEXT NULL',
        'prerequisite' => 'TEXT NULL', 'references_text' => 'TEXT NULL', 'procedure_json' => 'LONGTEXT NULL',
        'content' => 'MEDIUMTEXT NULL',
        // Review queue: when it was last submitted, and when a reviewer first opened it since (the department's "new" badge).
        'submitted_at' => 'DATETIME NULL', 'staff_seen_at' => 'DATETIME NULL',
    ];
    // Where the 80 comes from: mid term only, end of term only, or both averaged (set on Examinations).
    if (!tt_column_exists($db, 'report_settings', 'exam_basis')) {
        $db->exec("ALTER TABLE report_settings ADD COLUMN exam_basis VARCHAR(10) NOT NULL DEFAULT 'both'");
        $log('report_settings.exam_basis added');
    }
    // Secondary (AOIs + exams) or primary (exams only, D1 to F9).
    if (!tt_column_exists($db, 'report_settings', 'school_type')) {
        $db->exec("ALTER TABLE report_settings ADD COLUMN school_type VARCHAR(10) NOT NULL DEFAULT 'secondary'");
        $log('report_settings.school_type added');
    }
    foreach ($planColumns as $col => $type) {
        if (!tt_column_exists($db, 'lesson_plans', $col)) {
            $db->exec("ALTER TABLE lesson_plans ADD COLUMN $col $type");
            $log("lesson_plans.$col added");
            if ($col === 'staff_seen_at') {
                // Plans already in the system: submitted when last changed; anything already reviewed counts as seen.
                $db->exec("UPDATE lesson_plans SET submitted_at = updated_at WHERE status <> 'draft' AND submitted_at IS NULL");
                $db->exec("UPDATE lesson_plans SET staff_seen_at = COALESCE(reviewed_at, updated_at) WHERE status IN ('approved', 'returned')");
            }
        }
    }
}

/* ------------------------------------------------------------------ */
/* Who is calling                                                       */
/* ------------------------------------------------------------------ */

/** The signed-in classroom teacher's teachers row (403 for anyone else). */
function sf_require_teacher(PDO $db): array
{
    $payload = hr_require_auth([SF_TEACHER_ROLE]);
    $stmt = $db->prepare('SELECT t.* FROM users u JOIN teachers t ON t.id = u.teacher_id WHERE u.id = :id AND u.is_active = 1');
    $stmt->execute([':id' => (int)($payload['id'] ?? 0)]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$teacher) {
        hr_respond(false, 'This account is not linked to a teacher record. Ask the administrator.', null, 403);
    }
    return ['payload' => $payload, 'teacher' => $teacher];
}

function sf_is_staff_role(string $role): bool
{
    return in_array($role, ['admin', 'super_admin', 'hr_manager', 'teacher', 'academic_office'], true);
}

/**
 * Class/subject pairs a teacher teaches in a term: from Lesson Requirements and the timetable.
 * Returns [ ['class_id','subject_id','full_class_name','subject_name'], … ].
 */
function sf_teacher_assignments(PDO $db, int $teacherId, int $sessionId): array
{
    $stmt = $db->prepare('SELECT x.class_id, x.subject_id, c.full_class_name, c.class_name, c.stream_name, s.subject_name, s.subject_code
        FROM (
            SELECT class_id, subject_id FROM lesson_requirements WHERE academic_session_id = :s1 AND teacher_id = :t1
            UNION
            SELECT t.class_id, t.subject_id FROM timetable t JOIN academic_sessions a ON a.academic_year = t.academic_year AND a.term = t.term
            WHERE a.id = :s2 AND t.teacher_id = :t2 AND t.entry_type = \'lesson\' AND t.subject_id IS NOT NULL
        ) x
        JOIN classes c ON c.id = x.class_id
        JOIN subjects_new s ON s.id = x.subject_id
        ORDER BY c.class_name, c.stream_name, s.subject_name');
    $stmt->execute([':s1' => $sessionId, ':t1' => $teacherId, ':s2' => $sessionId, ':t2' => $teacherId]);
    return array_map(static function ($r) {
        $r['class_id'] = (int)$r['class_id'];
        $r['subject_id'] = (int)$r['subject_id'];
        $r['level'] = tt_class_level((string)$r['class_name']);
        return $r;
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

/**
 * Class/subject pairs a teacher may enter marks for and plan lessons for: the classes the
 * academic office assigned to them this term (Teaching Assignments), plus any timetabled ones.
 */
function sf_teacher_mark_scope(PDO $db, int $teacherId, int $sessionId): array
{
    $scope = array_map(fn($a) => $a + ['source' => 'assigned'], sf_teacher_assignments($db, $teacherId, $sessionId));
    usort($scope, fn($a, $b) => [$a['subject_name'], $a['class_name'], $a['stream_name']] <=> [$b['subject_name'], $b['class_name'], $b['stream_name']]);
    return $scope;
}

/**
 * How far a teacher has got with marks for each open exam of the term, per subject:
 * the classes they may mark, how many are complete or started, and the class to continue with.
 */
function sf_teacher_marks_progress(PDO $db, int $teacherId, int $sessionId): array
{
    $exams = $db->prepare("SELECT id, name, exam_type, max_marks, class_level FROM exams WHERE academic_session_id = :s AND status = 'open'
        ORDER BY FIELD(exam_type,'BOT','AOI','MOT','EOT','OTHER'), id");
    $exams->execute([':s' => $sessionId]);
    $exams = $exams->fetchAll(PDO::FETCH_ASSOC);
    $scope = sf_teacher_mark_scope($db, $teacherId, $sessionId);
    if (!$exams || !$scope) {
        return [];
    }

    // Learners per class and marks entered per exam/class/subject, in two queries.
    $sizes = [];
    foreach (array_unique(array_column($scope, 'class_id')) as $classId) {
        $sizes[$classId] = count(sf_class_students($db, (int)$classId));
    }
    $subjectIds = implode(',', array_map('intval', array_unique(array_column($scope, 'subject_id'))));
    $examIds = implode(',', array_map(fn($e) => (int)$e['id'], $exams));
    $entered = [];
    foreach ($db->query("SELECT exam_id, class_id, subject_id, COUNT(*) n FROM exam_marks
        WHERE exam_id IN ($examIds) AND subject_id IN ($subjectIds) AND marks IS NOT NULL GROUP BY exam_id, class_id, subject_id") as $r) {
        $entered["{$r['exam_id']}-{$r['class_id']}-{$r['subject_id']}"] = (int)$r['n'];
    }

    $out = [];
    foreach ($exams as $e) {
        $subjects = [];
        foreach ($scope as $a) {
            if (!sf_exam_applies($e, $a['level']) || !($sizes[$a['class_id']] ?? 0)) {
                continue;
            }
            $sub = &$subjects[$a['subject_id']];
            $sub ??= ['subject_id' => $a['subject_id'], 'subject_name' => $a['subject_name'], 'classes' => 0, 'complete' => 0, 'started' => 0, 'next' => null];
            $n = $entered["{$e['id']}-{$a['class_id']}-{$a['subject_id']}"] ?? 0;
            $size = $sizes[$a['class_id']];
            $sub['classes']++;
            if ($n >= $size) {
                $sub['complete']++;
            } elseif ($n > 0) {
                $sub['started']++;
                // Continue where the teacher left off: the first class they started but did not finish.
                $sub['next'] ??= ['class_id' => $a['class_id'], 'full_class_name' => $a['full_class_name'], 'entered' => $n, 'students' => $size];
            }
            unset($sub);
        }
        if ($subjects) {
            $out[] = ['exam_id' => (int)$e['id'], 'name' => $e['name'], 'exam_type' => $e['exam_type'], 'max_marks' => (float)$e['max_marks'],
                'out_of' => sf_component_out_of($e['exam_type'], sf_is_primary($db)), 'subjects' => array_values($subjects)];
        }
    }
    return $out;
}

function sf_teaches(PDO $db, int $teacherId, int $sessionId, int $classId, int $subjectId): bool
{
    foreach (sf_teacher_mark_scope($db, $teacherId, $sessionId) as $a) {
        if ($a['class_id'] === $classId && $a['subject_id'] === $subjectId) {
            return true;
        }
    }
    return false;
}

/** Students of a class stream (classes row → students.class / students.stream). */
function sf_class_students(PDO $db, int $classId): array
{
    $stmt = $db->prepare('SELECT s.id, s.admission_number, s.full_name, s.gender, s.lin, s.profile_picture
        FROM students s JOIN classes c ON TRIM(s.class) COLLATE utf8mb4_unicode_ci = TRIM(c.class_name) COLLATE utf8mb4_unicode_ci AND TRIM(s.stream) COLLATE utf8mb4_unicode_ci = TRIM(c.stream_name) COLLATE utf8mb4_unicode_ci
        WHERE c.id = :c AND (s.is_active = 1 OR s.is_active IS NULL) AND s.status = \'active\'
        ORDER BY s.full_name');
    $stmt->execute([':c' => $classId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function sf_class(PDO $db, int $classId): ?array
{
    $stmt = $db->prepare('SELECT id, class_name, stream_name, full_class_name FROM classes WHERE id = :id');
    $stmt->execute([':id' => $classId]);
    $c = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($c) {
        $c['level'] = tt_class_level((string)$c['class_name']);
    }
    return $c ?: null;
}

/* ------------------------------------------------------------------ */
/* Report settings + grading                                           */
/* ------------------------------------------------------------------ */

function sf_settings(PDO $db): array
{
    $row = $db->query('SELECT * FROM report_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC) ?: [];
    $scale = json_decode((string)($row['grade_scale'] ?? ''), true);
    $type = in_array($row['school_type'] ?? '', SF_SCHOOL_TYPES, true) ? $row['school_type'] : 'secondary';
    return [
        'school_name' => $row['school_name'] ?? "St Mark's College Namagoma",
        'motto' => $row['motto'] ?? null,
        'address' => $row['address'] ?? null,
        'phone' => $row['phone'] ?? null,
        'email' => $row['email'] ?? null,
        'website' => $row['website'] ?? null,
        'logo' => $row['logo'] ?? null,
        'head_teacher' => $row['head_teacher'] ?? null,
        'next_term_begins' => $row['next_term_begins'] ?? null,
        'footer_note' => $row['footer_note'] ?? null,
        'grade_scale' => is_array($scale) && $scale ? $scale : ($type === 'primary' ? SF_PRIMARY_SCALE : SF_DEFAULT_SCALE),
        'exam_basis' => in_array($row['exam_basis'] ?? '', SF_EXAM_BASES, true) ? $row['exam_basis'] : 'both',
        'school_type' => $type,
    ];
}

/** Where the exam part (80) comes from: the mid term, the end of term, or both averaged. */
const SF_EXAM_BASES = ['MOT', 'EOT', 'both'];

function sf_exam_basis(PDO $db): string
{
    $v = (string)$db->query('SELECT exam_basis FROM report_settings WHERE id = 1')->fetchColumn();
    return in_array($v, SF_EXAM_BASES, true) ? $v : 'both';
}

function sf_save_exam_basis(PDO $db, string $basis): string
{
    if (!in_array($basis, SF_EXAM_BASES, true)) {
        throw new TtException('Choose mid term, end of term or both', 422);
    }
    $db->prepare('INSERT INTO report_settings (id, exam_basis) VALUES (1, :b) ON DUPLICATE KEY UPDATE exam_basis = VALUES(exam_basis)')->execute([':b' => $basis]);
    return $basis;
}

function sf_is_primary(PDO $db): bool
{
    return (string)$db->query('SELECT school_type FROM report_settings WHERE id = 1')->fetchColumn() === 'primary';
}

/** Whether an exam type counts towards the final mark (secondary AOIs always do, towards the 20; primary has no AOIs). */
function sf_counts_to_final(string $examType, string $basis, bool $primary = false): bool
{
    if ($examType === 'AOI') {
        return !$primary;
    }
    return $basis === 'both' ? in_array($examType, ['MOT', 'EOT'], true) : $examType === $basis;
}

/** The marking scheme as the screens show it. Primary: the mid term / end of term make the whole 100. */
function sf_scheme(PDO $db): array
{
    $primary = sf_is_primary($db);
    return ['school_type' => $primary ? 'primary' : 'secondary', 'aoi_each_out_of' => SF_AOI_EACH_OUT_OF, 'aoi_out_of' => $primary ? 0 : SF_AOI_OUT_OF,
        'aoi_best' => SF_AOI_BEST, 'exam_out_of' => $primary ? 100 : SF_EXAM_OUT_OF, 'exam_basis' => sf_exam_basis($db)];
}

/** Validates and stores settings. The logo must be a PNG/JPEG data URL under 600 KB. */
function sf_save_settings(PDO $db, array $data): array
{
    $errors = [];
    $text = static fn($k, $max) => mb_substr(trim((string)($data[$k] ?? '')), 0, $max) ?: null;
    $values = [
        'school_name' => $text('school_name', 255),
        'motto' => $text('motto', 255),
        'address' => $text('address', 500),
        'phone' => $text('phone', 100),
        'email' => $text('email', 255),
        'website' => $text('website', 255),
        'head_teacher' => $text('head_teacher', 255),
        'next_term_begins' => $text('next_term_begins', 100),
        'footer_note' => $text('footer_note', 500),
    ];
    if (!$values['school_name']) {
        $errors['school_name'] = 'Enter the school name';
    }
    if ($values['email'] && !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address';
    }

    $logo = $data['logo'] ?? null;
    if ($logo !== null && $logo !== '') {
        if (!preg_match('#^data:image/(png|jpeg|jpg);base64,([A-Za-z0-9+/=]+)$#', (string)$logo, $m)) {
            $errors['logo'] = 'The logo must be a PNG or JPG image';
        } elseif (strlen(base64_decode($m[2], true) ?: '') > 600 * 1024) {
            $errors['logo'] = 'The logo must be smaller than 600 KB';
        } elseif (!@getimagesizefromstring((string)base64_decode($m[2], true))) {
            $errors['logo'] = 'That file is not a readable image';
        }
    }

    $scale = $data['grade_scale'] ?? SF_DEFAULT_SCALE;
    if (!is_array($scale) || !$scale) {
        $errors['grade_scale'] = 'Add at least one grade';
    } else {
        $clean = [];
        foreach ($scale as $i => $band) {
            $min = $band['min'] ?? null;
            $grade = trim((string)($band['grade'] ?? ''));
            if (!is_numeric($min) || $min < 0 || $min > 100 || $grade === '') {
                $errors['grade_scale'] = 'Row ' . ($i + 1) . ': give a grade and a lowest mark between 0 and 100';
                break;
            }
            $clean[] = ['min' => (float)$min, 'grade' => mb_substr($grade, 0, 5), 'comment' => mb_substr(trim((string)($band['comment'] ?? '')), 0, 120)];
        }
        if (!isset($errors['grade_scale'])) {
            usort($clean, fn($a, $b) => $b['min'] <=> $a['min']);
            $mins = array_column($clean, 'min');
            if (count($mins) !== count(array_unique($mins))) {
                $errors['grade_scale'] = 'Two grades start at the same mark';
            } elseif (end($clean)['min'] > 0) {
                $errors['grade_scale'] = 'The lowest grade must start at 0';
            }
            $scale = $clean;
        }
    }
    $type = (string)($data['school_type'] ?? (sf_is_primary($db) ? 'primary' : 'secondary'));
    if (!in_array($type, SF_SCHOOL_TYPES, true)) {
        $errors['school_type'] = 'Choose secondary or primary';
    }
    if ($errors) {
        throw new TtException((string)reset($errors), 422, ['errors' => $errors]);
    }

    $values['school_type'] = $type;
    $cols = array_keys($values);
    $sql = 'INSERT INTO report_settings (id, ' . implode(', ', $cols) . ', grade_scale' . ($logo !== null ? ', logo' : '') . ')
        VALUES (1, ' . implode(', ', array_map(fn($c) => ":$c", $cols)) . ', :grade_scale' . ($logo !== null ? ', :logo' : '') . ')
        ON DUPLICATE KEY UPDATE ' . implode(', ', array_map(fn($c) => "$c = VALUES($c)", $cols)) . ', grade_scale = VALUES(grade_scale)'
        . ($logo !== null ? ', logo = VALUES(logo)' : '');
    $params = [':grade_scale' => json_encode($scale)];
    foreach ($values as $k => $v) {
        $params[":$k"] = $v;
    }
    if ($logo !== null) {
        $params[':logo'] = $logo === '' ? null : $logo; // '' removes the logo
    }
    $db->prepare($sql)->execute($params);
    return sf_settings($db);
}

function sf_grade(array $scale, ?float $percent): array
{
    if ($percent === null) {
        return ['grade' => null, 'comment' => null];
    }
    foreach ($scale as $band) {
        if ($percent >= (float)$band['min']) {
            return ['grade' => $band['grade'], 'comment' => $band['comment'] ?? null];
        }
    }
    $last = end($scale);
    return ['grade' => $last['grade'] ?? null, 'comment' => $last['comment'] ?? null];
}

/* ------------------------------------------------------------------ */
/* Exams                                                               */
/* ------------------------------------------------------------------ */

function sf_exam(PDO $db, int $id): ?array
{
    $stmt = $db->prepare('SELECT e.*, a.academic_year, a.term, a.session_name, (a.is_active = 1 OR (a.is_archived = 0 AND a.reopened_at IS NOT NULL)) AS session_open FROM exams e JOIN academic_sessions a ON a.id = e.academic_session_id WHERE e.id = :id');
    $stmt->execute([':id' => $id]);
    $e = $stmt->fetch(PDO::FETCH_ASSOC);
    return $e ?: null;
}

function sf_exam_applies(array $exam, string $level): bool
{
    return $exam['class_level'] === 'all' || $exam['class_level'] === $level;
}

/**
 * The mark sheet for one exam, class and subject: every student in the stream with their mark.
 */
function sf_mark_sheet(PDO $db, array $exam, int $classId, int $subjectId): array
{
    $class = sf_class($db, $classId);
    if (!$class) {
        throw new TtException('That class no longer exists', 404);
    }
    if (!sf_exam_applies($exam, $class['level'])) {
        throw new TtException("{$exam['name']} is for {$exam['class_level']} classes only", 422);
    }
    $marks = [];
    $stmt = $db->prepare('SELECT student_id, marks, comment, updated_at FROM exam_marks WHERE exam_id = :e AND class_id = :c AND subject_id = :s');
    $stmt->execute([':e' => $exam['id'], ':c' => $classId, ':s' => $subjectId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $m) {
        $marks[(int)$m['student_id']] = $m;
    }
    // Option subjects list only the students who offer them (once a class has subjects recorded);
    // compulsory subjects list everyone.
    $students = sf_class_students($db, $classId);
    $compulsory = in_array($subjectId, array_column(ac_compulsory($db)[$class['level']], 'id'), true);
    $filtered = false;
    if (!$compulsory) {
        $t = ac_subject_takers($db, $subjectId, array_map(fn($s) => (int)$s['id'], $students));
        if ($t && $t['with_options']) {
            $students = array_values(array_filter($students, fn($s) => isset($t['takers'][(int)$s['id']]) || isset($marks[(int)$s['id']])));
            $filtered = true;
        }
    }
    $rows = [];
    foreach ($students as $s) {
        $m = $marks[(int)$s['id']] ?? null;
        $rows[] = [
            'student_id' => (int)$s['id'], 'admission_number' => $s['admission_number'], 'full_name' => $s['full_name'], 'gender' => $s['gender'],
            'marks' => $m && $m['marks'] !== null ? (float)$m['marks'] : null, 'comment' => $m['comment'] ?? null,
        ];
    }
    return ['class' => $class, 'students' => $rows, 'entered' => count(array_filter($rows, fn($r) => $r['marks'] !== null)), 'offering_only' => $filtered];
}

/**
 * Saves a batch of marks. $rows: [{student_id, marks|null, comment?}]. Marks must be within
 * 0..max_marks; blank removes a mark. Only students of the class are accepted.
 */
function sf_save_marks(PDO $db, array $exam, int $classId, int $subjectId, array $rows, ?int $userId): array
{
    if (isset($exam['session_open']) && (int)$exam['session_open'] !== 1) {
        throw new TtException("Term {$exam['term']} {$exam['academic_year']} is closed. Marks can be entered in the open term, or in a term reopened under Terms & Years.", 423);
    }
    if ($exam['status'] !== 'open') {
        throw new TtException("Marks for {$exam['name']} are " . ($exam['status'] === 'draft' ? 'not open yet' : 'closed') . '.', 423);
    }
    $sheet = sf_mark_sheet($db, $exam, $classId, $subjectId);
    $allowed = array_flip(array_column($sheet['students'], 'student_id'));
    $max = (float)$exam['max_marks'];
    $errors = [];
    $clean = [];
    foreach ($rows as $r) {
        $sid = (int)($r['student_id'] ?? 0);
        if (!isset($allowed[$sid])) {
            continue; // not in this class, ignore rather than write elsewhere
        }
        $raw = $r['marks'] ?? null;
        if ($raw === null || $raw === '') {
            $clean[$sid] = null;
            continue;
        }
        if (!is_numeric($raw) || (float)$raw < 0 || (float)$raw > $max) {
            $errors[$sid] = "Must be between 0 and " . rtrim(rtrim(number_format($max, 2, '.', ''), '0'), '.');
            continue;
        }
        $clean[$sid] = ['marks' => round((float)$raw, 2), 'comment' => mb_substr(trim((string)($r['comment'] ?? '')), 0, 255) ?: null];
    }
    if ($errors) {
        throw new TtException(count($errors) . ' mark(s) are out of range, nothing was saved', 422, ['errors' => (object)$errors]);
    }

    $db->beginTransaction();
    try {
        $up = $db->prepare('INSERT INTO exam_marks (exam_id, student_id, subject_id, class_id, marks, comment, entered_by)
            VALUES (:e, :st, :su, :c, :m, :cm, :by)
            ON DUPLICATE KEY UPDATE marks = VALUES(marks), comment = VALUES(comment), class_id = VALUES(class_id), entered_by = VALUES(entered_by)');
        $del = $db->prepare('DELETE FROM exam_marks WHERE exam_id = :e AND student_id = :st AND subject_id = :su');
        $saved = 0;
        $cleared = 0;
        foreach ($clean as $sid => $v) {
            if ($v === null) {
                $del->execute([':e' => $exam['id'], ':st' => $sid, ':su' => $subjectId]);
                $cleared += $del->rowCount();
            } else {
                $up->execute([':e' => $exam['id'], ':st' => $sid, ':su' => $subjectId, ':c' => $classId, ':m' => $v['marks'], ':cm' => $v['comment'], ':by' => $userId]);
                $saved++;
            }
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
    return ['saved' => $saved, 'cleared' => $cleared];
}

/* ------------------------------------------------------------------ */
/* Report cards                                                        */
/* ------------------------------------------------------------------ */

/**
 * Teachers enter raw marks (out of the exam's max, normally 100). Each AOI converts to 10 and the
 * best two make the AOI part (20); the mid term / end of term (as the admin sets it) convert to 80.
 */
const SF_AOI_EACH_OUT_OF = 10;
const SF_AOI_BEST = 2;
const SF_AOI_OUT_OF = 20;
const SF_EXAM_OUT_OF = 80;

function sf_component_out_of(string $examType, bool $primary = false): int
{
    if ($primary) {
        return 100; // primary: no AOIs, the exams make the whole mark
    }
    return $examType === 'AOI' ? SF_AOI_EACH_OUT_OF : SF_EXAM_OUT_OF;
}

/**
 * A subject's final mark out of 100 from the learner's converted scores.
 * $aoi: each AOI out of 10; $exam: each counted exam out of 80.
 * AOI part (/20) = average of the best two AOIs, doubled (a single AOI so far counts double);
 * exam part = average of the counted exams (/80); final = both added.
 * When only one part exists it is scaled to 100, so a learner who has sat only exams (or only
 * AOIs) so far is marked on what they have done.
 *
 * @return array{aoi: ?float, exam: ?float, final: ?float}
 */
function sf_final_mark(array $aoi, array $exam, int $examOutOf = SF_EXAM_OUT_OF): array
{
    rsort($aoi);
    $best = array_slice($aoi, 0, SF_AOI_BEST);
    $aoiPart = $best ? array_sum($best) / count($best) * (SF_AOI_OUT_OF / SF_AOI_EACH_OUT_OF) : null;
    $examPart = $exam ? array_sum($exam) / count($exam) : null;
    if ($aoiPart !== null && $examPart !== null) {
        $final = $aoiPart + $examPart;
    } elseif ($aoiPart !== null) {
        $final = $aoiPart / SF_AOI_OUT_OF * 100;
    } elseif ($examPart !== null) {
        $final = $examPart / $examOutOf * 100;
    } else {
        $final = null;
    }
    $r = fn($v) => $v === null ? null : round($v, 1);
    return ['aoi' => $r($aoiPart), 'exam' => $r($examPart), 'final' => $r($final)];
}

/**
 * Report-card data for a class stream (or one student in it) for a term.
 * Final subject mark: see sf_final_mark() (best two AOIs /20 + mid/end of term /80).
 * Position is by average among students of the stream who have marks.
 */
function sf_report_cards(PDO $db, int $sessionId, int $classId, ?int $studentId = null): array
{
    $class = sf_class($db, $classId);
    if (!$class) {
        throw new TtException('That class no longer exists', 404);
    }
    $stmt = $db->prepare("SELECT * FROM exams WHERE academic_session_id = :s AND status <> 'draft' ORDER BY FIELD(exam_type,'BOT','AOI','MOT','EOT','OTHER'), id");
    $stmt->execute([':s' => $sessionId]);
    $exams = array_values(array_filter($stmt->fetchAll(PDO::FETCH_ASSOC), fn($e) => sf_exam_applies($e, $class['level'])));
    $settings = sf_settings($db);
    $scale = $settings['grade_scale'];
    $basis = $settings['exam_basis'];
    $primary = $settings['school_type'] === 'primary';
    $students = sf_class_students($db, $classId);
    if (!$exams) {
        return ['class' => $class, 'exams' => [], 'students' => [], 'settings' => $settings, 'message' => 'No exams are open or closed for this term yet.'];
    }
    $examIds = array_map(fn($e) => (int)$e['id'], $exams);
    $in = implode(',', $examIds);
    $marks = [];
    $q = $db->prepare("SELECT m.student_id, m.subject_id, m.exam_id, m.marks, m.comment FROM exam_marks m WHERE m.class_id = :c AND m.exam_id IN ($in) AND m.marks IS NOT NULL");
    $q->execute([':c' => $classId]);
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $m) {
        $marks[(int)$m['student_id']][(int)$m['subject_id']][(int)$m['exam_id']] = $m;
    }
    $subjects = [];
    foreach ($db->query('SELECT id, subject_name, subject_code FROM subjects_new')->fetchAll(PDO::FETCH_ASSOC) as $s) {
        $subjects[(int)$s['id']] = $s;
    }
    // Subject teachers for the term (from requirements), for the report's "Teacher" column.
    $t = $db->prepare('SELECT lr.subject_id, GROUP_CONCAT(DISTINCT t.full_name SEPARATOR ", ") AS names
        FROM lesson_requirements lr JOIN teachers t ON t.id = lr.teacher_id WHERE lr.academic_session_id = :s AND lr.class_id = :c GROUP BY lr.subject_id');
    $t->execute([':s' => $sessionId, ':c' => $classId]);
    $subjectTeacher = array_column($t->fetchAll(PDO::FETCH_ASSOC), 'names', 'subject_id');

    $byId = array_column(array_map(fn($e) => $e + ['id_int' => (int)$e['id']], $exams), null, 'id_int');
    $cards = [];
    foreach ($students as $s) {
        $sid = (int)$s['id'];
        $rows = [];
        foreach ($marks[$sid] ?? [] as $subjectId => $examMarks) {
            $cells = [];
            $scores = [];
            $aoi = [];
            $exam = [];
            foreach ($examMarks as $examId => $m) {
                $e = $byId[$examId];
                $outOf = sf_component_out_of($e['exam_type'], $primary);
                $score = (float)$m['marks'] / max(0.01, (float)$e['max_marks']) * $outOf;
                $cells[$examId] = (float)$m['marks'];
                $scores[$examId] = round($score, 1);
                if (!sf_counts_to_final($e['exam_type'], $basis, $primary)) {
                    continue;
                }
                if ($e['exam_type'] === 'AOI') {
                    $aoi[] = $score;
                } else {
                    $exam[] = $score;
                }
            }
            $parts = sf_final_mark($aoi, $exam, $primary ? 100 : SF_EXAM_OUT_OF);
            $g = sf_grade($scale, $parts['final']);
            $rows[] = [
                'subject_id' => $subjectId,
                'subject' => $subjects[$subjectId]['subject_name'] ?? 'Subject',
                'code' => $subjects[$subjectId]['subject_code'] ?? '',
                'teacher' => $subjectTeacher[$subjectId] ?? null,
                'marks' => (object)$cells,
                'scores' => (object)$scores,
                'aoi' => $parts['aoi'],
                'exam' => $parts['exam'],
                'final' => $parts['final'],
                'grade' => $g['grade'],
                'comment' => $g['comment'],
            ];
        }
        usort($rows, fn($a, $b) => strcmp($a['subject'], $b['subject']));
        $finals = array_filter(array_column($rows, 'final'), fn($v) => $v !== null);
        $average = $finals ? round(array_sum($finals) / count($finals), 1) : null;
        $cards[] = [
            'student' => ['id' => $sid, 'full_name' => $s['full_name'], 'admission_number' => $s['admission_number'], 'gender' => $s['gender'], 'lin' => $s['lin']],
            'subjects' => $rows,
            'average' => $average,
            'overall' => sf_grade($scale, $average),
        ];
    }
    // Positions (ties share a position).
    $ranked = array_values(array_filter($cards, fn($c) => $c['average'] !== null));
    usort($ranked, fn($a, $b) => $b['average'] <=> $a['average']);
    $positions = [];
    $prev = null;
    $pos = 0;
    foreach ($ranked as $i => $c) {
        if ($c['average'] !== $prev) {
            $pos = $i + 1;
            $prev = $c['average'];
        }
        $positions[$c['student']['id']] = $pos;
    }
    foreach ($cards as &$c) {
        $c['position'] = $positions[$c['student']['id']] ?? null;
        $c['out_of'] = count($ranked);
    }
    unset($c);
    if ($studentId) {
        $cards = array_values(array_filter($cards, fn($c) => $c['student']['id'] === $studentId));
        if (!$cards) {
            throw new TtException('That student is not in this class', 404);
        }
    }
    $session = $db->prepare('SELECT session_name, academic_year, term FROM academic_sessions WHERE id = :s');
    $session->execute([':s' => $sessionId]);
    return [
        'class' => $class,
        'session' => $session->fetch(PDO::FETCH_ASSOC),
        'exams' => array_map(fn($e) => ['id' => (int)$e['id'], 'name' => $e['name'], 'exam_type' => $e['exam_type'], 'max_marks' => (float)$e['max_marks'], 'out_of' => sf_component_out_of($e['exam_type'], $primary), 'counts' => sf_counts_to_final($e['exam_type'], $basis, $primary)], $exams),
        'scheme' => sf_scheme($db),
        'students' => $cards,
        'settings' => $settings,
    ];
}

/* ------------------------------------------------------------------ */
/* Teacher accounts                                                    */
/* ------------------------------------------------------------------ */

/** Readable temporary password, e.g. "Bird-4827-Lake". */
function sf_temp_password(): string
{
    $words = ['Lake', 'Hill', 'Bird', 'Tree', 'Star', 'Moon', 'Rain', 'Leaf', 'Rock', 'Wind', 'Sun', 'Sky', 'Seed', 'Book', 'Pen', 'Bell'];
    return $words[random_int(0, count($words) - 1)] . '-' . random_int(1000, 9999) . '-' . $words[random_int(0, count($words) - 1)];
}

/**
 * Creates (or, with $reset, re-issues) a teacher-portal login. The login name is the teacher's
 * email, or their teacher code when they have no email. Returns the temporary password once.
 */
function sf_issue_teacher_login(PDO $db, int $teacherId, bool $reset): array
{
    $stmt = $db->prepare('SELECT id, full_name, email, teacher_code, is_active FROM teachers WHERE id = :id');
    $stmt->execute([':id' => $teacherId]);
    $t = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$t) {
        throw new TtException('Teacher not found', 404);
    }
    $login = strtolower(trim((string)$t['email'])) ?: strtolower(trim((string)$t['teacher_code']));
    if ($login === '') {
        throw new TtException("{$t['full_name']} has neither an email nor a teacher code", 422);
    }
    $password = sf_temp_password();
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $existing = $db->prepare('SELECT id FROM users WHERE teacher_id = :t');
    $existing->execute([':t' => $teacherId]);
    $userId = $existing->fetchColumn();
    if ($userId) {
        if (!$reset) {
            return ['teacher_id' => $teacherId, 'name' => $t['full_name'], 'login' => null, 'skipped' => 'already has a login'];
        }
        $db->prepare('UPDATE users SET password_hash = :h, must_change_password = 1, is_active = 1 WHERE id = :id')->execute([':h' => $hash, ':id' => $userId]);
    } else {
        $clash = $db->prepare('SELECT id, role FROM users WHERE LOWER(TRIM(email)) = :e');
        $clash->execute([':e' => $login]);
        if ($clash->fetch()) {
            throw new TtException("The login {$login} is already used by another account", 409);
        }
        $db->prepare("INSERT INTO users (full_name, email, password_hash, role, is_active, teacher_id, must_change_password)
            VALUES (:n, :e, :h, '" . SF_TEACHER_ROLE . "', 1, :t, 1)")
            ->execute([':n' => $t['full_name'], ':e' => $login, ':h' => $hash, ':t' => $teacherId]);
    }
    return ['teacher_id' => $teacherId, 'name' => $t['full_name'], 'login' => $login, 'password' => $password];
}

require_once __DIR__ . '/CalendarService.php';
