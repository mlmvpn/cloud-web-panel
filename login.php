<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (is_authenticated()) {
    header('Location: ' . url('/dashboard.php'));
    exit;
}

$returnTo = $_GET['return'] ?? url('/dashboard.php');
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        flash('error', 'درخواست نامعتبر. دوباره تلاش کنید.');
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $user = attempt_login($email, $password);
        if ($user) {
            $dataKey = ensure_user_data_key($user, $password);
            login_user((int) $user['id'], $dataKey);
            header('Location: ' . ($_POST['return'] ?: url('/dashboard.php')));
            exit;
        }
        flash('error', 'ایمیل یا رمز عبور اشتباه است.');
    }
}

$pageTitle = 'ورود';
$containerClass = 'container-narrow';
require __DIR__ . '/includes/layout_header.php';
?>
<div class="auth-shell" style="min-height:auto;padding-top:40px;">
<div class="auth-card">
    <div class="auth-logo">
        <div class="dot"><?= icon('cloud', 'icon-lg') ?></div>
        <h2 style="margin:0;">ورود به پنل ابری</h2>
        <p class="muted text-sm">برای دریافت کانفیگ وارد حساب خود شوید</p>
    </div>
    <form method="post" class="card">
        <?= csrf_field() ?>
        <input type="hidden" name="return" value="<?= h($returnTo) ?>">
        <div class="field">
            <label>ایمیل</label>
            <input type="email" name="email" value="<?= h($email) ?>" required autofocus placeholder="you@example.com">
        </div>
        <div class="field">
            <label>رمز عبور</label>
            <input type="password" name="password" required placeholder="••••••••">
        </div>
        <button class="btn btn-primary btn-block" type="submit"><?= icon('login') ?> ورود</button>
        <p class="text-sm muted" style="text-align:center;margin-bottom:0;margin-top:16px;">
            حساب ندارید؟ <a href="<?= url('/register.php') ?>">ثبت‌نام</a>
        </p>
    </form>
</div>
</div>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>
