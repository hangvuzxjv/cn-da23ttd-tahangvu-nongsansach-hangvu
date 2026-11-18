<?php include FRONTEND_PATH . '/views/admin/_inventory-navigation.php'; ?>
<div class="inventory-stock-page">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h2 mb-0">Tồn kho theo lô</h1>
        <a class="btn btn-outline-success" href="<?= e(url('/admin/inventory/stock.csv?' . http_build_query(['q' => $query, 'filter' => $filter]))) ?>">Xuất CSV</a>
    </div>
    <p class="inventory-stock__guide">Xem hàng còn bao nhiêu theo từng lô. Bấm Điều chỉnh khi hàng hỏng, hao hụt hoặc cần sửa số lượng.</p>
    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <form class="row g-2 mb-3" method="get" action="<?= e(url('/admin/inventory/stock')) ?>">
        <div class="col-md-5"><input class="form-control" name="q" value="<?= e($query) ?>" placeholder="Tìm sản phẩm, SKU hoặc mã lô"></div>
        <div class="col-md-4"><select class="form-select" name="filter">
            <option value="">Tất cả lô</option>
            <option value="low" <?= $filter === 'low' ? 'selected' : '' ?>>Sắp hết hàng</option>
            <option value="near_expiry" <?= $filter === 'near_expiry' ? 'selected' : '' ?>>Sắp hết hạn (<?= (int) $warningDays ?> ngày)</option>
            <option value="expired" <?= $filter === 'expired' ? 'selected' : '' ?>>Đã hết hạn</option>
            <option value="out_of_stock" <?= $filter === 'out_of_stock' ? 'selected' : '' ?>>Hết hàng</option>
        </select></div>
        <div class="col-md-3 d-flex gap-2"><button class="btn btn-primary">Lọc</button><a class="btn btn-outline-secondary" href="<?= e(url('/admin/inventory/stock')) ?>">Xóa lọc</a></div>
    </form>
    <nav class="inventory-stock__quick-filters mb-3" aria-label="Lọc nhanh tồn kho">
        <?php foreach (['' => 'Tất cả', 'near_expiry' => 'Sắp hết hạn', 'expired' => 'Đã hết hạn', 'low' => 'Sắp hết hàng'] as $quickFilter => $quickLabel): ?>
            <?php $quickQuery = $query !== '' ? ['q' => $query] : []; if ($quickFilter !== '') { $quickQuery['filter'] = $quickFilter; } ?>
            <a class="btn btn-sm <?= $filter === $quickFilter ? 'btn-success' : 'btn-outline-secondary' ?>"
               href="<?= e(url('/admin/inventory/stock' . ($quickQuery ? '?' . http_build_query($quickQuery) : ''))) ?>"
               <?= $filter === $quickFilter ? 'aria-current="page"' : '' ?>><?= e($quickLabel) ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="table-responsive">
        <table class="table table-striped align-middle inventory-stock__table">
            <thead><tr><th>Sản phẩm</th><th>Mã lô <span class="small text-secondary" title="Lô là một đợt hàng nhập có mã riêng để truy xuất." aria-label="Giải thích về lô" tabindex="0">?</span></th><th>Hạn dùng</th><th>Còn trong kho</th><th>Còn có thể bán <span class="small text-secondary" title="Đã đặt chờ giao là hàng dành cho đơn chưa giao; số này được trừ khi tính lượng còn có thể bán." aria-label="Giải thích hàng đã đặt chờ giao và lượng còn có thể bán" tabindex="0">?</span></th><th>Trạng thái</th><?php if ($canManage): ?><th></th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <?php
                $today = new DateTimeImmutable('today');
                $expiresAt = !empty($row['expires_at']) ? new DateTimeImmutable($row['expires_at']) : null;
                $isExpired = $expiresAt !== null && $expiresAt < $today;
                $isNearExpiry = $expiresAt !== null && $expiresAt >= $today
                    && $expiresAt <= $today->modify('+' . (int) $warningDays . ' days');
                $isStopped = in_array($row['status'], ['ended', 'recalled'], true);
                $statusLabel = $isExpired ? 'Đã hết hạn' : InventoryLabels::status($row['status']);
                $statusClass = $isExpired || $isStopped ? 'text-bg-danger'
                    : ($isNearExpiry ? 'text-bg-warning'
                        : ($row['status'] === 'selling' ? 'text-bg-success' : 'text-bg-secondary'));
                if (!$isExpired && !$isStopped && $isNearExpiry) {
                    $statusLabel = 'Sắp hết hạn';
                }
                ?>
                <tr>
                    <td class="inventory-stock__product"><?= e($row['product_name']) ?><small class="d-block text-secondary"><?= e($row['sku']) ?></small></td>
                    <td><a href="<?= e(url('/admin/batches/' . $row['batch_id'])) ?>"><code><?= e($row['batch_code']) ?></code></a></td>
                    <td><?= e($row['expires_at'] ?? '—') ?></td>
                    <td><?= (int) $row['quantity_remaining'] ?></td>
                    <td><strong><?= (int) $row['available_quantity'] ?></strong></td>
                    <td><span class="badge rounded-pill <?= e($statusClass) ?>"><?= e($statusLabel) ?></span></td>
                    <?php if ($canManage): ?><td>
                        <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal"
                                data-bs-target="#stockAdjustmentModal" data-stock-adjustment-trigger
                                data-batch-id="<?= (int) $row['batch_id'] ?>"
                                data-batch-code="<?= e($row['batch_code']) ?>"
                                data-product-name="<?= e($row['product_name']) ?>">Điều chỉnh</button>
                    </td><?php endif; ?>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="<?= $canManage ? 7 : 6 ?>" class="text-secondary">Không tìm thấy lô phù hợp.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php include FRONTEND_PATH . '/views/admin/_pagination.php'; ?>
    <p class="text-secondary small">Tệp CSV xuất toàn bộ lô theo bộ lọc.</p>

    <?php if ($canManage): ?>
        <div class="modal fade" id="stockAdjustmentModal" tabindex="-1" aria-labelledby="stockAdjustmentModalTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="post" action="<?= e(url('/admin/inventory/movement')) ?>" data-stock-adjustment-form>
                        <?= csrf_field() ?>
                        <input type="hidden" name="batch_id" value="" data-stock-adjustment-batch-id>
                        <input type="hidden" name="action" value="adjust" data-stock-adjustment-action>
                        <input type="hidden" name="direction" value="decrease" data-stock-adjustment-direction>
                        <div class="modal-header">
                            <div>
                                <h2 class="modal-title fs-5" id="stockAdjustmentModalTitle">Điều chỉnh tồn kho</h2>
                                <p class="small text-secondary mb-0" data-stock-adjustment-batch></p>
                            </div>
                            <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Đóng"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label" for="stockAdjustmentType">Loại điều chỉnh</label>
                                <select class="form-select" id="stockAdjustmentType" data-stock-adjustment-type>
                                    <option value="damage">Giảm do hỏng/hao hụt</option>
                                    <option value="issue">Xuất khác</option>
                                    <option value="increase">Tăng</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="stockAdjustmentQuantity">Số lượng</label>
                                <input class="form-control" id="stockAdjustmentQuantity" type="number" name="quantity" min="1" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="stockAdjustmentReason">Lý do</label>
                                <select class="form-select" id="stockAdjustmentReason" name="reason_code" required data-stock-adjustment-reason>
                                    <option value="">Chọn lý do</option>
                                    <option value="damage">Hư hỏng</option>
                                    <option value="expired">Hết hạn</option>
                                    <option value="loss">Mất mát</option>
                                    <option value="sample">Hàng mẫu</option>
                                    <option value="other">Khác</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label" for="stockAdjustmentNotes">Ghi chú</label>
                                <textarea class="form-control" id="stockAdjustmentNotes" name="notes" rows="2" data-stock-adjustment-notes></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Đóng</button>
                            <button class="btn btn-primary" type="submit">Xác nhận</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
