<div class="row justify-content-center">
    <div class="col-12 col-md-7 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h3 mb-3">Đăng nhập giả lập</h1>
                <?php if ($error = flash('error')): ?>
                    <div class="alert alert-warning"><?= e($error) ?></div>
                <?php endif; ?>
                <p class="text-secondary">Nhập email bất kỳ để vào khu vực quản trị mẫu.</p>
                <form method="post" action="<?= e(url('/login')) ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <input class="form-control" id="email" name="email" type="email" required value="<?= e(old('email')) ?>">
                    </div>
                    <button class="btn btn-success" type="submit">Đăng nhập</button>
                </form>
            </div>
        </div>
    </div>
</div>
