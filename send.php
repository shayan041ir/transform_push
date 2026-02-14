<?php
header("Content-Type: application/json");

// قبول ورودی JSON
$input = json_decode(file_get_contents("php://input"), true);
$msg = trim($input["msg"] ?? "");

if ($msg === "") {
    echo json_encode(["error" => "Empty message!"]);
    exit;
}

// داده برای ارسال به Centrifugo
$data = [
    "channel" => "chat",
    "data" => ["message" => $msg]
];

// کلید API همانی که در config.json گذاشتی
$apiKey = "shayan1383";

$ch = curl_init("http://127.0.0.1:8000/api/publish");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "X-API-Key: " . $apiKey
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
curl_close($ch);

// پاسخ به کلاینت
echo $response;
