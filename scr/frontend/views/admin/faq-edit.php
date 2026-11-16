<header class="page-heading">
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url('/admin')) ?>">Quản trị</a></li><li class="breadcrumb-item"><a href="<?= e(url('/admin/content/faqs')) ?>">Câu hỏi thường gặp</a></li><li class="breadcrumb-item active" aria-current="page">Sửa</li></ol></nav>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div><h1 class="h2 mb-1">Sửa câu hỏi thường gặp</h1><p class="text-secondary mb-0">Câu trả lời được lưu và hiển thị dưới dạng văn bản thuần.</p></div>
        <a class="btn btn-outline-secondary" href="<?= e(url('/admin/content/faqs')) ?>">Danh sách câu hỏi</a>
    </div>
</header>
<?php if (!empty($success)): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
<section class="card"><div class="card-body">
    <form method="post" action="<?= e(url('/admin/content/faqs/' . (int) $item['id'])) ?>" class="row g-3">
        <?= csrf_field() ?>
        <div class="col-12 col-md-4">
            <label class="form-label" for="faqCategory">Nhóm</label>
            <select class="form-select" id="faqCategory" name="category" required>
                <?php foreach ($categories as $category): ?><option value="<?= e($category) ?>" <?= $item['category'] === $category ? 'selected' : '' ?>><?= e($category) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-6">
            <label class="form-label" for="faqQuestion">Câu hỏi</label>
            <input class="form-control" id="faqQuestion" name="question" maxlength="255" required value="<?= e($item['question']) ?>">
        </div>
        <div class="col-12 col-md-2">
            <label class="form-label" for="faqSortOrder">Thứ tự</label>
            <input class="form-control" id="faqSortOrder" name="sort_order" type="number" min="0" max="1000000" required value="<?= (int) $item['sort_order'] ?>">
        </div>
        <div class="col-12">
            <label class="form-label" for="faqAnswer">Câu trả lời (văn bản thuần)</label>
            <textarea class="form-control" id="faqAnswer" name="answer" rows="8" maxlength="10000" required><?= e($item['answer']) ?></textarea>
        </div>
        <div class="col-12 d-flex align-items-center justify-content-between gap-3">
            <div class="form-check">
                <input type="hidden" name="is_active" value="0">
                <input class="form-check-input" id="faqActive" name="is_active" type="checkbox" value="1" <?= (int) $item['is_active'] === 1 ? 'checked' : '' ?>>
                <label class="form-check-label" for="faqActive">Hiển thị công khai</label>
            </div>
            <button class="btn btn-success" type="submit">Lưu câu hỏi</button>
        </div>
    </form>
</div></section>
