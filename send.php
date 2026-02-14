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

$CENTRIFUGO_URL = "http://127.0.0.1:8000";
$API_KEY = "shayan1383";
$userId = "1";
$channel = "chat:{$userId}";

$logger->info('🔹 Message publish request started');
$logger->debug('Configuration', [
    'centrifugoUrl' => $CENTRIFUGO_URL,
    'channel' => $channel,
    'userId' => $userId
]);

try {
    $logger->debug('📥 Reading input from php://input');
    $rawInput = file_get_contents("php://input");
    $logger->debug('Raw input received', ['length' => strlen($rawInput)]);
    
    $input = json_decode($rawInput, true);
    $logger->debug('JSON decoded', $input);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON: ' . json_last_error_msg());
    }
    
    $msg = trim($input["msg"] ?? "");
    $logger->debug('Message extracted and trimmed', [
        'length' => strlen($msg),
        'preview' => substr($msg, 0, 50)
    ]);
    
    $logger->info('🔍 Validating message');
    
    if ($msg === "") {
        $logger->warn('⚠️ Empty message rejected');
        http_response_code(400);
        echo json_encode([
            "error" => "پیام خالی است",
            "success" => false,
            "logs" => $logger->getLogs()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    if (strlen($msg) > 1000) {
        $logger->warn('⚠️ Message too long rejected', ['length' => strlen($msg)]);
        http_response_code(400);
        echo json_encode([
            "error" => "پیام خیلی طولانی است (حداکثر 1000 کاراکتر)",
            "success" => false,
            "logs" => $logger->getLogs()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $logger->success('✅ Message validation passed');
    
    $logger->info('📦 Preparing publish request to Centrifugo');
    
    $requestBody = [
        'channel' => $channel,
        'data' => [
            'message' => $msg,
            'timestamp' => round(microtime(true) * 1000),  // milliseconds
            'userId' => $userId,
            'sender' => "user_{$userId}"
        ]
    ];
    
    $requestBodyJson = json_encode($requestBody);
    $logger->debug('Request body prepared', [
        'channel' => $channel,
        'messageLength' => strlen($msg),
        'timestamp' => $requestBody['data']['timestamp'],
        'bodySize' => strlen($requestBodyJson) . ' bytes'
    ]);
    
    // ===== 5. Send cURL Request =====
    $logger->info('🚀 Sending request to Centrifugo API');
    
    $url = $CENTRIFUGO_URL . '/api/publish';
    $logger->debug('cURL configuration', [
        'url' => $url,
        'method' => 'POST',
        'headers' => [
            'Content-Type' => 'application/json',
            'X-API-Key' => substr($API_KEY, 0, 3) . '***' 
        ]
    ]);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-API-Key: ' . $API_KEY
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBodyJson);
    
    $logger->debug('cURL request initiated');
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $curlErrorNo = curl_errno($ch);
    
    $logger->debug('cURL response received', [
        'httpCode' => $httpCode,
        'responseSize' => strlen($response) . ' bytes',
        'curlErrorNo' => $curlErrorNo,
        'curlError' => $curlError ?: 'none'
    ]);
    
    curl_close($ch);
    
    // ===== 6. Handle cURL Errors =====
    if ($curlError) {
        $logger->error('❌ cURL error occurred', [
            'errorCode' => $curlErrorNo,
            'errorMessage' => $curlError
        ]);
        
        http_response_code(500);
        echo json_encode([
            "error" => "Connection error: " . $curlError,
            "success" => false,
            "curlErrorCode" => $curlErrorNo,
            "logs" => $logger->getLogs()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // ===== 7. Parse Response =====
    $logger->info('📨 Parsing Centrifugo response');
    
    $responseData = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        $logger->warn('⚠️ Response is not valid JSON', [
            'jsonError' => json_last_error_msg(),
            'response' => substr($response, 0, 100)
        ]);
        $responseData = ['raw' => $response];
    }
    
    $logger->debug('Response data', $responseData);
    
    // ===== 8. Check HTTP Status =====
    if ($httpCode === 200) {
        $logger->success('✅ Message published successfully', [
            'httpCode' => $httpCode,
            'channel' => $channel,
            'messagePreview' => substr($msg, 0, 30)
        ]);
        
        header('Content-Type: application/json');
        echo json_encode([
            "success" => true,
            "channel" => $channel,
            "message" => $msg,
            "timestamp" => time(),
            "centrifugoResponse" => $responseData,
            "logs" => $logger->getLogs()
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
    } else {
        $logger->error('❌ Centrifugo API error', [
            'httpCode' => $httpCode,
            'response' => $responseData ?? substr($response, 0, 200)
        ]);
        
        http_response_code($httpCode ?: 500);
        echo json_encode([
            "error" => "Centrifugo API error: HTTP {$httpCode}",
            "centrifugoResponse" => $responseData ?? $response,
            "success" => false,
            "logs" => $logger->getLogs()
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

} catch (Exception $e) {
    $logger->error('❌ Exception occurred', [
        'message' => $e->getMessage(),
        'code' => $e->getCode(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
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