<?php
$value = static function (string $key, mixed $default = '') use ($product): mixed {
    return old($key, $product[$key] ?? $default);
};
?>
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h2"><?= e($title) ?></h1><a class="btn btn-outline-secondary" href="<?= e(url('/admin/products')) ?>">Quay lại danh sách</a></div>
<?php if (!empty($success)): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
<form class="card mb-4" method="post" enctype="multipart/form-data" action="<?= e(url($product ? '/admin/products/' . $product['id'] . '/edit' : '/admin/products')) ?>">
    <div class="card-body"><div class="row g-3">
        <?php
        $fields = [
            'name' => ['Tên sản phẩm', 'text', 'required'],
            'slug' => ['Slug (để trống tự sinh)', 'text', ''],
            'sku' => ['SKU', 'text', 'required'],
            'unit' => ['Đơn vị tính', 'text', 'required'],
            'price' => ['Giá bán (VND)', 'number', 'required min="0.01" step="0.01"'],
            'sale_price' => ['Giá khuyến mãi (tùy chọn)', 'number', 'min="0.01" step="0.01"'],
            'suggested_shelf_life_days' => ['Hạn dùng gợi ý (ngày)', 'number', 'min="0" step="1"'],
        ];
        foreach ($fields as $field => [$label, $type, $attributes]):
        ?>
            <div class="col-md-6"><label class="form-label" for="<?= e($field) ?>"><?= e($label) ?></label>
                <input class="form-control<?= isset($errors[$field]) ? ' is-invalid' : '' ?>" id="<?= e($field) ?>" name="<?= e($field) ?>" type="<?= e($type) ?>" value="<?= e($value($field)) ?>" <?= $attributes ?>>
                <?php if (isset($errors[$field])): ?><div class="invalid-feedback"><?= e($errors[$field]) ?></div><?php endif; ?>
            </div>
        <?php endforeach; ?>
        <?php foreach (['category_id' => ['Danh mục', $categories], 'region_id' => ['Tỉnh/vùng', $regions], 'farm_id' => ['Nông trại', $farms]] as $field => [$label, $options]): ?>
            <div class="col-md-4"><label class="form-label" for="<?= e($field) ?>"><?= e($label) ?></label><select class="form-select<?= isset($errors[$field]) ? ' is-invalid' : '' ?>" id="<?= e($field) ?>" name="<?= e($field) ?>" required><option value="">Chọn</option><?php foreach ($options as $option): ?><option value="<?= (int) $option['id'] ?>" <?= (string) $value($field) === (string) $option['id'] ? 'selected' : '' ?> <?= $field === 'farm_id' ? 'data-region="' . (int) $option['region_id'] . '"' : '' ?>><?= e($option['name']) ?></option><?php endforeach; ?></select><?php if (isset($errors[$field])): ?><div class="invalid-feedback"><?= e($errors[$field]) ?></div><?php endif; ?></div>
        <?php endforeach; ?>
        <div class="col-md-4"><label class="form-label" for="status">Trạng thái</label><select class="form-select" id="status" name="status"><?php foreach (['draft'=>'Nháp','active'=>'Đang bán','inactive'=>'Ngừng bán'] as $status=>$label): ?><option value="<?= e($status) ?>" <?= $value('status', 'draft') === $status ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
        <div class="col-12"><label class="form-label" for="short_description">Mô tả ngắn</label><textarea class="form-control" id="short_description" name="short_description" maxlength="500" rows="2"><?= e($value('short_description')) ?></textarea></div>
        <div class="col-12"><label class="form-label" for="description">Mô tả chi tiết</label><textarea class="form-control" id="description" name="description" rows="4"><?= e($value('description')) ?></textarea></div>
        <div class="col-md-6"><label class="form-label" for="storage_instructions">Hướng dẫn bảo quản</label><textarea class="form-control" id="storage_instructions" name="storage_instructions" rows="3"><?= e($value('storage_instructions')) ?></textarea></div>
        <div class="col-md-6"><label class="form-label" for="certifications">Chứng nhận</label><textarea class="form-control" id="certifications" name="certifications" rows="3"><?= e($value('certifications')) ?></textarea></div>
        <div class="col-md-6"><div class="form-check"><input class="form-check-input" id="is_featured" name="is_featured" type="checkbox" value="1" <?= (int) $value('is_featured') === 1 ? 'checked' : '' ?>><label class="form-check-label" for="is_featured">Sản phẩm nổi bật</label></div></div>
        <div class="col-md-6"><label class="form-label" for="images">Ảnh sản phẩm (JPG, PNG, WebP; tối đa 2 MB/ảnh)</label><input class="form-control" id="images" name="images[]" type="file" accept="image/jpeg,image/png,image/webp" multiple><div class="form-text">Ảnh được kiểm tra MIME phía máy chủ. GD được dùng để thu nhỏ ảnh nếu có.</div></div>
    </div></div>
    <div class="card-footer"><input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>"><button class="btn btn-success" type="submit">Lưu sản phẩm</button></div>
</form>
<?php if ($images): ?>
<section class="card"><div class="card-body">
    <h2 class="h5">Ảnh hiện có</h2><p class="text-secondary">Kéo nút ↑ / ↓ để sắp xếp; ảnh đầu tiên là ảnh đại diện.</p>
    <form method="post" action="<?= e(url('/admin/products/' . $product['id'] . '/images/reorder')) ?>" id="image-order-form">
        <?= csrf_field() ?><div class="row g-3" id="image-order-list">
            <?php foreach ($images as $image): ?><div class="col-6 col-md-3 image-order-item"><div class="border rounded p-2">
                <img class="img-fluid rounded catalog-preview" src="<?= e(asset($image['image_url'])) ?>" alt="<?= e($image['alt_text']) ?>" loading="lazy">
                <div class="d-flex justify-content-between mt-2"><span><?= (int) $image['is_primary'] ? 'Ảnh đại diện' : 'Ảnh phụ' ?></span><span>
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-move="up" aria-label="Chuyển ảnh lên">↑</button>
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-move="down" aria-label="Chuyển ảnh xuống">↓</button>
                </span></div>
                <input type="hidden" name="image_ids[]" value="<?= (int) $image['id'] ?>">
                <button class="btn btn-sm btn-outline-danger mt-2" formaction="<?= e(url('/admin/products/images/' . $image['id'] . '/delete')) ?>" formmethod="post" onclick="return confirm('Xóa ảnh này?')">Xóa ảnh</button>
            </div></div><?php endforeach; ?>
        </div><button class="btn btn-outline-success mt-3" type="submit">Lưu thứ tự ảnh</button>
    </form>
</div></section>
<script>
document.querySelectorAll('[data-move]').forEach((button) => button.addEventListener('click', () => {
    const item = button.closest('.image-order-item');
    const sibling = button.dataset.move === 'up' ? item.previousElementSibling : item.nextElementSibling;
    if (!sibling) return;
    if (button.dataset.move === 'up') item.parentElement.insertBefore(item, sibling);
    else item.parentElement.insertBefore(sibling, item);
}));
</script>
<?php endif; ?>
