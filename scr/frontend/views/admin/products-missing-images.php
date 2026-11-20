<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h2 mb-0">Sản phẩm thiếu ảnh <span class="badge text-bg-secondary"><?= (int) $total ?></span></h1>
    <a class="btn btn-outline-secondary" href="<?= e(url('/admin/products')) ?>">Quay lại danh sách</a>
</div>
<?php if (!empty($success)): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<?php if (!$canManage): ?><div class="alert alert-info">Bạn có quyền xem danh sách nhưng không có quyền thêm ảnh sản phẩm.</div><?php endif; ?>
<div class="table-responsive bg-white border rounded">
    <table class="table table-striped align-middle mb-0">
        <thead><tr><th>Sản phẩm</th><th>SKU</th><th>Trạng thái</th><?php if ($canManage): ?><th></th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($products as $product): ?>
            <tr>
                <td><?= e($product['name']) ?></td>
                <td><?= e($product['sku']) ?></td>
                <td><?= e(['draft' => 'Nháp', 'active' => 'Đang bán', 'inactive' => 'Ngừng bán'][$product['status']] ?? $product['status']) ?></td>
                <?php if ($canManage): ?><td><a class="btn btn-sm btn-outline-primary" href="<?= e(url('/admin/products/' . $product['id'] . '/edit#product-images')) ?>">Thêm ảnh</a></td><?php endif; ?>
            </tr>
        <?php endforeach; ?>
        <?php if ($products === []): ?><tr><td colspan="<?= $canManage ? 4 : 3 ?>" class="text-center p-4">Không có sản phẩm nào thiếu ảnh.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
