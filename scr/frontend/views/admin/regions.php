<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h2">Quản lý vùng miền</h1>
    <?php if ($canManage): ?><button class="btn btn-success" data-bs-toggle="collapse" data-bs-target="#newRegion">Thêm vùng miền</button><?php endif; ?>
</div>
<?php if (!empty($success)): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
<?php if ($canManage): ?>
    <div class="collapse mb-4" id="newRegion"><div class="card"><div class="card-body">
        <h2 class="h5">Thêm miền hoặc tỉnh/vùng</h2>
        <form class="row g-2" method="post" action="<?= e(url('/admin/regions')) ?>">
            <?= csrf_field() ?>
            <div class="col-md-3"><label class="form-label">Tên</label><input class="form-control" name="name" required></div>
            <div class="col-md-3"><label class="form-label">Miền cha (để trống để tạo miền)</label><select class="form-select" name="parent_id"><option value="">Miền cấp 1</option><?php foreach ($regions as $item): if ((int) $item['level'] === 1): ?><option value="<?= (int) $item['id'] ?>"><?= e($item['name']) ?></option><?php endif; endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label">Slug (để trống tự tạo)</label><input class="form-control" name="slug"></div>
            <div class="col-md-3"><label class="form-label">Mô tả</label><input class="form-control" name="description"></div>
            <div class="col-12 form-check ms-2"><input class="form-check-input" id="new_region_active" name="is_active" type="checkbox" checked><label class="form-check-label" for="new_region_active">Hiển thị</label></div>
            <div class="col-12"><button class="btn btn-success" type="submit">Lưu vùng miền</button></div>
        </form>
    </div></div></div>
<?php endif; ?>
<div class="table-responsive bg-white border rounded">
    <table class="table align-middle mb-0">
        <thead><tr><th>Miền / Tỉnh, vùng</th><th>Slug</th><th>Mô tả</th><th>Sản phẩm</th><th>Hiển thị</th><?php if ($canManage): ?><th>Thao tác</th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($regions as $region): ?>
            <tr class="<?= (int) $region['level'] === 1 ? 'table-success' : '' ?>">
                <td><?= (int) $region['level'] === 2 ? '<span class="ms-4">↳ </span>' : '<strong>' ?><?= e($region['name']) ?><?= (int) $region['level'] === 1 ? '</strong>' : '' ?></td>
                <td><code><?= e($region['slug']) ?></code></td>
                <td><?= e($region['description']) ?></td>
                <td><?= (int) $region['product_count'] ?></td>
                <td><?= (int) $region['is_active'] === 1 ? 'Đang hiển thị' : 'Đang ẩn' ?></td>
                <?php if ($canManage): ?>
                    <td>
                        <details><summary class="btn btn-sm btn-outline-primary">Sửa</summary>
                            <form class="border rounded p-2 mt-2" method="post" action="<?= e(url('/admin/regions/' . $region['id'] . '/update')) ?>">
                                <?= csrf_field() ?>
                                <label class="form-label">Tên</label><input class="form-control mb-2" name="name" value="<?= e($region['name']) ?>" required>
                                <?php if ((int) $region['level'] === 2): ?>
                                    <label class="form-label">Miền cha</label><select class="form-select mb-2" name="parent_id" required><?php foreach ($regions as $parent): if ((int) $parent['level'] === 1): ?><option value="<?= (int) $parent['id'] ?>" <?= (int) $parent['id'] === (int) $region['parent_id'] ? 'selected' : '' ?>><?= e($parent['name']) ?></option><?php endif; endforeach; ?></select>
                                <?php else: ?><input type="hidden" name="parent_id" value="0"><?php endif; ?>
                                <label class="form-label">Slug</label><input class="form-control mb-2" name="slug" value="<?= e($region['slug']) ?>">
                                <label class="form-label">Mô tả</label><textarea class="form-control mb-2" name="description"><?= e($region['description']) ?></textarea>
                                <div class="form-check mb-2"><input class="form-check-input" id="region_active_<?= (int) $region['id'] ?>" name="is_active" type="checkbox" <?= (int) $region['is_active'] === 1 ? 'checked' : '' ?>><label class="form-check-label" for="region_active_<?= (int) $region['id'] ?>">Hiển thị</label></div>
                                <button class="btn btn-sm btn-success" type="submit">Lưu</button>
                            </form>
                        </details>
                        <form class="mt-2" method="post" action="<?= e(url('/admin/regions/' . $region['id'] . '/delete')) ?>" onsubmit="return confirm('Xóa vùng miền này? Nếu còn sản phẩm hoặc mục con, hệ thống sẽ từ chối và thông báo lý do.')">
                            <?= csrf_field() ?><button class="btn btn-sm btn-outline-danger" type="submit">Xóa</button>
                        </form>
                    </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
