<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

$database = inventoryTestDatabase();
$batchCode = (string) $database->query('SELECT batch_code FROM product_batches ORDER BY id LIMIT 1')->fetchColumn();
if ($batchCode === '') {
    throw new RuntimeException('QR test requires at least one product batch.');
}

$target = absolute_url('/truy-xuat/' . rawurlencode($batchCode));
$svg = (new Writer(new ImageRenderer(new RendererStyle(320, 8), new SvgImageBackEnd())))->writeString($target);

if (!preg_match('#^https?://[^/]+/truy-xuat/' . preg_quote(rawurlencode($batchCode), '#') . '$#', $target)
    || !str_starts_with(trim($svg), '<?xml') || !str_contains($svg, '<svg')) {
    throw new RuntimeException('Traceability QR SVG or its absolute batch URL is invalid.');
}

echo "PASS: traceability QR is a local SVG containing the configured absolute batch URL.\n";
