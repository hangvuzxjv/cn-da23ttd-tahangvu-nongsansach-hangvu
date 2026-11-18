<?php

declare(strict_types=1);

final class PaymentLabels
{
    private const STATUSES = [
        'unpaid' => 'Chưa thanh toán',
        'pending' => 'Chờ thanh toán',
        'paid' => 'Đã thanh toán',
        'failed' => 'Thất bại',
        'refunded' => 'Đã hoàn tiền',
    ];

    private const STATUS_TONES = [
        'unpaid' => 'warning',
        'pending' => 'warning',
        'paid' => 'success',
        'failed' => 'danger',
        'refunded' => 'neutral',
    ];

    private const METHODS = [
        'cod' => 'Thanh toán khi nhận hàng (COD)',
        'vnpay' => 'VNPay',
        'bank_transfer' => 'Chuyển khoản',
        'online' => 'Thanh toán trực tuyến',
    ];

    public static function statuses(): array
    {
        return self::STATUSES;
    }

    public static function methods(): array
    {
        return self::METHODS;
    }

    public static function status(string $status): string
    {
        return self::STATUSES[$status] ?? $status;
    }

    public static function statusBadgeClass(string $status): string
    {
        return 'status-badge--' . (self::STATUS_TONES[$status] ?? 'neutral');
    }

    public static function method(string $method): string
    {
        return self::METHODS[$method] ?? $method;
    }

    public static function dateTime(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return '—';
        }

        $timestamp = strtotime($value);
        return $timestamp === false ? $value : date('d/m/Y H:i', $timestamp);
    }
}
