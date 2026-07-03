<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$userId = current_user_id();
$accountId = (int) ($_GET['account_id'] ?? 0);
$account = get_account_for_user($accountId, $userId);
if (!$account) {
    flash('error', 'اکانت یافت نشد.');
    header('Location: ' . url('/dashboard.php'));
    exit;
}

$pageTitle = 'تنظیمات Zeus';
require __DIR__ . '/includes/layout_header.php';
?>

<div class="flex gap-8" style="margin-bottom:16px;">
    <a class="btn btn-ghost btn-sm" href="<?= url('/dashboard.php') ?>"><?= icon('arrow_forward') ?></a>
    <h2 style="margin:0;font-size:17px;"><?= icon('tune', 'icon-sm') ?> تنظیمات سراسری پنل Zeus</h2>
</div>
<div class="text-sm muted" style="margin-bottom:14px;">
    برای مدیریت کاربران این پنل به <a href="<?= url('/zeus_users.php?account_id=' . (int) $accountId) ?>">صفحهٔ کاربران Zeus</a> بروید.
</div>

<div id="loadingBox" class="card" style="text-align:center;"><span class="spinner"></span> در حال دریافت تنظیمات...</div>
<div id="errorBox" class="alert alert-error" style="display:none;"></div>

<form id="settingsForm" class="card" style="display:none;">
    <div class="field">
        <label>Proxy IP</label>
        <input type="text" id="proxyIp">
    </div>
    <div class="field">
        <label>موقعیت (IATA)</label>
        <input type="text" id="iata" placeholder="مثلاً FRA">
    </div>
    <div class="field">
        <label>Fragment Length (مثلاً 20-30)</label>
        <input type="text" id="fragLen">
    </div>
    <div class="field">
        <label>Fragment Interval (مثلاً 1-2)</label>
        <input type="text" id="fragInt">
    </div>
    <button type="submit" class="btn btn-primary btn-block" id="saveBtn"><?= icon('save') ?> ذخیره</button>
</form>

<div class="card" style="border-color:var(--red-error);">
    <div class="card-title" style="color:var(--red-error);"><?= icon('warning', 'icon-sm') ?> منطقهٔ خطر</div>
    <p class="text-sm muted" style="margin-top:4px;">حذف دیپلوی، Worker و دیتابیس Zeus این اکانت را از Cloudflare پاک می‌کند و همهٔ کاربران/کانفیگ‌های ساخته‌شده در آن برای همیشه از بین می‌رود. بعد از حذف می‌توانید از داشبورد دوباره نصب کنید.</p>
    <button class="btn btn-ghost btn-sm" style="color:var(--red-error);margin-top:8px;" id="removeDeployBtn"><?= icon('delete') ?> حذف دیپلوی Zeus</button>
</div>

<script>
var ACCOUNT_ID = <?= (int) $accountId ?>;

document.getElementById('removeDeployBtn').addEventListener('click', function () {
    if (!confirm('دیپلوی Zeus این اکانت به‌طور کامل حذف شود؟ این کار غیرقابل‌بازگشت است و همهٔ کاربران این پنل از بین می‌روند.')) return;
    CWP.runAction(this, '/api/remove_deploy_zeus.php', { account_id: ACCOUNT_ID }, { reload: false, onSuccess: function () { window.location.href = CWP.url('/dashboard.php'); } });
});
CWP.apiPost('/api/zeus_settings_fetch.php', { account_id: ACCOUNT_ID }).then(function (res) {
    document.getElementById('loadingBox').style.display = 'none';
    var body = res.body || {};
    if (!body.success) {
        var box = document.getElementById('errorBox');
        box.style.display = 'block';
        box.textContent = body.message || 'دریافت تنظیمات ناموفق بود.';
        return;
    }
    var s = body.settings || {};
    document.getElementById('proxyIp').value = s.proxy_ip || '';
    document.getElementById('iata').value = s.iata || '';
    document.getElementById('fragLen').value = s.frag_len || '';
    document.getElementById('fragInt').value = s.frag_int || '';
    document.getElementById('settingsForm').style.display = 'block';
});

document.getElementById('settingsForm').addEventListener('submit', function (e) {
    e.preventDefault();
    CWP.runAction(document.getElementById('saveBtn'), '/api/zeus_settings_save.php', {
        account_id: ACCOUNT_ID,
        proxy_ip: document.getElementById('proxyIp').value.trim(),
        iata: document.getElementById('iata').value.trim(),
        frag_len: document.getElementById('fragLen').value.trim(),
        frag_int: document.getElementById('fragInt').value.trim(),
    }, { reload: false, onSuccess: function () { window.location.href = CWP.url('/dashboard.php'); } });
});
</script>

<?php require __DIR__ . '/includes/layout_footer.php'; ?>
