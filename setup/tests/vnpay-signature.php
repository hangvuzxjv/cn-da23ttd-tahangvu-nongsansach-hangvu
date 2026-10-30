<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$parameters = [
    'vnp_TxnRef' => 'TEST123',
    'vnp_OrderInfo' => 'Thanh toan + don hang 123',
    'vnp_Empty' => '',
    'ignored' => 'not-signed',
    'vnp_SecureHash' => 'ignored',
    'vnp_SecureHashType' => 'HmacSHA512',
    'vnp_Amount' => 1000000,
    'vnp_CreateDate' => '20261030122722',
];
$expectedData = 'vnp_Amount=1000000&vnp_CreateDate=20261030122722'
    . '&vnp_OrderInfo=Thanh+toan+%2B+don+hang+123&vnp_TxnRef=TEST123';
$expectedSignature = '8c4a887378ec4322e693aa72e013ccfef833a3d6d0d3720cd25c1993e28bca4a'
    . 'fd8859d8cc412a13ede553aecb6c31c78d5949c7509851fa528c5cf94f7f9605';
$secret = 'test-vnpay-secret-123';

if (VnpaySignature::signatureData($parameters) !== $expectedData) {
    throw new RuntimeException('VNPay signature data does not match the urlencode test vector.');
}
$signature = VnpaySignature::sign($parameters, $secret);
$callback = $parameters;
$callback['vnp_SecureHash'] = $signature;
$callback['vnp_SecureHashType'] = 'HmacSHA512';
if ($signature !== $expectedSignature || !VnpaySignature::verify($callback, $secret, $signature)) {
    throw new RuntimeException('VNPay signing and Return/IPN verification are not symmetric.');
}
$tampered = $parameters;
$tampered['vnp_OrderInfo'] = 'Tampered order info';
if (VnpaySignature::verify($tampered, $secret, $signature)) {
    throw new RuntimeException('VNPay verification accepted a modified parameter.');
}
if (VnpaySignature::amountVnd('5200000') !== 52000
    || VnpaySignature::amountVnd('5200001') !== null
    || VnpaySignature::amountVnd('52000.00') !== null
    || VnpaySignature::amountVnd('invalid') !== null) {
    throw new RuntimeException('VNPay minor-unit amount conversion is not exact integer arithmetic.');
}
$validPayment = [
    'vnp_TxnRef' => 'TEST123',
    'vnp_Amount' => '5200000',
    'vnp_OrderInfo' => 'Thanh toan don DH2610300006',
];
if (VnpaySignature::paymentMismatchReason(
    $validPayment,
    'TEST123',
    52000,
    'Thanh toan don DH2610300006'
) !== null
    || VnpaySignature::paymentMismatchReason(
        array_replace($validPayment, ['vnp_Amount' => '5200100']),
        'TEST123',
        52000,
        'Thanh toan don DH2610300006'
    ) !== 'amount_mismatch'
    || VnpaySignature::paymentMismatchReason(
        array_replace($validPayment, ['vnp_TxnRef' => 'OTHER']),
        'TEST123',
        52000,
        'Thanh toan don DH2610300006'
    ) !== 'transaction_reference_mismatch') {
    throw new RuntimeException('VNPay payment-to-order reconciliation failed its pure tests.');
}

$loggedCallback = [
    'vnp_Amount' => '5200000',
    'vnp_BankCode' => 'NCB',
    'vnp_BankTranNo' => 'VNP15720044',
    'vnp_OrderInfo' => 'Thanh toan don DH2610300008',
    'vnp_PayDate' => '20261030132306',
    'vnp_ResponseCode' => '00',
    'vnp_TmnCode' => 'TESTTMN',
    'vnp_TransactionNo' => '15720044',
    'vnp_TransactionStatus' => '00',
    'vnp_TxnRef' => 'VP2610301322419CDEE3A4186635474CB67C242C4931BE',
];
$loggedCallback['vnp_SecureHash'] = VnpaySignature::sign($loggedCallback, $secret);
if (array_key_exists('vnp_CurrCode', $loggedCallback)
    || array_key_exists('vnp_CardType', $loggedCallback)
    || !VnpaySignature::verify($loggedCallback, $secret, $loggedCallback['vnp_SecureHash'])
    || VnpaySignature::paymentMismatchReason(
        $loggedCallback,
        $loggedCallback['vnp_TxnRef'],
        52000,
        'Thanh toan don DH2610300008'
    ) !== null) {
    throw new RuntimeException('The real VNPay callback shape without optional currency/card fields was rejected.');
}
$missingOptionalOrderInfo = array_diff_key($validPayment, ['vnp_OrderInfo' => true]);
if (VnpaySignature::paymentMismatchReason(
    $missingOptionalOrderInfo,
    'TEST123',
    52000,
    'Thanh toan don DH2610300006'
) !== null
    || VnpaySignature::paymentMismatchReason(
        array_replace($validPayment, ['vnp_CurrCode' => 'USD']),
        'TEST123',
        52000,
        'Thanh toan don DH2610300006'
    ) !== 'currency_mismatch') {
    throw new RuntimeException('Optional VNPay fields were treated as mandatory or ignored when present.');
}

fwrite(STDOUT, "VNPay signature test vector and symmetric verification passed.\n");
