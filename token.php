<?php

// ===== Logger Helper =====
class Logger {
    private $logs = [];
    
    public function log($level, $message, $data = null) {
        $timestamp = date('Y-m-d H:i:s.') . substr(microtime(), 2, 3);
        $logEntry = [
            'timestamp' => $timestamp,
            'level' => $level,
            'message' => $message,
            'data' => $data
        ];
        $this->logs[] = $logEntry;
        error_log("[$timestamp] [$level] $message" . ($data ? ': ' . json_encode($data) : ''));
    }
    
    public function debug($msg, $data = null) { $this->log('DEBUG', $msg, $data); }
    public function info($msg, $data = null) { $this->log('INFO', $msg, $data); }
    public function success($msg, $data = null) { $this->log('SUCCESS', $msg, $data); }
    public function error($msg, $data = null) { $this->log('ERROR', $msg, $data); }
    public function warn($msg, $data = null) { $this->log('WARN', $msg, $data); }
    
    public function getLogs() {
        return $this->logs;
    }
}

$logger = new Logger();

// ===== Configuration =====
$userId = "1";
$secret = "shayan1383";
$tokenExpiry = 86400; // 24 ساعت

$logger->info('🔹 Token generation started', ['userId' => $userId]);

// ===== JWT Generation =====
function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function generateConnectionToken($userId, $secret, $expiry, $logger) {
    $logger->debug('📝 Creating JWT token', ['userId' => $userId, 'expiry' => $expiry]);
    
    // Header
    $header = [
        'alg' => 'HS256',
        'typ' => 'JWT'
    ];
    $logger->debug('JWT Header created', $header);
    
    // Payload
    $now = time();
    $payload = [
        'sub' => (string)$userId,           // Subject (ضروری)
        'iat' => $now,                      // Issued At
        'exp' => $now + $expiry            // Expiration
    ];
    $logger->debug('JWT Payload created', $payload);
    
    // Encoding
    $headerEncoded = base64url_encode(json_encode($header));
    $payloadEncoded = base64url_encode(json_encode($payload));
    $logger->debug('✅ Header and Payload encoded', [
        'headerLength' => strlen($headerEncoded),
        'payloadLength' => strlen($payloadEncoded)
    ]);
    
    // Signing
    $signatureInput = "$headerEncoded.$payloadEncoded";
    $signature = hash_hmac('sha256', $signatureInput, $secret, true);
    $signatureEncoded = base64url_encode($signature);
    $logger->debug('✅ Token signed with HMAC-SHA256', [
        'signatureLength' => strlen($signatureEncoded)
    ]);
    
    $token = "$signatureInput.$signatureEncoded";
    $logger->success('✅ JWT token generated successfully', [
        'tokenLength' => strlen($token),
        'preview' => substr($token, 0, 30) . '...'
    ]);
    
    return $token;
}

try {
    $token = generateConnectionToken($userId, $secret, $tokenExpiry, $logger);
    
    $responseData = [
        "token" => $token,
        "userId" => $userId,
        "success" => true,
        "expiresIn" => $tokenExpiry,
        "logs" => $logger->getLogs()
    ];
    
    header('Content-Type: application/json');
    $logger->success('✅ Response sent successfully');
    echo json_encode($responseData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    $logger->error('❌ Exception occurred', [
        'message' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
    
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        "error" => $e->getMessage(),
        "success" => false,
        "logs" => $logger->getLogs()
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

?>