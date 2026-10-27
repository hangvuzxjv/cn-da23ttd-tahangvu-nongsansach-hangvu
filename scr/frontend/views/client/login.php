<div class="row justify-content-center">
    <div class="col-12 col-md-7 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h3 mb-3">Đăng nhập</h1>
                <?php if ($success = flash('success')): ?>
                    <div class="alert alert-success"><?= e($success) ?></div>
                <?php endif; ?>
                <?php foreach ($errors as $error): ?>
                    <div class="alert alert-danger"><?= e($error) ?></div>
                <?php endforeach; ?>
                <form method="post" action="<?= e(url('/login')) ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <input class="form-control" id="email" name="email" type="email" autocomplete="email" required value="<?= e(old('email')) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">Mật khẩu</label>
                        <input class="form-control" id="password" name="password" type="password" autocomplete="current-password" required>
                    </div>
                    <button class="btn btn-success" type="submit">Đăng nhập</button>
                </form>
                <div class="mt-3 d-flex justify-content-between">
                    <a href="<?= e(url('/forgot-password')) ?>">Quên mật khẩu?</a>
                    <a href="<?= e(url('/register')) ?>">Tạo tài khoản</a>
                </div>
                <?php if (!empty($mailLink)): ?>
                    <div class="alert alert-info mt-3 mb-0">Liên kết email thử nghiệm (debug): <a href="<?= e($mailLink) ?>">Mở liên kết</a></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
