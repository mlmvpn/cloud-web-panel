<?php
$__user = current_user();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<title><?= h($pageTitle ?? 'پنل ابری') ?></title>
<meta name="csrf-token" content="<?= h(csrf_token()) ?>">
<meta name="app-base" content="<?= h(url()) ?>">
<link rel="preconnect" href="https://fonts.bunny.net">
<link rel="stylesheet" href="<?= url('/assets/css/style.css') ?>?v=<?= asset_version() ?>">
<script src="<?= url('/assets/js/app.js') ?>?v=<?= asset_version() ?>"></script>
</head>
<body>
<div class="navbar">
    <a class="brand" href="<?= url($__user ? '/dashboard.php' : '/index.php') ?>">
        <span class="dot"><?= icon('cloud') ?></span> <span>پنل ابری</span>
    </a>
    <nav>
        <?php if ($__user): ?>
            <a href="<?= url('/dashboard.php') ?>" class="<?= ($activeNav ?? '') === 'dashboard' ? 'active' : '' ?>"><?= icon('dashboard') ?><span>اکانت‌ها</span></a>
            <a href="<?= url('/groups.php') ?>" class="<?= ($activeNav ?? '') === 'groups' ? 'active' : '' ?>"><?= icon('dns') ?><span>کانفیگ‌ها</span></a>
            <div class="dropdown">
                <a href="javascript:void(0);" class="<?= in_array($activeNav ?? '', ['cf_workers', 'cf_databases', 'cf_kvs']) ? 'active' : '' ?>">
                    <?= icon('info') ?><span>اطلاعات</span>
                </a>
                <div class="dropdown-content">
                    <a href="<?= url('/cf_workers.php') ?>"><?= icon('cloud_sync') ?> ورکرها</a>
                    <a href="<?= url('/cf_databases.php') ?>"><?= icon('database') ?> دیتابیس‌ها</a>
                    <a href="<?= url('/cf_kvs.php') ?>"><?= icon('sd_storage') ?> فضاهای KV</a>
                </div>
            </div>
            <?php if (current_user_is_admin()): ?>
                <a href="<?= url('/stats.php') ?>" class="<?= ($activeNav ?? '') === 'stats' ? 'active' : '' ?>"><?= icon('monitoring') ?><span>آمار</span></a>
                <a href="<?= url('/admin_clean_ips.php') ?>" class="<?= ($activeNav ?? '') === 'admin_clean_ips' ? 'active' : '' ?>"><?= icon('shield') ?><span>IP تمیز</span></a>
                <a href="<?= url('/admin_logs.php') ?>" class="<?= ($activeNav ?? '') === 'admin_logs' ? 'active' : '' ?>"><?= icon('monitoring') ?><span>گزارش خطاها</span></a>
            <?php endif; ?>
            <a href="<?= url('/logout.php') ?>" style="margin-inline-start: auto; color: var(--red-error);"><?= icon('logout') ?><span>خروج</span></a>
        <?php else: ?>
            <a href="<?= url('/login.php') ?>"><?= icon('login') ?><span>ورود</span></a>
            <a href="<?= url('/register.php') ?>"><?= icon('person_add') ?><span>ثبت‌نام</span></a>
        <?php endif; ?>
    </nav>
</div>
<div class="<?= h($containerClass ?? 'container') ?>">
<?php foreach (get_flashes() as $__f): ?>
    <?= alert_box($__f['type'], $__f['message']) ?>
<?php endforeach; ?>
