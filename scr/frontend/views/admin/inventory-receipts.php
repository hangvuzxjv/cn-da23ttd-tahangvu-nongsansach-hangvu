<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h2 mb-0">Phiếu nhập kho</h1>
    <a class="btn btn-outline-primary" href="<?= e(url('/admin/inventory')) ?>">Tổng quan kho</a>
</div>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<?php if ($canManage): ?>
<form class="card mb-4" method="post" action="<?= e(url('/admin/inventory/receipts')) ?>">
    <div class="card-header"><h2 class="h5 mb-0">Tạo phiếu nhập nháp</h2></div>
    <div class="card-body">
        <?= csrf_field() ?>
        <div class="row g-3 mb-3">
            <div class="col-md-5"><label class="form-label">Nhà cung cấp / nông trại</label><select class="form-select" name="supplier_farm_id" required><option value="">Chọn nông trại</option><?php foreach ($farms as $farm): ?><option value="<?= (int) $farm['id'] ?>"><?= e($farm['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-7"><label class="form-label">Ghi chú</label><input class="form-control" name="notes" maxlength="500"></div>
        </div>
        <p class="small text-secondary">Chọn lô có sẵn hoặc để trống để tạo lô mới ngay trong phiếu. Lô mới cần nhập đủ ngày.</p>
        <div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Sản phẩm</th><th>Lô có sẵn (để trống = tạo lô)</th><th>Số lượng</th><th>Giá nhập/đơn vị</th><th>Ngày thu hoạch</th><th>Đóng gói</th><th>Hạn dùng</th></tr></thead><tbody>
        <?php for ($index = 0; $index < 4; $index++): ?><tr>
            <td><select class="form-select form-select-sm" name="product_id[]"><option value="">Chọn</option><?php foreach ($products as $product): ?><option value="<?= (int) $product['id'] ?>"><?= e($product['name']) ?> (<?= e($product['sku']) ?>)</option><?php endforeach; ?></select></td>
            <td><select class="form-select form-select-sm" name="batch_id[]"><option value="">Lô mới</option><?php foreach ($batches as $batch): ?><option value="<?= (int) $batch['id'] ?>"><?= e($batch['batch_code']) ?></option><?php endforeach; ?></select></td>
            <td><input class="form-control form-control-sm" type="number" name="quantity[]" min="1"></td>
            <td><input class="form-control form-control-sm" type="number" name="unit_cost[]" min="0" step="0.01" value="0"></td>
            <td><input class="form-control form-control-sm" type="date" name="harvested_at[]"></td>
            <td><input class="form-control form-control-sm" type="date" name="packed_at[]"></td>
            <td><input class="form-control form-control-sm" type="date" name="expires_at[]"></td>
        </tr><?php endfor; ?>
        </tbody></table></div>
        <button class="btn btn-primary">Tạo phiếu nháp</button>
    </div>
</form>
<?php endif; ?>
<div class="table-responsive"><table class="table table-striped align-middle">
    <thead><tr><th>Mã phiếu</th><th>Nhà cung cấp</th><th>Ngày</th><th>Số dòng</th><th>Trạng thái</th><th>Người xác nhận</th><th>Thao tác</th></tr></thead><tbody>
    <?php foreach ($receipts as $receipt): ?><tr>
        <td><strong><?= e($receipt['receipt_no']) ?></strong></td><td><?= e($receipt['farm_name'] ?? $receipt['supplier_name']) ?></td>
        <td><?= e($receipt['received_at']) ?></td><td><?= (int) $receipt['item_count'] ?></td><td><?= e($receipt['status']) ?></td>
        <td><?= e($receipt['receiver_name'] ?? '—') ?></td><td>
            <?php if ($canManage && $receipt['status'] === 'draft'): ?>
                <form class="d-inline" method="post" action="<?= e(url('/admin/inventory/receipts/' . $receipt['id'] . '/confirm')) ?>" onsubmit="return confirm('Xác nhận phiếu này và cộng tồn theo lô?')"><?= csrf_field() ?><button class="btn btn-sm btn-success">Xác nhận</button></form>
            <?php elseif ($canManage && $receipt['status'] === 'received'): ?>
                <form method="post" action="<?= e(url('/admin/inventory/receipts/' . $receipt['id'] . '/cancel')) ?>" class="d-flex gap-1">
                    <?= csrf_field() ?><input class="form-control form-control-sm" name="reason" placeholder="Lý do đảo phiếu" required><button class="btn btn-sm btn-outline-danger">Hủy/đảo</button>
                </form>
            <?php else: ?><?= e($receipt['cancellation_reason'] ?? '—') ?><?php endif; ?>
        </td>
    </tr><?php endforeach; ?>
    <?php if (!$receipts): ?><tr><td colspan="7" class="text-secondary">Chưa có phiếu nhập.</td></tr><?php endif; ?>
    </tbody>
</table></div>
