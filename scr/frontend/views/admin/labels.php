<div class="d-flex justify-content-between align-items-center mb-3 label-screen-controls"><h1 class="h2">Tem truy xuất nguồn gốc</h1><button class="btn btn-success" type="button" onclick="window.print()" <?= !$batches ? 'disabled' : '' ?>>In</button></div>
<?php if ($error): ?><div class="alert alert-warning"><?= e($error) ?></div><?php elseif (!$batches): ?><div class="alert alert-warning">Không tìm thấy lô được chọn.</div><?php else: ?>
<div class="label-grid"><?php foreach ($batches as $batch): ?><article class="trace-label">
    <img src="<?= e(url('/qr/' . $batch['batch_code'])) ?>" alt="QR <?= e($batch['batch_code']) ?>">
    <div><strong><?= e($batch['batch_code']) ?></strong><p><?= e($batch['product_name']) ?></p><small>Hạn dùng: <?= e($batch['expires_at'] ?: 'Không có hạn') ?></small></div>
</article><?php endforeach; ?></div>
<?php endif; ?>
