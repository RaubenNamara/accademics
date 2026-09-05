<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/Database.php';
require_once '../config/JWT.php';
require_once '../config/HrHelpers.php';

$database = new Database();
$db = $database->getConnection();

function respond($success, $message = '', $data = null, $status = 200) {
    http_response_code($status);

    $payload = ['success' => $success];

    if ($message !== '') {
        $payload['message'] = $message;
    }

    if ($data !== null) {
        $payload['data'] = $data;
    }

    echo json_encode($payload);
    exit();
}

function getJsonInput() {
    $raw = file_get_contents("php://input");
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function getRequestData() {
    $json = getJsonInput();
    if (!empty($json)) {
        return $json;
    }

    if (!empty($_POST)) {
        return $_POST;
    }

    return [];
}

function normalizeObligation($value) {
    $allowed = [
        'Class Teacher',
        'Subject Teacher',
        'HoD',
        'Deputy Admin',
        'Deputy Academics',
        'Head Teacher'
    ];

    $value = trim((string)$value);
    return in_array($value, $allowed, true) ? $value : 'Subject Teacher';
}

function generateTeacherCode(PDO $db): string {
    return hr_generate_sequential_code($db, 'TS');
}

function teacherExistsByEmail(PDO $db, string $email, int $exceptId = 0): bool {
    if ($exceptId > 0) {
        $stmt = $db->prepare("
            SELECT id
            FROM teachers
            WHERE email = :email AND id <> :id
            LIMIT 1
        ");
        $stmt->execute([
            ':email' => $email,
            ':id' => $exceptId
        ]);
    } else {
        $stmt = $db->prepare("
            SELECT id
            FROM teachers
            WHERE email = :email
            LIMIT 1
        ");
        $stmt->execute([
            ':email' => $email
        ]);
    }

    return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}

function teacherExistsByCode(PDO $db, string $code, int $exceptId = 0): bool {
    if ($exceptId > 0) {
        $stmt = $db->prepare("
            SELECT id
            FROM teachers
            WHERE teacher_code = :teacher_code AND id <> :id
            LIMIT 1
        ");
        $stmt->execute([
            ':teacher_code' => $code,
            ':id' => $exceptId
        ]);
    } else {
        $stmt = $db->prepare("
            SELECT id
            FROM teachers
            WHERE teacher_code = :teacher_code
            LIMIT 1
        ");
        $stmt->execute([
            ':teacher_code' => $code
        ]);
    }

    return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}

function normalizeNullable($value): ?string {
    $value = trim((string)($value ?? ''));
    return $value === '' ? null : $value;
}

function normalizeSalary($value): ?float {
    if ($value === null || $value === '') {
        return null;
    }
    return is_numeric($value) ? (float)$value : null;
}

/** Store long base64 images as files; DB column is VARCHAR(255). */
function normalizePassportPhoto($value): ?string {
    $value = trim((string)($value ?? ''));
    if ($value === '') {
        return null;
    }

    if (strlen($value) <= 255 && strpos($value, 'data:image') !== 0) {
        return $value;
    }

    if (!preg_match('#^data:image/(\w+);base64,(.+)$#', $value, $matches)) {
        return strlen($value) <= 255 ? $value : null;
    }

    $ext = strtolower($matches[1]) === 'jpeg' ? 'jpg' : strtolower($matches[1]);
    $binary = base64_decode($matches[2], true);
    if ($binary === false) {
        return null;
    }

    $dir = dirname(__DIR__) . '/uploads/teachers';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return null;
    }

    $filename = 'teacher_' . uniqid('', true) . '.' . $ext;
    $fullPath = $dir . '/' . $filename;
    if (file_put_contents($fullPath, $binary) === false) {
        return null;
    }

    return 'uploads/teachers/' . $filename;
}

function teacherPayload(array $data, string $full_name, string $email, string $subject): array {
    $contact = normalizeNullable($data['contact'] ?? $data['phone_number'] ?? null);

    return [
        ':full_name' => $full_name,
        ':gender' => normalizeNullable($data['gender'] ?? null),
        ':date_of_birth' => normalizeNullable($data['date_of_birth'] ?? null),
        ':national_id' => normalizeNullable($data['national_id'] ?? null),
        ':passport_photo' => normalizePassportPhoto($data['passport_photo'] ?? null),
        ':nationality' => normalizeNullable($data['nationality'] ?? null),
        ':marital_status' => normalizeNullable($data['marital_status'] ?? null),
        ':religion' => normalizeNullable($data['religion'] ?? null),
        ':email' => $email,
        ':contact' => $contact,
        ':alternative_phone' => normalizeNullable($data['alternative_phone'] ?? null),
        ':address' => normalizeNullable($data['address'] ?? null),
        ':district' => normalizeNullable($data['district'] ?? null),
        ':emergency_contact_name' => normalizeNullable($data['emergency_contact_name'] ?? null),
        ':emergency_contact_phone' => normalizeNullable($data['emergency_contact_phone'] ?? null),
        ':department' => normalizeNullable($data['department'] ?? null),
        ':position' => normalizeNullable($data['position'] ?? null),
        ':employment_type' => normalizeNullable($data['employment_type'] ?? null),
        ':date_joined' => normalizeNullable($data['date_joined'] ?? null),
        ':contract_start' => normalizeNullable($data['contract_start'] ?? null),
        ':contract_end' => normalizeNullable($data['contract_end'] ?? null),
        ':supervisor' => normalizeNullable($data['supervisor'] ?? null),
        ':qualification' => normalizeNullable($data['qualification'] ?? null),
        ':university' => normalizeNullable($data['university'] ?? null),
        ':year_graduated' => normalizeNullable($data['year_graduated'] ?? null),
        ':teaching_license_number' => normalizeNullable($data['teaching_license_number'] ?? null),
        ':other_duties' => normalizeNullable($data['other_duties'] ?? null),
        ':second_subject' => normalizeNullable($data['second_subject'] ?? null),
        ':subject' => $subject,
        ':obligation' => normalizeObligation($data['obligation'] ?? 'Subject Teacher'),
        ':salary' => normalizeSalary($data['salary'] ?? null),
        ':bank_name' => normalizeNullable($data['bank_name'] ?? null),
        ':account_number' => normalizeNullable($data['account_number'] ?? null),
        ':tin_number' => normalizeNullable($data['tin_number'] ?? null),
        ':nssf_number' => normalizeNullable($data['nssf_number'] ?? null),
        ':is_active' => isset($data['is_active']) ? (int)$data['is_active'] : 1,
    ];
}

function createTeacher(PDO $db, array $data): array {
    $full_name = trim((string)($data['full_name'] ?? ''));
    $email = trim((string)($data['email'] ?? ''));
    $subject = trim((string)($data['subject'] ?? ''));

    if ($full_name === '' || $email === '' || $subject === '') {
        return [
            'ok' => false,
            'status' => 400,
            'message' => 'Full name, email, and subject are required'
        ];
    }

    if (teacherExistsByEmail($db, $email)) {
        return [
            'ok' => false,
            'status' => 409,
            'message' => 'A teacher with this email already exists'
        ];
    }

    $teacher_code = generateTeacherCode($db);

    $stmt = $db->prepare("
        INSERT INTO teachers
        (
            teacher_code,
            full_name,
            gender,
            date_of_birth,
            national_id,
            passport_photo,
            nationality,
            marital_status,
            religion,
            email,
            contact,
            alternative_phone,
            address,
            district,
            emergency_contact_name,
            emergency_contact_phone,
            department,
            position,
            employment_type,
            date_joined,
            contract_start,
            contract_end,
            supervisor,
            qualification,
            university,
            year_graduated,
            teaching_license_number,
            other_duties,
            second_subject,
            subject,
            obligation,
            salary,
            bank_name,
            account_number,
            tin_number,
            nssf_number,
            is_active,
            created_at,
            updated_at
        )
        VALUES
        (
            :teacher_code,
            :full_name,
            :gender,
            :date_of_birth,
            :national_id,
            :passport_photo,
            :nationality,
            :marital_status,
            :religion,
            :email,
            :contact,
            :alternative_phone,
            :address,
            :district,
            :emergency_contact_name,
            :emergency_contact_phone,
            :department,
            :position,
            :employment_type,
            :date_joined,
            :contract_start,
            :contract_end,
            :supervisor,
            :qualification,
            :university,
            :year_graduated,
            :teaching_license_number,
            :other_duties,
            :second_subject,
            :subject,
            :obligation,
            :salary,
            :bank_name,
            :account_number,
            :tin_number,
            :nssf_number,
            :is_active,
            NOW(),
            NOW()
        )
    ");

    try {
        $params = array_merge(
            [':teacher_code' => $teacher_code],
            teacherPayload($data, $full_name, $email, $subject)
        );
        $ok = $stmt->execute($params);
    } catch (PDOException $e) {
        return [
            'ok' => false,
            'status' => 500,
            'message' => 'Failed to create teacher: ' . $e->getMessage()
        ];
    }

    if (!$ok) {
        return [
            'ok' => false,
            'status' => 500,
            'message' => 'Failed to create teacher'
        ];
    }

    $teacherId = (int)$db->lastInsertId();

    if (function_exists('hr_sync_employee_for_teacher')) {
        hr_sync_employee_for_teacher($db, $teacherId, []);
    }

    return [
        'ok' => true,
        'status' => 201,
        'message' => 'Teacher created successfully',
        'data' => [
            'id' => $teacherId,
            'teacher_code' => $teacher_code
        ]
    ];
}

function bulkImportTeachers(PDO $db, array $teachers): array {
    $imported = 0;
    $skipped = 0;
    $errors = [];

    foreach ($teachers as $index => $teacher) {
        if (!is_array($teacher)) {
            $skipped++;
            $errors[] = "Row " . ($index + 1) . ": invalid format";
            continue;
        }

        $result = createTeacher($db, $teacher);

        if ($result['ok']) {
            $imported++;
        } else {
            $skipped++;
            $errors[] = "Row " . ($index + 1) . ": " . $result['message'];
        }
    }

    return [
        'ok' => true,
        'status' => 200,
        'message' => 'Import completed',
        'data' => [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => array_slice($errors, 0, 20)
        ]
    ];
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

        if ($id > 0) {
            $stmt = $db->prepare("SELECT * FROM teachers WHERE id = :id LIMIT 1");
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$teacher) {
                respond(false, 'Teacher not found', null, 404);
            }

            respond(true, 'Teacher loaded', $teacher);
        }

        $search = trim($_GET['search'] ?? '');
        $obligation = trim($_GET['obligation'] ?? '');
        $subject = trim($_GET['subject'] ?? '');
        $isActive = $_GET['is_active'] ?? '';
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 10;
        $offset = ($page - 1) * $limit;

        $query = "SELECT * FROM teachers WHERE 1=1";
        $countQuery = "SELECT COUNT(*) as total FROM teachers WHERE 1=1";
        $params = [];

        if ($search !== '') {
            $searchCondition = " AND (
                teacher_code LIKE :search
                OR full_name LIKE :search
                OR email LIKE :search
                OR contact LIKE :search
                OR alternative_phone LIKE :search
                OR subject LIKE :search
                OR second_subject LIKE :search
                OR nationality LIKE :search
                OR qualification LIKE :search
                OR teaching_license_number LIKE :search
                OR department LIKE :search
                OR position LIKE :search
                OR employment_type LIKE :search
                OR obligation LIKE :search
                OR national_id LIKE :search
            )";
            $query .= $searchCondition;
            $countQuery .= $searchCondition;
            $params[':search'] = '%' . $search . '%';
        }

        if ($obligation !== '') {
            $query .= " AND obligation = :obligation";
            $countQuery .= " AND obligation = :obligation";
            $params[':obligation'] = $obligation;
        }

        if ($subject !== '') {
            $query .= " AND subject = :subject";
            $countQuery .= " AND subject = :subject";
            $params[':subject'] = $subject;
        }

        if ($isActive !== '') {
            $query .= " AND is_active = :is_active";
            $countQuery .= " AND is_active = :is_active";
            $params[':is_active'] = (int)$isActive;
        }

        $query .= " ORDER BY is_active DESC, full_name ASC LIMIT :limit OFFSET :offset";

        $stmt = $db->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $countStmt = $db->prepare($countQuery);
        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, $value);
        }
        $countStmt->execute();
        $totalResult = $countStmt->fetch(PDO::FETCH_ASSOC);
        $total = (int)$totalResult['total'];
        $totalPages = (int)ceil($total / $limit);

        respond(true, 'Teachers loaded', [
            'teachers' => $teachers,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'totalPages' => $totalPages
            ]
        ]);
        break;

    case 'POST':
        $data = getRequestData();

        if (isset($data['action']) && strtolower((string)$data['action']) === 'import') {
            $teachers = $data['teachers'] ?? [];

            if (!is_array($teachers)) {
                respond(false, 'Import data must be an array of teachers', null, 400);
            }

            $result = bulkImportTeachers($db, $teachers);
            respond(true, 'Import completed', $result['data'], 200);
        }

        $result = createTeacher($db, $data);

        if ($result['ok']) {
            respond(true, $result['message'], $result['data'], $result['status']);
        }

        respond(false, $result['message'], null, $result['status']);
        break;

    case 'PUT':
        $data = getRequestData();
        $id = (int)($data['id'] ?? 0);

        if ($id <= 0) {
            respond(false, 'Teacher ID is required', null, 400);
        }

        $full_name = trim((string)($data['full_name'] ?? ''));
        $email = trim((string)($data['email'] ?? ''));
        $subject = trim((string)($data['subject'] ?? ''));

        if ($full_name === '' || $email === '' || $subject === '') {
            respond(false, 'Full name, email, and subject are required', null, 400);
        }

        if (teacherExistsByEmail($db, $email, $id)) {
            respond(false, 'Another teacher already uses this email', null, 409);
        }

        $stmt = $db->prepare("
            UPDATE teachers
            SET
                full_name = :full_name,
                gender = :gender,
                date_of_birth = :date_of_birth,
                national_id = :national_id,
                passport_photo = :passport_photo,
                nationality = :nationality,
                marital_status = :marital_status,
                religion = :religion,
                email = :email,
                contact = :contact,
                alternative_phone = :alternative_phone,
                address = :address,
                district = :district,
                emergency_contact_name = :emergency_contact_name,
                emergency_contact_phone = :emergency_contact_phone,
                department = :department,
                position = :position,
                employment_type = :employment_type,
                date_joined = :date_joined,
                contract_start = :contract_start,
                contract_end = :contract_end,
                supervisor = :supervisor,
                qualification = :qualification,
                university = :university,
                year_graduated = :year_graduated,
                teaching_license_number = :teaching_license_number,
                other_duties = :other_duties,
                second_subject = :second_subject,
                subject = :subject,
                obligation = :obligation,
                salary = :salary,
                bank_name = :bank_name,
                account_number = :account_number,
                tin_number = :tin_number,
                nssf_number = :nssf_number,
                is_active = :is_active,
                updated_at = NOW()
            WHERE id = :id
        ");

        try {
            $params = array_merge(
                [':id' => $id],
                teacherPayload($data, $full_name, $email, $subject)
            );
            $ok = $stmt->execute($params);
        } catch (PDOException $e) {
            respond(false, 'Failed to update teacher: ' . $e->getMessage(), null, 500);
        }

        if ($ok) {
            if (function_exists('hr_sync_employee_for_teacher')) {
                hr_sync_employee_for_teacher($db, $id, []);
            }
            respond(true, 'Teacher updated successfully');
        }

        respond(false, 'Failed to update teacher', null, 500);
        break;

    case 'PATCH':
        $data = getRequestData();
        $id = (int)($data['id'] ?? 0);
        $is_active = isset($data['is_active']) ? (int)$data['is_active'] : null;

        if ($id <= 0 || $is_active === null) {
            respond(false, 'Teacher ID and status are required', null, 400);
        }

        $stmt = $db->prepare("
            UPDATE teachers
            SET is_active = :is_active, updated_at = NOW()
            WHERE id = :id
        ");

        $ok = $stmt->execute([
            ':id' => $id,
            ':is_active' => $is_active
        ]);

        if ($ok) {
            respond(true, 'Status updated');
        }

        respond(false, 'Failed to update status', null, 500);
        break;

    case 'DELETE':
        $data = getRequestData();
        $id = isset($_GET['id']) ? (int)$_GET['id'] : (int)($data['id'] ?? 0);

        if ($id <= 0) {
            respond(false, 'Teacher ID is required', null, 400);
        }

        $check = $db->prepare("SELECT id FROM teachers WHERE id = :id LIMIT 1");
        $check->execute([':id' => $id]);

        $teacher = $check->fetch(PDO::FETCH_ASSOC);

        if (!$teacher) {
            respond(false, 'Teacher not found', null, 404);
        }

        $stmt = $db->prepare("DELETE FROM teachers WHERE id = :id");
        $ok = $stmt->execute([':id' => $id]);

        if ($ok) {
            respond(true, 'Teacher deleted successfully');
        }

        respond(false, 'Failed to delete teacher', null, 500);
        break;

    default:
        respond(false, 'Method not allowed', null, 405);
        break;
}