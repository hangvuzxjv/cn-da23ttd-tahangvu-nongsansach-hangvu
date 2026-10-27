<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h2 mb-0">Quản lý người dùng</h1>
    <span class="text-secondary">Tối đa 200 kết quả</span>
</div>
<?php if (!empty($success)): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<form class="row g-2 mb-4" method="get" action="<?= e(url('/admin/users')) ?>">
    <div class="col-md-6"><label class="visually-hidden" for="q">Tìm kiếm</label><input class="form-control" id="q" name="q" placeholder="Tên, email hoặc số điện thoại" value="<?= e($query) ?>"></div>
    <div class="col-md-4"><label class="visually-hidden" for="role">Vai trò</label><select class="form-select" id="role" name="role"><option value="">Tất cả vai trò</option><?php foreach ($roles as $item): ?><option value="<?= e($item) ?>" <?= $role === $item ? 'selected' : '' ?>><?= e($item) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><button class="btn btn-success w-100" type="submit">Lọc</button></div>
</form>
<div class="table-responsive bg-white border rounded">
    <table class="table table-striped align-middle mb-0">
        <thead><tr><th>Người dùng</th><th>Liên hệ</th><th>Vai trò</th><th>Trạng thái</th><?php if ($canManage): ?><th>Cập nhật</th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($users as $item): ?>
            <tr>
                <td><?= e($item['name']) ?><div class="small text-secondary">#<?= (int) $item['id'] ?></div></td>
                <td><?= e($item['email']) ?><div class="small text-secondary"><?= e($item['phone']) ?></div></td>
                <td><?= e($item['role']) ?></td>
                <td><?= e($item['status']) ?></td>
                <?php if ($canManage): ?>
                    <td>
                        <?php if ((int) $item['id'] === $currentUserId): ?>
                            <span class="small text-secondary">Không thể tự đổi vai trò/trạng thái</span>
                        <?php else: ?>
                            <form class="d-flex gap-2" method="post" action="<?= e(url('/admin/users/' . $item['id'] . '/update')) ?>">
                                <?= csrf_field() ?>
                                <select class="form-select form-select-sm" name="role" aria-label="Vai trò">
                                    <?php foreach ($roles as $option): ?><option value="<?= e($option) ?>" <?= $item['role'] === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?>
                                </select>
                                <select class="form-select form-select-sm" name="status" aria-label="Trạng thái">
                                    <?php foreach (['active', 'inactive', 'banned'] as $option): ?><option value="<?= e($option) ?>" <?= $item['status'] === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?>
                                </select>
                                <button class="btn btn-sm btn-outline-success" type="submit">Lưu</button>
                            </form>
                        <?php endif; ?>
                    </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        <?php if (!$users): ?><tr><td colspan="<?= $canManage ? 5 : 4 ?>" class="text-center py-4">Không tìm thấy người dùng.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
