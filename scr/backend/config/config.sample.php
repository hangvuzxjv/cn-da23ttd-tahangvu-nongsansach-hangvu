<?php

return [
    'app' => [
        'name' => 'Nông Sản Sạch',
        'url' => 'http://localhost',
        'debug' => false,
    ],
    'mailer' => [
        'driver' => 'log',
        'from_address' => 'no-reply@example.com',
        'from_name' => 'Nông Sản Sạch',
        'smtp' => [
            'host' => '',
            'port' => 587,
            'username' => '',
            'password' => '',
            'encryption' => 'tls',
        ],
    ],
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'nongsan',
        'username' => 'root',
        'password' => '',
    ],
    'vnpay' => [
        'tmn_code' => '',
        'hash_secret' => '',
        'url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
        'return_url' => '',
        'ipn_url' => '',
    ],
];
