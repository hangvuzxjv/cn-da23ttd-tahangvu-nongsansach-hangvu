<div class="row justify-content-center">
    <div class="col-12 col-md-7 col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4 p-md-5">
                <span class="badge rounded-pill bg-success text-white mb-2">Bảo mật tài khoản</span>
                <h1 class="h3 mb-2">Xác minh email</h1>
                <p class="text-secondary mb-4">
                    Nhập mã gồm 6 chữ số đã gửi đến <strong><?= e($email) ?></strong>.
                    Mã có hiệu lực trong 10 phút.
                </p>
                <?php if (!empty($success)): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
                <?php if (!empty($error)): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
                <?php foreach ($errors as $message): ?><div class="alert alert-danger"><?= e($message) ?></div><?php endforeach; ?>
                <form method="post" action="<?= e(url('/verify-email-otp')) ?>" novalidate>
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="code">Mã xác minh</label>
                        <input class="form-control form-control-lg text-center"
                               id="code" name="code" type="text" inputmode="numeric"
                               autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6"
                               placeholder="000000" aria-describedby="codeHelp" required autofocus>
                        <div class="form-text" id="codeHelp">Bạn có tối đa 5 lần nhập mã.</div>
                    </div>
                    <button class="btn btn-success w-100" type="submit">Xác minh email</button>
                </form>
                <form class="mt-3" method="post" action="<?= e(url('/verify-email-otp/resend')) ?>">
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
