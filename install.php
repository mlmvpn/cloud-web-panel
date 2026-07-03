<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

function h($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$configPath = __DIR__ . '/config.php';
$alreadyInstalled = file_exists($configPath);
$forceReinstall = isset($_POST['force_reinstall']) && $_POST['force_reinstall'] === 'RESET';

$errors = [];
$success = false;
$manualConfig = null;

$old = [
    'db_host' => $_POST['db_host'] ?? 'localhost',
    'db_name' => $_POST['db_name'] ?? '',
    'db_user' => $_POST['db_user'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!$alreadyInstalled || $forceReinstall)) {
    $dbHost = trim($_POST['db_host'] ?? '');
    $dbName = trim($_POST['db_name'] ?? '');
    $dbUser = trim($_POST['db_user'] ?? '');
    $dbPass = (string) ($_POST['db_pass'] ?? '');

    if ($dbHost === '' || $dbName === '' || $dbUser === '') {
        $errors[] = 'لطفاً همهٔ فیلدهای دیتابیس (به‌جز رمز که می‌تواند خالی باشد) را پر کنید.';
    }

    $pdo = null;
    if (!$errors) {
        try {
            $pdo = new PDO('mysql:host=' . $dbHost . ';charset=utf8mb4', $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', $dbName) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $pdo->exec('USE `' . str_replace('`', '', $dbName) . '`');
        } catch (Throwable $e) {
            $errors[] = 'اتصال به MySQL ناموفق بود: ' . $e->getMessage();
        }
    }

    if (!$errors && $pdo) {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(190) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                kdf_salt VARCHAR(32) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $pdo->exec("CREATE TABLE IF NOT EXISTS cloud_accounts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                email VARCHAR(190) NOT NULL DEFAULT '',
                token TEXT NOT NULL,
                name VARCHAR(190) NOT NULL DEFAULT '',
                account_id VARCHAR(64) NOT NULL DEFAULT '',
                status VARCHAR(32) NOT NULL DEFAULT 'active',
                added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                is_email_verified TINYINT(1) NOT NULL DEFAULT 1,
                has_subdomain TINYINT(1) NOT NULL DEFAULT 0,
                worker_url VARCHAR(255) DEFAULT NULL,
                uuid VARCHAR(64) DEFAULT NULL,
                tr_pass VARCHAR(64) DEFAULT NULL,
                sub_path VARCHAR(64) DEFAULT NULL,
                edg_worker_url VARCHAR(255) DEFAULT NULL,
                edg_uuid VARCHAR(64) DEFAULT NULL,
                edg_admin_pass VARCHAR(64) DEFAULT NULL,
                edg_kv_namespace_id VARCHAR(64) DEFAULT NULL,
                edg_status VARCHAR(32) NOT NULL DEFAULT 'idle',
                nahan_worker_url VARCHAR(255) DEFAULT NULL,
                nahan_db_id VARCHAR(64) DEFAULT NULL,
                nahan_api_route VARCHAR(64) NOT NULL DEFAULT 'sync',
                nahan_master_key VARCHAR(190) DEFAULT NULL,
                nahan_status VARCHAR(32) NOT NULL DEFAULT 'idle',
                mlm_worker_url VARCHAR(255) DEFAULT NULL,
                mlm_db_id VARCHAR(64) DEFAULT NULL,
                mlm_admin_password VARCHAR(190) DEFAULT NULL,
                mlm_status VARCHAR(32) NOT NULL DEFAULT 'idle',
                auth_type VARCHAR(16) NOT NULL DEFAULT 'key',
                oauth_refresh_token TEXT DEFAULT NULL,
                oauth_expires_at DATETIME DEFAULT NULL,
                KEY idx_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $pdo->exec("CREATE TABLE IF NOT EXISTS cloud_groups (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                account_id INT NOT NULL,
                engine_type VARCHAR(16) NOT NULL DEFAULT 'BPB',
                title VARCHAR(190) NOT NULL DEFAULT '',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_user (user_id),
                KEY idx_account (account_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $pdo->exec("CREATE TABLE IF NOT EXISTS cloud_group_nodes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                group_id INT NOT NULL,
                name VARCHAR(255) NOT NULL DEFAULT '',
                uri TEXT NOT NULL,
                type VARCHAR(16) NOT NULL DEFAULT 'vless',
                engine_type VARCHAR(16) NOT NULL DEFAULT 'BPB',
                KEY idx_group (group_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $pdo->exec("CREATE TABLE IF NOT EXISTS clean_ips (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ip VARCHAR(64) NOT NULL,
                label VARCHAR(190) NOT NULL DEFAULT '',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (Throwable $e) {
            $errors[] = 'ساخت جداول با خطا مواجه شد: ' . $e->getMessage();
        }
    }

    if (!$errors) {
        $encKey = bin2hex(random_bytes(32));
        $configContents = "<?php\n" .
            "// این فایل به‌صورت خودکار توسط install.php ساخته شده — دستی ویرایشش نکنید مگر بدانید چه‌کار می‌کنید.\n" .
            "// تاریخ نصب: " . date('Y-m-d H:i:s') . "\n" .
            "define('DB_HOST', " . var_export($dbHost, true) . ");\n" .
            "define('DB_NAME', " . var_export($dbName, true) . ");\n" .
            "define('DB_USER', " . var_export($dbUser, true) . ");\n" .
            "define('DB_PASS', " . var_export($dbPass, true) . ");\n" .
            "define('ENCRYPTION_KEY', " . var_export($encKey, true) . ");\n";

        if (is_writable(__DIR__) && (!file_exists($configPath) || is_writable($configPath))) {
            file_put_contents($configPath, $configContents, LOCK_EX);
            $success = true;
        } else {
            $manualConfig = $configContents;
        }
    }
}

$phpChecks = [
    'PHP >= 7.4' => version_compare(PHP_VERSION, '7.4.0', '>='),
    'افزونهٔ curl' => extension_loaded('curl'),
    'افزونهٔ openssl' => extension_loaded('openssl'),
    'افزونهٔ pdo_mysql' => extension_loaded('pdo_mysql'),
    'افزونهٔ mbstring' => extension_loaded('mbstring'),
    'افزونهٔ json' => extension_loaded('json'),
];
$allChecksPass = !in_array(false, $phpChecks, true);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<title>نصب پنل ابری</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="container-narrow" style="padding-top:40px;">
    <div class="auth-logo">
        <div class="dot"><span class="material-symbols-outlined icon-lg" aria-hidden="true">cloud</span></div>
        <h2 style="margin:0;">نصب پنل ابری</h2>
        <p class="muted text-sm">راه‌اندازی اولیهٔ دیتابیس و تنظیمات سایت</p>
    </div>

    <?php if ($success): ?>
        <div class="card">
            <div class="alert alert-success">
                نصب با موفقیت انجام شد و فایل <code>config.php</code> ساخته شد.
            </div>
            <div class="alert alert-warning">
                برای امنیت، همین الان فایل <code>install.php</code> را از روی هاست حذف یا تغییرنام دهید تا کسی نتواند دوباره نصب را اجرا کند.
            </div>
            <a class="btn btn-primary btn-block" href="register.php"><span class="material-symbols-outlined" aria-hidden="true">arrow_back</span> رفتن به صفحهٔ ثبت‌نام</a>
        </div>
    <?php elseif ($manualConfig): ?>
        <div class="card">
            <div class="alert alert-warning">
                دیتابیس و جداول با موفقیت ساخته شدند، اما پوشهٔ سایت قابل‌نوشتن نیست تا <code>config.php</code> خودکار ساخته شود.
                محتوای زیر را کپی کنید و با نام <code>config.php</code> در ریشهٔ همین پوشه آپلود کنید، سپس این صفحه را رفرش کنید.
            </div>
            <textarea readonly rows="10" style="font-family:monospace;direction:ltr;text-align:left;"><?= h($manualConfig) ?></textarea>
        </div>
    <?php else: ?>

        <div class="card">
            <div class="card-title" style="margin-bottom:10px;">بررسی سازگاری سرور</div>
            <?php foreach ($phpChecks as $label => $ok): ?>
                <div class="flex-between text-sm" style="padding:5px 0;">
                    <span><?= h($label) ?></span>
                    <span class="badge <?= $ok ? 'ok' : '' ?>" style="<?= $ok ? '' : 'color:var(--red-error)' ?>">
                        <span class="badge-dot"></span><?= $ok ? 'موجود' : 'موجود نیست' ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (!$allChecksPass): ?>
            <div class="alert alert-error">برخی افزونه‌های موردنیاز PHP روی این هاست فعال نیست. با پشتیبانی هاست هماهنگ کنید تا فعال شوند، سپس دوباره تلاش کنید.</div>
        <?php endif; ?>

        <?php if ($alreadyInstalled && !$forceReinstall): ?>
            <div class="alert alert-warning">
                به نظر می‌رسد این سایت قبلاً نصب شده (فایل <code>config.php</code> موجود است).
                اجرای دوباره نصب، دیتابیس فعلی را عوض نمی‌کند مگر با گزینهٔ زیر تأیید کنید — با این‌حال بهتر است بدون نیاز واقعی این کار را نکنید.
            </div>
            <form method="post">
                <div class="field">
                    <label>برای ادامه، عبارت <code>RESET</code> را تایپ کنید</label>
                    <input type="text" name="force_reinstall" placeholder="RESET">
                </div>
                <button class="btn btn-secondary btn-block" type="submit">تلاش دوباره برای نصب</button>
            </form>
        <?php else: ?>

            <?php foreach ($errors as $err): ?>
                <div class="alert alert-error"><?= h($err) ?></div>
            <?php endforeach; ?>

            <form method="post" class="card">
                <div class="card-title" style="margin-bottom:14px;">اطلاعات دیتابیس MySQL</div>
                <p class="text-sm muted" style="margin-top:-6px;margin-bottom:16px;">این اطلاعات را از پنل کنترل هاست خود (مثلاً بخش MySQL Databases در InfinityFree) بردارید.</p>

                <div class="field">
                    <label>MySQL Host</label>
                    <input type="text" name="db_host" value="<?= h($old['db_host']) ?>" placeholder="sqlXXX.infinityfree.com" required>
                </div>
                <div class="field">
                    <label>نام دیتابیس</label>
                    <input type="text" name="db_name" value="<?= h($old['db_name']) ?>" placeholder="if0_XXXXXXX_cloudpanel" required>
                </div>
                <div class="field">
                    <label>یوزرنیم دیتابیس</label>
                    <input type="text" name="db_user" value="<?= h($old['db_user']) ?>" placeholder="if0_XXXXXXX" required>
                </div>
                <div class="field">
                    <label>پسورد دیتابیس</label>
                    <input type="password" name="db_pass" placeholder="••••••••">
                </div>
                <?php if ($forceReinstall): ?><input type="hidden" name="force_reinstall" value="RESET"><?php endif; ?>
                <button class="btn btn-primary btn-block" type="submit" <?= $allChecksPass ? '' : 'disabled' ?>><span class="material-symbols-outlined" aria-hidden="true">rocket_launch</span> نصب و ساخت جداول</button>
            </form>
        <?php endif; ?>
    <?php endif; ?>

    <p class="footer-note">Cloud Web Panel — نصب مستقل از اپ اندروید</p>
</div>
</body>
</html>
