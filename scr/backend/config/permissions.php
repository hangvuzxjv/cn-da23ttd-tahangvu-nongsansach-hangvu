<?php

return [
    'admin' => ['*'],
    'staff_sales' => [
        'dashboard.view',
        'orders.view',
        'orders.manage',
        'vouchers.view',
        'vouchers.manage',
        'reviews.view',
        'reviews.manage',
        'customers.view',
    ],
    'staff_warehouse' => [
        'dashboard.view',
        'inventory.view',
        'batches.view',
        'products.view',
    ],
    'customer' => [],
];
