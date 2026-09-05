<?php
// Test the lesson monitoring API
$url = 'http://localhost/accademics/backend/api/lesson-monitoring.php';

// Test GET request
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url . '?year=2026&term=1');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer test'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n";
?>
