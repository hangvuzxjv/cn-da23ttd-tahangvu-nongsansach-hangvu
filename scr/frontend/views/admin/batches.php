<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><h1 class="h2 mb-0">Lô hàng và truy xuất</h1><a class="btn btn-outline-success" href="<?= e(url('/admin/farms')) ?>">Nông trại</a></div>
<?php if (!empty($success)): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<details class="card mb-4"><summary class="card-header">Tạo lô hàng</summary><div class="card-body">
    <form method="post" action="<?= e(url('/admin/batches')) ?>" class="row g-3">
        <?= csrf_field() ?>
        <div class="col-md-6"><label class="form-label">Sản phẩm *</label><select class="form-select" name="product_id" required><option value="">Chọn sản phẩm</option><?php foreach ($products as $product): ?><option value="<?= (int) $product['id'] ?>" data-farm="<?= (int) $product['farm_id'] ?>"><?= e($product['name']) ?> (<?= e($product['sku']) ?>)</option><?php endforeach; ?></select></div>
        <div class="col-md-6"><label class="form-label">Nông trại *</label><select class="form-select" name="farm_id" required><option value="">Chọn nông trại cùng vùng miền</option><?php foreach ($farms as $farm): ?><option value="<?= (int) $farm['id'] ?>"><?= e($farm['name']) ?></option><?php endforeach; ?></select><small class="text-secondary">Mã lô tự sinh theo NS-YYMM-SKU-số thứ tự.</small></div>
        <div class="col-md-4"><label class="form-label">Ngày thu hoạch *</label><input class="form-control" type="date" name="harvested_at" required></div>
        <div class="col-md-4"><label class="form-label">Ngày đóng gói *</label><input class="form-control" type="date" name="packed_at" required></div>
        <div class="col-md-4"><label class="form-label">Hạn dùng *</label><input class="form-control" type="date" name="expires_at" required></div>
        <div class="col-md-3"><label class="form-label">Số lượng nhập *</label><input class="form-control" type="number" name="quantity_received" min="1" required></div>
        <div class="col-md-3"><div class="form-text mt-4">Tồn được ghi nhận qua movement nhập kho; không sửa trực tiếp tại đây.</div></div>
        <div class="col-md-3"><label class="form-label">Kiểm định</label><select class="form-select" name="inspection_result"><option value="pending">Đang chờ</option><option value="passed">Đạt</option><option value="failed">Không đạt</option></select></div>
        <div class="col-md-3"><label class="form-label">Trạng thái</label><select class="form-select" name="status"><option value="selling">Đang bán</option><option value="suspended">Tạm ngưng</option><option value="recalled">Thu hồi</option><option value="ended">Đã hết</option></select></div>
        <div class="col-md-6"><label class="form-label">Ghi chú kiểm định</label><textarea class="form-control" name="inspection_notes"></textarea></div>
        <div class="col-md-6"><label class="form-label">Lý do thu hồi (bắt buộc nếu trạng thái thu hồi)</label><textarea class="form-control" name="recall_reason"></textarea></div>
        <div class="col-12"><button class="btn btn-success">Tạo lô</button></div>
    </form>
</div></details>

<form method="get" action="<?= e(url('/admin/labels')) ?>" id="labelSelection">
    <div class="d-flex justify-content-between align-items-center mb-2"><h2 class="h5 mb-0">Danh sách lô</h2><button class="btn btn-sm btn-outline-primary">In tem các lô đã chọn</button></div>
    <div class="table-responsive bg-white border rounded"><table class="table align-middle mb-0">
        <thead><tr><th><span class="visually-hidden">Chọn</span></th><th>Mã lô</th><th>Sản phẩm / nông trại</th><th>Ngày</th><th>Số lượng</th><th>Kiểm định</th><th>Trạng thái</th><th>Lượt quét</th><th></th></tr></thead>
        <tbody><?php foreach ($batches as $batch): ?><tr>
            <td><input class="form-check-input" type="checkbox" name="ids[]" value="<?= (int) $batch['id'] ?>" aria-label="Chọn <?= e($batch['batch_code']) ?>"></td>
            <td><code><?= e($batch['batch_code']) ?></code></td><td><?= e($batch['product_name']) ?><br><span class="text-secondary"><?= e($batch['farm_name']) ?></span></td>
            <td>Thu hoạch: <?= e($batch['harvested_at']) ?><br>Hạn: <?= e($batch['expires_at']) ?></td>
            <td><?= (int) $batch['quantity_remaining'] ?> / <?= (int) $batch['quantity_received'] ?></td>
            <td><?= e($batch['inspection_result']) ?></td><td><?= e($batch['status']) ?></td><td><?= (int) $batch['scan_count'] ?></td>
            <td><a class="btn btn-sm btn-outline-primary" href="<?= e(url('/admin/batches/' . $batch['id'])) ?>">Chi tiết</a><a class="btn btn-sm btn-outline-secondary mt-1" href="<?= e(url('/qr/' . $batch['batch_code'])) ?>" download="<?= e($batch['batch_code']) ?>.svg">QR SVG</a></td>
        </tr><?php endforeach; ?></tbody>
    </table></div>
</form>
