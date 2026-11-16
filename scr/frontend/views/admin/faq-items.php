<header class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div><h1 class="h2 mb-1">Câu hỏi thường gặp</h1><p class="text-secondary mb-0">Quản lý nội dung hiển thị trên trang FAQ.</p></div>
    <a class="btn btn-outline-success" href="<?= e(url('/cau-hoi-thuong-gap')) ?>" target="_blank" rel="noopener">Xem trang công khai</a>
</header>
<?php if (!empty($success)): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
<section class="card mb-4">
    <div class="card-header"><h2 class="h5 mb-0">Thêm câu hỏi</h2></div>
    <div class="card-body">
        <form method="post" action="<?= e(url('/admin/content/faqs')) ?>" class="row g-3">
            <?= csrf_field() ?>
            <div class="col-12 col-md-4">
                <label class="form-label" for="faqCategory">Nhóm</label>
                <select class="form-select" id="faqCategory" name="category" required>
                    <?php foreach ($categories as $category): ?><option value="<?= e($category) ?>"><?= e($category) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="faqQuestion">Câu hỏi</label>
                <input class="form-control" id="faqQuestion" name="question" maxlength="255" required>
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label" for="faqSortOrder">Thứ tự</label>
                <input class="form-control" id="faqSortOrder" name="sort_order" type="number" min="0" max="1000000" value="100" required>
            </div>
            <div class="col-12">
                <label class="form-label" for="faqAnswer">Câu trả lời (văn bản thuần)</label>
                <textarea class="form-control" id="faqAnswer" name="answer" rows="4" maxlength="10000" required></textarea>
            </div>
            <div class="col-12 d-flex align-items-center justify-content-between gap-3">
                <div class="form-check">
                    <input type="hidden" name="is_active" value="0">
                    <input class="form-check-input" id="faqActive" name="is_active" type="checkbox" value="1" checked>
                    <label class="form-check-label" for="faqActive">Hiển thị công khai</label>
                </div>
                <button class="btn btn-success" type="submit">Thêm câu hỏi</button>
            </div>
        </form>
    </div>
</section>
<div class="table-responsive">
    <table class="table table-hover align-middle bg-white">
        <thead><tr><th>Thứ tự</th><th>Nhóm / Câu hỏi</th><th>Trả lời</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
        <tbody>
        <?php foreach ($items as $item): ?>
            <tr>
                <td><?= (int) $item['sort_order'] ?></td>
                <td><span class="small text-secondary"><?= e($item['category']) ?></span><div><strong><?= e($item['question']) ?></strong></div></td>
                <td class="text-break"><details><summary>Xem câu trả lời</summary><p class="mt-2 mb-0"><?= nl2br(e($item['answer'])) ?></p></details></td>
                <td><?= (int) $item['is_active'] === 1 ? 'Đang hiển thị' : 'Đang ẩn' ?></td>
                <td>
                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-sm btn-outline-success" href="<?= e(url('/admin/content/faqs/' . (int) $item['id'] . '/edit')) ?>">Sửa</a>
                        <form method="post" action="<?= e(url('/admin/content/faqs/' . (int) $item['id'] . '/toggle')) ?>">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-outline-secondary" type="submit"><?= (int) $item['is_active'] === 1 ? 'Ẩn' : 'Hiện' ?></button>
                        </form>
                        <form method="post" action="<?= e(url('/admin/content/faqs/' . (int) $item['id'] . '/delete')) ?>" onsubmit="return confirm('Xóa câu hỏi này?');">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-outline-danger" type="submit">Xóa</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$items): ?><tr><td colspan="5" class="text-center text-secondary py-4">Chưa có câu hỏi nào.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
