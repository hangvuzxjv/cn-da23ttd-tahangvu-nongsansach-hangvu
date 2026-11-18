<header class="page-heading">
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url('/')) ?>">Trang chủ</a></li><li class="breadcrumb-item"><a href="<?= e(url('/tai-khoan/don-hang')) ?>">Đơn hàng của tôi</a></li><li class="breadcrumb-item"><a href="<?= e(url('/tai-khoan/don-hang/' . (int) $context['order']['id'])) ?>"><?= e($context['order']['order_no']) ?></a></li><li class="breadcrumb-item active" aria-current="page">Yêu cầu trả hàng</li></ol></nav>
    <h1 class="h2">Yêu cầu trả hàng / hoàn tiền</h1>
    <p class="text-secondary mb-0">Đơn <?= e($context['order']['order_no']) ?> · Gửi trong vòng <?= (int) $context['return_window_hours'] ?> giờ kể từ khi đơn được giao.</p>
</header>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
<form class="card card-body gap-4" method="post" enctype="multipart/form-data" action="<?= e(url('/tai-khoan/don-hang/' . (int) $context['order']['id'] . '/tra-hang')) ?>">
    <?= csrf_field() ?>
    <fieldset>
        <legend class="h5">Sản phẩm và số lượng cần trả</legend>
        <p class="small text-secondary">Chỉ chọn sản phẩm có trong đơn. Số lượng không vượt quá phần chưa được yêu cầu trả.</p>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Sản phẩm</th><th>Đã mua</th><th>Còn có thể yêu cầu</th><th class="w-25">Số lượng trả</th></tr></thead>
                <tbody>
                <?php foreach ($context['items'] as $item): ?>
                    <?php $itemId = (int) $item['id']; $oldQuantity = (int) ($oldInput['quantities'][$itemId] ?? 0); ?>
                    <tr>
                        <td><?= e($item['product_name']) ?><?php if (!empty($item['sku'])): ?><div class="small text-secondary">SKU: <?= e($item['sku']) ?></div><?php endif; ?></td>
                        <td><?= (int) $item['quantity'] ?> <?= e($item['unit']) ?></td>
                        <td><?= (int) $item['available_quantity'] ?> <?= e($item['unit']) ?></td>
                        <td><label class="visually-hidden" for="returnQuantity<?= $itemId ?>">Số lượng <?= e($item['product_name']) ?></label><input class="form-control" id="returnQuantity<?= $itemId ?>" name="quantities[<?= $itemId ?>]" type="number" min="0" max="<?= (int) $item['available_quantity'] ?>" step="1" value="<?= max(0, min($oldQuantity, (int) $item['available_quantity'])) ?>"></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </fieldset>
    <div class="row g-3">
        <div class="col-md-5">
            <label class="form-label" for="returnReason">Lý do</label>
            <select class="form-select" id="returnReason" name="reason_code" required>
                <option value="">Chọn lý do</option>
                <?php foreach ($reasons as $key => $label): ?><option value="<?= e($key) ?>" <?= ($oldInput['reason_code'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-12">
            <label class="form-label" for="returnDescription">Mô tả tình trạng</label>
            <textarea class="form-control" id="returnDescription" name="description" rows="5" minlength="10" maxlength="1000" required aria-describedby="returnDescriptionHint"><?= e($oldInput['description'] ?? '') ?></textarea>
            <div class="form-text" id="returnDescriptionHint">Mô tả từ 10 đến 1.000 ký tự để cửa hàng hiểu rõ vấn đề.</div>
        </div>
        <div class="col-12">
            <label class="form-label" for="returnImages">Ảnh bằng chứng</label>
            <input class="form-control" id="returnImages" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple required>
            <div class="form-text">Tải lên 1–3 ảnh JPG, PNG hoặc WebP; tối đa 2 MB mỗi ảnh.</div>
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <button class="btn btn-success" type="submit">Gửi yêu cầu</button>
        <a class="btn btn-outline-secondary" href="<?= e(url('/tai-khoan/don-hang/' . (int) $context['order']['id'])) ?>">Quay lại đơn hàng</a>
    </div>
</form>
