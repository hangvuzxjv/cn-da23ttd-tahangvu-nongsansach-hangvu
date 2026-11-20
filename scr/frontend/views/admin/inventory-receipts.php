<?php include FRONTEND_PATH . '/views/admin/_inventory-navigation.php'; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h2 mb-0">Nhập hàng</h1>
    <a class="btn btn-outline-primary" href="<?= e(url('/admin/inventory')) ?>">Tổng quan kho</a>
</div>
<p class="inventory-receipt-guide">Phiếu nháp chưa làm tăng tồn kho. Bấm Xác nhận nhập kho để cộng hàng vào kho.</p>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<?php if ($canManage): ?>
    <form class="card mb-4 inventory-receipt-form" method="post" action="<?= e(url('/admin/inventory/receipts')) ?>" data-receipt-form novalidate>
        <div class="card-header"><h2 class="h5 mb-0">Tạo phiếu nhập</h2></div>
        <div class="card-body">
            <?= csrf_field() ?>
            <div class="alert alert-danger d-none" role="alert" aria-live="assertive" data-receipt-error></div>
            <section class="inventory-receipt-form__step">
                <h3 class="h6">Bước 1: Chọn nông trại</h3>
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label w-100">Nông trại
                            <select class="form-select" name="supplier_farm_id" required data-receipt-farm>
                                <option value="">Chọn nông trại</option>
                                <?php foreach ($farms as $farm): ?><option value="<?= (int) $farm['id'] ?>"><?= e($farm['name']) ?></option><?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label w-100">Ghi chú
                            <input class="form-control" name="notes" maxlength="500">
                        </label>
                    </div>
                </div>
            </section>
            <section class="inventory-receipt-form__step">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h3 class="h6 mb-0">Bước 2: Thêm sản phẩm nhập</h3>
                    <button class="btn btn-sm btn-outline-primary" type="button" data-receipt-add-line>+ Thêm sản phẩm</button>
                </div>
                <p class="small text-secondary mt-2 mb-3">Mỗi lần nhập hàng là một lô, hệ thống tự đặt mã lô. Khách quét QR sẽ thấy nông trại, ngày thu hoạch và hạn dùng của lô này.</p>
                <div class="inventory-receipt-lines" data-receipt-lines>
                    <div class="inventory-receipt-line" data-receipt-line>
                        <div class="inventory-receipt-line__header">
                            <strong>Sản phẩm nhập</strong>
                            <button class="btn btn-sm btn-outline-danger" type="button" data-receipt-remove-line>Xóa dòng</button>
                        </div>
                        <div class="inventory-receipt-line__fields">
                            <label class="form-label">Sản phẩm
                                <select class="form-select" name="product_id[]" data-receipt-field="product">
                                    <option value="">Chọn sản phẩm</option>
                                    <?php foreach ($products as $product): ?>
                                        <?php
                                        $saleUnit = trim((string) ($product['sale_unit'] ?? ''));
                                        $unitLabel = ProductUnitFormatter::shortLabel(
                                            $saleUnit !== '' ? $saleUnit : (string) $product['unit'],
                                            $saleUnit !== '' && $product['pack_size'] !== null ? (float) $product['pack_size'] : null,
                                            $saleUnit !== '' ? ($product['pack_size_unit'] ?? null) : null
                                        );
                                        ?>
                                        <option value="<?= (int) $product['id'] ?>" data-farm-id="<?= (int) $product['farm_id'] ?>"
                                                data-unit-label="<?= e(mb_strtolower($unitLabel, 'UTF-8')) ?>">
                                            <?= e($product['name']) ?> (<?= e($unitLabel) ?>) · <?= e($product['sku']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="form-label">Lô có sẵn (nếu cần)
                                <select class="form-select" name="batch_id[]" data-receipt-field="batch">
                                    <option value="">Tạo lô mới (tự đặt mã)</option>
                                    <?php foreach ($batches as $batch): ?><option value="<?= (int) $batch['id'] ?>" data-product-id="<?= (int) $batch['product_id'] ?>"><?= e($batch['batch_code']) ?></option><?php endforeach; ?>
                                </select>
                            </label>
                            <label class="form-label">Số lượng
                                <span class="input-group">
                                    <input class="form-control" type="number" name="quantity[]" min="1" step="1" data-receipt-field="quantity">
                                    <span class="input-group-text" data-receipt-unit></span>
                                </span>
                            </label>
                            <label class="form-label"><span data-receipt-cost-label>Giá nhập / đơn vị</span>
                                <input class="form-control" type="number" name="unit_cost[]" min="0" step="0.01" value="0" data-receipt-field="cost">
                            </label>
                            <label class="form-label">Ngày thu hoạch
                                <input class="form-control" type="date" name="harvested_at[]" data-receipt-field="harvested">
                            </label>
                            <label class="form-label">Ngày đóng gói
                                <input class="form-control" type="date" name="packed_at[]" data-receipt-field="packed">
                            </label>
                            <label class="form-label">Hạn dùng
                                <input class="form-control" type="date" name="expires_at[]" data-receipt-field="expires">
                            </label>
                        </div>
                        <p class="form-text mb-0" data-receipt-note hidden></p>
                    </div>
                </div>
                <template data-receipt-line-template>
                    <div class="inventory-receipt-line" data-receipt-line>
                        <div class="inventory-receipt-line__header">
                            <strong>Sản phẩm nhập</strong>
                            <button class="btn btn-sm btn-outline-danger" type="button" data-receipt-remove-line>Xóa dòng</button>
                        </div>
                        <div class="inventory-receipt-line__fields">
                            <label class="form-label">Sản phẩm
                                <select class="form-select" name="product_id[]" data-receipt-field="product">
                                    <option value="">Chọn sản phẩm</option>
                                    <?php foreach ($products as $product): ?>
                                        <?php
                                        $saleUnit = trim((string) ($product['sale_unit'] ?? ''));
                                        $unitLabel = ProductUnitFormatter::shortLabel(
                                            $saleUnit !== '' ? $saleUnit : (string) $product['unit'],
                                            $saleUnit !== '' && $product['pack_size'] !== null ? (float) $product['pack_size'] : null,
                                            $saleUnit !== '' ? ($product['pack_size_unit'] ?? null) : null
                                        );
                                        ?>
                                        <option value="<?= (int) $product['id'] ?>" data-farm-id="<?= (int) $product['farm_id'] ?>"
                                                data-unit-label="<?= e(mb_strtolower($unitLabel, 'UTF-8')) ?>">
                                            <?= e($product['name']) ?> (<?= e($unitLabel) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="form-label">Lô có sẵn (nếu cần)
                                <select class="form-select" name="batch_id[]" data-receipt-field="batch">
                                    <option value="">Tạo lô mới (tự đặt mã)</option>
                                    <?php foreach ($batches as $batch): ?><option value="<?= (int) $batch['id'] ?>" data-product-id="<?= (int) $batch['product_id'] ?>"><?= e($batch['batch_code']) ?></option><?php endforeach; ?>
                                </select>
                            </label>
                            <label class="form-label">Số lượng
                                <span class="input-group">
                                    <input class="form-control" type="number" name="quantity[]" min="1" step="1" data-receipt-field="quantity">
                                    <span class="input-group-text" data-receipt-unit></span>
                                </span>
                            </label>
                            <label class="form-label"><span data-receipt-cost-label>Giá nhập / đơn vị</span>
                                <input class="form-control" type="number" name="unit_cost[]" min="0" step="0.01" value="0" data-receipt-field="cost">
                            </label>
                            <label class="form-label">Ngày thu hoạch
                                <input class="form-control" type="date" name="harvested_at[]" data-receipt-field="harvested">
                            </label>
                            <label class="form-label">Ngày đóng gói
                                <input class="form-control" type="date" name="packed_at[]" data-receipt-field="packed">
                            </label>
                            <label class="form-label">Hạn dùng
                                <input class="form-control" type="date" name="expires_at[]" data-receipt-field="expires">
                            </label>
                        </div>
                        <p class="form-text mb-0" data-receipt-note hidden></p>
                    </div>
                </template>
            </section>
            <section class="inventory-receipt-form__step inventory-receipt-form__submit">
                <h3 class="h6">Bước 3: Lưu phiếu</h3>
                <button class="btn btn-primary" type="submit">Lưu phiếu nháp</button>
            </section>
        </div>
    </form>
<?php endif; ?>
<div class="table-responsive"><table class="table table-striped align-middle">
    <thead><tr><th>Mã phiếu</th><th>Nhà cung cấp</th><th>Ngày</th><th>Số dòng</th><th>Trạng thái</th><th>Người xác nhận</th><th>Thao tác</th></tr></thead><tbody>
    <?php foreach ($receipts as $receipt): ?><tr>
        <td><strong><?= e($receipt['receipt_no']) ?></strong></td><td><?= e($receipt['farm_name'] ?? $receipt['supplier_name']) ?></td>
        <td><?= e($receipt['received_at']) ?></td><td>
            <?= (int) $receipt['item_count'] ?>
            <?php if (!empty($receiptItems[(int) $receipt['id']])): ?>
                <details class="small">
                    <summary>Chi tiết</summary>
                    <ul class="mb-0 ps-3">
                        <?php foreach ($receiptItems[(int) $receipt['id']] as $receiptItem): ?>
                            <?php
                            $saleUnit = trim((string) ($receiptItem['sale_unit'] ?? ''));
                            $unitLabel = ProductUnitFormatter::shortLabel(
                                $saleUnit !== '' ? $saleUnit : (string) $receiptItem['unit'],
                                $saleUnit !== '' && $receiptItem['pack_size'] !== null ? (float) $receiptItem['pack_size'] : null,
                                $saleUnit !== '' ? ($receiptItem['pack_size_unit'] ?? null) : null
                            );
                            ?>
                            <li><?= e($receiptItem['product_name']) ?>: <?= (int) $receiptItem['quantity'] ?> <?= e(mb_strtolower($unitLabel, 'UTF-8')) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            <?php endif; ?>
        </td><td><?= e(InventoryLabels::status($receipt['status'])) ?></td>
        <td><?= e($receipt['receiver_name'] ?? '—') ?></td><td>
            <?php if ($canManage && $receipt['status'] === 'draft'): ?>
                <form class="d-inline" method="post" action="<?= e(url('/admin/inventory/receipts/' . $receipt['id'] . '/confirm')) ?>" onsubmit="return confirm('Xác nhận phiếu này và cộng tồn theo lô?')"><?= csrf_field() ?><button class="btn btn-sm btn-success">Xác nhận nhập kho</button></form>
            <?php elseif ($canManage && $receipt['status'] === 'received'): ?>
                <form method="post" action="<?= e(url('/admin/inventory/receipts/' . $receipt['id'] . '/cancel')) ?>" class="d-flex gap-1">
                    <?= csrf_field() ?><input class="form-control form-control-sm" name="reason" placeholder="Lý do hủy" required><button class="btn btn-sm btn-outline-danger">Hủy phiếu nhập</button>
                </form>
            <?php else: ?><?= e($receipt['cancellation_reason'] ?? '—') ?><?php endif; ?>
        </td>
    </tr><?php endforeach; ?>
    <?php if (!$receipts): ?><tr><td colspan="7" class="text-secondary">Chưa có phiếu nhập.</td></tr><?php endif; ?>
    </tbody>
</table></div>
<?php include FRONTEND_PATH . '/views/admin/_pagination.php'; ?>
