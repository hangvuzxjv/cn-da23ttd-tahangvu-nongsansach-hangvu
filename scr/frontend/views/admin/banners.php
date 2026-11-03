<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div><h1 class="h2 mb-1">Quản lý banner</h1><p class="text-secondary mb-0">Ảnh tối đa 2MB, định dạng JPG, PNG, WebP hoặc SVG.</p></div>
    <?php if ($editBanner): ?><a class="btn btn-outline-secondary" href="<?= e(url('/admin/content/banners')) ?>">Thêm banner mới</a><?php endif; ?>
</div>
<?php if (!empty($success)): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<section class="card mb-4">
    <div class="card-body">
        <h2 class="h5"><?= $editBanner ? 'Sửa banner' : 'Thêm banner' ?></h2>
        <form method="post" enctype="multipart/form-data" action="<?= e(url($editBanner ? '/admin/content/banners/' . $editBanner['id'] : '/admin/content/banners')) ?>">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label" for="banner-title">Tiêu đề</label><input class="form-control" id="banner-title" name="title" maxlength="180" required value="<?= e($editBanner['title'] ?? '') ?>"></div>
                <div class="col-md-6"><label class="form-label" for="banner-image">Ảnh <?= $editBanner ? '(để trống nếu giữ ảnh hiện tại)' : '' ?></label><input class="form-control" id="banner-image" name="image" type="file" accept=".jpg,.jpeg,.png,.webp,.svg,image/jpeg,image/png,image/webp,image/svg+xml" <?= $editBanner ? '' : 'required' ?>></div>
                <div class="col-12"><label class="form-label" for="banner-description">Mô tả ngắn</label><input class="form-control" id="banner-description" name="description" maxlength="300" value="<?= e($editBanner['description'] ?? '') ?>"></div>
                <div class="col-md-4"><label class="form-label" for="banner-button">Nhãn nút</label><input class="form-control" id="banner-button" name="button_label" maxlength="80" value="<?= e($editBanner['button_label'] ?? '') ?>" placeholder="Xem sản phẩm"></div>
                <div class="col-md-8"><label class="form-label" for="banner-link">Liên kết tùy chọn</label><input class="form-control" id="banner-link" name="link_url" maxlength="500" value="<?= e($editBanner['link_url'] ?? '') ?>" placeholder="/san-pham"></div>
                <div class="col-md-3"><label class="form-label" for="banner-order">Thứ tự</label><input class="form-control" id="banner-order" name="sort_order" type="number" min="0" max="2147483647" value="<?= e($editBanner['sort_order'] ?? 0) ?>"></div>
                <div class="col-md-3"><label class="form-label" for="banner-status">Trạng thái</label><select class="form-select" id="banner-status" name="status"><option value="active" <?= ($editBanner['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Đang hiện</option><option value="inactive" <?= ($editBanner['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Đang ẩn</option></select></div>
                <div class="col-12"><button class="btn btn-success" type="submit"><?= $editBanner ? 'Lưu thay đổi' : 'Thêm banner' ?></button></div>
            </div>
        </form>
    </div>
</section>

<section class="card">
    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Ảnh</th><th>Nội dung</th><th>Thứ tự</th><th>Trạng thái</th><th class="text-end">Thao tác</th></tr></thead>
        <tbody>
        <?php foreach ($banners as $banner): ?>
            <tr>
                <td><img class="admin-banner-thumb" src="<?= e(asset($banner['image_url'])) ?>" alt=""></td>
                <td><strong><?= e($banner['title']) ?></strong><br><span class="small text-secondary"><?= e($banner['description'] ?: 'Không có mô tả') ?></span></td>
                <td><?= (int) $banner['sort_order'] ?></td>
                <td><?= $banner['status'] === 'active' ? 'Đang hiện' : 'Đang ẩn' ?></td>
                <td><div class="d-flex justify-content-end flex-wrap gap-2">
                    <a class="btn btn-sm btn-outline-success" href="<?= e(url('/admin/content/banners/' . $banner['id'] . '/edit')) ?>">Sửa</a>
                    <form method="post" action="<?= e(url('/admin/content/banners/' . $banner['id'] . '/toggle')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline-secondary" type="submit"><?= $banner['status'] === 'active' ? 'Ẩn' : 'Hiện' ?></button></form>
                    <form method="post" action="<?= e(url('/admin/content/banners/' . $banner['id'] . '/delete')) ?>" onsubmit="return confirm('Xóa banner này?');"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger" type="submit">Xóa</button></form>
                </div></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$banners): ?><tr><td colspan="5" class="text-center text-secondary py-4">Chưa có banner.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</section>
