<?php

declare(strict_types=1);

final class InventoryLabels
{
    private const STATUSES = [
        'draft' => 'Nháp',
        'received' => 'Đã nhập kho',
        'cancelled' => 'Đã hủy',
        'reversed' => 'Đã hủy',
        'selling' => 'Đang bán',
        'suspended' => 'Tạm ngưng',
        'recalled' => 'Đã thu hồi',
        'ended' => 'Ngừng bán',
        'confirmed' => 'Đã xác nhận',
    ];

    private const INSPECTION_RESULTS = [
        'pending' => 'Đang chờ',
        'passed' => 'Đạt',
        'failed' => 'Không đạt',
    ];

    private const MOVEMENT_TYPES = [
        'in' => 'Nhập',
        'out' => 'Xuất bán',
        'adjustment' => 'Điều chỉnh',
        'return' => 'Nhập lại kho',
    ];

    private const MOVEMENT_REFERENCES = [
        'warehouse_receipt_cancel' => 'Hủy phiếu',
        'stocktake' => 'Kiểm kê',
    ];

    private const REFERENCES = [
        'warehouse_receipt' => 'Phiếu nhập',
        'warehouse_receipt_cancel' => 'Hủy phiếu nhập',
        'manual_adjustment' => 'Điều chỉnh thủ công',
        'stocktake' => 'Kiểm kê',
        'order' => 'Đơn hàng',
        'order_restore' => 'Khôi phục tồn đơn hàng',
        'order_return' => 'Trả hàng',
        'adjustment' => 'Điều chỉnh',
    ];

    public static function status(string $status): string
    {
        return self::STATUSES[$status] ?? 'Khác';
    }

    public static function statuses(array $values): array
    {
        return array_intersect_key(self::STATUSES, array_flip($values));
    }

    public static function inspectionResult(string $result): string
    {
        return self::INSPECTION_RESULTS[$result] ?? 'Khác';
    }

    public static function inspectionResults(): array
    {
        return self::INSPECTION_RESULTS;
    }

    public static function movement(string $type, ?string $referenceType = null): string
    {
        if ($referenceType !== null && isset(self::MOVEMENT_REFERENCES[$referenceType])) {
            return self::MOVEMENT_REFERENCES[$referenceType];
        }

        return self::MOVEMENT_TYPES[$type] ?? 'Khác';
    }

    public static function reference(?string $referenceType): string
    {
        if ($referenceType === null || $referenceType === '') {
            return '';
        }

        return self::REFERENCES[$referenceType] ?? 'Khác';
    }
}
