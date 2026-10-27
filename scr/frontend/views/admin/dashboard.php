<div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div>
        <h1 class="h2">Bảng điều khiển</h1>
        <p class="text-secondary mb-0">Đăng nhập giả lập thành công.</p>
    </div>
    <form method="post" action="<?= e(url('/logout')) ?>">
        <?= csrf_field() ?>
        <button class="btn btn-outline-danger" type="submit">Đăng xuất</button>
    </form>
</div>
