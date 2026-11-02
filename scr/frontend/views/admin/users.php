<?php
$roleLabels = [
    'customer' => 'Khách hàng',
    'admin' => 'Quản trị viên',
    'staff_sales' => 'Nhân viên bán hàng',
    'staff_warehouse' => 'Nhân viên kho',
];
$statusLabels = [
    'active' => 'Hoạt động',
    'inactive' => 'Ngừng hoạt động',
    'banned' => 'Đã khóa',
];
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h2 mb-0"><?= $role === 'customer' ? 'Khách hàng' : 'Quản lý người dùng' ?></h1>
    <span class="text-secondary">Tổng <?= (int) $total ?> kết quả</span>
</div>
<?php if (!empty($success)): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<form class="row g-2 mb-4" method="get" action="<?= e($listUrl) ?>">
    <div class="col-md-6"><label class="visually-hidden" for="q">Tìm kiếm</label><input class="form-control" id="q" name="q" placeholder="Tên, email hoặc số điện thoại" value="<?= e($query) ?>"></div>
    <div class="col-md-4"><label class="visually-hidden" for="role">Vai trò</label><select class="form-select" id="role" name="role"><option value="">Tất cả vai trò</option><?php foreach ($roles as $item): ?><option value="<?= e($item) ?>" <?= $role === $item ? 'selected' : '' ?>><?= e($roleLabels[$item] ?? $item) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><button class="btn btn-success w-100" type="submit">Lọc</button></div>
</form>
<div class="table-responsive bg-white border rounded">
    <table class="table table-striped align-middle mb-0">
        <thead><tr><th><?= $role === 'customer' ? 'Khách hàng' : 'Người dùng' ?></th><th>Liên hệ</th><th>Vai trò</th><th>Trạng thái</th><?php if ($canManage): ?><th>Cập nhật</th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($users as $item): ?>
            <tr>
                <td><?= e($item['name']) ?><div class="small text-secondary">#<?= (int) $item['id'] ?></div></td>
                <td><?= e($item['email']) ?><div class="small text-secondary"><?= e($item['phone']) ?></div></td>
                <td><?= e($roleLabels[$item['role']] ?? $item['role']) ?></td>
                <td><?= e($statusLabels[$item['status']] ?? $item['status']) ?></td>
                <?php if ($canManage): ?>
                    <td>
                        <?php if ((int) $item['id'] === $currentUserId): ?>
                            <span class="small text-secondary">Không thể tự đổi vai trò/trạng thái</span>
                        <?php else: ?>
                            <form class="d-flex gap-2" method="post" action="<?= e(url('/admin/users/' . $item['id'] . '/update')) ?>">
                                <?= csrf_field() ?>
                                <select class="form-select form-select-sm" name="role" aria-label="Vai trò">
                                    <?php foreach ($roles as $option): ?><option value="<?= e($option) ?>" <?= $item['role'] === $option ? 'selected' : '' ?>><?= e($roleLabels[$option] ?? $option) ?></option><?php endforeach; ?>
                                </select>
                                <select class="form-select form-select-sm" name="status" aria-label="Trạng thái">
                                    <?php foreach (['active', 'inactive', 'banned'] as $option): ?><option value="<?= e($option) ?>" <?= $item['status'] === $option ? 'selected' : '' ?>><?= e($statusLabels[$option]) ?></option><?php endforeach; ?>
                                </select>
                                <button class="btn btn-sm btn-outline-success" type="submit">Lưu</button>
                            </form>
                        <?php endif; ?>
                    </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        <?php if (!$users): ?><tr><td colspan="<?= $canManage ? 5 : 4 ?>" class="text-center py-4">Không tìm thấy <?= $role === 'customer' ? 'khách hàng' : 'người dùng' ?>.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php if ($pages > 1): ?>
    <nav class="mt-3" aria-label="Phân trang người dùng">
        <ul class="pagination">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <?php if ($page <= 1): ?><span class="page-link">Trước</span><?php else: ?>
                    <a class="page-link" href="<?= e($listUrl . '?' . http_build_query(['q' => $query, 'role' => $role, 'page' => $page - 1])) ?>">Trước</a>
                <?php endif; ?>
            </li>
            <?php for ($index = 1; $index <= $pages; $index++): ?>
                <li class="page-item <?= $index === $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= e($listUrl . '?' . http_build_query(['q' => $query, 'role' => $role, 'page' => $index])) ?>"<?= $index === $page ? ' aria-current="page"' : '' ?>><?= $index ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
                <?php if ($page >= $pages): ?><span class="page-link">Sau</span><?php else: ?>
                    <a class="page-link" href="<?= e($listUrl . '?' . http_build_query(['q' => $query, 'role' => $role, 'page' => $page + 1])) ?>">Sau</a>
                <?php endif; ?>
            </li>
        </ul>
    </nav>
<?php endif; ?>
