<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h2">Quản lý danh mục</h1>
    <?php if ($canManage): ?><button class="btn btn-success" data-bs-toggle="collapse" data-bs-target="#newCategory">Thêm danh mục</button><?php endif; ?>
</div>
<?php if (!empty($success)): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
<?php if ($canManage): ?>
<div class="collapse mb-4" id="newCategory"><div class="card"><div class="card-body">
    <form class="row g-2" method="post" action="<?= e(url('/admin/categories')) ?>">
        <?= csrf_field() ?>
        <div class="col-md-3"><label class="form-label">Tên</label><input class="form-control" name="name" required></div>
        <div class="col-md-3"><label class="form-label">Danh mục cha</label><select class="form-select" name="parent_id"><option value="">Danh mục gốc</option><?php foreach ($categories as $parent): ?><option value="<?= (int) $parent['id'] ?>"><?= e($parent['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label">Slug (để trống tự tạo)</label><input class="form-control" name="slug"></div>
        <div class="col-md-2"><label class="form-label">Thứ tự</label><input class="form-control" name="sort_order" type="number" value="0"></div>
        <div class="col-md-1 d-flex align-items-end"><button class="btn btn-success" type="submit">Lưu</button></div>
        <div class="col-md-10"><label class="form-label">Mô tả</label><input class="form-control" name="description"></div>
        <div class="col-md-2 d-flex align-items-end"><div class="form-check mb-2"><input class="form-check-input" id="new_category_active" name="is_active" type="checkbox" checked><label class="form-check-label" for="new_category_active">Hiển thị</label></div></div>
    </form>
</div></div></div>
<?php endif; ?>
<div class="table-responsive bg-white border rounded"><table class="table align-middle mb-0">
    <thead><tr><th>Danh mục</th><th>Cha</th><th>Slug</th><th>Thứ tự</th><th>Sản phẩm</th><th>Hiển thị</th><?php if ($canManage): ?><th>Thao tác</th><?php endif; ?></tr></thead>
    <tbody><?php foreach ($categories as $category): ?>
        <tr><td><?= !empty($category['parent_id']) ? '<span class="ms-3">↳ </span>' : '<strong>' ?><?= e($category['name']) ?><?= empty($category['parent_id']) ? '</strong>' : '' ?></td><td><?= e($category['parent_name']) ?></td><td><code><?= e($category['slug']) ?></code></td><td><?= (int) $category['sort_order'] ?></td><td><?= (int) $category['product_count'] ?></td><td><?= (int) $category['is_active'] ? 'Hiển thị' : 'Ẩn' ?></td>
        <?php if ($canManage): ?><td><details><summary class="btn btn-sm btn-outline-primary">Sửa</summary>
            <form class="border rounded p-2 mt-2" method="post" action="<?= e(url('/admin/categories/' . $category['id'] . '/update')) ?>">
                <?= csrf_field() ?><input class="form-control mb-2" name="name" value="<?= e($category['name']) ?>" required>
                <select class="form-select mb-2" name="parent_id"><option value="">Danh mục gốc</option><?php foreach ($categories as $parent): if ((int) $parent['id'] !== (int) $category['id']): ?><option value="<?= (int) $parent['id'] ?>" <?= (int) $parent['id'] === (int) $category['parent_id'] ? 'selected' : '' ?>><?= e($parent['name']) ?></option><?php endif; endforeach; ?></select>
                <input class="form-control mb-2" name="slug" value="<?= e($category['slug']) ?>" aria-label="Slug">
                <textarea class="form-control mb-2" name="description" aria-label="Mô tả"><?= e($category['description']) ?></textarea>
                <input class="form-control mb-2" name="sort_order" type="number" value="<?= (int) $category['sort_order'] ?>" aria-label="Thứ tự">
                <div class="form-check mb-2"><input class="form-check-input" id="cat_active_<?= (int) $category['id'] ?>" name="is_active" type="checkbox" <?= (int) $category['is_active'] ? 'checked' : '' ?>><label class="form-check-label" for="cat_active_<?= (int) $category['id'] ?>">Hiển thị</label></div>
                <button class="btn btn-sm btn-success" type="submit">Lưu</button>
            </form></details>
            <form class="mt-2" method="post" action="<?= e(url('/admin/categories/' . $category['id'] . '/delete')) ?>" onsubmit="return confirm('Xóa danh mục? Hệ thống sẽ từ chối nếu còn sản phẩm hoặc danh mục con.')"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger" type="submit">Xóa</button></form>
        </td><?php endif; ?></tr>
    <?php endforeach; ?></tbody>
</table></div>
