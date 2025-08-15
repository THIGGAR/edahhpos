<?php
require_once '../vendor/autoload.php';
$config = require_once 'config.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

// Test script to verify PayChangu API connectivity
$secret_key = trim($config['paychangu']['secret_key'] ?? '');
$transaction_ref = 'your_test_transaction_ref'; // Replace with a valid transaction_ref from a test payment
$environment = $config['paychangu']['environment'] ?? 'production';
$api_endpoint = ($environment === 'sandbox' ? 'https://api-sandbox.paychangu.com' : 'https://api.paychangu.com') . "/verify-payment/{$transaction_ref}";

if (empty($secret_key)) {
    echo "Error: PayChangu secret key is missing or empty in config.php\n";
    exit();
}

$client = new Client([
    'timeout' => 10.0,
    'connect_timeout' => 5.0,
]);

echo "Testing PayChangu API with transaction_ref: $transaction_ref\n";
echo "API Endpoint: $api_endpoint\n";
echo "Secret Key (masked): " . substr($secret_key, 0, 4) . str_repeat('*', strlen($secret_key) - 8) . substr($secret_key, -4) . "\n";

try {
    $response = $client->request('GET', $api_endpoint, [
        'headers' => [
            'Authorization' => 'Bearer ' . $secret_key,
            'Accept' => 'application/json',
            'User-Agent' => 'EDAHHPOS-Test/1.0',
        ],
    ]);

    $status_code = $response->getStatusCode();
    $response_body = $response->getBody()->getContents();
    echo "Status Code: $status_code\n";
    echo "Response Body: $response_body\n";

    $data = json_decode($response_body, true);
    if ($data === null) {
        echo "Error: Invalid JSON response from PayChangu API\n";
    } else {
        echo "Parsed Response: " . print_r($data, true) . "\n";
    }
} catch (RequestException $e) {
    echo "Request Failed: " . $e->getMessage() . "\n";
    if ($e->hasResponse()) {
        $status_code = $e->getResponse()->getStatusCode();
        $response_body = $e->getResponse()->getBody()->getContents();
        echo "Status Code: $status_code\n";
        echo "Response Body: $response_body\n";
    }
} catch (Exception $e) {
    echo "General Error: " . $e->getMessage() . "\n";
}
?>