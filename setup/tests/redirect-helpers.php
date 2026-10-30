<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

function assertRedirectTarget(string $input, ?string $expected = null, ?array $redirectConfig = null): void
{
    $actual = redirect_target($input, $redirectConfig);
    if ($actual !== ($expected ?? $input)) {
        throw new RuntimeException("Unexpected redirect target for: {$input}");
    }
}

function assertRedirectRejected(string $input, ?array $redirectConfig = null): void
{
    try {
        redirect_target($input, $redirectConfig);
    } catch (InvalidArgumentException) {
        return;
    }

    throw new RuntimeException("Unsafe redirect target was accepted: {$input}");
}

assertRedirectTarget('orders/42', url('orders/42'));
assertRedirectTarget('/orders/42', url('/orders/42'));
if (!is_internal_redirect_path('/orders/42?tab=payments')
    || is_internal_redirect_path('//attacker.example')
    || is_internal_redirect_path('https://attacker.example')
    || is_internal_redirect_path('/\\attacker.example')) {
    throw new RuntimeException('An external or malformed return path was treated as internal.');
}

$config = require BACKEND_PATH . '/config/config.php';
$redirectConfig = $config;
$redirectConfig['vnpay'] = $redirectConfig['vnpay'] ?? [];
$redirectConfig['vnpay']['url'] = trim((string) ($redirectConfig['vnpay']['url'] ?? ''))
    ?: 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html';
$redirectConfig['vnpay']['tmn_code'] = 'TESTTMN';
$redirectConfig['vnpay']['hash_secret'] = 'redirect-helper-test-secret';
$vnpay = $redirectConfig['vnpay'];
$parameters = [
    'vnp_Amount' => '5200000',
    'vnp_Command' => 'pay',
    'vnp_TmnCode' => (string) ($vnpay['tmn_code'] ?? ''),
];
$hashData = VnpaySignature::signatureData($parameters);
$signature = VnpaySignature::sign($parameters, (string) ($vnpay['hash_secret'] ?? ''));
$gatewayUrl = (string) ($vnpay['url'] ?? '') . '?' . $hashData . '&vnp_SecureHash=' . $signature;
assertRedirectTarget($gatewayUrl, null, $redirectConfig);

foreach ([
    '//attacker.example/path',
    'javascript:alert(1)',
    'https://attacker.example/path',
    str_replace('https://', 'http://', $gatewayUrl),
] as $unsafeTarget) {
    assertRedirectRejected($unsafeTarget, $redirectConfig);
}
assertRedirectRejected(substr($gatewayUrl, 0, -1) . ($gatewayUrl[-1] === '0' ? '1' : '0'), $redirectConfig);

foreach ([$gatewayUrl, 'http://example.test/return'] as $absoluteUrl) {
    if (url($absoluteUrl) !== $absoluteUrl) {
        throw new RuntimeException('url() modified an absolute URL.');
    }
}

fwrite(STDOUT, "Redirect helper tests passed.\n");
