<?php
/**
 * Direct connection test for AI service
 * This script bypasses Laravel and directly tests the connection
 * Use this to diagnose CORS, firewall, or network issues
 */

// Allow from any origin
header("Access-Control-Allow-Origin: *");
header("Content-Type: text/plain");
header("X-Content-Type-Options: nosniff");

// Display all errors
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "AI Service Direct Connection Test\n";
echo "--------------------------------\n\n";

// Endpoint configuration - edit these to match your setup
$aiEndpoint = "http://localhost:1234";  // Update this if your endpoint is different
$aiModel = "deepseek-r1-distill-qwen-7b";  // Update with your model name

// Load from .env if available
if (file_exists(__DIR__ . '/../.env')) {
    $env_lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($env_lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            if (trim($key) === 'AI_SERVICE_URL') {
                $aiEndpoint = trim($value);
            }
            if (trim($key) === 'AI_MODEL_NAME') {
                $aiModel = trim($value);
            }
        }
    }
}

echo "Using endpoint: $aiEndpoint\n";
echo "Using model: $aiModel\n\n";

// Test 1: Basic connection test
echo "Test 1: Basic connectivity test...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $aiEndpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
curl_setopt($ch, CURLOPT_HEADER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
$info = curl_getinfo($ch);
curl_close($ch);

if ($error) {
    echo "❌ ERROR: Could not connect to endpoint: $error\n";
    echo "Connection details: \n";
    echo "  Connect time: " . $info['connect_time'] . "s\n";
    echo "  Total time: " . $info['total_time'] . "s\n";
    echo "  Primary IP: " . $info['primary_ip'] . "\n";
    echo "  Primary port: " . $info['primary_port'] . "\n";

    // Test local loopback connection
    echo "\nTesting local loopback connection...\n";
    $ch = curl_init("http://localhost/");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    $loopbackResponse = curl_exec($ch);
    $loopbackError = curl_error($ch);
    curl_close($ch);

    if ($loopbackError) {
        echo "  ❌ Local loopback test failed: $loopbackError\n";
        echo "  This suggests a problem with your network configuration or PHP/curl setup.\n";
    } else {
        echo "  ✅ Local loopback test succeeded.\n";
        echo "  This suggests the AI service is not running or listening on $aiEndpoint\n";
    }
} else {
    echo "✅ SUCCESS: Basic connection to endpoint succeeded\n";
    echo "HTTP Status: $httpCode\n";
    echo "Headers received: " . strlen($response) . " bytes\n\n";
}

// Test 2: Check models endpoint
echo "Test 2: Checking models endpoint...\n";
$ch = curl_init($aiEndpoint . '/v1/models');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "❌ ERROR: Could not connect to models endpoint: $error\n";
} else if ($httpCode != 200) {
    echo "❌ ERROR: Models endpoint returned HTTP status $httpCode\n";
    echo "Response body: " . substr($response, 0, 200) . "...\n";
} else {
    echo "✅ SUCCESS: Models endpoint is accessible\n";
    $models = json_decode($response, true);
    if (is_array($models) && count($models) > 0) {
        echo "Available models:\n";
        foreach ($models as $model) {
            if (isset($model['id'])) {
                echo "  - " . $model['id'] . "\n";
            }
        }

        // Check if our model exists
        $modelExists = false;
        foreach ($models as $model) {
            if (isset($model['id']) && $model['id'] === $aiModel) {
                $modelExists = true;
                break;
            }
        }

        if ($modelExists) {
            echo "\n✅ Good news! The model '$aiModel' exists on this server.\n";
        } else {
            echo "\n⚠️ Warning: The model '$aiModel' was not found in the list of available models.\n";
            echo "Available models are: ";
            $modelNames = [];
            foreach ($models as $model) {
                if (isset($model['id'])) {
                    $modelNames[] = $model['id'];
                }
            }
            echo implode(", ", $modelNames) . "\n";
            echo "You should update your .env file to use one of these models.\n";
        }
    } else {
        echo "No models or unexpected format returned\n";
        echo "Response: " . $response . "\n";
    }
}

// Test 3: Test chat completion endpoint with minimal prompt
echo "\nTest 3: Testing chat completion endpoint...\n";

// Prepare minimal test data
$testData = [
    'model' => $aiModel,
    'messages' => [
        [
            'role' => 'system',
            'content' => 'You are a helpful assistant. Respond with a single word.'
        ],
        [
            'role' => 'user',
            'content' => 'Say OK if you can hear me.'
        ]
    ],
    'temperature' => 0.7,
    'max_tokens' => 10
];

// Encode JSON with error handling
$jsonData = json_encode($testData);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo "❌ ERROR: Failed to encode JSON: " . json_last_error_msg() . "\n";
    exit(1);
}

$ch = curl_init($aiEndpoint . '/v1/chat/completions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'Content-Length: ' . strlen($jsonData)
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 15); // Longer timeout for LLM processing
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
$info = curl_getinfo($ch);
curl_close($ch);

if ($error) {
    echo "❌ ERROR: Failed to connect to chat completions endpoint: $error\n";
    echo "Connection details: \n";
    echo "  Connect time: " . $info['connect_time'] . "s\n";
    echo "  Total time: " . $info['total_time'] . "s\n";
    echo "  Primary IP: " . $info['primary_ip'] . "\n";
    echo "  Primary port: " . $info['primary_port'] . "\n";
} else if ($httpCode != 200) {
    echo "❌ ERROR: Chat completions endpoint returned HTTP status $httpCode\n";
    echo "Response: " . substr($response, 0, 300) . "...\n";
} else {
    echo "✅ SUCCESS: Chat completions endpoint responded\n";

    // Parse response
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "❌ ERROR: Failed to parse JSON response: " . json_last_error_msg() . "\n";
        echo "Raw response: " . substr($response, 0, 300) . "...\n";
    } else if (isset($data['choices'][0]['message']['content'])) {
        $content = $data['choices'][0]['message']['content'];
        echo "AI response: " . $content . "\n";
        echo "\n✅ Great! Your AI service is responding correctly.\n";
    } else {
        echo "⚠️ Warning: Unexpected response format.\n";
        echo "Response structure: " . print_r($data, true) . "\n";
    }
}

echo "\n--------------------------------\n";
echo "Test completed at: " . date('Y-m-d H:i:s') . "\n";
echo "If you see any errors above, check your AI service configuration and ensure it's running.\n";
echo "If all tests passed, the issue may be within your Laravel application or its interaction with the AI service.\n";
