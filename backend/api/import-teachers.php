<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/Database.php';

$database = new Database();
$db = $database->getConnection();

function respond($success, $message = '', $data = null, $status = 200) {
    http_response_code($status);
    $payload = ['success' => $success];
    if ($message !== '') $payload['message'] = $message;
    if ($data !== null) $payload['data'] = $data;
    echo json_encode($payload);
    exit();
}

$raw = file_get_contents("php://input");
$input = json_decode($raw, true);

if (!is_array($input) || !isset($input['teachers']) || !is_array($input['teachers'])) {
    respond(false, 'Teachers array is required', null, 400);
}

$teachers = $input['teachers'];
$imported = 0;
$skipped = 0;

$checkStmt = $db->prepare("SELECT id FROM teachers WHERE email = :email LIMIT 1");
$insertStmt = $db->prepare("
    INSERT INTO teachers
    (full_name, email, contact, subject, obligation, class, stream, is_active, created_at, updated_at)
    VALUES
    (:full_name, :email, :contact, :subject, :obligation, :class, :stream, :is_active, NOW(), NOW())
");

foreach ($teachers as $row) {
    $full_name = trim($row['full_name'] ?? '');
    $email = trim($row['email'] ?? '');

    if ($full_name === '' || $email === '') {
        $skipped++;
        continue;
    }

    $checkStmt->execute([':email' => $email]);
    if ($checkStmt->fetch(PDO::FETCH_ASSOC)) {
        $skipped++;
        continue;
    }

    $insertStmt->execute([
        ':full_name' => $full_name,
        ':email' => $email,
        ':contact' => trim($row['contact'] ?? ''),
        ':subject' => trim($row['subject'] ?? ''),
        ':obligation' => trim($row['obligation'] ?? 'Subject Teacher'),
        ':class' => trim($row['class'] ?? ''),
        ':stream' => trim($row['stream'] ?? ''),
        ':is_active' => isset($row['is_active']) ? (int)$row['is_active'] : 1
    ]);

    $imported++;
}

respond(true, 'Import completed', [
    'imported' => $imported,
    'skipped' => $skipped
], 201);