<?php
require_once(__DIR__ . '/../../../../config.php');

$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_HELLOASSO_SIGNATURE'] ?? '';

// Vérifier la signature
$expected_signature = hash_hmac('sha256', $payload, get_config('payment_gateway_helloasso', 'clientsecret'));

if (!hash_equals($expected_signature, $signature)) {
    http_response_code(403);
    die('Invalid signature');
}

$data = json_decode($payload, true);
$paymentid = $data['metadata']['paymentid'] ?? null;

if ($paymentid) {
    $payment = \core_payment\helper::get_payment_record($paymentid);
    if ($data['state'] === 'Paid') {
        \core_payment\helper::deliver_order($payment);
    }
}

http_response_code(200);