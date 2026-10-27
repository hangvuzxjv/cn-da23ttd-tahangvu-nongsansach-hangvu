<section class="p-4 p-md-5 mb-4 bg-body-tertiary rounded-3">
    <div class="container-fluid py-2">
        <h1 class="display-5 fw-bold">Nông sản sạch, an tâm mỗi ngày</h1>
        <p class="col-md-8 fs-5">Chào mừng bạn đến với hệ thống bán nông sản sạch. Đây là trang chủ mẫu cho khung MVC.</p>
        <?php if (isset($message)): ?>
            <p class="alert alert-info"><?= e($message) ?></p>
        <?php endif; ?>
        <a class="btn btn-success btn-lg" href="<?= e(url('/login')) ?>">Đăng nhập thử</a>
    </div>
</section>
