<?php
// Test the leave API endpoint directly
$url = 'http://localhost/accademics/backend/api/leave.php';
$data = [
    'employee_id' => 17,
    'leave_type' => 'annual',
    'start_date' => '2026-05-26',
    'end_date' => '2026-05-28',
    'reason' => 'Test API call'
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "HTTP Code: " . $httpCode . "\n";
if ($curlError) {
    echo "cURL Error: " . $curlError . "\n";
}
echo "Response: " . $response . "\n";
