<h1 class="h2 mb-3">Sổ cái biến động kho</h1>
<form class="row g-2 mb-3" method="get" action="<?= e(url('/admin/inventory/movements')) ?>">
    <div class="col-md-3"><select class="form-select" name="product_id"><option value="">Mọi sản phẩm</option><?php foreach ($products as $product): ?><option value="<?= (int) $product['id'] ?>" <?= $filters['product_id'] === (string) $product['id'] ? 'selected' : '' ?>><?= e($product['name']) ?> (<?= e($product['sku']) ?>)</option><?php endforeach; ?></select></div>
    <div class="col-md-2"><select class="form-select" name="batch_id"><option value="">Mọi lô</option><?php foreach ($batches as $batch): ?><option value="<?= (int) $batch['id'] ?>" <?= $filters['batch_id'] === (string) $batch['id'] ? 'selected' : '' ?>><?= e($batch['batch_code']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><select class="form-select" name="movement_type"><option value="">Mọi loại</option><?php foreach (['in','out','adjustment','return'] as $type): ?><option value="<?= e($type) ?>" <?= $filters['movement_type'] === $type ? 'selected' : '' ?>><?= e($type) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><select class="form-select" name="performed_by"><option value="">Mọi người</option><?php foreach ($users as $user): ?><option value="<?= (int) $user['id'] ?>" <?= $filters['performed_by'] === (string) $user['id'] ? 'selected' : '' ?>><?= e($user['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><input class="form-control" type="date" name="from" value="<?= e($filters['from']) ?>"></div>
    <div class="col-md-2"><input class="form-control" type="date" name="to" value="<?= e($filters['to']) ?>"></div>
    <div class="col-md-2"><button class="btn btn-primary">Lọc</button></div>
</form>
<p class="text-secondary">Tổng <?= (int) $total ?> movement. Sổ cái chỉ ghi thêm; mọi số lượng là signed quantity.</p>
<div class="table-responsive"><table class="table table-striped">
    <thead><tr><th>Thời điểm</th><th>Sản phẩm / lô</th><th>Loại</th><th>Biến động</th><th>Số dư sau movement</th><th>Tham chiếu</th><th>Người thực hiện</th><th>Lý do</th></tr></thead><tbody>
    <?php foreach ($rows as $row): ?><tr>
        <td><?= e($row['created_at']) ?></td><td><?= e($row['product_name']) ?><br><code><?= e($row['batch_code'] ?? '—') ?></code></td>
        <td><?= e($row['movement_type']) ?></td><td class="<?= (int) $row['quantity'] < 0 ? 'text-danger' : 'text-success' ?>"><?= (int) $row['quantity'] ?></td>
        <td><?= (int) $row['balance_after'] ?></td><td><?= e(($row['reference_type'] ?? '') . ($row['reference_id'] ? ' #' . $row['reference_id'] : '')) ?></td>
        <td><?= e($row['performed_by_name'] ?? 'Hệ thống') ?></td><td><?= e($row['notes']) ?></td>
    </tr><?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="8" class="text-secondary">Chưa có movement phù hợp.</td></tr><?php endif; ?>
    </tbody>
</table></div>
<?php if ($pages > 1): ?><nav aria-label="Phân trang movement"><ul class="pagination"><?php for ($number = 1; $number <= $pages; $number++): $params = array_merge($filters, ['page' => $number]); ?><li class="page-item <?= $number === $page ? 'active' : '' ?>"><a class="page-link" href="<?= e(url('/admin/inventory/movements?' . http_build_query($params))) ?>"><?= $number ?></a></li><?php endfor; ?></ul></nav><?php endif; ?>
