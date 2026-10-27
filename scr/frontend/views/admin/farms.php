<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h2">Nông trại và nhà cung cấp</h1><a class="btn btn-outline-secondary" href="<?= e(url('/admin/batches')) ?>">Quản lý lô hàng</a></div>
<?php if (!empty($success)): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<details class="card mb-4"><summary class="card-header">Thêm nông trại</summary><div class="card-body">
    <form method="post" enctype="multipart/form-data" action="<?= e(url('/admin/farms')) ?>" class="row g-3">
        <?= csrf_field() ?>
        <div class="col-md-6"><label class="form-label">Tên nông trại / nhà cung cấp *</label><input class="form-control" name="name" required maxlength="180"></div>
        <div class="col-md-6"><label class="form-label">Vùng/Tỉnh *</label><select class="form-select" name="region_id" required><option value="">Chọn vùng</option><?php foreach ($regions as $region): ?><option value="<?= (int) $region['id'] ?>"><?= e($region['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-8"><label class="form-label">Địa chỉ *</label><input class="form-control" name="address" required maxlength="255"></div>
        <div class="col-md-4"><label class="form-label">Người đại diện</label><input class="form-control" name="contact_name"></div>
        <div class="col-md-4"><label class="form-label">Điện thoại</label><input class="form-control" name="phone"></div>
        <div class="col-md-4"><label class="form-label">Chứng nhận</label><input class="form-control" name="certifications" placeholder="VietGAP, OCOP"></div>
        <div class="col-md-4"><label class="form-label">Số chứng nhận</label><input class="form-control" name="certification_number"></div>
        <div class="col-md-4"><label class="form-label">Ngày hết hạn chứng nhận</label><input class="form-control" type="date" name="certification_expires_at"></div>
        <div class="col-md-4"><label class="form-label">Vĩ độ</label><input class="form-control" type="number" step="any" min="-90" max="90" name="latitude"></div>
        <div class="col-md-4"><label class="form-label">Kinh độ</label><input class="form-control" type="number" step="any" min="-180" max="180" name="longitude"></div>
        <div class="col-md-6"><label class="form-label">Ảnh nông trại (JPG/PNG/WebP, tối đa 2 MB)</label><input class="form-control" type="file" name="image" accept=".jpg,.jpeg,.png,.webp"></div>
        <div class="col-md-6"><label class="form-label">Ảnh giấy chứng nhận</label><input class="form-control" type="file" name="certificate_image" accept=".jpg,.jpeg,.png,.webp"></div>
        <div class="col-12"><label class="form-label">Mô tả</label><textarea class="form-control" name="description" rows="2"></textarea></div>
        <div class="col-12"><button class="btn btn-success">Thêm nông trại</button></div>
    </form>
</div></details>

<div class="table-responsive bg-white border rounded"><table class="table align-middle mb-0">
    <thead><tr><th>Nông trại</th><th>Địa chỉ / vùng</th><th>Chứng nhận</th><th>Liên kết</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
    <tbody><?php foreach ($farms as $farm): ?><tr>
        <td><?= e($farm['name']) ?><?php if ($farm['image_url']): ?><br><img class="farm-admin-thumb mt-2" src="<?= e(asset($farm['image_url'])) ?>" alt=""><?php endif; ?></td>
        <td><?= e($farm['address']) ?><br><span class="text-secondary"><?= e($farm['region_name']) ?></span></td>
        <td><?= e($farm['certifications']) ?><?= $farm['certification_number'] ? '<br>Số: ' . e($farm['certification_number']) : '' ?><?= $farm['certification_expires_at'] ? '<br>Hết hạn: ' . e($farm['certification_expires_at']) : '' ?><?php if ($farm['certificate_image_url']): ?><br><a href="<?= e(asset($farm['certificate_image_url'])) ?>" target="_blank" rel="noopener">Xem giấy chứng nhận</a><?php endif; ?></td>
        <td><?= (int) $farm['product_count'] ?> sản phẩm<br><?= (int) $farm['batch_count'] ?> lô</td>
        <td><?= $farm['status'] === 'active' ? 'Đang hoạt động' : 'Ngừng hoạt động' ?></td>
        <td><details><summary class="btn btn-sm btn-outline-primary">Sửa</summary><form class="border rounded p-2 mt-2 trace-edit-form" method="post" enctype="multipart/form-data" action="<?= e(url('/admin/farms/' . $farm['id'] . '/update')) ?>">
            <?= csrf_field() ?>
            <label class="form-label">Tên</label><input class="form-control mb-2" name="name" value="<?= e($farm['name']) ?>" required>
            <label class="form-label">Vùng/Tỉnh</label><select class="form-select mb-2" name="region_id" required><?php foreach ($regions as $region): ?><option value="<?= (int) $region['id'] ?>" <?= (int) $region['id'] === (int) $farm['region_id'] ? 'selected' : '' ?>><?= e($region['name']) ?></option><?php endforeach; ?></select>
            <label class="form-label">Địa chỉ</label><input class="form-control mb-2" name="address" value="<?= e($farm['address']) ?>" required>
            <label class="form-label">Đại diện</label><input class="form-control mb-2" name="contact_name" value="<?= e($farm['contact_name']) ?>">
            <label class="form-label">Điện thoại</label><input class="form-control mb-2" name="phone" value="<?= e($farm['phone']) ?>">
            <label class="form-label">Chứng nhận</label><input class="form-control mb-2" name="certifications" value="<?= e($farm['certifications']) ?>">
            <label class="form-label">Số chứng nhận</label><input class="form-control mb-2" name="certification_number" value="<?= e($farm['certification_number']) ?>">
            <label class="form-label">Ngày hết hạn</label><input class="form-control mb-2" type="date" name="certification_expires_at" value="<?= e($farm['certification_expires_at']) ?>">
            <div class="row g-2"><div class="col"><label class="form-label">Vĩ độ</label><input class="form-control" type="number" step="any" min="-90" max="90" name="latitude" value="<?= e($farm['latitude']) ?>"></div><div class="col"><label class="form-label">Kinh độ</label><input class="form-control" type="number" step="any" min="-180" max="180" name="longitude" value="<?= e($farm['longitude']) ?>"></div></div>
            <label class="form-label mt-2">Đổi ảnh nông trại</label><input class="form-control mb-2" type="file" name="image" accept=".jpg,.jpeg,.png,.webp">
            <?php if ($farm['image_url']): ?><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="remove_image" value="1" id="removeFarmImage<?= (int) $farm['id'] ?>"><label class="form-check-label" for="removeFarmImage<?= (int) $farm['id'] ?>">Xóa ảnh nông trại hiện tại</label></div><?php endif; ?>
            <label class="form-label">Đổi ảnh chứng nhận</label><input class="form-control mb-2" type="file" name="certificate_image" accept=".jpg,.jpeg,.png,.webp">
            <?php if ($farm['certificate_image_url']): ?><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="remove_certificate_image" value="1" id="removeFarmCertificate<?= (int) $farm['id'] ?>"><label class="form-check-label" for="removeFarmCertificate<?= (int) $farm['id'] ?>">Xóa ảnh chứng nhận hiện tại</label></div><?php endif; ?>
            <label class="form-label">Mô tả</label><textarea class="form-control mb-2" name="description"><?= e($farm['description']) ?></textarea>
            <label class="form-label">Trạng thái</label><select class="form-select mb-2" name="status"><option value="active" <?= $farm['status'] === 'active' ? 'selected' : '' ?>>Đang hoạt động</option><option value="inactive" <?= $farm['status'] === 'inactive' ? 'selected' : '' ?>>Ngừng hoạt động</option></select>
            <button class="btn btn-sm btn-success">Lưu</button>
        </form></details>
        <form class="mt-2" method="post" action="<?= e(url('/admin/farms/' . $farm['id'] . '/delete')) ?>" onsubmit="return confirm('Xóa nông trại? Sẽ bị chặn nếu còn sản phẩm hoặc lô liên kết.')"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger">Xóa</button></form></td>
    </tr><?php endforeach; ?></tbody>
</table></div>
