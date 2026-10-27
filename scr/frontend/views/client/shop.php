<nav aria-label="breadcrumb"><ol class="breadcrumb"><?php foreach ($breadcrumbs as $crumb): ?><li class="breadcrumb-item"><?php if ($crumb['url']): ?><a href="<?= e(url($crumb['url'])) ?>"><?= e($crumb['name']) ?></a><?php else: ?><?= e($crumb['name']) ?><?php endif; ?></li><?php endforeach; ?></ol></nav>
<div class="mb-4"><h1 class="h2"><?= e($title) ?></h1><p class="text-secondary"><?= e($description) ?></p></div>

<?php $filterAction = $scopeType === 'region' ? '/vung-mien/' . $scope['region']['slug'] : ($scopeType === 'category' ? '/danh-muc/' . $scope['category']['slug'] : '/san-pham'); ?>
<form class="card card-body mb-4" method="get" action="<?= e(url($filterAction)) ?>">
    <?php if ($scopeType === 'region'): ?><input type="hidden" name="region" value="<?= (int) $scope['region']['id'] ?>"><?php endif; ?>
    <?php if ($scopeType === 'category'): ?><input type="hidden" name="category" value="<?= (int) $scope['category']['id'] ?>"><?php endif; ?>
    <div class="row g-2 align-items-end">
        <div class="col-sm-6 col-lg-3"><label class="form-label" for="q">Tìm theo tên</label><input class="form-control" id="q" name="q" value="<?= e($query) ?>" placeholder="Ví dụ: xoài cát"></div>
        <?php if ($scopeType !== 'category'): ?><div class="col-sm-6 col-lg-2"><label class="form-label" for="category">Danh mục</label><select class="form-select" id="category" name="category"><option value="">Tất cả</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>" <?= (int) $categoryId === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
        <?php if ($scopeType !== 'region'): ?><div class="col-sm-6 col-lg-2"><label class="form-label" for="region">Vùng miền</label><select class="form-select" id="region" name="region"><option value="">Tất cả</option><?php foreach ($regions as $region): ?><option value="<?= (int) $region['id'] ?>" <?= (int) $regionId === (int) $region['id'] ? 'selected' : '' ?>><?= str_repeat('— ', max(0, (int) $region['level'] - 1)) . e($region['name']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
        <div class="col-6 col-lg-1"><label class="form-label" for="min_price">Giá từ</label><input class="form-control" id="min_price" name="min_price" type="number" min="0" step="1000" value="<?= e($minimum) ?>"></div>
        <div class="col-6 col-lg-1"><label class="form-label" for="max_price">Đến</label><input class="form-control" id="max_price" name="max_price" type="number" min="0" step="1000" value="<?= e($maximum) ?>"></div>
        <div class="col-sm-6 col-lg-2"><label class="form-label" for="sort">Sắp xếp</label><select class="form-select" id="sort" name="sort"><?php foreach (['newest'=>'Mới nhất','price_asc'=>'Giá tăng dần','price_desc'=>'Giá giảm dần','best_selling'=>'Bán chạy'] as $value=>$label): ?><option value="<?= e($value) ?>" <?= $sort === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
        <div class="col-sm-6 col-lg-1 d-grid"><button class="btn btn-success" type="submit">Lọc</button></div>
    </div>
</form>

<?php foreach (($filterErrors ?? []) as $filterError): ?><div class="alert alert-warning"><?= e($filterError) ?></div><?php endforeach; ?>
<?php if ($query !== '' || $regionId || $categoryId || $minimum !== '' || $maximum !== ''): ?>
    <div class="alert alert-success d-flex flex-wrap justify-content-between align-items-center"><span>Đã lọc theo: <?= e(implode(' · ', array_filter([$query !== '' ? 'từ khóa "' . $query . '"' : '', $regionId ? 'vùng miền' : '', $categoryId ? 'danh mục' : '', $minimum !== '' ? 'giá từ ' . $minimum : '', $maximum !== '' ? 'giá đến ' . $maximum : '']))) ?></span><a class="btn btn-sm btn-outline-success" href="<?= e(url($scopeType === 'region' ? '/vung-mien/' . $scope['region']['slug'] : ($scopeType === 'category' ? '/danh-muc/' . $scope['category']['slug'] : '/san-pham'))) ?>">Xóa bộ lọc</a></div>
<?php endif; ?>
<p class="text-secondary"><?= (int) $total ?> sản phẩm</p>
<?php require FRONTEND_PATH . '/views/client/product-cards.php'; ?>
<?php if ($pages > 1): ?><nav class="mt-4" aria-label="Phân trang"><ul class="pagination"><?php for ($number = 1; $number <= $pages; $number++): $params = $_GET; $params['page'] = $number; ?><li class="page-item <?= $number === $page ? 'active' : '' ?>"><a class="page-link" href="<?= e(url($scopeType === 'region' ? '/vung-mien/' . $scope['region']['slug'] : ($scopeType === 'category' ? '/danh-muc/' . $scope['category']['slug'] : '/san-pham')) . '?' . http_build_query($params)) ?>"><?= $number ?></a></li><?php endfor; ?></ul></nav><?php endif; ?>
