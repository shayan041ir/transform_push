<?php



function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function generateConnectionToken($userId, $secret) {
    // Header
    $header = [
        'alg' => 'HS256',
        'typ' => 'JWT'
    ];
    
    // Payload - فقط 'sub' (subject) ضروری است
    $payload = [
        'sub' => (string)$userId,
        'iat' => time(),
        'exp' => time() + 86400  // 24 ساعت validity
    ];
    
    // Encode header و payload
    $headerEncoded = base64url_encode(json_encode($header));
    $payloadEncoded = base64url_encode(json_encode($payload));
    
    // امضا کن (Sign)
    $signatureInput = "$headerEncoded.$payloadEncoded";
    $signature = hash_hmac('sha256', $signatureInput, $secret, true);
    $signatureEncoded = base64url_encode($signature);
    
    // بازگردان token
    return "$signatureInput.$signatureEncoded";
}

// ===== Main Code =====

$userId = "1";  // User ID
$secret = "shayan1383";  // باید با hmac_secret_key در config مطابقت داشته باشد

try {
    $token = generateConnectionToken($userId, $secret);
    
    echo json_encode([
        "token" => $token,
        "userId" => $userId,
        "success" => true
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "error" => $e->getMessage(),
        "success" => false
    ]);
}

?>