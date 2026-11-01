<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('ROOT_PATH', $root . DIRECTORY_SEPARATOR . 'scr');
define('BACKEND_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'backend');
define('FRONTEND_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'frontend');
define('BASE_URL', '');

require BACKEND_PATH . '/Helpers/helpers.php';
require BACKEND_PATH . '/Core/View.php';

function uiDesignAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$requiredAssets = [
    ROOT_PATH . '/css/vendor/bootstrap.min.css',
    ROOT_PATH . '/css/app.css',
    ROOT_PATH . '/js/vendor/bootstrap.bundle.min.js',
    ROOT_PATH . '/favicon.svg',
];
foreach ($requiredAssets as $assetPath) {
    uiDesignAssert(is_file($assetPath), 'Required local design asset is missing: ' . basename($assetPath));
}

$stylesheet = file_get_contents(ROOT_PATH . '/css/app.css');
uiDesignAssert(is_string($stylesheet), 'Unable to read the shared stylesheet.');
foreach ([
    '--color-primary:',
    '--color-success:',
    '--color-warning:',
    '--color-danger:',
    '--color-page:',
    '--color-text:',
    '--radius-md:',
    '--shadow-sm:',
    '--space-4:',
    '--font-body:',
    '.product-card',
    '.product-card__region',
    '.product-card__stock',
    '.status-badge',
    '.pagination',
    '.empty-state',
    '.loading-state',
    ':focus-visible',
    '@media (max-width: 575.98px)',
    '@media (min-width: 768px)',
] as $designToken) {
    uiDesignAssert(str_contains($stylesheet, $designToken), 'Shared design system is missing ' . $designToken);
}

foreach (['client.php', 'admin.php', 'error.php'] as $layout) {
    $markup = file_get_contents(FRONTEND_PATH . '/views/layouts/' . $layout);
    uiDesignAssert(is_string($markup), 'Unable to read layout ' . $layout);
    uiDesignAssert(
        !str_contains($markup, 'cdn.jsdelivr.net') && !str_contains($markup, 'fonts.googleapis.com'),
        'Layout ' . $layout . ' must not require a remote asset CDN.'
    );
    uiDesignAssert(str_contains($markup, '/css/vendor/bootstrap.min.css'), 'Layout must load local Bootstrap CSS.');
}

$clientLayout = file_get_contents(FRONTEND_PATH . '/views/layouts/client.php');
$adminLayout = file_get_contents(FRONTEND_PATH . '/views/layouts/admin.php');
uiDesignAssert(
    str_contains((string) $clientLayout, 'id="clientNavigation"')
        && str_contains((string) $clientLayout, 'id="notificationList"')
        && str_contains((string) $clientLayout, '/js/vendor/bootstrap.bundle.min.js')
        && str_contains((string) $adminLayout, 'id="adminNavigation"')
        && str_contains((string) $adminLayout, 'data-bs-target="#adminNavigation"')
        && str_contains((string) $adminLayout, '/js/vendor/bootstrap.bundle.min.js'),
    'Existing customer selectors and the responsive admin navigation contract must remain available.'
);

ob_start();
View::renderError(500, new RuntimeException('private-database-detail'), false);
$errorPage = (string) ob_get_clean();
uiDesignAssert(
    http_response_code() === 500
        && str_contains($errorPage, 'Đã xảy ra lỗi máy chủ')
        && !str_contains($errorPage, 'private-database-detail')
        && str_contains($errorPage, '/css/app.css')
        && !str_contains($errorPage, 'cdn.jsdelivr.net'),
    'Production error pages must be styled locally and must not expose technical details.'
);

echo "PASS shared tokens and reusable component styles\n";
echo "PASS local assets and responsive navigation selectors\n";
echo "PASS production error detail suppression\n";
