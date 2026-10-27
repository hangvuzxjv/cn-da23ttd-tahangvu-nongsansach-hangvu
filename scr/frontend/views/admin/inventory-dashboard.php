<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h1 class="h2 mb-0">Tổng quan kho</h1>
    <nav class="d-flex gap-2">
        <a class="btn btn-outline-primary" href="<?= e(url('/admin/inventory/stock')) ?>">Tồn kho</a>
        <a class="btn btn-outline-primary" href="<?= e(url('/admin/inventory/receipts')) ?>">Phiếu nhập</a>
        <a class="btn btn-outline-primary" href="<?= e(url('/admin/inventory/movements')) ?>">Sổ cái</a>
        <a class="btn btn-outline-primary" href="<?= e(url('/admin/inventory/stocktakes')) ?>">Kiểm kê</a>
    </nav>
</div>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-secondary">Sản phẩm dưới ngưỡng</div><div class="display-6"><?= count($underStock) ?></div></div></div></div>
    <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-secondary">Lô sắp hết hạn (<?= (int) $warningDays ?> ngày)</div><div class="display-6"><?= (int) $nearExpiryCount ?></div></div></div></div>
    <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-secondary">Lô đã hết hạn</div><div class="display-6"><?= (int) $expiredCount ?></div></div></div></div>
    <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-secondary">Giá trị tồn theo giá nhập</div><div class="h3 mt-2"><?= number_format($inventoryValue, 0, ',', '.') ?> ₫</div></div></div></div>
</div>
<div class="row g-4">
    <div class="col-lg-5">
        <section class="card">
            <div class="card-header d-flex justify-content-between align-items-center"><h2 class="h5 mb-0">Sản phẩm dưới tồn tối thiểu</h2><a href="<?= e(url('/admin/inventory/stock?filter=low')) ?>">Xem tất cả</a></div>
            <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Sản phẩm</th><th>Tồn</th><th>Ngưỡng</th></tr></thead><tbody>
                <?php foreach ($underStock as $product): ?><tr><td><?= e($product['name']) ?> <small class="text-secondary"><?= e($product['sku']) ?></small></td><td><?= (int) $product['total_stock'] ?></td><td><?= (int) $product['min_stock'] ?></td></tr><?php endforeach; ?>
                <?php if (!$underStock): ?><tr><td colspan="3" class="text-secondary">Không có sản phẩm nào dưới ngưỡng.</td></tr><?php endif; ?>
            </tbody></table></div>
        </section>
        <?php if (Auth::can('inventory.manage')): ?>
            <form class="card mt-3" method="post" action="<?= e(url('/admin/inventory/settings')) ?>">
                <div class="card-body"><label class="form-label" for="expiry-warning">Ngưỡng sắp hết hạn (ngày)</label><div class="input-group">
                    <input class="form-control" id="expiry-warning" type="number" name="expiry_warning_days" min="1" max="365" value="<?= (int) $warningDays ?>" required>
                    <button class="btn btn-primary" type="submit">Lưu</button>
                </div><?= csrf_field() ?></div>
            </form>
            <form class="mt-3" method="post" action="<?= e(url('/admin/inventory/expire-batches')) ?>" onsubmit="return confirm('Chuyển các lô đã quá hạn sang trạng thái đã hết?')">
                <?= csrf_field() ?><button class="btn btn-outline-danger">Chuyển lô quá hạn sang đã hết</button>
            </form>
        <?php endif; ?>
    </div>
    <div class="col-lg-7">
        <section class="card"><div class="card-header"><h2 class="h5 mb-0">10 biến động gần nhất</h2></div>
            <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Thời điểm</th><th>Sản phẩm / lô</th><th>Loại</th><th>SL</th><th>Người thực hiện</th></tr></thead><tbody>
            <?php foreach ($recentMovements as $movement): ?><tr>
                <td><?= e($movement['created_at']) ?></td><td><?= e($movement['product_name']) ?><br><code><?= e($movement['batch_code'] ?? '-') ?></code></td>
                <td><?= e($movement['movement_type']) ?></td><td class="<?= (int) $movement['quantity'] < 0 ? 'text-danger' : 'text-success' ?>"><?= (int) $movement['quantity'] ?></td>
                <td><?= e($movement['performed_by_name'] ?? 'Hệ thống') ?></td>
            </tr><?php endforeach; ?>
            <?php if (!$recentMovements): ?><tr><td colspan="5" class="text-secondary">Chưa có biến động kho.</td></tr><?php endif; ?>
            </tbody></table></div>
        </section>
    </div>
</div>
