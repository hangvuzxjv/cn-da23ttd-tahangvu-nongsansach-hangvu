<?php include FRONTEND_PATH . '/views/admin/_inventory-navigation.php'; ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h2 mb-0">Kiểm kê</h1>
    <?php if ($canManage): ?><form method="post" action="<?= e(url('/admin/inventory/stocktakes')) ?>" class="d-flex gap-2">
        <?= csrf_field() ?><input class="form-control" name="notes" placeholder="Ghi chú đợt kiểm kê"><button class="btn btn-primary">Tạo đợt kiểm kê</button>
    </form><?php endif; ?>
</div>
<p class="inventory-stocktake-guide">Kiểm kê là đếm hàng thực tế rồi so với số trong hệ thống. Bước 1: Tạo đợt kiểm kê. Bước 2: Nhập số đếm thực tế. Bước 3: Xác nhận để hệ thống tự điều chỉnh chênh lệch.</p>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<div class="table-responsive mb-4"><table class="table table-sm table-striped">
    <thead><tr><th>Mã đợt</th><th>Ngày tạo</th><th>Người tạo</th><th>Số lô</th><th>Trạng thái</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($stocktakes as $stocktake): ?>
        <tr>
            <td><?= e($stocktake['stocktake_no']) ?></td>
            <td><?= e($stocktake['created_at']) ?></td>
            <td><?= e($stocktake['creator_name'] ?? '—') ?></td>
            <td><?= (int) $stocktake['item_count'] ?></td>
            <td><?= e($stocktake['status'] === 'draft' ? 'Đang kiểm' : InventoryLabels::status($stocktake['status'])) ?></td>
            <td><a class="btn btn-sm btn-outline-primary" href="<?= e(url('/admin/inventory/stocktakes?id=' . $stocktake['id'])) ?>">Mở đợt kiểm kê</a></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$stocktakes): ?><tr><td colspan="6" class="text-secondary">Chưa có đợt kiểm kê.</td></tr><?php endif; ?>
    </tbody>
</table></div>
<?php include FRONTEND_PATH . '/views/admin/_pagination.php'; ?>
<?php if ($selectedId): ?>
    <?php
    $isDraft = false;
    foreach ($stocktakes as $stocktake) {
        if ((int) $stocktake['id'] === $selectedId && $stocktake['status'] === 'draft') {
            $isDraft = true;
            break;
        }
    }
    $initialCounted = 0;
    $initialDifferences = 0;
    foreach ($items as $item) {
        if ($item['counted_quantity'] !== null) {
            $initialCounted++;
            if ((int) $item['counted_quantity'] !== (int) $item['system_quantity']) {
                $initialDifferences++;
            }
        }
    }
    ?>
    <section class="inventory-stocktake-details" data-stocktake-details>
        <h2 class="h4">Chi tiết đợt kiểm kê #<?= $selectedId ?></h2>
        <div class="row g-2 align-items-end mb-3 inventory-stocktake-controls">
            <div class="col-md-7">
                <label class="form-label" for="stocktakeSearch">Tìm sản phẩm hoặc mã lô</label>
                <input class="form-control" id="stocktakeSearch" type="search" placeholder="Nhập tên sản phẩm hoặc mã lô" data-stocktake-search>
            </div>
            <div class="col-md-5">
                <label class="form-label" for="stocktakeFilter">Lọc dòng</label>
                <select class="form-select" id="stocktakeFilter" data-stocktake-filter>
                    <option value="all">Tất cả dòng</option>
                    <option value="uncounted">Chỉ hiện dòng chưa đếm</option>
                    <option value="difference">Chỉ hiện dòng chênh lệch</option>
                </select>
            </div>
        </div>
        <p class="inventory-stocktake-summary" aria-live="polite" data-stocktake-summary>Đã đếm <?= $initialCounted ?>/<?= count($items) ?> dòng, có <?= $initialDifferences ?> dòng chênh lệch.</p>
        <?php if ($isDraft && $canManage): ?>
            <form method="post" action="<?= e(url('/admin/inventory/stocktakes/' . $selectedId . '/counts')) ?>" data-stocktake-count-form>
                <?= csrf_field() ?>
        <?php endif; ?>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle inventory-stocktake-table">
                        <thead><tr><th>Sản phẩm</th><th>Mã lô</th><th>Tồn hệ thống</th><th>Số đếm thực tế</th><th>Chênh lệch</th></tr></thead>
                        <tbody>
                        <?php foreach ($items as $item): ?>
                            <?php
                            $countedQuantity = $item['counted_quantity'] === null ? null : (int) $item['counted_quantity'];
                            $difference = $countedQuantity === null ? null : $countedQuantity - (int) $item['system_quantity'];
                            $differenceClass = $difference === null || $difference === 0 ? '' : ($difference > 0 ? 'inventory-stocktake-difference--surplus' : 'inventory-stocktake-difference--shortage');
                            $differenceLabel = $difference === null ? '—'
                                : ($difference > 0 ? '+' . $difference . ' · Dư' : ($difference < 0 ? $difference . ' · Thiếu' : '0'));
                            ?>
                            <tr data-stocktake-row
                                data-system-quantity="<?= (int) $item['system_quantity'] ?>"
                                data-counted-quantity="<?= $countedQuantity === null ? '' : $countedQuantity ?>"
                                data-saved-counted-quantity="<?= $countedQuantity === null ? '' : $countedQuantity ?>"
                                data-product-name="<?= e($item['product_name']) ?>"
                                data-batch-code="<?= e($item['batch_code']) ?>">
                                <td><?= e($item['product_name']) ?> <small class="text-secondary"><?= e($item['sku']) ?></small></td>
                                <td><code><?= e($item['batch_code']) ?></code></td>
                                <td><?= (int) $item['system_quantity'] ?></td>
                                <td>
                                    <?php if ($isDraft && $canManage): ?>
                                        <input class="form-control form-control-sm" type="number" name="counted_quantity[<?= (int) $item['batch_id'] ?>]" min="0" step="1" value="<?= $countedQuantity === null ? '' : $countedQuantity ?>" aria-label="Số đếm thực tế: <?= e($item['product_name']) ?>, lô <?= e($item['batch_code']) ?>" data-stocktake-count data-system-quantity="<?= (int) $item['system_quantity'] ?>">
                                    <?php else: ?>
                                        <?= $countedQuantity === null ? '—' : $countedQuantity ?>
                                    <?php endif; ?>
                                </td>
                                <td><span class="<?= e($differenceClass) ?>" data-stocktake-difference><?= e($differenceLabel) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$items): ?><tr data-stocktake-empty><td colspan="5" class="text-secondary">Đợt kiểm kê không có dòng hàng.</td></tr><?php endif; ?>
                        <tr class="d-none" data-stocktake-no-results><td colspan="5" class="text-secondary">Không tìm thấy dòng phù hợp.</td></tr>
                        </tbody>
                    </table>
                </div>
                <?php if ($isDraft && $canManage): ?><button class="btn btn-outline-primary" type="submit">Lưu số đếm</button></form><?php endif; ?>
        <?php if ($isDraft && $canManage): ?>
            <form class="mt-2" method="post" action="<?= e(url('/admin/inventory/stocktakes/' . $selectedId . '/confirm')) ?>" data-stocktake-confirm>
                <?= csrf_field() ?><button class="btn btn-success" type="submit">Xác nhận kiểm kê</button>
            </form>
        <?php endif; ?>
    </section>
<?php endif; ?>
