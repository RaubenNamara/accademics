<?php
require_once 'backend/config/Database.php';

$database = new Database();
$db = $database->getConnection();

// Simulate the API call
$query = "SELECT * FROM teachers WHERE 1=1 ORDER BY is_active DESC, full_name ASC LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute();
$teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Direct query result:\n";
print_r($teachers);

echo "\n\nSimulating API response structure:\n";
$response = [
    'success' => true,
    'data' => [
        'teachers' => $teachers,
        'pagination' => [
            'page' => 1,
            'limit' => 10,
            'total' => count($teachers),
            'totalPages' => 1
        ]
    ]
];
print_r($response);
