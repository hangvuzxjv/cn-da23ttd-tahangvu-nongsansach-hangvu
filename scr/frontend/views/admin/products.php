<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h2">Sản phẩm <span class="badge text-bg-secondary"><?= (int) $total ?></span></h1>
    <?php if ($canManage): ?><a class="btn btn-success" href="<?= e(url('/admin/products/new')) ?>">Thêm sản phẩm</a><?php endif; ?>
</div>
<?php if (!empty($success)): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<form class="row g-2 mb-3" method="get" action="<?= e(url('/admin/products')) ?>">
    <div class="col-sm-6 col-lg"><label class="visually-hidden" for="q">Tìm kiếm</label><input class="form-control" id="q" name="q" placeholder="Tên hoặc SKU" value="<?= e($query) ?>"></div>
    <div class="col-sm-6 col-lg"><label class="visually-hidden" for="category">Danh mục</label><select class="form-select" id="category" name="category"><option value="">Mọi danh mục</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>" <?= $categoryId === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-sm-6 col-lg"><label class="visually-hidden" for="region">Vùng</label><select class="form-select" id="region" name="region"><option value="">Mọi vùng</option><?php foreach ($regions as $region): ?><option value="<?= (int) $region['id'] ?>" <?= $regionId === (int) $region['id'] ? 'selected' : '' ?>><?= e($region['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-sm-6 col-lg"><label class="visually-hidden" for="status">Trạng thái</label><select class="form-select" id="status" name="status"><option value="">Mọi trạng thái</option><?php foreach (['draft'=>'Nháp','active'=>'Đang bán','inactive'=>'Ngừng bán'] as $value=>$label): ?><option value="<?= e($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
    <div class="col-sm-6 col-lg-auto"><button class="btn btn-outline-success" type="submit">Lọc</button></div>
</form>
<div class="table-responsive bg-white border rounded"><table class="table table-striped align-middle mb-0">
    <thead><tr><th>Sản phẩm</th><th>SKU</th><th>Danh mục / Vùng</th><th>Giá</th><th>Tồn</th><th>Trạng thái</th><th></th></tr></thead>
    <tbody><?php foreach ($products as $product): ?>
        <tr>
            <td><div class="d-flex gap-2 align-items-center"><img class="catalog-thumb" src="<?= e(asset($product['image_url'] ?: '/img/product-placeholder.svg')) ?>" alt="" loading="lazy"><span><?= e($product['name']) ?><?= (int) $product['is_featured'] ? ' ★' : '' ?></span></div></td>
            <td><?= e($product['sku']) ?></td><td><?= e($product['category_name']) ?><div class="small text-secondary"><?= e($product['region_name']) ?></div></td>
            <td><?php if ($product['sale_price'] !== null): ?><del class="text-secondary"><?= number_format((float) $product['price'], 0, ',', '.') ?></del><br><strong class="text-danger"><?= number_format((float) $product['sale_price'], 0, ',', '.') ?></strong><?php else: ?><?= number_format((float) $product['price'], 0, ',', '.') ?><?php endif; ?></td>
            <td><?= (int) $product['total_stock'] ?></td><td><?= e(['draft' => 'Nháp', 'active' => 'Đang bán', 'inactive' => 'Ngừng bán'][$product['status']] ?? $product['status']) ?></td>
            <td><?php if ($canManage): ?><a class="btn btn-sm btn-outline-primary" href="<?= e(url('/admin/products/' . $product['id'] . '/edit')) ?>">Sửa</a><form class="d-inline" method="post" action="<?= e(url('/admin/products/' . $product['id'] . '/delete')) ?>" onsubmit="return confirm('Ẩn sản phẩm khỏi cửa hàng?')"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger" type="submit">Ẩn</button></form><?php endif; ?></td>
        </tr>
    <?php endforeach; ?><?php if (!$products): ?><tr><td colspan="7" class="text-center p-4">Không tìm thấy sản phẩm.</td></tr><?php endif; ?></tbody>
</table></div>
<?php if ($pages > 1): ?><nav class="mt-3" aria-label="Phân trang sản phẩm"><ul class="pagination"><?php for ($number = 1; $number <= $pages; $number++): $params = $_GET; $params['page'] = $number; ?><li class="page-item <?= $number === $page ? 'active' : '' ?>"><a class="page-link" href="<?= e(url('/admin/products') . '?' . http_build_query($params)) ?>"><?= $number ?></a></li><?php endfor; ?></ul></nav><?php endif; ?>
