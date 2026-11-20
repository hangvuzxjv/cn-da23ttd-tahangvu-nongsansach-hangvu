<div class="row justify-content-center">
    <div class="col-12 col-md-7 col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4 p-md-5">
                <span class="badge rounded-pill bg-success text-white mb-2">Bảo mật tài khoản</span>
                <h1 class="h3 mb-2">Quên mật khẩu</h1>
                <p class="text-secondary mb-4">Nhập email tài khoản để nhận mã xác minh đặt lại mật khẩu.</p>
                <?php if (!empty($success)): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
                <?php if (isset($errors['email'])): ?><div class="alert alert-danger"><?= e($errors['email']) ?></div><?php endif; ?>
                <form method="post" action="<?= e(url('/forgot-password')) ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="email">Email tài khoản</label>
                        <input class="form-control" id="email" name="email" type="email" autocomplete="email" required value="<?= e(old('email')) ?>">
                    </div>
                    <button class="btn btn-success w-100" type="submit">Gửi mã xác minh</button>
                </form>
                <p class="text-center mt-4 mb-0"><a href="<?= e(url('/login')) ?>">Quay lại đăng nhập</a></p>
            </div>
        </div>
    </div>
</div>
