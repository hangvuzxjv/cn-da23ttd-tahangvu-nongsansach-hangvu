<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div><h1 class="h2 mb-1">Lô <code><?= e($batch['batch_code']) ?></code></h1><p class="text-secondary mb-0"><?= e($batch['product_name']) ?> · <?= e($batch['farm_name']) ?></p></div>
    <div><a class="btn btn-outline-secondary" href="<?= e(url('/admin/batches')) ?>">Danh sách lô</a><a class="btn btn-outline-primary" href="<?= e(url('/qr/' . $batch['batch_code'])) ?>" download="<?= e($batch['batch_code']) ?>.svg">Tải QR SVG</a></div>
</div>
<form class="mb-4" method="post" action="<?= e(url('/admin/batches/' . $batch['id'] . '/delete')) ?>" onsubmit="return confirm('Chỉ xóa được lô chưa có sự kiện, lượt quét, nhập kho hoặc đơn hàng. Xóa lô này?')">
    <?= csrf_field() ?><button class="btn btn-outline-danger btn-sm">Xóa lô chưa có lịch sử</button>
</form>
<?php if (!empty($success)): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<div class="row g-3 mb-4">
    <div class="col-lg-7"><section class="card h-100"><div class="card-body">
        <h2 class="h5">Thông tin lô</h2><dl class="row">
            <dt class="col-sm-4">Sản phẩm</dt><dd class="col-sm-8"><?= e($batch['product_name']) ?> (SKU <?= e($batch['sku']) ?>; cố định theo mã lô)</dd>
            <dt class="col-sm-4">Nông trại</dt><dd class="col-sm-8"><?= e($batch['farm_name'] ?: 'Chưa gắn nông trại') ?></dd>
            <dt class="col-sm-4">Ngày thu hoạch</dt><dd class="col-sm-8"><?= e($batch['harvested_at']) ?></dd>
            <dt class="col-sm-4">Đóng gói / hạn dùng</dt><dd class="col-sm-8"><?= e($batch['packed_at']) ?> / <?= e($batch['expires_at']) ?></dd>
            <dt class="col-sm-4">Số lượng</dt><dd class="col-sm-8"><?= (int) $batch['quantity_remaining'] ?> còn / <?= (int) $batch['quantity_received'] ?> nhập</dd>
            <dt class="col-sm-4">Lượt quét</dt><dd class="col-sm-8"><?= (int) $scanCount ?></dd>
            <dt class="col-sm-4">QR xem trước</dt><dd class="col-sm-8"><img src="<?= e(url('/qr/' . $batch['batch_code'])) ?>" width="160" height="160" alt="QR truy xuất <?= e($batch['batch_code']) ?>"></dd>
        </dl>
    </div></section></div>
    <div class="col-lg-5"><section class="card h-100"><div class="card-body">
        <h2 class="h5">Cập nhật trạng thái / kiểm định</h2>
        <form method="post" action="<?= e(url('/admin/batches/' . $batch['id'] . '/update')) ?>" class="vstack gap-2">
            <?= csrf_field() ?>
            <label class="form-label mb-0">Nông trại</label><select class="form-select" name="farm_id" required><?php foreach ($farms as $farm): ?><option value="<?= (int) $farm['id'] ?>" <?= (int) $batch['farm_id'] === (int) $farm['id'] ? 'selected' : '' ?>><?= e($farm['name']) ?><?= $farm['status'] === 'inactive' ? ' (ngừng hoạt động)' : '' ?></option><?php endforeach; ?></select>
            <div class="row g-2"><div class="col-4"><label class="form-label mb-0">Thu hoạch</label><input class="form-control" type="date" name="harvested_at" value="<?= e($batch['harvested_at']) ?>" required></div><div class="col-4"><label class="form-label mb-0">Đóng gói</label><input class="form-control" type="date" name="packed_at" value="<?= e($batch['packed_at']) ?>" required></div><div class="col-4"><label class="form-label mb-0">Hạn dùng</label><input class="form-control" type="date" name="expires_at" value="<?= e($batch['expires_at']) ?>" required></div></div>
            <label class="form-label mb-0">Trạng thái</label><select class="form-select" name="status"><?php foreach (['selling'=>'Đang bán','suspended'=>'Tạm ngưng','recalled'=>'Thu hồi','ended'=>'Đã hết'] as $value => $label): ?><option value="<?= e($value) ?>" <?= $batch['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
            <p class="small text-secondary mb-0">Tồn: <?= (int) $batch['quantity_remaining'] ?> còn / <?= (int) $batch['quantity_received'] ?> nhập. Điều chỉnh số lượng tại <a href="<?= e(url('/admin/inventory/stock')) ?>">màn hình tồn kho</a>; không thể sửa số tồn trực tiếp.</p>
            <label class="form-label mb-0">Kết quả kiểm định</label><select class="form-select" name="inspection_result"><?php foreach (['pending'=>'Đang chờ','passed'=>'Đạt','failed'=>'Không đạt'] as $value => $label): ?><option value="<?= e($value) ?>" <?= $batch['inspection_result'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
            <label class="form-label mb-0">Ghi chú kiểm định</label><textarea class="form-control" name="inspection_notes" rows="2"><?= e($batch['inspection_notes']) ?></textarea>
            <label class="form-label mb-0">Lý do thu hồi (bắt buộc khi thu hồi)</label><textarea class="form-control" name="recall_reason" rows="2"><?= e($batch['recall_reason']) ?></textarea>
            <div class="d-flex flex-wrap gap-2"><button class="btn btn-success" name="action" value="update">Lưu cập nhật</button><button class="btn btn-danger" name="action" value="recall" onclick="return confirm('Thu hồi lô này? Hãy kiểm tra lý do trước khi xác nhận.')">Thu hồi lô</button></div>
        </form>
    </div></section></div>
</div>

<section class="card mb-4"><div class="card-body">
    <div class="d-flex justify-content-between align-items-center gap-2"><h2 class="h4">Sự kiện truy xuất</h2>
        <form method="post" action="<?= e(url('/admin/batches/' . $batch['id'] . '/events/quick')) ?>" onsubmit="return confirm('Chỉ dùng khi lô chưa có sự kiện. Tạo 6 bước mẫu?')"><?= csrf_field() ?><button class="btn btn-outline-success">Tạo nhanh 6 bước mẫu</button></form>
    </div>
    <form method="post" enctype="multipart/form-data" action="<?= e(url('/admin/batches/' . $batch['id'] . '/events')) ?>" class="row g-2 border rounded p-3 mb-4">
        <?= csrf_field() ?><h3 class="h6">Thêm sự kiện</h3>
        <div class="col-md-3"><label class="form-label">Bước</label><select class="form-select" name="event_type"><?php foreach ($eventTypes as $type => $label): ?><option value="<?= e($type) ?>"><?= e($label) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label">Ngày giờ</label><input class="form-control" type="datetime-local" name="event_at" required></div>
        <div class="col-md-3"><label class="form-label">Địa điểm</label><input class="form-control" name="location"></div>
        <div class="col-md-3"><label class="form-label">Thứ tự</label><input class="form-control" type="number" min="0" name="sort_order" value="<?= count($events) ?>"></div>
        <div class="col-md-8"><label class="form-label">Mô tả</label><textarea class="form-control" name="description" rows="2" required></textarea></div>
        <div class="col-md-4"><label class="form-label">Ảnh (JPG/PNG/WebP, tối đa 2 MB)</label><input class="form-control" type="file" name="image" accept=".jpg,.jpeg,.png,.webp"></div>
        <div class="col-12"><button class="btn btn-success">Thêm bước</button></div>
    </form>
    <?php if (!$events): ?><p class="text-secondary">Lô này chưa có sự kiện. Có thể tạo nhanh 6 bước mẫu hoặc thêm từng bước ở trên.</p><?php endif; ?>
    <?php foreach ($events as $event): ?><details class="border rounded p-3 mb-2">
        <summary class="d-flex flex-wrap justify-content-between gap-2"><strong><?= e($eventTypes[$event['event_type']] ?? $event['event_type']) ?></strong><span><?= e($event['event_at']) ?> · thứ tự <?= (int) $event['sort_order'] ?></span></summary>
        <form class="row g-2 mt-2" method="post" enctype="multipart/form-data" action="<?= e(url('/admin/trace-events/' . $event['id'] . '/update')) ?>">
            <?= csrf_field() ?>
            <div class="col-md-3"><label class="form-label">Bước</label><select class="form-select" name="event_type"><?php foreach ($eventTypes as $type => $label): ?><option value="<?= e($type) ?>" <?= $event['event_type'] === $type ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label">Ngày giờ</label><input class="form-control" type="datetime-local" name="event_at" value="<?= e(substr(str_replace(' ', 'T', $event['event_at']), 0, 16)) ?>" required></div>
            <div class="col-md-3"><label class="form-label">Địa điểm</label><input class="form-control" name="location" value="<?= e($event['location']) ?>"></div>
            <div class="col-md-3"><label class="form-label">Thứ tự</label><input class="form-control" type="number" min="0" name="sort_order" value="<?= (int) $event['sort_order'] ?>"></div>
            <div class="col-md-8"><label class="form-label">Mô tả</label><textarea class="form-control" name="description" rows="2" required><?= e($event['description']) ?></textarea></div>
            <div class="col-md-4"><label class="form-label">Ảnh mới</label><input class="form-control" type="file" name="image" accept=".jpg,.jpeg,.png,.webp"><?php if ($event['image_url']): ?><a href="<?= e(asset($event['image_url'])) ?>" target="_blank" rel="noopener">Xem ảnh hiện tại</a><div class="form-check"><input class="form-check-input" type="checkbox" name="remove_image" value="1" id="removeEventImage<?= (int) $event['id'] ?>"><label class="form-check-label" for="removeEventImage<?= (int) $event['id'] ?>">Xóa ảnh hiện tại</label></div><?php endif; ?></div>
            <div class="col-12"><button class="btn btn-sm btn-success">Lưu bước</button></div>
        </form>
        <form method="post" class="mt-2" action="<?= e(url('/admin/trace-events/' . $event['id'] . '/delete')) ?>" onsubmit="return confirm('Xóa sự kiện này?')"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger">Xóa sự kiện</button></form>
    </details><?php endforeach; ?>
</div></section>
