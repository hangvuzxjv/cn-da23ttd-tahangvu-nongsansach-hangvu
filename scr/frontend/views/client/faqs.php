<header class="page-heading">
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url('/')) ?>">Trang chủ</a></li><li class="breadcrumb-item active" aria-current="page">Câu hỏi thường gặp</li></ol></nav>
    <h1 class="h2 mb-2">Câu hỏi thường gặp</h1>
    <p class="text-secondary">Tìm thông tin về đặt hàng, thanh toán, giao nhận và chăm sóc khách hàng.</p>
</header>
<div class="mb-4">
    <label class="form-label" for="faqSearch">Tìm câu hỏi</label>
    <input class="form-control" id="faqSearch" type="search" placeholder="Nhập từ khóa cần tìm" autocomplete="off" aria-controls="faqList">
</div>
<div id="faqList">
    <?php foreach ($faqGroups as $category => $items): ?>
        <section class="mb-4" data-faq-category>
            <h2 class="h4 mb-3"><?= e($category) ?></h2>
            <div class="accordion">
                <?php foreach ($items as $item): ?>
                    <?php $answerId = 'faq-answer-' . (int) $item['id']; ?>
                    <article class="accordion-item" data-faq-item>
                        <h3 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?= e($answerId) ?>" aria-expanded="false" aria-controls="<?= e($answerId) ?>">
                                <?= e($item['question']) ?>
                            </button>
                        </h3>
                        <div id="<?= e($answerId) ?>" class="accordion-collapse collapse">
                            <div class="accordion-body text-break"><?= nl2br(e($item['answer'])) ?></div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
</div>
<p class="alert alert-light border" id="faqNoResults" role="status" aria-live="polite" <?= $faqGroups ? 'hidden' : '' ?>>
    <?= $faqGroups ? 'Không tìm thấy câu hỏi phù hợp.' : 'Hiện chưa có câu hỏi thường gặp được công bố.' ?>
</p>
<p class="text-secondary">Chưa tìm thấy câu trả lời? <a href="<?= e(url('/lien-he')) ?>">Liên hệ cửa hàng</a> hoặc <a href="<?= e(url('/ho-tro')) ?>">gửi phiếu hỗ trợ</a>.</p>
