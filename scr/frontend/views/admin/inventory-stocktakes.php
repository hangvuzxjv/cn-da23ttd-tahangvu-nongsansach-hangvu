<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h2 mb-0">Kiểm kê kho</h1>
    <?php if ($canManage): ?><form method="post" action="<?= e(url('/admin/inventory/stocktakes')) ?>" class="d-flex gap-2">
        <?= csrf_field() ?><input class="form-control" name="notes" placeholder="Ghi chú đợt kiểm kê"><button class="btn btn-primary">Tạo đợt kiểm kê</button>
    </form><?php endif; ?>
</div>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<div class="table-responsive mb-4"><table class="table table-sm table-striped"><thead><tr><th>Mã đợt</th><th>Ngày tạo</th><th>Người tạo</th><th>Số lô</th><th>Trạng thái</th><th></th></tr></thead><tbody>
<?php foreach ($stocktakes as $stocktake): ?><tr><td><?= e($stocktake['stocktake_no']) ?></td><td><?= e($stocktake['created_at']) ?></td><td><?= e($stocktake['creator_name'] ?? '—') ?></td><td><?= (int) $stocktake['item_count'] ?></td><td><?= e($stocktake['status']) ?></td><td><a href="<?= e(url('/admin/inventory/stocktakes?id=' . $stocktake['id'])) ?>">Mở</a></td></tr><?php endforeach; ?>
<?php if (!$stocktakes): ?><tr><td colspan="6" class="text-secondary">Chưa có đợt kiểm kê.</td></tr><?php endif; ?>
</tbody></table></div>
<?php if ($selectedId): ?>
    <h2 class="h4">Chi tiết đợt #<?= $selectedId ?></h2>
    <?php $isDraft = false; foreach ($stocktakes as $stocktake) { if ((int) $stocktake['id'] === $selectedId && $stocktake['status'] === 'draft') { $isDraft = true; break; } } ?>
    <form method="post" action="<?= e(url('/admin/inventory/stocktakes/' . $selectedId . '/counts')) ?>">
        <?= csrf_field() ?>
        <div class="table-responsive"><table class="table table-sm table-bordered"><thead><tr><th>Sản phẩm</th><th>Mã lô</th><th>Tồn hệ thống lúc tạo</th><th>Số đếm thực tế</th><th>Chênh lệch khi tạo</th></tr></thead><tbody>
        <?php foreach ($items as $item): ?><tr>
            <td><?= e($item['product_name']) ?> <small><?= e($item['sku']) ?></small></td><td><code><?= e($item['batch_code']) ?></code></td>
            <td><?= (int) $item['system_quantity'] ?></td><td>
                <?php if ($isDraft && $canManage): ?><input class="form-control form-control-sm" type="number" name="counted_quantity[<?= (int) $item['batch_id'] ?>]" min="0" value="<?= e($item['counted_quantity'] ?? '') ?>">
                <?php else: ?><?= $item['counted_quantity'] === null ? '—' : (int) $item['counted_quantity'] ?><?php endif; ?>
            </td><td><?= $item['counted_quantity'] === null ? '—' : (int) $item['counted_quantity'] - (int) $item['system_quantity'] ?></td>
        </tr><?php endforeach; ?>
        </tbody></table></div>
        <?php if ($isDraft && $canManage): ?><div class="d-flex gap-2"><button class="btn btn-outline-primary">Lưu số đếm</button></form>
            <form method="post" action="<?= e(url('/admin/inventory/stocktakes/' . $selectedId . '/confirm')) ?>" onsubmit="return confirm('Xác nhận kiểm kê và ghi nhận chênh lệch thành movement?')"><?= csrf_field() ?><button class="btn btn-success">Xác nhận kiểm kê</button></form>
        <?php else: ?></form><?php endif; ?>
<?php endif; ?>
