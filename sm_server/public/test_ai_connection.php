<?php
/**
 * This is a simple script to test the connection to the AI service
 * You can run it directly in the browser or via CLI
 */

// Basic error handling
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== AI Service Connection Test ===\n\n";

// Load environment variables if .env file exists
if (file_exists(__DIR__ . '/../.env')) {
    $env = parse_ini_file(__DIR__ . '/../.env');
    $aiEndpoint = $env['AI_SERVICE_URL'] ?? 'http://localhost:1234';
    $aiModel = $env['AI_MODEL_NAME'] ?? 'deepseek-r1-distill-qwen-7b';
} else {
    $aiEndpoint = 'http://localhost:1234';
    $aiModel = 'deepseek-r1-distill-qwen-7b';
}

echo "Testing connection to AI service at: {$aiEndpoint}\n";
echo "Model: {$aiModel}\n\n";

// Test 1: Check if we can reach the models endpoint
echo "Test 1: Checking models endpoint...\n";
$ch = curl_init("{$aiEndpoint}/v1/models");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "❌ ERROR: Could not connect to models endpoint: {$error}\n";
    echo "Please check if the AI service is running at {$aiEndpoint}\n";
    exit(1);
} else if ($httpCode !== 200) {
    echo "❌ ERROR: Models endpoint returned HTTP status {$httpCode}\n";
    echo "Response: " . substr($response, 0, 200) . "...\n";
} else {
    echo "✅ SUCCESS: Models endpoint is accessible\n";
    $models = json_decode($response, true);
    if (is_array($models) && count($models) > 0) {
        echo "Available models:\n";
        foreach ($models as $model) {
            if (isset($model['id'])) {
                echo " - {$model['id']}\n";
            }
        }
    } else {
        echo "No models or unexpected format returned\n";
    }
}

echo "\n";

// Test 2: Try a basic completion
echo "Test 2: Testing chat completion...\n";

$data = [
    'model' => $aiModel,
    'messages' => [
        [
            'role' => 'system',
            'content' => 'You are a helpful assistant. Please respond with a very short answer.'
        ],
        [
            'role' => 'user',
            'content' => 'Hello, are you working?'
        ]
    ],
    'temperature' => 0.7,
    'max_tokens' => 50
];

$ch = curl_init("{$aiEndpoint}/v1/chat/completions");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "❌ ERROR: Could not connect to chat completions endpoint: {$error}\n";
} else if ($httpCode !== 200) {
    echo "❌ ERROR: Chat completions endpoint returned HTTP status {$httpCode}\n";
    echo "Response: " . substr($response, 0, 200) . "...\n";
} else {
    echo "✅ SUCCESS: Chat completions endpoint responded\n";
    $completion = json_decode($response, true);
    if (isset($completion['choices'][0]['message']['content'])) {
        $message = $completion['choices'][0]['message']['content'];
        echo "AI response: {$message}\n";
    } else {
        echo "No completion content or unexpected format returned\n";
        echo "Response: " . substr($response, 0, 200) . "...\n";
    }
}

echo "\n=== Test Complete ===\n";
echo "If all tests passed, your AI service is properly configured.\n";
echo "If any tests failed, please check your AI service and configuration.\n";
