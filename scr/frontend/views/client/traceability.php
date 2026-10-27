<nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url('/')) ?>">Trang chủ</a></li><li class="breadcrumb-item active" aria-current="page">Truy xuất nguồn gốc</li></ol></nav>

<?php if ($batch['status'] === 'recalled'): ?>
    <div class="alert alert-danger border-danger border-3 shadow-sm" role="alert">
        <h1 class="h3 alert-heading">CẢNH BÁO: LÔ HÀNG ĐÃ BỊ THU HỒI</h1>
        <p class="mb-0"><strong>Lý do thu hồi:</strong> <?= e($batch['recall_reason'] ?: 'Vui lòng không sử dụng sản phẩm thuộc lô này và liên hệ nhà cung cấp.') ?></p>
    </div>
<?php endif; ?>

<section class="card shadow-sm mb-4">
    <div class="row g-0">
        <div class="col-md-4"><img class="img-fluid rounded-start trace-product-image" src="<?= e(asset($batch['product_image'])) ?>" alt="<?= e($batch['product_name']) ?>"></div>
        <div class="col-md-8"><div class="card-body">
            <p class="text-success mb-1">TRUY XUẤT NGUỒN GỐC</p>
            <h1 class="h2"><?= e($batch['product_name']) ?></h1>
            <p><a class="btn btn-outline-success" href="<?= e(url('/san-pham/' . $batch['product_slug'])) ?>">Xem trang sản phẩm</a></p>
            <dl class="row mb-0"><dt class="col-sm-4">Mã lô</dt><dd class="col-sm-8"><code class="fs-5"><?= e($batch['batch_code']) ?></code></dd>
                <dt class="col-sm-4">Trạng thái lô</dt><dd class="col-sm-8"><?= e($batchStatusLabel) ?></dd>
                <dt class="col-sm-4">Ngày thu hoạch</dt><dd class="col-sm-8"><?= e($batch['harvested_at']) ?></dd>
                <dt class="col-sm-4">Ngày đóng gói</dt><dd class="col-sm-8"><?= e($batch['packed_at'] ?: 'Chưa cập nhật') ?></dd>
                <dt class="col-sm-4">Hạn dùng</dt><dd class="col-sm-8"><?= e($batch['expires_at'] ?: 'Chưa cập nhật') ?> <span class="badge <?= $expiryStatus === 'Đã hết hạn' ? 'text-bg-danger' : ($daysToExpiry !== null && $daysToExpiry <= 7 ? 'text-bg-warning' : 'text-bg-success') ?>"><?= e($expiryStatus) ?></span></dd>
                <dt class="col-sm-4">Kết quả kiểm định</dt><dd class="col-sm-8"><span class="badge <?= $batch['inspection_result'] === 'failed' ? 'text-bg-danger' : ($batch['inspection_result'] === 'passed' ? 'text-bg-success' : 'text-bg-secondary') ?>"><?= e($inspectionLabel) ?></span><?php if ($batch['inspection_notes']): ?><div><?= nl2br(e($batch['inspection_notes'])) ?></div><?php endif; ?></dd>
            </dl>
        </div></div>
    </div>
</section>

<section class="card mb-4"><div class="card-body">
    <div class="row g-3 align-items-center">
        <div class="col-sm-3 col-lg-2"><?php if ($batch['farm_image']): ?><img class="img-fluid rounded farm-profile-image" src="<?= e(asset($batch['farm_image'])) ?>" alt="<?= e($batch['farm_name']) ?>"><?php endif; ?></div>
        <div class="col-sm-9 col-lg-10">
            <h2 class="h4">Nông trại / nhà cung cấp</h2>
            <h3 class="h5"><?= e($batch['farm_name'] ?: 'Chưa cập nhật') ?></h3>
            <p class="mb-1"><?= e($batch['farm_address'] ?: 'Chưa cập nhật địa chỉ') ?><?php if ($batch['parent_region_name'] || $batch['region_name']): ?> · <?= e(implode(' · ', array_filter([$batch['parent_region_name'], $batch['region_name']]))) ?><?php endif; ?></p>
            <?php if ($batch['contact_name']): ?><p class="mb-1">Đại diện: <?= e($batch['contact_name']) ?><?= $batch['farm_phone'] ? ' · ' . e($batch['farm_phone']) : '' ?></p><?php endif; ?>
            <?php if ($batch['certifications']): ?><p class="mb-1">Chứng nhận: <?= e($batch['certifications']) ?><?php if ($batch['certification_number']): ?> · Số: <?= e($batch['certification_number']) ?><?php endif; ?></p><?php endif; ?>
            <?php if ($batch['certification_expires_at']): ?><p class="mb-1">Chứng nhận hết hạn: <?= e($batch['certification_expires_at']) ?></p><?php endif; ?>
            <?php if ($batch['farm_address']): ?><a href="<?= e($mapsUrl) ?>" target="_blank" rel="noopener noreferrer">Xem vị trí trên bản đồ</a><?php endif; ?>
        </div>
    </div>
</div></section>

<section class="mb-4"><h2 class="h3 mb-3">Hành trình sản phẩm</h2>
    <?php if ($events): ?><ol class="trace-timeline">
        <?php foreach ($events as $event): ?><li class="trace-timeline-item">
            <div class="trace-event-date"><?= e($event['event_at']) ?></div>
            <h3 class="h5 mb-1"><?= e($event['event_label']) ?></h3>
            <?php if ($event['location']): ?><p class="small text-success mb-1"><?= e($event['location']) ?></p><?php endif; ?>
            <p class="mb-2"><?= nl2br(e($event['description'])) ?></p>
            <?php if ($event['image_url']): ?><a href="<?= e(asset($event['image_url'])) ?>" target="_blank" rel="noopener"><img class="img-fluid rounded trace-event-image" src="<?= e(asset($event['image_url'])) ?>" alt="Ảnh: <?= e($event['event_label']) ?>" loading="lazy"></a><?php endif; ?>
        </li><?php endforeach; ?>
    </ol><?php else: ?><p class="alert alert-light border">Thông tin các bước truy xuất đang được cập nhật.</p><?php endif; ?>
</section>
<p class="small text-secondary">Lượt tra cứu ghi nhận: <?= (int) $scanCount ?></p>
