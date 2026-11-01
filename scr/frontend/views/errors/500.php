<section class="text-center py-5 error-card">
    <p class="display-1 fw-bold text-danger error-card__code">500</p>
    <h1 class="h3">Đã xảy ra lỗi máy chủ</h1>
    <p class="text-secondary">Vui lòng thử lại sau.</p>
    <?php if (!empty($detail)): ?>
        <pre class="text-start bg-light border rounded p-3 mt-4"><?= e($detail) ?></pre>
    <?php endif; ?>
    <a class="btn btn-success" href="<?= e(url('/')) ?>">Về trang chủ</a>
</section>
