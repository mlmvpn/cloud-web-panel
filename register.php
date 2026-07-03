<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (is_authenticated()) {
    header('Location: ' . url('/dashboard.php'));
    exit;
}

$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        flash('error', 'درخواست نامعتبر. دوباره تلاش کنید.');
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $password2 = (string) ($_POST['password2'] ?? '');

        if ($password !== $password2) {
            flash('error', 'رمز عبور و تکرار آن یکسان نیستند.');
        } else {
            $result = register_user($email, $password);
            if ($result['success']) {
                login_user($result['id'], $result['data_key']);
                flash('success', 'ثبت‌نام با موفقیت انجام شد. حالا اکانت کلادفلر خودتان را اضافه کنید.');
                header('Location: ' . url('/dashboard.php'));
                exit;
            }
            flash('error', $result['message']);
        }
    }
}

$pageTitle = 'ثبت‌نام';
$containerClass = 'container-narrow';
require __DIR__ . '/includes/layout_header.php';
?>
<div class="auth-shell" style="min-height:auto;padding-top:40px;">
<div class="auth-card">
    <div class="auth-logo">
        <div class="dot"><?= icon('cloud', 'icon-lg') ?></div>
        <h2 style="margin:0;">ساخت حساب کاربری</h2>
        <p class="muted text-sm">مخصوص کاربرانی که نمی‌توانند از اپ اندروید استفاده کنند</p>
    </div>
    <form method="post" class="card">
        <?= csrf_field() ?>
        <div class="field">
            <label>ایمیل</label>
            <input type="email" name="email" value="<?= h($email) ?>" required autofocus placeholder="you@example.com">
        </div>
        <div class="field">
            <label>رمز عبور (حداقل ۶ کاراکتر)</label>
            <input type="password" name="password" required minlength="6" placeholder="••••••••">
        </div>
        <div class="field">
            <label>تکرار رمز عبور</label>
            <input type="password" name="password2" required minlength="6" placeholder="••••••••">
        </div>
        <button class="btn btn-primary btn-block" type="submit"><?= icon('person_add') ?> ثبت‌نام</button>
        <p class="text-sm muted" style="text-align:center;margin-bottom:0;margin-top:16px;">
            قبلاً ثبت‌نام کرده‌اید؟ <a href="<?= url('/login.php') ?>">ورود</a>
        </p>
    </form>
</div>
</div>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>
