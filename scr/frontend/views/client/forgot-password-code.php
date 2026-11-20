<div class="row justify-content-center">
    <div class="col-12 col-md-7 col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4 p-md-5">
                <span class="badge rounded-pill bg-success text-white mb-2">Đặt lại mật khẩu</span>
                <h1 class="h3 mb-2">Nhập mã xác minh</h1>
                <p class="text-secondary mb-4">
                    Nếu email <strong><?= e($email) ?></strong> thuộc tài khoản, mã xác minh sẽ được gửi đến hộp thư.
                    Mã có hiệu lực trong 10 phút.
                </p>
                <?php if (!empty($success)): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
                <?php if (!empty($error)): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
                <?php foreach ($errors as $message): ?><div class="alert alert-danger"><?= e($message) ?></div><?php endforeach; ?>
                <form method="post" action="<?= e(url('/forgot-password/code')) ?>" novalidate>
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="code">Mã xác minh gồm 6 chữ số</label>
                        <input class="form-control form-control-lg text-center"
                               id="code" name="code" type="text" inputmode="numeric"
                               autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6"
                               placeholder="000000" required autofocus>
                    </div>
                    <button class="btn btn-success w-100" type="submit">Xác minh mã</button>
                </form>
                <form class="mt-3" method="post" action="<?= e(url('/forgot-password/code/resend')) ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-outline-success w-100"
                            type="submit" data-otp-resend data-seconds="<?= (int) $resendAfter ?>"
                            <?= $resendAfter > 0 ? 'disabled' : '' ?>>
                        <span data-otp-resend-label>Gửi lại mã</span>
                    </button>
                </form>
                <p class="text-center mt-4 mb-0"><a href="<?= e(url('/login')) ?>">Quay lại đăng nhập</a></p>
            </div>
        </div>
    </div>
</div>
