<section class="mx-auto trace-lookup-card">
    <div class="text-center mb-4"><div class="display-4 text-secondary" aria-hidden="true">⌕</div><h1 class="h2">Chưa tìm thấy mã lô</h1>
        <?php if (!empty($batchCode)): ?><p class="text-secondary">Không tìm thấy mã <code><?= e($batchCode) ?></code>. Vui lòng kiểm tra ký tự trên nhãn và thử lại.</p><?php else: ?><p class="text-secondary">Nhập mã lô trên nhãn sản phẩm để xem thông tin nguồn gốc.</p><?php endif; ?>
    </div>
    <form method="get" action="<?= e(url('/truy-xuat')) ?>">
        <?php if (!empty($error)): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
        <label class="form-label" for="batch_code">Mã lô</label>
        <div class="input-group"><input class="form-control form-control-lg" id="batch_code" name="batch_code" value="<?= e($batchCode ?? '') ?>" required maxlength="100"><button class="btn btn-success" type="submit">Tra cứu</button></div>
        <a class="btn btn-link mt-3 px-0" href="<?= e(url('/')) ?>">Về trang chủ</a>
    </form>
</section>
