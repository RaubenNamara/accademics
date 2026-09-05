<?php
date_default_timezone_set('Africa/Kampala');

require_once __DIR__ . '/../config/Database.php';

$apiUrl = "https://smisug.com/STMARKLIBRARYAPI/SLISLIBRARYAPI/api/student";

// ---------------- DATABASE CONNECTION ----------------
$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    die("Database connection failed.");
}

// ---------------- FETCH API DATA ----------------
function fetchApiData($url)
{
    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => [
            "Accept: application/json"
        ]
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        die("cURL Error: " . curl_error($ch));
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        die("API request failed. HTTP Code: " . $httpCode);
    }

    $data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        die("Invalid JSON response from API: " . json_last_error_msg());
    }

    if (is_array($data) && array_is_list($data)) {
        return $data;
    }

    if (isset($data['data']) && is_array($data['data'])) {
        return $data['data'];
    }

    if (isset($data['students']) && is_array($data['students'])) {
        return $data['students'];
    }

    if (isset($data['items']) && is_array($data['items'])) {
        return $data['items'];
    }

    if (is_array($data)) {
        return [$data];
    }

    return [];
}

// ---------------- DETERMINE LEVEL ----------------
function determineLevel($class)
{
    if (!$class) {
        return null;
    }

    $class = strtoupper(trim($class));
    $class = str_replace([' ', '.', '-'], '', $class);

    if (in_array($class, ['S1', 'S2', 'S3', 'S4'])) {
        return 'O-Level';
    }

    if (in_array($class, ['S5', 'S6'])) {
        return 'A-Level';
    }

    return null;
}

// ---------------- SAFE TRIM ----------------
function safeTrim($value)
{
    if ($value === null) {
        return null;
    }

    $value = trim((string)$value);
    return $value === '' ? null : $value;
}

// ---------------- SYNC CLASS + STREAM ----------------
function syncClassStream(PDO $conn, ?string $class, ?string $stream, array &$stats): void
{
    $class = safeTrim($class);
    $stream = safeTrim($stream);

    if (!$class || !$stream) {
        $stats['class_skipped']++;
        return;
    }

    $fullClassName = trim($class . ' ' . $stream);

    $checkSql = "
        SELECT id
        FROM classes
        WHERE class_name = :class_name
          AND stream_name = :stream_name
        LIMIT 1
    ";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->execute([
        ':class_name' => $class,
        ':stream_name' => $stream
    ]);

    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $updateSql = "
            UPDATE classes
            SET full_class_name = :full_class_name,
                updated_at = NOW()
            WHERE id = :id
        ";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->execute([
            ':full_class_name' => $fullClassName,
            ':id' => $existing['id']
        ]);
        $stats['class_updated']++;
    } else {
        $insertSql = "
            INSERT INTO classes (
                class_name,
                stream_name,
                full_class_name,
                created_at,
                updated_at
            ) VALUES (
                :class_name,
                :stream_name,
                :full_class_name,
                NOW(),
                NOW()
            )
        ";
        $insertStmt = $conn->prepare($insertSql);
        $insertStmt->execute([
            ':class_name' => $class,
            ':stream_name' => $stream,
            ':full_class_name' => $fullClassName
        ]);
        $stats['class_inserted']++;
    }
}

// ---------------- GET STUDENTS FROM API ----------------
$students = fetchApiData($apiUrl);

if (empty($students)) {
    die("No student data found from API.");
}

// ---------------- SQL STATEMENTS ----------------
$checkSql = "SELECT id FROM students WHERE admission_number = :admission_number LIMIT 1";
$checkStmt = $conn->prepare($checkSql);

$insertSql = "
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
        behaviour_notes,
        behaviour_document,
        medical_notes,
        special_needs,
        profile_picture,
        former_school,
        former_school_support_doc,
        is_active,
        created_at,
        updated_at
    ) VALUES (
        :admission_number,
        :full_name,
        :gender,
        :date_of_birth,
        :class,
        :stream,
        :level,
        :enrollment_date,
        :lin,
        :behaviour_notes,
        :behaviour_document,
        :medical_notes,
        :special_needs,
        :profile_picture,
        :former_school,
        :former_school_support_doc,
        :is_active,
        NOW(),
        NOW()
    )
";
$insertStmt = $conn->prepare($insertSql);

$updateSql = "
    UPDATE students SET
        full_name = :full_name,
        gender = :gender,
        date_of_birth = :date_of_birth,
        `class` = :class,
        stream = :stream,
        level = :level,
        enrollment_date = :enrollment_date,
        lin = :lin,
        behaviour_notes = :behaviour_notes,
        behaviour_document = :behaviour_document,
        medical_notes = :medical_notes,
        special_needs = :special_needs,
        profile_picture = :profile_picture,
        former_school = :former_school,
        former_school_support_doc = :former_school_support_doc,
        is_active = :is_active,
        updated_at = NOW()
    WHERE admission_number = :admission_number
";
$updateStmt = $conn->prepare($updateSql);

// ---------------- COUNTERS ----------------
$inserted = 0;
$updated = 0;
$skipped = 0;
$failed = 0;
$classInserted = 0;
$classUpdated = 0;
$classSkipped = 0;

// ---------------- LOOP THROUGH API DATA ----------------
foreach ($students as $student) {
    if (!is_array($student)) {
        $skipped++;
        continue;
    }

    $admission_number = safeTrim($student['RegNo'] ?? null);
    $full_name        = safeTrim($student['Name'] ?? null);
    $class            = safeTrim($student['Class'] ?? null);
    $stream           = safeTrim($student['Stream'] ?? null);

    if (!$admission_number || !$full_name || !$class || !$stream) {
        $skipped++;
        continue;
    }

    $level = determineLevel($class);

    try {
        // Sync class + stream into classes table first
        $classStats = [
            'class_inserted' => 0,
            'class_updated' => 0,
            'class_skipped' => 0
        ];
        syncClassStream($conn, $class, $stream, $classStats);
        $classInserted += $classStats['class_inserted'];
        $classUpdated  += $classStats['class_updated'];
        $classSkipped   += $classStats['class_skipped'];

        // Default values for missing API fields
        $gender = null;
        $date_of_birth = null;
        $enrollment_date = null;
        $lin = null;
        $behaviour_notes = null;
        $behaviour_document = null;
        $medical_notes = null;
        $special_needs = null;
        $profile_picture = null;
        $former_school = null;
        $former_school_support_doc = null;
        $is_active = 1;

        // Check if student already exists
        $checkStmt->execute([
            ':admission_number' => $admission_number
        ]);

        if ($checkStmt->fetch()) {
            $updateStmt->execute([
                ':admission_number' => $admission_number,
                ':full_name' => $full_name,
                ':gender' => $gender,
                ':date_of_birth' => $date_of_birth,
                ':class' => $class,
                ':stream' => $stream,
                ':level' => $level,
                ':enrollment_date' => $enrollment_date,
                ':lin' => $lin,
                ':behaviour_notes' => $behaviour_notes,
                ':behaviour_document' => $behaviour_document,
                ':medical_notes' => $medical_notes,
                ':special_needs' => $special_needs,
                ':profile_picture' => $profile_picture,
                ':former_school' => $former_school,
                ':former_school_support_doc' => $former_school_support_doc,
                ':is_active' => $is_active
            ]);

            $updated++;
            echo "Updated: " . htmlspecialchars($full_name) . "<br>";
        } else {
            $insertStmt->execute([
                ':admission_number' => $admission_number,
                ':full_name' => $full_name,
                ':gender' => $gender,
                ':date_of_birth' => $date_of_birth,
                ':class' => $class,
                ':stream' => $stream,
                ':level' => $level,
                ':enrollment_date' => $enrollment_date,
                ':lin' => $lin,
                ':behaviour_notes' => $behaviour_notes,
                ':behaviour_document' => $behaviour_document,
                ':medical_notes' => $medical_notes,
                ':special_needs' => $special_needs,
                ':profile_picture' => $profile_picture,
                ':former_school' => $former_school,
                ':former_school_support_doc' => $former_school_support_doc,
                ':is_active' => $is_active
            ]);

            $inserted++;
            echo "Inserted: " . htmlspecialchars($full_name) . "<br>";
        }
    } catch (PDOException $e) {
        $failed++;
        echo "Failed to save " . htmlspecialchars($full_name) . " - " . $e->getMessage() . "<br>";
    } catch (Throwable $e) {
        $failed++;
        echo "Failed to process " . htmlspecialchars($full_name) . " - " . $e->getMessage() . "<br>";
    }
}

echo "<hr>";
echo "<h3>Import Completed</h3>";
echo "Inserted: " . $inserted . "<br>";
echo "Updated: " . $updated . "<br>";
echo "Skipped: " . $skipped . "<br>";
echo "Failed: " . $failed . "<br>";
echo "Classes Inserted: " . $classInserted . "<br>";
echo "Classes Updated: " . $classUpdated . "<br>";
echo "Class Rows Skipped: " . $classSkipped . "<br>";
?>