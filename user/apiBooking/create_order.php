<?php
session_start();
header('Content-Type: application/json');//JASON data send

require 'config.php';

$data = json_decode(file_get_contents("php://input"), true);//read row jason and convert it to php
$amount = (float)($data['amount'] ?? 0);

if ($amount <= 0) {
    echo json_encode(['error' => 'Invalid amount']);
    exit;
}

//CURL to call the Razorpay API with your Key ID and Key Secret
$payload = json_encode([
    'amount'   => $amount * 100, // paises
    'currency' => 'INR',
    'receipt'  => 'rcpt_' . time()
]);

$ch = curl_init('https://api.razorpay.com/v1/orders');//api request to razorpay
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);//response instead of printing
curl_setopt($ch, CURLOPT_POST, true);//methos type-post
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);//send playload
curl_setopt($ch, CURLOPT_USERPWD, RAZORPAY_KEY_ID . ":" . RAZORPAY_KEY_SECRET);//use razorpay credentials
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);//data in json formate

$response = curl_exec($ch);//request to Razorpay
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);//response status code
curl_close($ch);

if ($http_code !== 200) {
    echo json_encode(['error' => 'Razorpay API Error: ' . $response]);
    exit;
}

echo $response; // Return the REAL Razorpay Order (including the real ID)