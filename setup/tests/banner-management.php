<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$scr = $root . DIRECTORY_SEPARATOR . 'scr';

function bannerTestAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$bannerDirectory = $scr . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'banners';
foreach (range(1, 5) as $number) {
    $path = $bannerDirectory . DIRECTORY_SEPARATOR . 'banner-' . $number . '.svg';
    $contents = file_get_contents($path);
    bannerTestAssert(is_string($contents) && strlen($contents) < 60 * 1024, 'Banner SVG is missing or too large.');
    bannerTestAssert(
        str_starts_with(trim($contents), '<svg')
            && stripos($contents, '<script') === false
            && preg_match('/\s(?:href|xlink:href)\s*=\s*["\'](?!#)/i', $contents) !== 1,
        'Banner SVG must be local, script-free and valid.'
    );
}

$controller = file_get_contents($scr . '/backend/Controllers/AdminContentController.php');
$routes = file_get_contents($scr . '/backend/routes.php');
$home = file_get_contents($scr . '/frontend/views/client/home.php');
$script = file_get_contents($scr . '/js/home-banners.js');
$migration = file_get_contents($root . '/setup/database/migrations/20261102_banners_management.sql');
bannerTestAssert(
    is_string($controller)
        && str_contains($controller, '2 * 1024 * 1024')
        && str_contains($controller, 'finfo')
        && str_contains($controller, 'move_uploaded_file')
        && str_contains($controller, '<script\b')
        && str_contains($controller, 'on[a-z]+')
        && str_contains($controller, 'url\s*\('),
    'Banner upload validation and SVG sanitization must be implemented.'
);
bannerTestAssert(
    is_string($routes)
        && str_contains($routes, "['permission:content.view']")
        && str_contains($routes, "['permission:content.manage', 'csrf']"),
    'Banner routes must protect viewing and writing with content permissions and CSRF.'
);
bannerTestAssert(
    is_string($home)
        && str_contains($home, 'data-home-banner-slider')
        && str_contains($home, 'loading="lazy"')
        && str_contains($home, 'Tạm dừng')
        && is_string($script)
        && str_contains($script, 'prefers-reduced-motion')
        && str_contains($script, 'visibilitychange')
        && str_contains($script, 'touchstart'),
    'Homepage banner carousel must support lazy loading, pause, reduced motion, tab visibility and touch.'
);
bannerTestAssert(
    is_string($migration)
        && substr_count($migration, '/img/banners/banner-') === 10
        && str_contains($migration, 'WHERE NOT EXISTS'),
    'Banner migration must seed five local banners without overwriting existing data.'
);

echo "PASS banner assets, validation, permissions and carousel contracts\n";
