<?php

declare(strict_types=1);

final class CatalogSlug
{
    public static function from(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', trim($value));
        if ($ascii === false) {
            throw new RuntimeException('Không thể tạo slug từ dữ liệu Unicode.');
        }
        $slug = strtolower($ascii);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        return trim($slug, '-');
    }
}
