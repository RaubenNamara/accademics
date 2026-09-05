<?php
declare(strict_types=1);

require_once '../config/Database.php';
require_once '../config/JWT.php';
require_once '../config/HrHelpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function nts_sendJson(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function nts_getAuthorizationHeader(): string
{
    $headers = [];
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
    }

    foreach (['Authorization', 'authorization'] as $key) {
        if (!empty($headers[$key])) {
            return trim((string)$headers[$key]);
        }
    }

    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        return trim((string)$_SERVER['HTTP_AUTHORIZATION']);
    }

    if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        return trim((string)$_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
    }

    return '';
}

function nts_requireAuth(array $allowedRoles = []): array
{
    $auth = nts_getAuthorizationHeader();
    if ($auth === '') {
        nts_sendJson(401, [
            'success' => false,
            'error'   => 'Authorization header missing',
        ]);
    }

    $token   = preg_replace('/^Bearer\s+/i', '', $auth);
    $payload = JWT::decode($token);

    if (!$payload) {
        nts_sendJson(401, [
            'success' => false,
            'error'   => 'Invalid or expired token',
        ]);
    }

    if (!empty($allowedRoles)) {
        $role = (string)($payload['role'] ?? '');
        if (!in_array($role, $allowedRoles, true)) {
            nts_sendJson(403, [
                'success' => false,
                'error'   => 'Forbidden',
            ]);
        }
    }

    return $payload;
}

function nts_getJsonInput(): array
{
    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function nts_requestData(): array
{
    $data = $_POST;

    $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
    if (empty($data) && stripos($contentType, 'application/json') !== false) {
        $data = nts_getJsonInput();
    }

    return is_array($data) ? $data : [];
}

function nts_generateHrCode(PDO $db): string
{
    return hr_generate_sequential_code($db, 'NTS');
}

function nts_normalizeNullable($value): ?string
{
    if ($value === null) {
        return null;
    }
    $value = trim((string)$value);
    return $value === '' ? null : $value;
}

function nts_columnExists(PDO $db, string $table, string $column): bool
{
    $stmt = $db->prepare("SHOW COLUMNS FROM `{$table}` LIKE :column");
    $stmt->execute([':column' => $column]);
    return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}

function nts_baseUrl(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';

    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $appBase    = rtrim(dirname(dirname($scriptName)), '/');

    if ($appBase === '/' || $appBase === '.' || $appBase === '\\') {
        $appBase = '';
    }

    return $scheme . '://' . $host . $appBase;
}

function nts_publicPhotoUrl(?string $path): ?string
{
    if ($path === null) {
        return null;
    }

    $path = trim($path);
    if ($path === '') {
        return null;
    }

    if (preg_match('~^https?://~i', $path)) {
        return $path;
    }

    $fileName = basename($path);
    return nts_baseUrl() . '/uploads/profile_pictures/' . rawurlencode($fileName);
}

try {
    $database = new Database();
    $db       = $database->getConnection();

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    $employeesHasUpdatedAt = nts_columnExists($db, 'employees', 'updated_at');

    if ($method === 'GET') {
        $id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $deptId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
        $status = trim((string)($_GET['staff_status'] ?? ''));
        $search = trim((string)($_GET['search'] ?? ''));

        $employeeUpdatedAtSelect = $employeesHasUpdatedAt
            ? 'e.updated_at AS updated_at'
            : 'NULL AS updated_at';

        if ($id > 0) {
            $sql = "
                SELECT
                    e.*,
                    d.name AS department_name,
                    r.name AS role_name,
                    nts.duty_assignment,
                    nts.shift_schedule,
                    nts.specialization,
                    nts.staff_status,
                    {$employeeUpdatedAtSelect}
                FROM employees e
                LEFT JOIN non_teaching_staff nts ON nts.employee_id = e.id
                LEFT JOIN departments d ON e.department_id = d.id
                LEFT JOIN roles r ON e.role_id = r.id
                WHERE e.id = :id
                  AND e.staff_type = 'non_teaching'
                LIMIT 1
            ";

            $stmt = $db->prepare($sql);
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                nts_sendJson(404, [
                    'success' => false,
                    'error'   => 'Non-teaching staff not found',
                ]);
            }

            $row['passport_photo'] = nts_publicPhotoUrl($row['passport_photo'] ?? null);

            nts_sendJson(200, [
                'success' => true,
                'data'    => $row,
            ]);
        }

        $conditions = ["e.staff_type = 'non_teaching'"];
        $params     = [];

        if ($deptId > 0) {
            $conditions[]             = 'e.department_id = :department_id';
            $params[':department_id'] = $deptId;
        }

        if ($status !== '') {
            $conditions[]            = 'COALESCE(nts.staff_status, e.status) = :staff_status';
            $params[':staff_status'] = $status;
        }

        if ($search !== '') {
            $conditions[] = '(
                e.hr_code LIKE :search OR
                e.first_name LIKE :search OR
                e.last_name LIKE :search OR
                e.email LIKE :search OR
                e.phone_number LIKE :search OR
                e.religion LIKE :search OR
                e.alternative_phone LIKE :search OR
                e.district LIKE :search OR
                e.qualification LIKE :search OR
                e.university LIKE :search OR
                nts.duty_assignment LIKE :search OR
                nts.specialization LIKE :search
            )';
            $params[':search'] = '%' . $search . '%';
        }

        $conditions[] = "(e.status IS NULL OR e.status <> 'terminated')";

        $whereSql = 'WHERE ' . implode(' AND ', $conditions);

        $sql = "
            SELECT
                e.id,
                e.hr_code,
                e.first_name,
                e.last_name,
                e.gender,
                e.date_of_birth,
                e.national_id,
                e.nationality,
                e.marital_status,
                e.religion,
                e.passport_photo,
                e.phone_number,
                e.alternative_phone,
                e.address,
                e.district,
                e.email,
                e.address,
                e.emergency_contact,
                e.status,
                e.department_id,
                d.name AS department_name,
                e.role_id,
                e.employment_type,
                r.name AS role_name,
                e.date_joined,
                e.contract_start,
                e.salary,
                e.qualification,
                e.university,
                e.year_graduated,
                e.bank_name,
                e.account_number,
                e.mobile_money,
                e.tin_number,
                e.nssf_number,
                nts.duty_assignment,
                nts.shift_schedule,
                nts.specialization,
                nts.staff_status,
                e.created_at,
                {$employeeUpdatedAtSelect}
            FROM employees e
            LEFT JOIN non_teaching_staff nts ON nts.employee_id = e.id
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN roles r ON e.role_id = r.id
            {$whereSql}
            ORDER BY e.created_at DESC
        ";

        $stmt = $db->prepare($sql);
        foreach ($params as $key => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $type);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['passport_photo'] = nts_publicPhotoUrl($row['passport_photo'] ?? null);
        }
        unset($row);

        nts_sendJson(200, [
            'success' => true,
            'data'    => $rows,
        ]);
    }

    nts_requireAuth(['admin', 'hr_manager', 'super_admin']);

    if ($method === 'POST') {
        $data = nts_requestData();

        // Auto-populate department_id if role_id is provided but department_id is not
        if (isset($data['role_id']) && trim((string)$data['role_id']) !== '' && (!isset($data['department_id']) || trim((string)$data['department_id']) === '')) {
            $roleId = (int)$data['role_id'];
            $deptStmt = $db->prepare("SELECT department_id FROM roles WHERE id = :role_id LIMIT 1");
            $deptStmt->execute([':role_id' => $roleId]);
            $role = $deptStmt->fetch(PDO::FETCH_ASSOC);
            if ($role && $role['department_id']) {
                $data['department_id'] = $role['department_id'];
            }
        }

        $required = ['first_name', 'last_name', 'department_id', 'role_id'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
                nts_sendJson(400, [
                    'success' => false,
                    'error'   => $field . ' is required',
                ]);
            }
        }

        $firstName = trim((string)$data['first_name']);
        $lastName  = trim((string)$data['last_name']);

        $gender      = nts_normalizeNullable($data['gender'] ?? null);
        $dob         = nts_normalizeNullable($data['date_of_birth'] ?? null);
        $nationality = nts_normalizeNullable($data['nationality'] ?? null);
        $nationalId  = nts_normalizeNullable($data['national_id'] ?? null);
        $religion    = nts_normalizeNullable($data['religion'] ?? null);
        $phone       = nts_normalizeNullable($data['phone_number'] ?? null);
        $altPhone    = nts_normalizeNullable($data['alternative_phone'] ?? null);
        $email       = nts_normalizeNullable($data['email'] ?? null);
        $address     = nts_normalizeNullable($data['address'] ?? null);
        $district    = nts_normalizeNullable($data['district'] ?? null);
        $emergency   = nts_normalizeNullable($data['emergency_contact'] ?? null);
        $marital     = nts_normalizeNullable($data['marital_status'] ?? null);
        $qualification = nts_normalizeNullable($data['qualification'] ?? null);
        $university    = nts_normalizeNullable($data['university'] ?? null);
        $yearGraduated = nts_normalizeNullable($data['year_graduated'] ?? null);
        $contractStart = nts_normalizeNullable($data['contract_start'] ?? null);

        $passportPhoto = null;
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/profile_pictures/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $file = $_FILES['profile_picture'];
            $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (!in_array($fileExt, $allowedExts, true)) {
                nts_sendJson(400, [
                    'success' => false,
                    'error'   => 'Invalid file type. Only JPG, JPEG, PNG, GIF, and WEBP are allowed.',
                ]);
            }

            if ($file['size'] > 5 * 1024 * 1024) {
                nts_sendJson(400, [
                    'success' => false,
                    'error'   => 'File size exceeds 5MB limit.',
                ]);
            }

            $fileName = 'nts_' . uniqid('', true) . '_' . time() . '.' . $fileExt;
            $filePath = $uploadDir . $fileName;

            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                nts_sendJson(500, [
                    'success' => false,
                    'error'   => 'Failed to upload profile picture.',
                ]);
            }

            $passportPhoto = 'uploads/profile_pictures/' . $fileName;
        }

        $departmentId = (int)$data['department_id'];
        $roleId       = (int)$data['role_id'];

        $employment   = nts_normalizeNullable($data['employment_type'] ?? null);
        $dateJoined   = nts_normalizeNullable($data['date_joined'] ?? null);
        $salary       = isset($data['salary']) && $data['salary'] !== '' ? (float)$data['salary'] : 0.0;
        $statusValue  = nts_normalizeNullable($data['status'] ?? null) ?? 'active';
        $supervisorId = isset($data['supervisor_id']) && $data['supervisor_id'] !== '' ? (int)$data['supervisor_id'] : null;

        $bankName    = nts_normalizeNullable($data['bank_name'] ?? null);
        $accountNo   = nts_normalizeNullable($data['account_number'] ?? null);
        $mobileMoney = nts_normalizeNullable($data['mobile_money'] ?? null);
        $tinNumber   = nts_normalizeNullable($data['tin_number'] ?? null);
        $nssfNumber  = nts_normalizeNullable($data['nssf_number'] ?? null);

        $duty        = nts_normalizeNullable($data['duty_assignment'] ?? null);
        $shift       = nts_normalizeNullable($data['shift_schedule'] ?? null);
        $special     = nts_normalizeNullable($data['specialization'] ?? null);
        $staffStatus = nts_normalizeNullable($data['staff_status'] ?? null) ?? 'active';

        $db->beginTransaction();

        try {
            $hrCode = nts_generateHrCode($db);

            $empSql = "
                INSERT INTO employees (
                    staff_type,
                    hr_code,
                    first_name,
                    last_name,
                    gender,
                    date_of_birth,
                    nationality,
                    national_id,
                    religion,
                    passport_photo,
                    phone_number,
                    alternative_phone,
                    email,
                    address,
                    district,
                    emergency_contact,
                    marital_status,
                    qualification,
                    university,
                    year_graduated,
                    department_id,
                    role_id,
                    employment_type,
                    date_joined,
                    contract_start,
                    salary,
                    status,
                    supervisor_id,
                    bank_name,
                    account_number,
                    mobile_money,
                    tin_number,
                    nssf_number
                ) VALUES (
                    'non_teaching',
                    :hr_code,
                    :first_name,
                    :last_name,
                    :gender,
                    :dob,
                    :nationality,
                    :national_id,
                    :religion,
                    :passport_photo,
                    :phone,
                    :alt_phone,
                    :email,
                    :address,
                    :district,
                    :emergency,
                    :marital,
                    :qualification,
                    :university,
                    :year_graduated,
                    :department_id,
                    :role_id,
                    :employment_type,
                    :date_joined,
                    :contract_start,
                    :salary,
                    :status,
                    :supervisor_id,
                    :bank_name,
                    :account_number,
                    :mobile_money,
                    :tin_number,
                    :nssf_number
                )
            ";

            $empStmt = $db->prepare($empSql);
            $empStmt->execute([
                ':hr_code'         => $hrCode,
                ':first_name'      => $firstName,
                ':last_name'       => $lastName,
                ':gender'          => $gender,
                ':dob'             => $dob,
                ':nationality'     => $nationality,
                ':national_id'     => $nationalId,
                ':religion'        => $religion,
                ':passport_photo'  => $passportPhoto,
                ':phone'           => $phone,
                ':alt_phone'       => $altPhone,
                ':email'           => $email,
                ':address'         => $address,
                ':district'        => $district,
                ':emergency'       => $emergency,
                ':marital'         => $marital,
                ':qualification'   => $qualification,
                ':university'      => $university,
                ':year_graduated'  => $yearGraduated,
                ':department_id'   => $departmentId,
                ':role_id'         => $roleId,
                ':employment_type' => $employment,
                ':date_joined'     => $dateJoined,
                ':contract_start'  => $contractStart,
                ':salary'          => $salary,
                ':status'          => $statusValue,
                ':supervisor_id'   => $supervisorId,
                ':bank_name'       => $bankName,
                ':account_number'  => $accountNo,
                ':mobile_money'    => $mobileMoney,
                ':tin_number'      => $tinNumber,
                ':nssf_number'     => $nssfNumber,
            ]);

            $employeeId = (int)$db->lastInsertId();

            $ntsSql = "
                INSERT INTO non_teaching_staff (
                    employee_id,
                    duty_assignment,
                    shift_schedule,
                    specialization,
                    staff_status
                ) VALUES (
                    :employee_id,
                    :duty_assignment,
                    :shift_schedule,
                    :specialization,
                    :staff_status
                )
            ";

            $ntsStmt = $db->prepare($ntsSql);
            $ntsStmt->execute([
                ':employee_id'     => $employeeId,
                ':duty_assignment' => $duty,
                ':shift_schedule'  => $shift,
                ':specialization'  => $special,
                ':staff_status'    => $staffStatus,
            ]);

            $db->commit();

            nts_sendJson(201, [
                'success' => true,
                'message' => 'Non-teaching staff created successfully',
                'data'    => [
                    'employee_id'   => $employeeId,
                    'hr_code'       => $hrCode,
                    'passport_photo'=> nts_publicPhotoUrl($passportPhoto),
                ],
            ]);
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            nts_sendJson(500, [
                'success' => false,
                'error'   => 'Failed to create non-teaching staff',
                'details' => $e->getMessage(),
            ]);
        }
    } elseif ($method === 'PUT') {
        $data = nts_requestData();
        
        // Handle FormData - data might be in $_POST or need to be parsed differently
        if (empty($data) && !empty($_POST)) {
            $data = $_POST;
        }
        
        // Debug logging
        error_log('PUT request - Received data: ' . print_r($data, true));
        error_log('PUT request - $_POST: ' . print_r($_POST, true));
        error_log('PUT request - Content-Type: ' . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));
        
        $id   = (int)($data['id'] ?? 0);

        if ($id <= 0) {
            nts_sendJson(400, [
                'success' => false,
                'error'   => 'Employee ID is required',
                'debug'  => [
                    'received_data' => $data,
                    'post_data' => $_POST,
                ]
            ]);
        }

        // Auto-populate department_id if role_id is provided but department_id is not
        if (isset($data['role_id']) && trim((string)$data['role_id']) !== '' && (!isset($data['department_id']) || trim((string)$data['department_id']) === '')) {
            $roleId = (int)$data['role_id'];
            $deptStmt = $db->prepare("SELECT department_id FROM roles WHERE id = :role_id LIMIT 1");
            $deptStmt->execute([':role_id' => $roleId]);
            $role = $deptStmt->fetch(PDO::FETCH_ASSOC);
            if ($role && $role['department_id']) {
                $data['department_id'] = $role['department_id'];
            }
        }

        $passportPhoto = null;
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/profile_pictures/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $file = $_FILES['profile_picture'];
            $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (!in_array($fileExt, $allowedExts, true)) {
                nts_sendJson(400, [
                    'success' => false,
                    'error'   => 'Invalid file type. Only JPG, JPEG, PNG, GIF, and WEBP are allowed.',
                ]);
            }

            if ($file['size'] > 5 * 1024 * 1024) {
                nts_sendJson(400, [
                    'success' => false,
                    'error'   => 'File size exceeds 5MB limit.',
                ]);
            }

            $fileName = 'nts_' . uniqid('', true) . '_' . time() . '.' . $fileExt;
            $filePath = $uploadDir . $fileName;

            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                nts_sendJson(500, [
                    'success' => false,
                    'error'   => 'Failed to upload profile picture.',
                ]);
            }

            $passportPhoto = 'uploads/profile_pictures/' . $fileName;
        }

        $db->beginTransaction();

        try {
            // Fetch current employee data to compare
            $currentStmt = $db->prepare("SELECT * FROM employees WHERE id = :id AND staff_type = 'non_teaching' LIMIT 1");
            $currentStmt->execute([':id' => $id]);
            $current = $currentStmt->fetch(PDO::FETCH_ASSOC);

            if (!$current) {
                nts_sendJson(404, [
                    'success' => false,
                    'error'   => 'Employee not found',
                ]);
            }

            // Build dynamic UPDATE statement - only update fields that have changed
            $updateFields = [];
            $params = [':id' => $id];

            // Helper to check if value changed
            $hasChanged = function($key, $newValue) use ($current) {
                $oldValue = $current[$key] ?? null;
                if ($newValue === null && $oldValue === null) return false;
                if ($newValue === null && $oldValue !== null) return true;
                if ($newValue !== null && $oldValue === null) return true;
                return (string)$newValue !== (string)$oldValue;
            };

            // Required fields - always update
            $updateFields[] = 'first_name = :first_name';
            $params[':first_name'] = trim((string)($data['first_name'] ?? ''));

            $updateFields[] = 'last_name = :last_name';
            $params[':last_name'] = trim((string)($data['last_name'] ?? ''));

            // Optional fields - only update if changed
            $genderVal = nts_normalizeNullable($data['gender'] ?? null);
            if ($hasChanged('gender', $genderVal)) {
                $updateFields[] = 'gender = :gender';
                $params[':gender'] = $genderVal;
            }

            $dobVal = nts_normalizeNullable($data['date_of_birth'] ?? null);
            if ($hasChanged('date_of_birth', $dobVal)) {
                $updateFields[] = 'date_of_birth = :dob';
                $params[':dob'] = $dobVal;
            }

            $nationalityVal = nts_normalizeNullable($data['nationality'] ?? null);
            if ($hasChanged('nationality', $nationalityVal)) {
                $updateFields[] = 'nationality = :nationality';
                $params[':nationality'] = $nationalityVal;
            }

            $nationalIdVal = nts_normalizeNullable($data['national_id'] ?? null);
            if ($hasChanged('national_id', $nationalIdVal)) {
                $updateFields[] = 'national_id = :national_id';
                $params[':national_id'] = $nationalIdVal;
            }

            $religionVal = nts_normalizeNullable($data['religion'] ?? null);
            if ($hasChanged('religion', $religionVal)) {
                $updateFields[] = 'religion = :religion';
                $params[':religion'] = $religionVal;
            }

            $altPhoneVal = nts_normalizeNullable($data['alternative_phone'] ?? null);
            if ($hasChanged('alternative_phone', $altPhoneVal)) {
                $updateFields[] = 'alternative_phone = :alternative_phone';
                $params[':alternative_phone'] = $altPhoneVal;
            }

            $districtVal = nts_normalizeNullable($data['district'] ?? null);
            if ($hasChanged('district', $districtVal)) {
                $updateFields[] = 'district = :district';
                $params[':district'] = $districtVal;
            }

            $qualificationVal = nts_normalizeNullable($data['qualification'] ?? null);
            if ($hasChanged('qualification', $qualificationVal)) {
                $updateFields[] = 'qualification = :qualification';
                $params[':qualification'] = $qualificationVal;
            }

            $universityVal = nts_normalizeNullable($data['university'] ?? null);
            if ($hasChanged('university', $universityVal)) {
                $updateFields[] = 'university = :university';
                $params[':university'] = $universityVal;
            }

            $yearGraduatedVal = nts_normalizeNullable($data['year_graduated'] ?? null);
            if ($hasChanged('year_graduated', $yearGraduatedVal)) {
                $updateFields[] = 'year_graduated = :year_graduated';
                $params[':year_graduated'] = $yearGraduatedVal;
            }

            $contractStartVal = nts_normalizeNullable($data['contract_start'] ?? null);
            if ($hasChanged('contract_start', $contractStartVal)) {
                $updateFields[] = 'contract_start = :contract_start';
                $params[':contract_start'] = $contractStartVal;
            }

            if ($passportPhoto !== null) {
                $updateFields[] = 'passport_photo = :passport_photo';
                $params[':passport_photo'] = $passportPhoto;
            }

            $phoneVal = nts_normalizeNullable($data['phone_number'] ?? null);
            if ($hasChanged('phone_number', $phoneVal)) {
                $updateFields[] = 'phone_number = :phone';
                $params[':phone'] = $phoneVal;
            }

            $emailVal = nts_normalizeNullable($data['email'] ?? null);
            if ($hasChanged('email', $emailVal)) {
                $updateFields[] = 'email = :email';
                $params[':email'] = $emailVal;
            }

            $addressVal = nts_normalizeNullable($data['address'] ?? null);
            if ($hasChanged('address', $addressVal)) {
                $updateFields[] = 'address = :address';
                $params[':address'] = $addressVal;
            }

            $emergencyVal = nts_normalizeNullable($data['emergency_contact'] ?? null);
            if ($hasChanged('emergency_contact', $emergencyVal)) {
                $updateFields[] = 'emergency_contact = :emergency';
                $params[':emergency'] = $emergencyVal;
            }

            $maritalVal = nts_normalizeNullable($data['marital_status'] ?? null);
            if ($hasChanged('marital_status', $maritalVal)) {
                $updateFields[] = 'marital_status = :marital';
                $params[':marital'] = $maritalVal;
            }

            $deptIdVal = isset($data['department_id']) && $data['department_id'] !== '' ? (int)$data['department_id'] : null;
            if ($hasChanged('department_id', $deptIdVal)) {
                $updateFields[] = 'department_id = :department_id';
                $params[':department_id'] = $deptIdVal;
            }

            $roleIdVal = isset($data['role_id']) && $data['role_id'] !== '' ? (int)$data['role_id'] : null;
            if ($hasChanged('role_id', $roleIdVal)) {
                $updateFields[] = 'role_id = :role_id';
                $params[':role_id'] = $roleIdVal;
            }

            $empTypeVal = nts_normalizeNullable($data['employment_type'] ?? null);
            if ($hasChanged('employment_type', $empTypeVal)) {
                $updateFields[] = 'employment_type = :employment_type';
                $params[':employment_type'] = $empTypeVal;
            }

            $dateJoinedVal = nts_normalizeNullable($data['date_joined'] ?? null);
            if ($hasChanged('date_joined', $dateJoinedVal)) {
                $updateFields[] = 'date_joined = :date_joined';
                $params[':date_joined'] = $dateJoinedVal;
            }

            $salaryVal = isset($data['salary']) && $data['salary'] !== '' ? (float)$data['salary'] : 0.0;
            if ($hasChanged('salary', $salaryVal)) {
                $updateFields[] = 'salary = :salary';
                $params[':salary'] = $salaryVal;
            }

            $statusVal = nts_normalizeNullable($data['status'] ?? $data['staff_status'] ?? null) ?? 'active';
            if ($hasChanged('status', $statusVal)) {
                $updateFields[] = 'status = :status';
                $params[':status'] = $statusVal;
            }

            $supervisorIdVal = isset($data['supervisor_id']) && $data['supervisor_id'] !== '' ? (int)$data['supervisor_id'] : null;
            if ($hasChanged('supervisor_id', $supervisorIdVal)) {
                $updateFields[] = 'supervisor_id = :supervisor_id';
                $params[':supervisor_id'] = $supervisorIdVal;
            }

            $bankNameVal = nts_normalizeNullable($data['bank_name'] ?? null);
            if ($hasChanged('bank_name', $bankNameVal)) {
                $updateFields[] = 'bank_name = :bank_name';
                $params[':bank_name'] = $bankNameVal;
            }

            $accountNumVal = nts_normalizeNullable($data['account_number'] ?? null);
            if ($hasChanged('account_number', $accountNumVal)) {
                $updateFields[] = 'account_number = :account_number';
                $params[':account_number'] = $accountNumVal;
            }

            $mobileMoneyVal = nts_normalizeNullable($data['mobile_money'] ?? null);
            if ($hasChanged('mobile_money', $mobileMoneyVal)) {
                $updateFields[] = 'mobile_money = :mobile_money';
                $params[':mobile_money'] = $mobileMoneyVal;
            }

            $tinVal = nts_normalizeNullable($data['tin_number'] ?? null);
            if ($hasChanged('tin_number', $tinVal)) {
                $updateFields[] = 'tin_number = :tin_number';
                $params[':tin_number'] = $tinVal;
            }

            $nssfVal = nts_normalizeNullable($data['nssf_number'] ?? null);
            if ($hasChanged('nssf_number', $nssfVal)) {
                $updateFields[] = 'nssf_number = :nssf_number';
                $params[':nssf_number'] = $nssfVal;
            }

            $empSql = "
                UPDATE employees SET
                    " . implode(', ', $updateFields) . "
                WHERE id = :id
                  AND staff_type = 'non_teaching'
            ";

            $empStmt = $db->prepare($empSql);
            $empStmt->execute($params);

            // Check if non_teaching_staff record exists first
            $checkStmt = $db->prepare("SELECT id FROM non_teaching_staff WHERE employee_id = :employee_id LIMIT 1");
            $checkStmt->execute([':employee_id' => $id]);
            $ntsExists = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($ntsExists) {
                // Update existing record
                $ntsUpdateSql = "
                    UPDATE non_teaching_staff SET
                        duty_assignment = :duty,
                        shift_schedule  = :shift,
                        specialization   = :special,
                        staff_status    = :staff_status
                    WHERE employee_id = :employee_id
                ";

                $ntsStmt = $db->prepare($ntsUpdateSql);
                $ntsStmt->execute([
                    ':employee_id' => $id,
                    ':duty'        => nts_normalizeNullable($data['duty_assignment'] ?? null),
                    ':shift'       => nts_normalizeNullable($data['shift_schedule'] ?? null),
                    ':special'     => nts_normalizeNullable($data['specialization'] ?? null),
                    ':staff_status'=> nts_normalizeNullable($data['staff_status'] ?? null) ?? 'active',
                ]);
            } else {
                // Insert new record
                $ntsInsertSql = "
                    INSERT INTO non_teaching_staff (
                        employee_id,
                        duty_assignment,
                        shift_schedule,
                        specialization,
                        staff_status
                    ) VALUES (
                        :employee_id,
                        :duty,
                        :shift,
                        :special,
                        :staff_status
                    )
                ";

                $ntsInsertStmt = $db->prepare($ntsInsertSql);
                $ntsInsertStmt->execute([
                    ':employee_id' => $id,
                    ':duty'        => nts_normalizeNullable($data['duty_assignment'] ?? null),
                    ':shift'       => nts_normalizeNullable($data['shift_schedule'] ?? null),
                    ':special'     => nts_normalizeNullable($data['specialization'] ?? null),
                    ':staff_status'=> nts_normalizeNullable($data['staff_status'] ?? null) ?? 'active',
                ]);
            }

            $db->commit();

            nts_sendJson(200, [
                'success' => true,
                'message' => 'Non-teaching staff updated successfully',
                'data'    => [
                    'id'            => $id,
                    'passport_photo'=> nts_publicPhotoUrl($passportPhoto),
                ],
            ]);
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            nts_sendJson(500, [
                'success' => false,
                'error'   => 'Failed to update non-teaching staff',
                'details' => $e->getMessage(),
            ]);
        }
    } elseif ($method === 'DELETE') {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

        if ($id <= 0) {
            nts_sendJson(400, [
                'success' => false,
                'error'   => 'Employee ID is required',
            ]);
        }

        try {
            $stmt = $db->prepare("
                UPDATE employees
                SET status = 'terminated'
                WHERE id = :id
                  AND staff_type = 'non_teaching'
            ");
            $stmt->execute([':id' => $id]);

            nts_sendJson(200, [
                'success' => true,
                'message' => 'Staff marked as terminated',
            ]);
        } catch (Throwable $e) {
            nts_sendJson(500, [
                'success' => false,
                'error'   => 'Failed to delete staff',
                'details' => $e->getMessage(),
            ]);
        }
    } else {
        nts_sendJson(405, [
            'success' => false,
            'error'   => 'Method not allowed',
        ]);
    }
} catch (PDOException $e) {
    nts_sendJson(500, [
        'success' => false,
        'error'   => 'Database error',
        'database_error' => $e->getMessage(),
    ]);
} catch (Throwable $e) {
    nts_sendJson(500, [
        'success' => false,
        'error'   => $e->getMessage(),
    ]);
}