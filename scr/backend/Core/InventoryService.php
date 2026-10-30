<?php

declare(strict_types=1);

final class InventoryService
{
    private PDO $database;

    public function __construct(?PDO $database = null)
    {
        $this->database = $database ?? Database::connection();
    }

    public function receive(
        int $batchId,
        int $quantity,
        string $reason,
        ?int $userId = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        float $unitCost = 0.0
    ): int {
        if ($quantity < 1 || trim($reason) === '' || $unitCost < 0) {
            throw new InvalidArgumentException('Số lượng nhập, lý do và giá nhập không hợp lệ.');
        }

        return $this->transaction(function () use ($batchId, $quantity, $reason, $userId, $referenceType, $referenceId, $unitCost): int {
            $batch = $this->lockBatch($batchId);
            $newReceived = (int) $batch['quantity_received'] + $quantity;
            $newRemaining = (int) $batch['quantity_remaining'] + $quantity;
            if ($newReceived > 4294967295 || $newRemaining > 4294967295) {
                throw new InventoryException('Số lượng vượt giới hạn lưu trữ.');
            }
            $oldReceived = (int) $batch['quantity_received'];
            $oldCost = (float) $batch['average_unit_cost'];
            $averageCost = $newReceived > 0
                ? (($oldReceived * $oldCost) + ($quantity * $unitCost)) / $newReceived
                : $unitCost;
            $this->database->prepare(
                'UPDATE product_batches
                 SET quantity_received = :received, quantity_remaining = :remaining,
                     average_unit_cost = :average_cost
                 WHERE id = :id'
            )->execute([
                'received' => $newReceived,
                'remaining' => $newRemaining,
                'average_cost' => $averageCost,
                'id' => $batchId,
            ]);
            $movementId = $this->insertMovement(
                (int) $batch['product_id'],
                $batchId,
                'in',
                $quantity,
                $referenceType,
                $referenceId,
                $userId,
                $reason,
                $unitCost,
                $newRemaining
            );
            $this->syncProductTotal((int) $batch['product_id']);
            return $movementId;
        });
    }

    public function issue(
        int $batchId,
        int $quantity,
        string $reason,
        ?int $userId = null,
        ?string $referenceType = null,
        ?int $referenceId = null
    ): int {
        if ($quantity < 1 || trim($reason) === '') {
            throw new InvalidArgumentException('Số lượng xuất và lý do bắt buộc phải hợp lệ.');
        }
        if (!$this->database->inTransaction()) {
            (new PaymentService($this->database))->expirePending();
        }

        return $this->transaction(function () use ($batchId, $quantity, $reason, $userId, $referenceType, $referenceId): int {
            $batch = $this->lockBatch($batchId);
            $this->expireReservations($batchId);
            $reserved = $this->reservedQuantity($batchId);
            if ((int) $batch['quantity_remaining'] - $reserved < $quantity) {
                throw new InventoryException('Lô không đủ tồn khả dụng; còn khả dụng ' . max(0, (int) $batch['quantity_remaining'] - $reserved) . '.');
            }
            return $this->decrementBatch($batch, $quantity, $reason, $userId, $referenceType, $referenceId, 'out');
        });
    }

    public function adjust(
        int $batchId,
        int $signedQuantity,
        string $reason,
        ?int $userId = null,
        ?string $referenceType = 'adjustment',
        ?int $referenceId = null
    ): int {
        if ($signedQuantity === 0 || trim($reason) === '') {
            throw new InvalidArgumentException('Điều chỉnh phải khác 0 và có lý do bắt buộc.');
        }
        if (!$this->database->inTransaction()) {
            (new PaymentService($this->database))->expirePending();
        }

        return $this->transaction(function () use ($batchId, $signedQuantity, $reason, $userId, $referenceType, $referenceId): int {
            $batch = $this->lockBatch($batchId);
            $this->expireReservations($batchId);
            $remaining = (int) $batch['quantity_remaining'] + $signedQuantity;
            if ($remaining < 0) {
                throw new InventoryException('Điều chỉnh làm tồn kho âm; số lượng hiện có là ' . (int) $batch['quantity_remaining'] . '.');
            }
            if ($remaining > 4294967295) {
                throw new InventoryException('Số lượng điều chỉnh vượt giới hạn lưu trữ.');
            }
            if ($signedQuantity < 0 && $remaining < $this->reservedQuantity($batchId)) {
                throw new InventoryException('Không thể giảm tồn thấp hơn số lượng đang được giữ chỗ.');
            }
            $received = (int) $batch['quantity_received'] + max(0, $signedQuantity);
            $this->database->prepare(
                'UPDATE product_batches SET quantity_received = :received, quantity_remaining = :remaining WHERE id = :id'
            )->execute(['received' => $received, 'remaining' => $remaining, 'id' => $batchId]);
            $movementId = $this->insertMovement(
                (int) $batch['product_id'],
                $batchId,
                'adjustment',
                $signedQuantity,
                $referenceType,
                $referenceId,
                $userId,
                $reason,
                (float) $batch['average_unit_cost'],
                $remaining
            );
            $this->syncProductTotal((int) $batch['product_id']);
            return $movementId;
        });
    }

    public function allocateFefo(int $productId, int $quantity): array
    {
        if ($productId < 1 || $quantity < 1) {
            throw new InvalidArgumentException('Sản phẩm và số lượng cần phân bổ không hợp lệ.');
        }
        if (!$this->database->inTransaction()) {
            (new PaymentService($this->database))->expirePending();
        }

        return $this->transaction(function () use ($productId, $quantity): array {
            $this->expireReservations();
            $rows = $this->eligibleBatches($productId, true);
            $allocations = [];
            $available = 0;
            foreach ($rows as $batch) {
                $batch = $this->lockBatch((int) $batch['id']);
                $free = max(0, (int) $batch['quantity_remaining'] - $this->reservedQuantity((int) $batch['id']));
                if ($free === 0) {
                    continue;
                }
                $allocated = min($quantity - $available, $free);
                $allocations[] = ['batch_id' => (int) $batch['id'], 'batch_code' => $batch['batch_code'], 'quantity' => $allocated];
                $available += $allocated;
                if ($available >= $quantity) {
                    return $allocations;
                }
            }
            throw new InventoryException('Thiếu ' . ($quantity - $available) . ' đơn vị tồn khả dụng cho sản phẩm #' . $productId . '.');
        });
    }

    public function deductForOrder(int $orderId, array $items, ?int $userId = null): array
    {
        if ($orderId < 1 || !$items) {
            throw new InvalidArgumentException('Đơn hàng và danh sách sản phẩm bắt buộc.');
        }

        return $this->transaction(function () use ($orderId, $items, $userId): array {
            $orderLock = $this->database->prepare('SELECT id FROM orders WHERE id = :id FOR UPDATE');
            $orderLock->execute(['id' => $orderId]);
            if (!$orderLock->fetchColumn()) {
                throw new InventoryException('Không tìm thấy đơn hàng #' . $orderId . '.');
            }
            $existing = $this->database->prepare(
                'SELECT batch_id, product_id, -quantity AS quantity, id AS movement_id
                 FROM inventory_movements
                 WHERE reference_type = \'order\' AND reference_id = :order_id AND movement_type = \'out\'
                 ORDER BY id'
            );
            $existing->execute(['order_id' => $orderId]);
            $existingRows = $existing->fetchAll();
            if ($existingRows) {
                $this->releaseOrderReservations($orderId);
                return array_map(static fn (array $row): array => [
                    'batch_id' => (int) $row['batch_id'],
                    'product_id' => (int) $row['product_id'],
                    'quantity' => (int) $row['quantity'],
                    'movement_id' => (int) $row['movement_id'],
                ], $existingRows);
            }
            $this->expireReservations();
            $deducted = [];
            foreach ($items as $item) {
                $productId = (int) ($item['product_id'] ?? 0);
                $quantity = (int) ($item['quantity'] ?? 0);
                if ($productId < 1 || $quantity < 1) {
                    throw new InvalidArgumentException('Dữ liệu sản phẩm trong đơn hàng không hợp lệ.');
                }
                $rows = $this->eligibleBatches($productId, true);
                $available = 0;
                $allocations = [];
                foreach ($rows as $row) {
                    $batch = $this->lockBatch((int) $row['id']);
                    $reserved = $this->reservedQuantity((int) $batch['id']);
                    $ownReserved = $this->reservedQuantity((int) $batch['id'], $orderId);
                    $freeForThisOrder = max(0, (int) $batch['quantity_remaining'] - $reserved + $ownReserved);
                    $take = min($quantity - $available, $freeForThisOrder);
                    if ($take > 0) {
                        $allocations[] = ['batch' => $batch, 'quantity' => $take];
                        $available += $take;
                    }
                    if ($available >= $quantity) {
                        break;
                    }
                }
                if ($available < $quantity) {
                    throw new InventoryException('Thiếu ' . ($quantity - $available) . ' đơn vị sản phẩm #' . $productId . ' để xử lý đơn hàng.');
                }

                foreach ($allocations as $allocation) {
                    $batch = $allocation['batch'];
                    $movementId = $this->decrementBatch(
                        $batch,
                        $allocation['quantity'],
                        'Xuất bán đơn hàng #' . $orderId,
                        $userId,
                        'order',
                        $orderId,
                        'out'
                    );
                    $deducted[] = [
                        'batch_id' => (int) $batch['id'],
                        'product_id' => $productId,
                        'quantity' => $allocation['quantity'],
                        'movement_id' => $movementId,
                    ];
                    $this->consumeOrderReservation((int) $orderId, (int) $batch['id'], (int) $allocation['quantity']);
                }
            }
            $this->releaseOrderReservations($orderId);
            return $deducted;
        });
    }

    public function restoreForOrder(int $orderId, ?int $userId = null): int
    {
        if ($orderId < 1) {
            throw new InvalidArgumentException('Mã đơn hàng không hợp lệ.');
        }

        return $this->transaction(function () use ($orderId, $userId): int {
            $orderLock = $this->database->prepare('SELECT id FROM orders WHERE id = :id FOR UPDATE');
            $orderLock->execute(['id' => $orderId]);
            if (!$orderLock->fetchColumn()) {
                throw new InventoryException('Không tìm thấy đơn hàng #' . $orderId . '.');
            }
            $this->releaseOrderReservations($orderId);
            $query = $this->database->prepare(
                'SELECT m.batch_id, m.product_id, -SUM(m.quantity) AS quantity
                 FROM inventory_movements m
                 WHERE m.reference_type = \'order\' AND m.reference_id = :order_id AND m.movement_type = \'out\'
                 GROUP BY m.batch_id, m.product_id'
            );
            $query->execute(['order_id' => $orderId]);
            $restored = 0;
            foreach ($query->fetchAll() as $row) {
                $returnedQuery = $this->database->prepare(
                    'SELECT COALESCE(SUM(quantity), 0) FROM inventory_movements
                     WHERE reference_type = \'order_restore\' AND reference_id = :order_id AND batch_id = :batch_id'
                );
                $returnedQuery->execute(['order_id' => $orderId, 'batch_id' => $row['batch_id']]);
                $quantity = (int) $row['quantity'] - (int) $returnedQuery->fetchColumn();
                if ($quantity <= 0) {
                    continue;
                }
                $batch = $this->lockBatch((int) $row['batch_id']);
                $remaining = (int) $batch['quantity_remaining'] + $quantity;
                $this->database->prepare('UPDATE product_batches SET quantity_remaining = :remaining WHERE id = :id')
                    ->execute(['remaining' => $remaining, 'id' => $batch['id']]);
                $this->insertMovement(
                    (int) $row['product_id'],
                    (int) $batch['id'],
                    'return',
                    $quantity,
                    'order_restore',
                    $orderId,
                    $userId,
                    'Hoàn kho khi hủy đơn hàng #' . $orderId,
                    (float) $batch['average_unit_cost'],
                    $remaining
                );
                $this->syncProductTotal((int) $row['product_id']);
                $restored += $quantity;
            }
            return $restored;
        });
    }

    public function reserveForOrder(int $orderId, array $items, DateTimeImmutable $expiresAt): array
    {
        if ($orderId < 1 || !$items || $expiresAt <= new DateTimeImmutable()) {
            throw new InvalidArgumentException('Đơn hàng, sản phẩm hoặc thời hạn giữ chỗ không hợp lệ.');
        }
        if (!$this->database->inTransaction()) {
            (new PaymentService($this->database))->expirePending();
        }

        $quantitiesByProduct = [];
        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $quantity = (int) ($item['quantity'] ?? 0);
            if ($productId < 1 || $quantity < 1) {
                throw new InvalidArgumentException('Dữ liệu sản phẩm trong yêu cầu giữ chỗ không hợp lệ.');
            }
            $quantitiesByProduct[$productId] = ($quantitiesByProduct[$productId] ?? 0) + $quantity;
        }
        $lifetimeSeconds = max(1, $expiresAt->getTimestamp() - time());

        return $this->transaction(function () use ($orderId, $quantitiesByProduct, $lifetimeSeconds): array {
            $orderLock = $this->database->prepare('SELECT id FROM orders WHERE id = :id FOR UPDATE');
            $orderLock->execute(['id' => $orderId]);
            if (!$orderLock->fetchColumn()) {
                throw new InventoryException('Không tìm thấy đơn hàng #' . $orderId . '.');
            }
            $this->releaseOrderReservations($orderId);
            $this->expireReservations();
            $result = [];
            foreach ($quantitiesByProduct as $productId => $quantity) {
                $allocations = $this->allocateFefo($productId, $quantity);
                foreach ($allocations as $allocation) {
                    $this->database->prepare(
                        'INSERT INTO stock_reservations (order_id, batch_id, quantity, expires_at, status)
                         VALUES (:order_id, :batch_id, :quantity, DATE_ADD(NOW(), INTERVAL ' . (int) $lifetimeSeconds . ' SECOND), \'reserved\')'
                    )->execute([
                        'order_id' => $orderId,
                        'batch_id' => $allocation['batch_id'],
                        'quantity' => $allocation['quantity'],
                    ]);
                    $result[] = $allocation + ['product_id' => (int) $productId];
                }
            }
            return $result;
        });
    }

    public function availableStock(int $productId): int
    {
        (new PaymentService($this->database))->expirePending();
        $this->expireReservations();
        $query = $this->database->prepare(
            'SELECT COALESCE(SUM(GREATEST(0, b.quantity_remaining - COALESCE(r.quantity, 0))), 0)
             FROM product_batches b
             LEFT JOIN (
                 SELECT batch_id, SUM(quantity) AS quantity
                 FROM stock_reservations WHERE status = \'reserved\' AND expires_at > NOW()
                 GROUP BY batch_id
             ) r ON r.batch_id = b.id
             WHERE b.product_id = :product_id AND b.status = \'selling\'
               AND b.inspection_result <> \'failed\' AND b.quantity_remaining > 0
               AND (b.expires_at IS NULL OR b.expires_at >= CURDATE())'
        );
        $query->execute(['product_id' => $productId]);
        return (int) $query->fetchColumn();
    }

    public function availableStockByProductIds(array $productIds): array
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds), static fn (int $id): bool => $id > 0)));
        if (!$productIds) {
            return [];
        }
        (new PaymentService($this->database))->expirePending();
        $this->expireReservations();
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $query = $this->database->prepare(
            'SELECT b.product_id, COALESCE(SUM(GREATEST(0, b.quantity_remaining - COALESCE(r.quantity, 0))), 0) AS available
             FROM product_batches b
             LEFT JOIN (
                 SELECT batch_id, SUM(quantity) AS quantity
                 FROM stock_reservations WHERE status = \'reserved\' AND expires_at > NOW()
                 GROUP BY batch_id
             ) r ON r.batch_id = b.id
             WHERE b.product_id IN (' . $placeholders . ') AND b.status = \'selling\'
               AND b.inspection_result <> \'failed\' AND b.quantity_remaining > 0
               AND (b.expires_at IS NULL OR b.expires_at >= CURDATE())
             GROUP BY b.product_id'
        );
        $query->execute($productIds);
        $result = array_fill_keys($productIds, 0);
        foreach ($query->fetchAll() as $row) {
            $result[(int) $row['product_id']] = (int) $row['available'];
        }
        return $result;
    }

    public function confirmReceipt(int $receiptId, int $userId): void
    {
        $this->transaction(function () use ($receiptId, $userId): void {
            $query = $this->database->prepare('SELECT * FROM warehouse_receipts WHERE id = :id FOR UPDATE');
            $query->execute(['id' => $receiptId]);
            $receipt = $query->fetch();
            if (!$receipt || $receipt['status'] !== 'draft') {
                throw new InventoryException('Chỉ phiếu nhập nháp mới được xác nhận.');
            }
            $items = $this->database->prepare('SELECT * FROM warehouse_receipt_items WHERE receipt_id = :id ORDER BY id');
            $items->execute(['id' => $receiptId]);
            $rows = $items->fetchAll();
            if (!$rows) {
                throw new InventoryException('Không thể xác nhận phiếu nhập chưa có dòng hàng.');
            }
            foreach ($rows as $item) {
                $this->receive(
                    (int) $item['batch_id'],
                    (int) $item['quantity'],
                    'Xác nhận phiếu nhập ' . $receipt['receipt_no'],
                    $userId,
                    'warehouse_receipt',
                    $receiptId,
                    (float) $item['unit_cost']
                );
            }
            $this->database->prepare(
                'UPDATE warehouse_receipts SET status = \'received\', received_by = :user_id,
                    received_at = NOW(), confirmed_at = NOW() WHERE id = :id'
            )->execute(['user_id' => $userId, 'id' => $receiptId]);
            AuditLog::record($userId, 'warehouse_receipt_confirmed', 'warehouse_receipt', $receiptId, $receipt['receipt_no'], $this->database);
        });
    }

    public function cancelReceipt(int $receiptId, int $userId, string $reason): void
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Lý do hủy phiếu nhập bắt buộc.');
        }
        $this->transaction(function () use ($receiptId, $userId, $reason): void {
            $query = $this->database->prepare('SELECT * FROM warehouse_receipts WHERE id = :id FOR UPDATE');
            $query->execute(['id' => $receiptId]);
            $receipt = $query->fetch();
            if (!$receipt || $receipt['status'] !== 'received') {
                throw new InventoryException('Chỉ phiếu nhập đã xác nhận mới được hủy bằng phiếu đảo.');
            }
            $items = $this->database->prepare('SELECT * FROM warehouse_receipt_items WHERE receipt_id = :id ORDER BY id');
            $items->execute(['id' => $receiptId]);
            foreach ($items->fetchAll() as $item) {
                $this->adjust(
                    (int) $item['batch_id'],
                    -(int) $item['quantity'],
                    'Đảo phiếu nhập ' . $receipt['receipt_no'] . ': ' . trim($reason),
                    $userId,
                    'warehouse_receipt_cancel',
                    $receiptId
                );
            }
            $this->database->prepare(
                'UPDATE warehouse_receipts SET status = \'cancelled\', cancelled_at = NOW(),
                    cancellation_reason = :reason WHERE id = :id'
            )->execute(['reason' => trim($reason), 'id' => $receiptId]);
            AuditLog::record($userId, 'warehouse_receipt_cancelled', 'warehouse_receipt', $receiptId, trim($reason), $this->database);
        });
    }

    public function confirmStocktake(int $stocktakeId, int $userId): void
    {
        $this->transaction(function () use ($stocktakeId, $userId): void {
            $query = $this->database->prepare('SELECT * FROM stocktakes WHERE id = :id FOR UPDATE');
            $query->execute(['id' => $stocktakeId]);
            $stocktake = $query->fetch();
            if (!$stocktake || $stocktake['status'] !== 'draft') {
                throw new InventoryException('Chỉ đợt kiểm kê nháp mới được xác nhận.');
            }
            $items = $this->database->prepare('SELECT * FROM stocktake_items WHERE stocktake_id = :id ORDER BY batch_id');
            $items->execute(['id' => $stocktakeId]);
            foreach ($items->fetchAll() as $item) {
                if ($item['counted_quantity'] === null) {
                    throw new InventoryException('Hãy nhập số đếm thực tế cho tất cả lô trước khi xác nhận.');
                }
                $batch = $this->lockBatch((int) $item['batch_id']);
                $difference = (int) $item['counted_quantity'] - (int) $batch['quantity_remaining'];
                if ($difference !== 0) {
                    $this->adjust(
                        (int) $item['batch_id'],
                        $difference,
                        'Kiểm kê ' . $stocktake['stocktake_no'],
                        $userId,
                        'stocktake',
                        $stocktakeId
                    );
                }
            }
            $this->database->prepare('UPDATE stocktakes SET status = \'confirmed\', confirmed_by = :user_id, confirmed_at = NOW() WHERE id = :id')
                ->execute(['user_id' => $userId, 'id' => $stocktakeId]);
            AuditLog::record($userId, 'stocktake_confirmed', 'stocktake', $stocktakeId, $stocktake['stocktake_no'], $this->database);
        });
    }

    public function expireBatches(): int
    {
        return $this->transaction(function (): int {
            $statement = $this->database->prepare(
                'UPDATE product_batches SET status = \'ended\'
                 WHERE status = \'selling\' AND expires_at IS NOT NULL AND expires_at < CURDATE()'
            );
            $statement->execute();
            $count = $statement->rowCount();
            if ($count > 0) {
                AuditLog::record(
                    Auth::user() ? (int) Auth::user()['id'] : null,
                    'expired_batches_ended',
                    'product_batch',
                    null,
                    'count=' . $count,
                    $this->database
                );
            }
            return $count;
        });
    }

    private function decrementBatch(
        array $batch,
        int $quantity,
        string $reason,
        ?int $userId,
        ?string $referenceType,
        ?int $referenceId,
        string $movementType
    ): int {
        $update = $this->database->prepare(
            'UPDATE product_batches SET quantity_remaining = quantity_remaining - :quantity
             WHERE id = :id AND quantity_remaining >= :minimum'
        );
        $update->execute(['quantity' => $quantity, 'id' => $batch['id'], 'minimum' => $quantity]);
        if ($update->rowCount() !== 1) {
            throw new InventoryException('Tồn lô đã thay đổi hoặc không đủ số lượng; vui lòng tải lại và thử lại.');
        }
        $remaining = (int) $batch['quantity_remaining'] - $quantity;
        $movementId = $this->insertMovement(
            (int) $batch['product_id'],
            (int) $batch['id'],
            $movementType,
            -$quantity,
            $referenceType,
            $referenceId,
            $userId,
            $reason,
            (float) $batch['average_unit_cost'],
            $remaining
        );
        $this->syncProductTotal((int) $batch['product_id']);
        return $movementId;
    }

    private function insertMovement(
        int $productId,
        int $batchId,
        string $movementType,
        int $quantity,
        ?string $referenceType,
        ?int $referenceId,
        ?int $userId,
        string $notes,
        float $unitCost,
        int $balanceAfter
    ): int {
        $statement = $this->database->prepare(
            'INSERT INTO inventory_movements
                (product_id, batch_id, movement_type, quantity, reference_type, reference_id,
                 performed_by, notes, unit_cost, balance_after)
             VALUES
                (:product_id, :batch_id, :movement_type, :quantity, :reference_type, :reference_id,
                 :performed_by, :notes, :unit_cost, :balance_after)'
        );
        $statement->execute([
            'product_id' => $productId,
            'batch_id' => $batchId,
            'movement_type' => $movementType,
            'quantity' => $quantity,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'performed_by' => $userId,
            'notes' => mb_substr($notes, 0, 500, 'UTF-8'),
            'unit_cost' => $unitCost,
            'balance_after' => $balanceAfter,
        ]);
        return (int) $this->database->lastInsertId();
    }

    private function lockBatch(int $batchId): array
    {
        $query = $this->database->prepare('SELECT * FROM product_batches WHERE id = :id FOR UPDATE');
        $query->execute(['id' => $batchId]);
        $batch = $query->fetch();
        if (!$batch) {
            throw new InventoryException('Không tìm thấy lô hàng #' . $batchId . '.');
        }
        return $batch;
    }

    private function eligibleBatches(int $productId, bool $lockRows): array
    {
        $sql = 'SELECT id, batch_code FROM product_batches
                WHERE product_id = :product_id AND status = \'selling\'
                  AND inspection_result <> \'failed\' AND quantity_remaining > 0
                  AND (expires_at IS NULL OR expires_at >= CURDATE())
                ORDER BY expires_at IS NULL, expires_at, harvested_at, id';
        if ($lockRows) {
            $sql .= ' FOR UPDATE';
        }
        $query = $this->database->prepare($sql);
        $query->execute(['product_id' => $productId]);
        return $query->fetchAll();
    }

    private function reservedQuantity(int $batchId, ?int $orderId = null): int
    {
        $sql = 'SELECT COALESCE(SUM(quantity), 0) FROM stock_reservations
                WHERE batch_id = :batch_id AND status = \'reserved\' AND expires_at > NOW()';
        $parameters = ['batch_id' => $batchId];
        if ($orderId !== null) {
            $sql .= ' AND order_id = :order_id';
            $parameters['order_id'] = $orderId;
        }
        $query = $this->database->prepare($sql);
        $query->execute($parameters);
        return (int) $query->fetchColumn();
    }

    public function expireReservations(?int $batchId = null): void
    {
        $sql = 'UPDATE stock_reservations SET status = \'expired\' WHERE status = \'reserved\' AND expires_at <= NOW()';
        $parameters = [];
        if ($batchId !== null) {
            $sql .= ' AND batch_id = :batch_id';
            $parameters['batch_id'] = $batchId;
        }
        $this->database->prepare($sql)->execute($parameters);
    }

    private function releaseOrderReservations(int $orderId): void
    {
        $this->database->prepare(
            'UPDATE stock_reservations SET status = \'released\'
             WHERE order_id = :order_id AND status = \'reserved\''
        )->execute(['order_id' => $orderId]);
    }

    private function consumeOrderReservation(int $orderId, int $batchId, int $quantity): void
    {
        $query = $this->database->prepare(
            'SELECT id, quantity, expires_at FROM stock_reservations
             WHERE order_id = :order_id AND batch_id = :batch_id
               AND status = \'reserved\' AND expires_at > NOW()
             ORDER BY id FOR UPDATE'
        );
        $query->execute(['order_id' => $orderId, 'batch_id' => $batchId]);
        foreach ($query->fetchAll() as $reservation) {
            if ($quantity <= 0) {
                break;
            }
            $reserved = (int) $reservation['quantity'];
            $consumed = min($reserved, $quantity);
            if ($consumed === $reserved) {
                $this->database->prepare(
                    'UPDATE stock_reservations SET status = \'consumed\' WHERE id = :id'
                )->execute(['id' => $reservation['id']]);
            } else {
                $this->database->prepare(
                    'UPDATE stock_reservations SET quantity = :remaining WHERE id = :id'
                )->execute(['remaining' => $reserved - $consumed, 'id' => $reservation['id']]);
                $this->database->prepare(
                    'INSERT INTO stock_reservations (order_id, batch_id, quantity, expires_at, status)
                     VALUES (:order_id, :batch_id, :quantity, :expires_at, \'consumed\')'
                )->execute([
                    'order_id' => $orderId,
                    'batch_id' => $batchId,
                    'quantity' => $consumed,
                    'expires_at' => $reservation['expires_at'],
                ]);
            }
            $quantity -= $consumed;
        }
    }

    private function syncProductTotal(int $productId): void
    {
        $this->database->prepare(
            'UPDATE products SET total_stock = (
                SELECT COALESCE(SUM(quantity_remaining), 0) FROM product_batches WHERE product_id = :batch_product_id
             ) WHERE id = :product_id'
        )->execute(['batch_product_id' => $productId, 'product_id' => $productId]);
    }

    private function transaction(callable $callback): mixed
    {
        $ownsTransaction = !$this->database->inTransaction();
        if ($ownsTransaction) {
            $this->database->beginTransaction();
        }
        try {
            $result = $callback();
            if ($ownsTransaction) {
                $this->database->commit();
            }
            return $result;
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }
}
