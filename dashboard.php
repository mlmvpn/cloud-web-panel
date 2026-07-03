<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$userId = current_user_id();
$accounts = list_user_accounts($userId);

function cw_engine_label(string $label) {
    echo '<div class="text-sm dim" style="padding:10px 18px 0;font-weight:600;">' . h($label) . '</div>';
}

$pageTitle = 'اکانت‌های کلادفلر';
$activeNav = 'dashboard';
require __DIR__ . '/includes/layout_header.php';
?>

<div class="flex-between" style="margin-bottom:18px;">
    <div>
        <h2 style="margin:0;font-size:19px;">اکانت‌های کلادفلر شما</h2>
        <p class="text-sm dim" style="margin:4px 0 0;">هر اکانت روی زیرساخت کلادفلر شخصی خودتان اجرا می‌شود.</p>
    </div>
    <button class="btn btn-primary btn-sm" onclick="document.getElementById('addAccModal').classList.add('open')"><?= icon('add') ?> افزودن اکانت</button>
</div>

<?php if (!$accounts): ?>
    <div class="empty-state card">
        <?= icon('cloud_off', 'icon') ?>
        <p>هنوز هیچ اکانت کلادفلری اضافه نکرده‌اید.</p>
        <button class="btn btn-primary" style="margin-top:10px;" onclick="document.getElementById('addAccModal').classList.add('open')"><?= icon('add') ?> افزودن اولین اکانت</button>
    </div>
<?php endif; ?>

<?php foreach ($accounts as $acc):
    $locked = !$acc['has_subdomain'];
    $deployedCount = (int) ($acc['status'] === 'deployed') + (int) ($acc['edg_status'] === 'deployed') + (int) ($acc['nahan_status'] === 'deployed') + (int) ($acc['mlm_status'] === 'deployed');
?>
<div class="card" style="padding:0;overflow:hidden;">
    <div class="flex-between" style="padding:18px;">
        <div class="flex gap-12">
            <div style="width:38px;height:38px;border-radius:12px;background:var(--surface-2);border:1px solid var(--border-dark);display:flex;align-items:center;justify-content:center;color:var(--primary);flex-shrink:0;">
                <?= icon('cloud') ?>
            </div>
            <div>
                <div style="font-weight:600;font-size:14px;"><?= h($acc['email'] ?: $acc['name']) ?></div>
                <div class="flex gap-8" style="margin-top:4px;">
                    <span class="text-sm dim mono" style="direction:ltr;text-align:left;">API: <?= h(mask_token(account_auth_token($acc))) ?></span>
                    <?php if (!$locked): ?>
                        <span class="badge <?= $deployedCount > 0 ? 'ok' : 'idle' ?>"><span class="badge-dot"></span><?= $deployedCount ?>/۴ پنل نصب‌شده</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <button class="btn btn-ghost btn-sm" data-action="delete_account" data-account="<?= (int) $acc['id'] ?>" title="حذف اکانت"><?= icon('delete') ?></button>
    </div>

    <?php if ($locked): ?>
        <div style="padding:28px 18px;text-align:center;background:var(--surface-2);border-top:1px solid var(--border-dark);">
            <div style="color:var(--primary);margin-bottom:10px;"><?= icon('dns', 'icon-lg') ?></div>
            <p class="text-sm muted" style="margin-bottom:14px;">برای دیپلوی، باید یک ساب‌دامین workers.dev بسازید (یک‌بار، خودکار).</p>
            <div class="flex gap-8" style="justify-content:center;flex-wrap:wrap;">
                <button class="btn btn-primary btn-sm" data-action="create_subdomain" data-account="<?= (int) $acc['id'] ?>"><?= icon('add_link') ?> ساخت ساب‌دامین</button>
                <button class="btn btn-secondary btn-sm" data-action="check_status" data-account="<?= (int) $acc['id'] ?>"><?= icon('refresh') ?> بررسی مجدد</button>
            </div>
        </div>
    <?php else: ?>
        <?php cw_engine_label('BPB'); ?>
        <div class="btn-group">
            <button class="btn-cell" data-action="deploy_bpb" data-account="<?= (int) $acc['id'] ?>" <?= $acc['status'] === 'deployed' ? 'disabled' : '' ?>>
                <?= icon($acc['status'] === 'deployed' ? 'check_circle' : 'play_arrow') ?>
                <?= $acc['status'] === 'deployed' ? 'نصب شده' : 'نصب BPB' ?>
            </button>
            <button class="btn-cell" data-action="fetch_bpb" data-account="<?= (int) $acc['id'] ?>" <?= $acc['status'] === 'deployed' ? '' : 'disabled' ?>>
                <?= icon('download') ?> دریافت کانفیگ
            </button>
            <a class="btn-cell" style="text-decoration:none;<?= $acc['status'] === 'deployed' ? '' : 'pointer-events:none;color:var(--text-dim);' ?>" href="<?= url('/settings_bpb.php?account_id=' . (int) $acc['id']) ?>">
                <?= icon('tune') ?> تنظیمات
            </a>
        </div>

        <?php cw_engine_label('EDG'); ?>
        <div class="btn-group">
            <button class="btn-cell" data-action="deploy_edg" data-account="<?= (int) $acc['id'] ?>" <?= $acc['edg_status'] === 'deployed' ? 'disabled' : '' ?>>
                <?= icon($acc['edg_status'] === 'deployed' ? 'check_circle' : 'play_arrow') ?>
                <?= $acc['edg_status'] === 'deployed' ? 'نصب شده' : 'نصب EDG' ?>
            </button>
            <button class="btn-cell" data-action="fetch_edg" data-account="<?= (int) $acc['id'] ?>" <?= $acc['edg_status'] === 'deployed' ? '' : 'disabled' ?>>
                <?= icon('download') ?> دریافت کانفیگ
            </button>
            <a class="btn-cell" style="text-decoration:none;<?= $acc['edg_status'] === 'deployed' ? '' : 'pointer-events:none;color:var(--text-dim);' ?>" href="<?= url('/settings_edg.php?account_id=' . (int) $acc['id']) ?>">
                <?= icon('tune') ?> تنظیمات
            </a>
        </div>

        <?php cw_engine_label('Nahan'); ?>
        <div class="btn-group">
            <button class="btn-cell" data-action="deploy_nahan" data-account="<?= (int) $acc['id'] ?>" <?= $acc['nahan_status'] === 'deployed' ? 'disabled' : '' ?>>
                <?= icon($acc['nahan_status'] === 'deployed' ? 'check_circle' : 'play_arrow') ?>
                <?= $acc['nahan_status'] === 'deployed' ? 'نصب شده' : 'نصب Nahan' ?>
            </button>
            <button class="btn-cell" data-action="fetch_nahan" data-account="<?= (int) $acc['id'] ?>" <?= $acc['nahan_status'] === 'deployed' ? '' : 'disabled' ?>>
                <?= icon('download') ?> کانفیگ ادمین
            </button>
            <a class="btn-cell" style="text-decoration:none;<?= $acc['nahan_status'] === 'deployed' ? '' : 'pointer-events:none;color:var(--text-dim);' ?>" href="<?= url('/settings_nahan.php?account_id=' . (int) $acc['id']) ?>">
                <?= icon('tune') ?> تنظیمات و کاربران
            </a>
        </div>

        <?php cw_engine_label('MLM'); ?>
        <div class="btn-group">
            <button class="btn-cell" data-action="deploy_mlm" data-account="<?= (int) $acc['id'] ?>" <?= $acc['mlm_status'] === 'deployed' ? 'disabled' : '' ?>>
                <?= icon($acc['mlm_status'] === 'deployed' ? 'check_circle' : 'play_arrow') ?>
                <?= $acc['mlm_status'] === 'deployed' ? 'نصب شده' : 'نصب MLM' ?>
            </button>
            <a class="btn-cell" style="text-decoration:none;<?= $acc['mlm_status'] === 'deployed' ? '' : 'pointer-events:none;color:var(--text-dim);' ?>" href="<?= url('/mlm_users.php?account_id=' . (int) $acc['id']) ?>">
                <?= icon('group') ?> کاربران
            </a>
            <a class="btn-cell" style="text-decoration:none;<?= $acc['mlm_status'] === 'deployed' ? '' : 'pointer-events:none;color:var(--text-dim);' ?>" href="<?= url('/settings_mlm.php?account_id=' . (int) $acc['id']) ?>">
                <?= icon('tune') ?> تنظیمات
            </a>
        </div>

        <?php cw_engine_label('Zeus'); ?>
        <div class="btn-group">
            <button class="btn-cell" data-action="deploy_zeus" data-account="<?= (int) $acc['id'] ?>" data-timeout="60000" <?= $acc['zeus_status'] === 'deployed' ? 'disabled' : '' ?>>
                <?= icon($acc['zeus_status'] === 'deployed' ? 'check_circle' : 'play_arrow') ?>
                <?= $acc['zeus_status'] === 'deployed' ? 'نصب شده' : 'نصب Zeus' ?>
            </button>
            <a class="btn-cell" style="text-decoration:none;<?= $acc['zeus_status'] === 'deployed' ? '' : 'pointer-events:none;color:var(--text-dim);' ?>" href="<?= url('/zeus_users.php?account_id=' . (int) $acc['id']) ?>">
                <?= icon('group') ?> کاربران
            </a>
            <a class="btn-cell" style="text-decoration:none;<?= $acc['zeus_status'] === 'deployed' ? '' : 'pointer-events:none;color:var(--text-dim);' ?>" href="<?= url('/settings_zeus.php?account_id=' . (int) $acc['id']) ?>">
                <?= icon('tune') ?> تنظیمات
            </a>
        </div>

        <div class="btn-cell" style="border-top:1px solid var(--border-dark);text-align:center;padding:12px;" data-action="usage" data-account="<?= (int) $acc['id'] ?>">
            <?= icon('monitoring') ?> مصرف روزانه
        </div>
        <div id="usage-<?= (int) $acc['id'] ?>" style="display:none;padding:14px 18px;background:var(--surface-2);border-top:1px solid var(--border-dark);"></div>
    <?php endif; ?>
</div>
<?php endforeach; ?>

<!-- Add Account Modal -->
<div class="modal-backdrop" id="addAccModal">
    <div class="modal-box">
        <div class="flex-between" style="margin-bottom:16px;">
            <h3 style="margin:0;font-size:16px;"><?= icon('add_circle', 'icon-sm') ?> افزودن اکانت کلادفلر</h3>
            <button class="btn btn-ghost btn-sm" onclick="document.getElementById('addAccModal').classList.remove('open')"><?= icon('close') ?></button>
        </div>
        <div class="field">
            <label>ایمیل حساب Cloudflare</label>
            <input type="email" id="acc_email" placeholder="you@example.com">
        </div>
        <div class="field">
            <label>Global API Key</label>
            <input type="text" id="acc_token" placeholder="از داشبورد کلادفلر → My Profile → API Tokens">
            <p class="help-text">
                برای دریافت کلید، وارد صفحهٔ API Tokens اکانت کلادفلر خودتان شوید و بخش Global API Key را View کنید.
            </p>
            <a class="btn btn-secondary btn-sm" style="margin-top:8px;" href="https://dash.cloudflare.com/profile/api-tokens" target="_blank" rel="noopener"><?= icon('open_in_new') ?> رفتن به صفحهٔ دریافت Global API Key</a>
        </div>
        <button class="btn btn-primary btn-block" id="addAccBtn"><?= icon('link') ?> ذخیره و اتصال</button>
    </div>
</div>

<script>
document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-action]');
    if (!btn) return;
    var action = btn.getAttribute('data-action');
    var accountId = btn.getAttribute('data-account');
    var endpoints = {
        deploy_bpb: '/api/deploy_bpb.php',
        fetch_bpb: '/api/fetch_bpb.php',
        deploy_edg: '/api/deploy_edg.php',
        fetch_edg: '/api/fetch_edg.php',
        deploy_nahan: '/api/deploy_nahan.php',
        fetch_nahan: '/api/fetch_nahan.php',
        deploy_mlm: '/api/deploy_mlm.php',
        deploy_zeus: '/api/deploy_zeus.php',
        create_subdomain: '/api/create_subdomain.php',
        check_status: '/api/check_status.php',
    };

    if (action === 'delete_account') {
        if (!confirm('این اکانت کلادفلر و همهٔ کانفیگ‌های ذخیره‌شدهٔ آن حذف شود؟')) return;
        CWP.runAction(btn, '/api/delete_account.php', { account_id: accountId });
        return;
    }

    if (action === 'usage') {
        var box = document.getElementById('usage-' + accountId);
        if (box.style.display === 'block') { box.style.display = 'none'; return; }
        box.style.display = 'block';
        box.innerHTML = '<span class="spinner"></span> در حال دریافت...';
        CWP.apiPost('/api/usage.php', { account_id: accountId }).then(function (res) {
            var body = res.body || {};
            if (body.success) {
                box.innerHTML = '<span class="muted text-sm">مصرف امروز: </span><b>' + body.requests.toLocaleString() + ' / 100,000</b> درخواست رایگان';
            } else {
                box.innerHTML = '<span class="text-sm" style="color:var(--red-error)">' + (body.message || 'خطا در دریافت مصرف') + '</span>';
            }
        });
        return;
    }

    var endpoint = endpoints[action];
    if (endpoint) {
        var timeoutAttr = btn.getAttribute('data-timeout');
        CWP.runAction(btn, endpoint, { account_id: accountId }, timeoutAttr ? { timeoutMs: parseInt(timeoutAttr, 10) } : undefined);
    }
});

document.getElementById('addAccBtn').addEventListener('click', function () {
    var email = document.getElementById('acc_email').value.trim();
    var token = document.getElementById('acc_token').value.trim();
    if (!token) { CWP.toast('Global API Key را وارد کنید', 'error'); return; }
    CWP.runAction(this, '/api/add_account.php', { email: email, token: token });
});
</script>

<?php require __DIR__ . '/includes/layout_footer.php'; ?>
