<h1 class="h2 mb-3">Phí vận chuyển</h1>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<form class="card mb-4" method="post" action="<?= e(url('/admin/shipping')) ?>"><?= csrf_field() ?><div class="card-header"><h2 class="h5 mb-0">Thêm / cập nhật khu vực</h2></div><div class="card-body">
    <div class="row g-3">
        <div class="col-md-4"><label class="form-label">Tỉnh/thành</label><select class="form-select" name="region_id" required><option value="">Chọn</option><?php foreach ($regions as $region): ?><?php if ((int) $region['level']===2): ?><option value="<?= (int) $region['id'] ?>"><?= e($region['name']) ?></option><?php endif; ?><?php endforeach; ?></select></div>
        <div class="col-md-2"><label class="form-label">Phí ship (₫)</label><input class="form-control" type="number" name="fee" min="0" required></div>
        <div class="col-md-3"><label class="form-label">Miễn phí từ (trống = dùng chung)</label><input class="form-control" type="number" name="free_shipping_minimum" min="1"></div>
        <div class="col-md-3"><label class="form-label">Ngưỡng chung miễn phí</label><input class="form-control" type="number" name="global_free_shipping_minimum" min="1" value="<?= e($freeMinimum) ?>" required></div>
        <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" checked id="shippingActive"><label class="form-check-label" for="shippingActive">Đang áp dụng</label></div></div>
    </div><button class="btn btn-success mt-3">Lưu phí</button>
</div></form>
<h2 class="h5">Các mức phí hiện có</h2>
<div class="table-responsive"><table class="table align-middle"><thead><tr><th>Tỉnh/thành</th><th>Phí</th><th>Miễn phí từ</th><th>Trạng thái</th><th></th></tr></thead><tbody>
<?php foreach ($fees as $fee): ?><tr>
    <td colspan="5"><form class="row g-2 align-items-center" method="post" action="<?= e(url('/admin/shipping/' . $fee['id'])) ?>">
        <?= csrf_field() ?>
        <div class="col-md-3"><select class="form-select form-select-sm" name="region_id"><?php foreach ($regions as $region): ?><?php if ((int) $region['level']===2): ?><option value="<?= (int) $region['id'] ?>" <?= (int)$fee['region_id']===(int)$region['id']?'selected':'' ?>><?= e($region['name']) ?></option><?php endif; ?><?php endforeach; ?></select></div>
        <div class="col-md-2"><input class="form-control form-control-sm" type="number" name="fee" min="0" value="<?= e($fee['fee']) ?>"></div>
        <div class="col-md-2"><input class="form-control form-control-sm" type="number" name="free_shipping_minimum" min="1" value="<?= e($fee['free_shipping_minimum'] ?? '') ?>" placeholder="Ngưỡng chung"></div>
        <div class="col-md-2"><input class="form-control form-control-sm" type="number" name="global_free_shipping_minimum" min="1" value="<?= e($freeMinimum) ?>"></div>
        <div class="col-md-1"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" <?= (int)$fee['is_active']?'checked':'' ?> aria-label="Đang áp dụng"></div></div>
        <div class="col-md-2 d-flex gap-1"><button class="btn btn-sm btn-primary">Lưu</button></form>
            <form method="post" action="<?= e(url('/admin/shipping/' . $fee['id'] . '/delete')) ?>" onsubmit="return confirm('Xóa cấu hình phí ship này?')"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger">Xóa</button></form>
        </div>
    </td>
</tr><?php endforeach; ?>
<?php if (!$fees): ?><tr><td colspan="5" class="text-secondary">Chưa có cấu hình.</td></tr><?php endif; ?>
</tbody></table></div>
