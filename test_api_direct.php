<?php
// Test the actual API endpoint
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/accademics/backend/api/teachers.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, false);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response:\n";
echo $response . "\n";
