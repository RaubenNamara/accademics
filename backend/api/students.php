<?php
declare(strict_types=1);

require_once '../config/Database.php';
require_once '../config/JWT.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

define('STUDENT_UPLOAD_DIR', __DIR__ . '/../uploads/students');

function sendJson(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function ensureUploadDir(): void
{
    if (!is_dir(STUDENT_UPLOAD_DIR)) {
        mkdir(STUDENT_UPLOAD_DIR, 0777, true);
    }
}

function getAuthorizationHeader(): string
{
    $headers = [];

    if (function_exists('getallheaders')) {
        $headers = getallheaders();
    }

    if (!empty($headers['Authorization'])) {
        return trim((string)$headers['Authorization']);
    }

    if (!empty($headers['authorization'])) {
        return trim((string)$headers['authorization']);
    }

    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        return trim((string)$_SERVER['HTTP_AUTHORIZATION']);
    }

    if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        return trim((string)$_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
    }

    return '';
}

function optionalAuth(): void
{
    $auth = getAuthorizationHeader();

    if ($auth === '') {
        return;
    }

    $token = preg_replace('/^Bearer\s+/i', '', $auth);
    $jwt = new JWT();
    $payload = $jwt->decode($token);

    if (!$payload) {
        sendJson(401, [
            'success' => false,
            'error' => 'Invalid or expired token'
        ]);
    }
}

function bindValues(PDOStatement $stmt, array $params): void
{
    foreach ($params as $key => $value) {
        $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stmt->bindValue($key, $value, $type);
    }
}

function sanitizeFilename(string $name): string
{
    $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
    return trim($name, '_');
}

function saveUploadedFile(string $fieldName): ?string
{
    if (empty($_FILES[$fieldName]) || !isset($_FILES[$fieldName]['error'])) {
        return null;
    }

    $file = $_FILES[$fieldName];

    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Upload failed for {$fieldName}");
    }

    $allowedExt = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
    $originalName = (string)$file['name'];
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if (!in_array($extension, $allowedExt, true)) {
        throw new Exception("Invalid file type for {$fieldName}");
    }

    ensureUploadDir();

    $baseName = pathinfo($originalName, PATHINFO_FILENAME);
    $safeName = sanitizeFilename($baseName);
    $uniqueName = $safeName . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;

    $relativePath = 'uploads/students/' . $uniqueName;
    $targetPath = STUDENT_UPLOAD_DIR . '/' . $uniqueName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception("Could not save uploaded file for {$fieldName}");
    }

    return $relativePath;
}

function saveMultipleUploadedFiles(string $fieldName): array
{
    $uploadedFiles = [];

    if (empty($_FILES[$fieldName]) || !isset($_FILES[$fieldName]['error'])) {
        return $uploadedFiles;
    }

    $files = $_FILES[$fieldName];

    // Handle multiple files
    if (is_array($files['name'])) {
        $fileCount = count($files['name']);
        for ($i = 0; $i < $fileCount; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                throw new Exception("Upload failed for {$fieldName}[{$i}]");
            }

            $allowedExt = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
            $originalName = (string)$files['name'][$i];
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

            if (!in_array($extension, $allowedExt, true)) {
                throw new Exception("Invalid file type for {$fieldName}[{$i}]");
            }

            ensureUploadDir();

            $baseName = pathinfo($originalName, PATHINFO_FILENAME);
            $safeName = sanitizeFilename($baseName);
            $uniqueName = $safeName . '_' . time() . '_' . bin2hex(random_bytes(4)) . '_' . $i . '.' . $extension;

            $relativePath = 'uploads/students/' . $uniqueName;
            $targetPath = STUDENT_UPLOAD_DIR . '/' . $uniqueName;

            if (!move_uploaded_file($files['tmp_name'][$i], $targetPath)) {
                throw new Exception("Could not save uploaded file for {$fieldName}[{$i}]");
            }

            $uploadedFiles[] = [
                'filename' => $originalName,
                'file_path' => $relativePath,
                'file_size' => $files['size'][$i]
            ];
        }
    }

    return $uploadedFiles;
}

function deleteFileIfExists(?string $path): void
{
    if (!$path) {
        return;
    }

    $fullPath = __DIR__ . '/../' . ltrim($path, '/');
    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}

try {
    $database = new Database();
    $db = $database->getConnection();

    optionalAuth();

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $action = $_GET['action'] ?? '';

    if ($method === 'GET') {
        if ($action === 'view') {
            getStudent($db);
        } elseif ($action === 'search') {
            searchStudents($db);
        } elseif ($action === 'export') {
            exportStudents($db);
        } else {
            getStudents($db);
        }
    } elseif ($method === 'POST') {
        if ($action === 'create') {
            createStudent($db);
        } elseif ($action === 'update') {
            updateStudent($db);
        } else {
            sendJson(400, ['success' => false, 'error' => 'Invalid action']);
        }
    } elseif ($method === 'DELETE') {
        if ($action === 'delete') {
            deleteStudent($db);
        } else {
            sendJson(400, ['success' => false, 'error' => 'Invalid action']);
        }
    } else {
        sendJson(405, ['success' => false, 'error' => 'Method not allowed']);
    }
} catch (PDOException $e) {
    sendJson(500, [
        'success' => false,
        'error' => 'Database error',
        'database_error' => $e->getMessage()
    ]);
} catch (Throwable $e) {
    sendJson(500, [
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function getStudents(PDO $db): void
{
    $class  = trim((string)($_GET['class'] ?? ''));
    $stream = trim((string)($_GET['stream'] ?? ''));
    $level  = trim((string)($_GET['level'] ?? ''));
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = max(1, min(100, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    $conditions = [];
    $params = [];

    if ($class !== '') {
        $conditions[] = '`class` = :class';
        $params[':class'] = $class;
    }

    if ($stream !== '') {
        $conditions[] = 'stream = :stream';
        $params[':stream'] = $stream;
    }

    if ($level !== '') {
        $conditions[] = 'level = :level';
        $params[':level'] = $level;
    }

    $whereSql = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

    $countSql = "SELECT COUNT(*) AS total FROM students $whereSql";
    $countStmt = $db->prepare($countSql);
    bindValues($countStmt, $params);
    $countStmt->execute();
    $total = (int)($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

    $sql = "
        SELECT
            s.id,
            s.admission_number,
            s.full_name,
            s.gender,
            s.date_of_birth,
            s.`class`,
            s.stream,
            s.level,
            s.enrollment_date,
            s.former_school,
            s.former_school_support_doc,
            s.behaviour_notes,
            s.behaviour_document,
            s.medical_notes,
            s.special_needs,
            s.profile_picture,
            (
                SELECT COUNT(*)
                FROM parents p
                WHERE p.student_id = s.id
            ) AS parent_count
        FROM students s
        $whereSql
        ORDER BY s.full_name ASC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $db->prepare($sql);
    bindValues($stmt, $params);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    sendJson(200, [
        'success' => true,
        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => max(1, (int)ceil($total / $limit))
        ]
    ]);
}

function exportStudents(PDO $db): void
{
    $class  = trim((string)($_GET['class'] ?? ''));
    $stream = trim((string)($_GET['stream'] ?? ''));
    $level  = trim((string)($_GET['level'] ?? ''));

    $conditions = [];
    $params = [];

    if ($class !== '') {
        $conditions[] = '`class` = :class';
        $params[':class'] = $class;
    }

    if ($stream !== '') {
        $conditions[] = 'stream = :stream';
        $params[':stream'] = $stream;
    }

    if ($level !== '') {
        $conditions[] = 'level = :level';
        $params[':level'] = $level;
    }

    $whereSql = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

    $sql = "
        SELECT
            s.admission_number,
            s.full_name,
            s.gender,
            s.date_of_birth,
            s.`class`,
            s.stream,
            s.level,
            s.enrollment_date,
            s.lin,
            s.former_school,
            (
                SELECT COUNT(*)
                FROM parents p
                WHERE p.student_id = s.id
            ) AS parent_count
        FROM students s
        $whereSql
        ORDER BY s.full_name ASC
    ";

    $stmt = $db->prepare($sql);
    bindValues($stmt, $params);
    $stmt->execute();

    sendJson(200, [
        'success' => true,
        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

function getStudent(PDO $db): void
{
    $id = (int)($_GET['id'] ?? 0);

    if ($id <= 0) {
        sendJson(400, ['success' => false, 'error' => 'Student ID is required']);
    }

    $stmt = $db->prepare("SELECT *, profile_picture FROM students WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        sendJson(404, ['success' => false, 'error' => 'Student not found']);
    }

    $parentStmt = $db->prepare("SELECT * FROM parents WHERE student_id = :id ORDER BY is_primary_contact DESC, full_name ASC");
    $parentStmt->execute([':id' => $id]);
    $parents = $parentStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get discipline documents
    $disciplineStmt = $db->prepare("SELECT * FROM student_discipline_documents WHERE student_id = :id ORDER BY uploaded_at DESC");
    $disciplineStmt->execute([':id' => $id]);
    $disciplineDocuments = $disciplineStmt->fetchAll(PDO::FETCH_ASSOC);

    $student['behaviour_documents'] = $disciplineDocuments;

    // Get former school documents
    $formerSchoolStmt = $db->prepare("SELECT * FROM student_former_school_documents WHERE student_id = :id ORDER BY uploaded_at DESC");
    $formerSchoolStmt->execute([':id' => $id]);
    $formerSchoolDocuments = $formerSchoolStmt->fetchAll(PDO::FETCH_ASSOC);

    $student['former_school_documents'] = $formerSchoolDocuments;

    $resultsSql = "
        SELECT
            sr.*,
            sub.subject_name,
            sub.subject_code
        FROM student_results sr
        LEFT JOIN subjects sub ON sr.subject_id = sub.id
        WHERE sr.student_id = :id
        ORDER BY sr.year DESC, sr.term DESC, sr.exam_type ASC, sub.subject_name ASC
    ";
    $resultsStmt = $db->prepare($resultsSql);
    $resultsStmt->execute([':id' => $id]);
    $results = $resultsStmt->fetchAll(PDO::FETCH_ASSOC);

    sendJson(200, [
        'success' => true,
        'data' => [
            'student' => $student,
            'parents' => $parents,
            'results' => $results
        ]
    ]);
}

function searchStudents(PDO $db): void
{
    $search = trim((string)($_GET['q'] ?? ''));

    if (mb_strlen($search) < 2) {
        sendJson(400, ['success' => false, 'error' => 'Search term must be at least 2 characters']);
    }

    $term = '%' . $search . '%';

    $sql = "
        SELECT
            s.id,
            s.admission_number,
            s.full_name,
            s.gender,
            s.`class`,
            s.stream,
            s.level,
            s.former_school,
            s.former_school_support_doc,
            s.behaviour_notes,
            s.behaviour_document,
            s.profile_picture,
            (
                SELECT COUNT(*)
                FROM parents p
                WHERE p.student_id = s.id
            ) AS parent_count
        FROM students s
        WHERE s.full_name LIKE :term OR s.admission_number LIKE :term
        ORDER BY s.full_name ASC
        LIMIT 20
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute([':term' => $term]);

    sendJson(200, [
        'success' => true,
        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

function createStudent(PDO $db): void
{
    $data = $_POST;
    $data['parents'] = [];

    if (!empty($_POST['parents'])) {
        $decoded = json_decode($_POST['parents'], true);
        if (is_array($decoded)) {
            $data['parents'] = $decoded;
        }
    }

    $required = ['admission_number', 'full_name', 'gender', 'class', 'stream', 'level'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
            sendJson(400, ['success' => false, 'error' => "{$field} is required"]);
        }
    }

    $check = $db->prepare("SELECT id FROM students WHERE admission_number = ?");
    $check->execute([$data['admission_number']]);

    if ($check->fetch()) {
        sendJson(409, ['success' => false, 'error' => 'Admission number already exists']);
    }

    // Handle multiple former school documents
    $formerSchoolDocuments = saveMultipleUploadedFiles('former_school_documents');

    // Handle multiple discipline documents
    $disciplineDocuments = saveMultipleUploadedFiles('behaviour_documents');

    // Handle profile picture
    $profilePicturePath = saveUploadedFile('profile_picture');

    $query = "
        INSERT INTO students (
            admission_number,
            full_name,
            gender,
            date_of_birth,
            `class`,
            stream,
            level,
            enrollment_date,
            lin,
            former_school,
            former_school_support_doc,
            behaviour_notes,
            medical_notes,
            special_needs,
            profile_picture
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";

    $stmt = $db->prepare($query);
    $ok = $stmt->execute([
        $data['admission_number'],
        $data['full_name'],
        $data['gender'],
        $data['date_of_birth'],
        $data['class'],
        $data['stream'],
        $data['level'],
        $data['enrollment_date'] ?? date('Y-m-d'),
        $data['lin'] ?? '',
        $data['former_school'] ?? '',
        $formerSchoolDoc,
        $data['behaviour_notes'] ?? '',
        $data['medical_notes'] ?? '',
        $data['special_needs'] ?? '',
        $profilePicturePath
    ]);

    if (!$ok) {
        sendJson(500, [
            'success' => false,
            'error' => 'Failed to save student',
            'db_error' => $stmt->errorInfo()
        ]);
    }

    $studentId = (int)$db->lastInsertId();

    // Save former school documents
    if (!empty($formerSchoolDocuments)) {
        $docStmt = $db->prepare("
            INSERT INTO student_former_school_documents (
                student_id, filename, file_path, file_size
            ) VALUES (?, ?, ?, ?)
        ");
        foreach ($formerSchoolDocuments as $doc) {
            $docStmt->execute([
                $studentId,
                $doc['filename'],
                $doc['file_path'],
                $doc['file_size']
            ]);
        }
    }

    // Save discipline documents
    if (!empty($disciplineDocuments)) {
        $docStmt = $db->prepare("
            INSERT INTO student_discipline_documents (
                student_id, filename, file_path, file_size
            ) VALUES (?, ?, ?, ?)
        ");
        foreach ($disciplineDocuments as $doc) {
            $docStmt->execute([
                $studentId,
                $doc['filename'],
                $doc['file_path'],
                $doc['file_size']
            ]);
        }
    }

    if (!empty($data['parents'])) {
        foreach ($data['parents'] as $parent) {
            if (empty($parent['full_name']) || empty($parent['phone'])) {
                continue;
            }

            $parentQuery = "
                INSERT INTO parents (
                    student_id,
                    full_name,
                    relationship,
                    phone,
                    email,
                    nin,
                    address,
                    is_primary_contact
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ";

            $parentStmt = $db->prepare($parentQuery);
            $parentStmt->execute([
                $studentId,
                $parent['full_name'],
                $parent['relationship'] ?? 'guardian',
                $parent['phone'],
                $parent['email'] ?? '',
                $parent['nin'] ?? '',
                $parent['address'] ?? '',
                !empty($parent['is_primary_contact']) ? 1 : 0
            ]);
        }
    }

    sendJson(201, [
        'success' => true,
        'message' => 'Student saved successfully',
        'student_id' => $studentId
    ]);
}

function updateStudent(PDO $db): void
{
    $data = $_POST;
    $data['parents'] = [];

    if (!empty($_POST['parents'])) {
        $decoded = json_decode($_POST['parents'], true);
        if (is_array($decoded)) {
            $data['parents'] = $decoded;
        }
    }

    $id = (int)($data['id'] ?? 0);

    if ($id <= 0) {
        sendJson(400, ['success' => false, 'error' => 'Student ID is required']);
    }

    $existingStmt = $db->prepare("SELECT * FROM students WHERE id = ?");
    $existingStmt->execute([$id]);
    $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        sendJson(404, ['success' => false, 'error' => 'Student not found']);
    }

    $required = ['admission_number', 'full_name', 'gender', 'class', 'stream', 'level'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
            sendJson(400, ['success' => false, 'error' => "{$field} is required"]);
        }
    }

    $dup = $db->prepare("SELECT id FROM students WHERE admission_number = ? AND id != ?");
    $dup->execute([$data['admission_number'], $id]);

    if ($dup->fetch()) {
        sendJson(409, ['success' => false, 'error' => 'Admission number already exists']);
    }

    // Handle multiple former school documents
    $newFormerSchoolDocuments = saveMultipleUploadedFiles('former_school_documents');

    // Handle existing former school documents to keep
    $existingFormerSchoolDocsToKeep = [];
    if (!empty($_POST['existing_former_school_documents'])) {
        $decoded = json_decode($_POST['existing_former_school_documents'], true);
        if (is_array($decoded)) {
            $existingFormerSchoolDocsToKeep = $decoded;
        }
    }

    // Handle multiple discipline documents
    $newDisciplineDocuments = saveMultipleUploadedFiles('behaviour_documents');

    // Handle existing documents to keep
    $existingDocumentsToKeep = [];
    if (!empty($_POST['existing_behaviour_documents'])) {
        $decoded = json_decode($_POST['existing_behaviour_documents'], true);
        if (is_array($decoded)) {
            $existingDocumentsToKeep = $decoded;
        }
    }

    // Handle profile picture
    $profilePicturePath = saveUploadedFile('profile_picture');
    
    // If no new profile picture uploaded, keep existing one
    if ($profilePicturePath === null && !empty($_POST['existing_profile_picture'])) {
        $profilePicturePath = $_POST['existing_profile_picture'];
    }

    $query = "
        UPDATE students SET
            admission_number = ?,
            full_name = ?,
            gender = ?,
            date_of_birth = ?,
            `class` = ?,
            stream = ?,
            level = ?,
            enrollment_date = ?,
            lin = ?,
            former_school = ?,
            behaviour_notes = ?,
            medical_notes = ?,
            special_needs = ?,
            profile_picture = ?
        WHERE id = ?
    ";

    $stmt = $db->prepare($query);
    $ok = $stmt->execute([
        $data['admission_number'],
        $data['full_name'],
        $data['gender'],
        $data['date_of_birth'],
        $data['class'],
        $data['stream'],
        $data['level'],
        $data['enrollment_date'] ?? date('Y-m-d'),
        $data['lin'] ?? '',
        $data['former_school'] ?? '',
        $data['behaviour_notes'] ?? '',
        $data['medical_notes'] ?? '',
        $data['special_needs'] ?? '',
        $profilePicturePath,
        $id
    ]);

    if (!$ok) {
        sendJson(500, [
            'success' => false,
            'error' => 'Failed to update student',
            'db_error' => $stmt->errorInfo()
        ]);
    }

    // Update former school documents
    // First, get all existing documents
    $allFormerSchoolDocsStmt = $db->prepare("SELECT * FROM student_former_school_documents WHERE student_id = ?");
    $allFormerSchoolDocsStmt->execute([$id]);
    $allFormerSchoolDocs = $allFormerSchoolDocsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Delete documents not in the keep list
    $keepFormerSchoolIds = array_column($existingFormerSchoolDocsToKeep, 'id');
    foreach ($allFormerSchoolDocs as $doc) {
        if (!in_array($doc['id'], $keepFormerSchoolIds)) {
            // Delete file
            deleteFileIfExists($doc['file_path']);
            // Delete from database
            $db->prepare("DELETE FROM student_former_school_documents WHERE id = ?")->execute([$doc['id']]);
        }
    }

    // Add new documents
    if (!empty($newFormerSchoolDocuments)) {
        $docStmt = $db->prepare("
            INSERT INTO student_former_school_documents (
                student_id, filename, file_path, file_size
            ) VALUES (?, ?, ?, ?)
        ");
        foreach ($newFormerSchoolDocuments as $doc) {
            $docStmt->execute([
                $id,
                $doc['filename'],
                $doc['file_path'],
                $doc['file_size']
            ]);
        }
    }

    // Update discipline documents
    // First, get all existing documents
    $allExistingDocsStmt = $db->prepare("SELECT * FROM student_discipline_documents WHERE student_id = ?");
    $allExistingDocsStmt->execute([$id]);
    $allExistingDocs = $allExistingDocsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Delete documents not in the keep list
    $keepIds = array_column($existingDocumentsToKeep, 'id');
    foreach ($allExistingDocs as $doc) {
        if (!in_array($doc['id'], $keepIds)) {
            // Delete file
            deleteFileIfExists($doc['file_path']);
            // Delete from database
            $db->prepare("DELETE FROM student_discipline_documents WHERE id = ?")->execute([$doc['id']]);
        }
    }

    // Add new documents
    if (!empty($newDisciplineDocuments)) {
        $docStmt = $db->prepare("
            INSERT INTO student_discipline_documents (
                student_id, filename, file_path, file_size
            ) VALUES (?, ?, ?, ?)
        ");
        foreach ($newDisciplineDocuments as $doc) {
            $docStmt->execute([
                $id,
                $doc['filename'],
                $doc['file_path'],
                $doc['file_size']
            ]);
        }
    }

    $db->prepare("DELETE FROM parents WHERE student_id = ?")->execute([$id]);

    if (!empty($data['parents'])) {
        foreach ($data['parents'] as $parent) {
            if (empty($parent['full_name']) || empty($parent['phone'])) {
                continue;
            }

            $parentQuery = "
                INSERT INTO parents (
                    student_id,
                    full_name,
                    relationship,
                    phone,
                    email,
                    nin,
                    address,
                    is_primary_contact
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ";

            $parentStmt = $db->prepare($parentQuery);
            $parentStmt->execute([
                $id,
                $parent['full_name'],
                $parent['relationship'] ?? 'guardian',
                $parent['phone'],
                $parent['email'] ?? '',
                $parent['nin'] ?? '',
                $parent['address'] ?? '',
                !empty($parent['is_primary_contact']) ? 1 : 0
            ]);
        }
    }

    sendJson(200, [
        'success' => true,
        'message' => 'Student updated successfully'
    ]);
}

function deleteStudent(PDO $db): void
{
    $id = (int)($_GET['id'] ?? 0);

    if ($id <= 0) {
        sendJson(400, ['success' => false, 'error' => 'Student ID is required']);
    }

    $stmt = $db->prepare("SELECT former_school_support_doc FROM students WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        sendJson(404, ['success' => false, 'error' => 'Student not found']);
    }

    // Get and delete all discipline documents
    $disciplineStmt = $db->prepare("SELECT file_path FROM student_discipline_documents WHERE student_id = ?");
    $disciplineStmt->execute([$id]);
    $disciplineDocs = $disciplineStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($disciplineDocs as $doc) {
        deleteFileIfExists($doc['file_path'] ?? null);
    }

    // Get and delete all former school documents
    $formerSchoolStmt = $db->prepare("SELECT file_path FROM student_former_school_documents WHERE student_id = ?");
    $formerSchoolStmt->execute([$id]);
    $formerSchoolDocs = $formerSchoolStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($formerSchoolDocs as $doc) {
        deleteFileIfExists($doc['file_path'] ?? null);
    }

    $db->prepare("DELETE FROM parents WHERE student_id = ?")->execute([$id]);
    $db->prepare("DELETE FROM student_discipline_documents WHERE student_id = ?")->execute([$id]);
    $db->prepare("DELETE FROM student_former_school_documents WHERE student_id = ?")->execute([$id]);
    $db->prepare("DELETE FROM students WHERE id = ?")->execute([$id]);

    sendJson(200, [
        'success' => true,
        'message' => 'Student deleted successfully'
    ]);
}