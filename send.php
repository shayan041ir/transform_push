<?php

/**
 * Publish Message to Centrifugo
 * بر اساس داکومنت: https://centrifugal.dev/docs/server/server_api
 * 
 * دو روش:
 * 1. /api/publish endpoint + X-API-Key header (ساده‌تر)
 * 2. /api endpoint + sign (پیچیده‌تر)
 * 
 * ما از روش 1 استفاده می‌کنیم
 */

// ===== Configuration =====
$CENTRIFUGO_URL = "http://127.0.0.1:8000";
$API_KEY = "shayan1383";

// ===== Main Code =====

try {
    // دریافت input
    $input = json_decode(file_get_contents("php://input"), true);
    $msg = trim($input["msg"] ?? "");

    // Validation
    if ($msg === "") {
        http_response_code(400);
        echo json_encode(["error" => "پیام خالی است"]);
        exit;
    }

    if (strlen($msg) > 1000) {
        http_response_code(400);
        echo json_encode(["error" => "پیام خیلی طولانی است"]);
        exit;
    }

    $userId = "1";  // Current user ID
    
    // Channel name format: namespace:userId
    // بر اساس داکومنت: allow_user_limited_channels فقط برای format "namespace:userId" کار میکنه
    $channel = "chat:{$userId}";

    // Prepare publish request body
    // فقط channel و data - بیشتر نیست!
    $requestBody = json_encode([
        'channel' => $channel,
        'data' => [
            'message' => $msg,
            'timestamp' => round(microtime(true) * 1000)  // milliseconds
        ]
    ]);

    // Make API request to /api/publish endpoint
    $url = $CENTRIFUGO_URL . '/api/publish';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-API-Key: ' . $API_KEY
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    // Handle response
    if ($error) {
        http_response_code(500);
        echo json_encode([
            "error" => "cURL error: " . $error,
            "success" => false
        ]);
        exit;
    }

    // Parse response
    $responseData = json_decode($response, true);

    if ($httpCode === 200) {
        // Success
        echo json_encode([
            "success" => true,
            "channel" => $channel,
            "message" => $msg,
            "response" => $responseData
        ]);
    } else {
        // Error response from Centrifugo
        http_response_code($httpCode);
        echo json_encode([
            "error" => "Centrifugo API error: HTTP $httpCode",
            "response" => $responseData ?? $response,
            "success" => false
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "error" => $e->getMessage(),
        "success" => false
    ]);
}

?>