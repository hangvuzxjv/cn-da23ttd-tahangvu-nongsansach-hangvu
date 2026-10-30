<?php

return [
    'app' => [
        'name' => 'Nông Sản Sạch',
        'url' => 'https://your-site.infinityfreeapp.com',
        'debug' => false,
    ],
    'mailer' => [
        'driver' => 'log',
        'from_address' => 'no-reply@your-domain.example',
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
        'host' => 'sqlXXX.infinityfree.com',
        'port' => 3306,
        'name' => 'if0_12345678_nongsan',
        'username' => 'if0_12345678',
        'password' => 'REPLACE_WITH_HOSTING_DATABASE_PASSWORD',
    ],
    'vnpay' => [
        'tmn_code' => '',
        'hash_secret' => '',
        'url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
        'return_url' => '',
        'ipn_url' => '',
    ],
];
