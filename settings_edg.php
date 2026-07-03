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

$pageTitle = 'تنظیمات EDG';
require __DIR__ . '/includes/layout_header.php';
?>

<div class="flex gap-8" style="margin-bottom:16px;">
    <a class="btn btn-ghost btn-sm" href="<?= url('/dashboard.php') ?>"><?= icon('arrow_forward') ?></a>
    <h2 style="margin:0;font-size:17px;"><?= icon('tune', 'icon-sm') ?> تنظیمات پنل EDG</h2>
</div>

<div id="loadingBox" class="card" style="text-align:center;"><span class="spinner"></span> در حال دریافت تنظیمات...</div>

<form id="settingsForm" class="card" style="display:none;">
    <div class="card-title">Proxy IP</div>
    <p class="text-sm muted">آی‌پی یا دامنهٔ پروکسی برای دور زدن فیلترینگ. خالی بگذارید تا حالت خودکار (auto) فعال بماند.</p>
    <div class="field">
        <input type="text" id="proxyIp" placeholder="مثلاً proxyip.cmliussss.net یا خالی برای auto">
    </div>
    <button type="submit" class="btn btn-primary btn-block" id="saveBtn"><?= icon('save') ?> ذخیره تنظیمات</button>
</form>

<script>
var ACCOUNT_ID = <?= (int) $accountId ?>;
CWP.apiPost('/api/edg_settings_fetch.php', { account_id: ACCOUNT_ID }).then(function (res) {
    document.getElementById('loadingBox').style.display = 'none';
    var body = res.body || {};
    if (!body.success) {
        CWP.toast(body.message || 'دریافت تنظیمات ناموفق بود', 'error');
        return;
    }
    document.getElementById('proxyIp').value = body.proxy_ip || '';
    document.getElementById('settingsForm').style.display = 'block';
});

document.getElementById('settingsForm').addEventListener('submit', function (e) {
    e.preventDefault();
    CWP.runAction(document.getElementById('saveBtn'), '/api/edg_settings_save.php', {
        account_id: ACCOUNT_ID,
        proxy_ip: document.getElementById('proxyIp').value.trim(),
    }, { reload: false, onSuccess: function () { window.location.href = CWP.url('/dashboard.php'); } });
});
</script>

<?php require __DIR__ . '/includes/layout_footer.php'; ?>
