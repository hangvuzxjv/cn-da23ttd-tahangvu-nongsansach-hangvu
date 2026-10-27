<div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div>
        <h1 class="h2">Bảng điều khiển</h1>
        <p class="text-secondary mb-0">Xin chào <?= e($user['name'] ?? '') ?> · Vai trò: <?= e($user['role'] ?? '') ?></p>
    </div>
    <a class="btn btn-outline-success" href="<?= e(url('/profile')) ?>">Hồ sơ</a>
</div>
<section class="card mt-4"><div class="card-body">
    <h2 class="h5">Top lô được quét nhiều</h2>
    <?php if (empty($topScanned)): ?><p class="text-secondary mb-0">Chưa có lượt quét nguồn gốc.</p><?php else: ?>
        <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Mã lô</th><th>Sản phẩm</th><th>Lượt quét</th><th></th></tr></thead><tbody>
            <?php foreach ($topScanned as $batch): ?><tr><td><code><?= e($batch['batch_code']) ?></code></td><td><?= e($batch['product_name']) ?></td><td><?= (int) $batch['scan_count'] ?></td><td><a href="<?= e(url('/admin/batches/' . $batch['id'])) ?>">Chi tiết</a></td></tr><?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</div></section>
