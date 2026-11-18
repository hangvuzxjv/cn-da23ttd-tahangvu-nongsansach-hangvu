<?php

declare(strict_types=1);

final class ProductUnitFormatter
{
    public const SALE_UNITS = ['Kg', 'Túi', 'Gói', 'Bó', 'Hộp', 'Khay', 'Trái', 'Chai', 'Hũ', 'Lít'];
    public const PACK_SIZE_UNITS = ['g', 'kg', 'ml', 'l'];

    public static function shortLabel(
        string $saleUnit,
        ?float $packSize = null,
        ?string $packSizeUnit = null
    ): string {
        if (in_array($saleUnit, ['Kg', 'Lít'], true)
            || $packSize === null
            || $packSize <= 0
            || !in_array($packSizeUnit, self::PACK_SIZE_UNITS, true)) {
            return $saleUnit;
        }

        return $saleUnit . ' ' . self::formatSize($packSize) . $packSizeUnit;
    }

    public static function priceLabel(float|int|string $price, string $unitLabel): string
    {
        return number_format((float) $price, 0, ',', '.') . ' ₫ / ' . $unitLabel;
    }

    public static function referencePriceLabel(
        float|int|string $price,
        string $saleUnit,
        ?float $packSize,
        ?string $packSizeUnit
    ): ?string {
        if (in_array($saleUnit, ['Kg', 'Lít'], true) || $packSize === null || $packSize <= 0) {
            return null;
        }

        if (in_array($packSizeUnit, ['g', 'kg'], true)) {
            $baseSize = $packSizeUnit === 'g' ? $packSize / 1000 : $packSize;
            $baseUnit = 'kg';
        } elseif (in_array($packSizeUnit, ['ml', 'l'], true)) {
            $baseSize = $packSizeUnit === 'ml' ? $packSize / 1000 : $packSize;
            $baseUnit = 'lít';
        } else {
            return null;
        }

        if ($baseSize <= 0) {
            return null;
        }

        return '≈ ' . number_format((float) $price / $baseSize, 0, ',', '.') . ' ₫/' . $baseUnit;
    }

    private static function formatSize(float $size): string
    {
        return rtrim(rtrim(number_format($size, 2, ',', '.'), '0'), ',');
    }
}
