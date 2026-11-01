<div class="mb-4"><h1 class="h2 mb-1">Quản lý trang tĩnh</h1><p class="text-secondary mb-0">Chỉ các thẻ HTML an toàn trong danh sách cho phép mới được lưu.</p></div>
<?php if (!empty($success)): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<?php foreach ($pages as $page): ?>
    <section class="card mb-4"><div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><h2 class="h5 mb-0"><?= e($page['title']) ?> <span class="small text-secondary">/<?= e($page['slug']) ?></span></h2><a href="<?= e(url('/' . $page['slug'])) ?>" target="_blank" rel="noopener">Xem trang</a></div>
        <form method="post" action="<?= e(url('/admin/content/pages/' . $page['id'])) ?>">
            <?= csrf_field() ?>
            <div class="mb-3"><label class="form-label" for="page-title-<?= (int) $page['id'] ?>">Tiêu đề</label><input class="form-control" id="page-title-<?= (int) $page['id'] ?>" name="title" maxlength="180" required value="<?= e($page['title']) ?>"></div>
            <div class="mb-3"><label class="form-label" for="page-content-<?= (int) $page['id'] ?>">Nội dung HTML</label><textarea class="form-control font-monospace" id="page-content-<?= (int) $page['id'] ?>" name="content" rows="8" maxlength="100000" required><?= e($page['content']) ?></textarea><div class="form-text">Thẻ cho phép: đoạn văn, tiêu đề, danh sách, trích dẫn, liên kết an toàn và nhấn mạnh.</div></div>
            <div class="row g-3 align-items-end"><div class="col-auto"><label class="form-label" for="page-status-<?= (int) $page['id'] ?>">Hiển thị</label><select class="form-select" id="page-status-<?= (int) $page['id'] ?>" name="status"><option value="published" <?= $page['status'] === 'published' ? 'selected' : '' ?>>Đang xuất bản</option><option value="draft" <?= $page['status'] === 'draft' ? 'selected' : '' ?>>Ẩn bản nháp</option></select></div><div class="col-auto"><button class="btn btn-success" type="submit">Lưu trang</button></div><div class="col text-secondary small">Cập nhật: <?= e($page['updated_at']) ?></div></div>
        </form>
    </div></section>
<?php endforeach; ?>
