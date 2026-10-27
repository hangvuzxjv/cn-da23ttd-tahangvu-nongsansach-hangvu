<div class="row justify-content-center">
    <div class="col-12 col-md-7 col-lg-5">
        <div class="card shadow-sm"><div class="card-body p-4">
            <h1 class="h3 mb-3">Quên mật khẩu</h1>
            <?php if (!empty($success)): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
            <?php if (isset($errors['email'])): ?><div class="alert alert-danger"><?= e($errors['email']) ?></div><?php endif; ?>
            <form method="post" action="<?= e(url('/forgot-password')) ?>">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label" for="email">Email tài khoản</label>
                    <input class="form-control" id="email" name="email" type="email" autocomplete="email" required value="<?= e(old('email')) ?>">
                </div>
                <button class="btn btn-success" type="submit">Gửi liên kết đặt lại</button>
                <a class="btn btn-link" href="<?= e(url('/login')) ?>">Quay lại đăng nhập</a>
            </form>
            <?php if (!empty($mailLink)): ?>
                <div class="alert alert-info mt-3 mb-0">Liên kết đặt lại thử nghiệm (debug): <a href="<?= e($mailLink) ?>">Đặt lại mật khẩu</a></div>
            <?php endif; ?>
        </div></div>
    </div>
</div>
