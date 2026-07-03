<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (is_authenticated()) {
    header('Location: ' . url('/dashboard.php'));
    exit;
}

$pageTitle = 'پنل ابری';
require __DIR__ . '/includes/layout_header.php';
?>
<div class="card" style="text-align:center;padding:52px 28px;">
    <div style="width:64px;height:64px;border-radius:18px;background:var(--primary-soft);color:var(--primary);display:inline-flex;align-items:center;justify-content:center;margin-bottom:18px;">
        <?= icon('cloud_sync', 'icon-lg') ?>
    </div>
    <h1 style="margin:0 0 12px;font-size:24px;">دریافت کانفیگ اختصاصی، بدون نیاز به اپ اندروید</h1>
    <p class="muted" style="max-width:540px;margin:0 auto 28px;line-height:2;font-size:14.5px;">
        اگر آیفون دارید و نمی‌توانید از اپ اندروید استفاده کنید، از همین‌جا با اکانت کلادفلر شخصی خودتان
        (ایمیل + Global API Key) پنل‌های اختصاصی‌تان را بسازید و کانفیگ‌های VLESS/Trojan را برای اپ‌های
        iOS مثل Shadowrocket، Streisand یا V2Box دریافت کنید.
    </p>
    <div class="flex gap-12" style="justify-content:center;">
        <a class="btn btn-primary" href="<?= url('/register.php') ?>"><?= icon('rocket_launch') ?> شروع کنید — ثبت‌نام رایگان</a>
        <a class="btn btn-secondary" href="<?= url('/login.php') ?>">قبلاً حساب دارم</a>
    </div>
</div>

<div class="grid-2">
    <?php
    $features = [
        ['link', 'اکانت کلادفلر رایگانتان را وصل کنید', 'فقط با ایمیل و Global API Key که از داشبورد Cloudflare می‌گیرید.'],
        ['bolt', 'پنل‌ها را با یک کلیک دیپلوی کنید', 'هر ۴ نوع پنل (BPB, EDG, Nahan, MLM) روی اکانت خودتان بالا می‌آید.'],
        ['content_paste', 'کانفیگ‌ها را دریافت کنید', 'کپی یک‌کلیکی یا لینک اشتراک ترکیبی برای ایمپورت راحت در اپ‌های آیفون.'],
        ['verified_user', 'کاملاً مستقل و شخصی', 'همه‌چیز روی اکانت کلادفلر خودتان اجرا می‌شود؛ داده‌هایتان بین کاربران دیگر مشترک نیست.'],
    ];
    foreach ($features as $f): ?>
    <div class="card">
        <div class="flex gap-12" style="align-items:flex-start;">
            <div style="width:38px;height:38px;flex-shrink:0;border-radius:11px;background:var(--surface-2);color:var(--primary);display:flex;align-items:center;justify-content:center;">
                <?= icon($f[0]) ?>
            </div>
            <div>
                <div class="card-title" style="margin-bottom:6px;"><?= h($f[1]) ?></div>
                <p class="muted text-sm mb-0"><?= h($f[2]) ?></p>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>
