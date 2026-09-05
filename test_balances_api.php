<?php
// Test the balances API endpoint directly
$url = 'http://localhost/accademics/backend/api/leave.php?action=balances&year=2026';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
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

// Parse and display the data
$data = json_decode($response, true);
if ($data && isset($data['success']) && $data['success']) {
    echo "\nBalances data received successfully\n";
    echo "Total records: " . count($data['data']) . "\n";
    
    // Show first 5 records
    echo "\nFirst 5 records:\n";
    for ($i = 0; $i < min(5, count($data['data'])); $i++) {
        $bal = $data['data'][$i];
        echo "  " . $bal['first_name'] . " " . $bal['last_name'] . " - " . $bal['leave_type'] . "\n";
        echo "    Entitled: " . $bal['entitled_days'] . ", Used: " . $bal['used_days'] . ", Remaining: " . $bal['remaining_days'] . "\n";
    }
} else {
    echo "\nFailed to get balances data\n";
}
