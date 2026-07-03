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

$pageTitle = 'تنظیمات Nahan';
require __DIR__ . '/includes/layout_header.php';
?>

<div class="flex gap-8" style="margin-bottom:16px;">
    <a class="btn btn-ghost btn-sm" href="<?= url('/dashboard.php') ?>"><?= icon('arrow_forward') ?></a>
    <h2 style="margin:0;font-size:17px;"><?= icon('tune', 'icon-sm') ?> تنظیمات و کاربران پنل Nahan</h2>
</div>

<div id="loadingBox" class="card" style="text-align:center;"><span class="spinner"></span> در حال دریافت تنظیمات...</div>
<div id="errorBox" class="alert alert-error" style="display:none;"></div>

<div id="mainArea" style="display:none;">
    <form id="settingsForm" class="card">
        <div class="card-title">تنظیمات پایه</div>
        <div class="grid-2" style="margin-top:12px;">
            <div class="field">
                <label>پروتکل خروجی</label>
                <select id="protocol"><option>VLESS</option><option>Trojan</option><option>Both</option></select>
            </div>
            <div class="field">
                <label>مسیر API</label>
                <input type="text" id="apiRoute" placeholder="sync">
            </div>
        </div>
        <div class="field">
            <label>کلید مدیریت (Master Key)</label>
            <input type="text" id="masterKey">
        </div>

        <hr>
        <div class="card-title">شبکه و کموفلاژ</div>
        <div class="field">
            <label>پورت‌های TLS</label>
            <div class="flex gap-8" id="tlsPorts" style="flex-wrap:wrap;"></div>
        </div>
        <div class="grid-2">
            <div class="field"><label>Maintenance Host</label><input type="text" id="maintenanceHost"></div>
            <div class="field"><label>Resolve IP (DNS)</label><input type="text" id="resolveIp"></div>
        </div>
        <div class="field"><label>Custom DNS</label><input type="text" id="customDns"></div>
        <div class="grid-2">
            <div class="field"><label>Custom Relay</label><input type="text" id="customRelay"></div>
            <div class="field"><label>Backup Relay</label><input type="text" id="backupRelay"></div>
        </div>
        <div class="field"><label>Clean IPs</label><textarea id="cleanIps" rows="2"></textarea></div>

        <hr>
        <div class="flex-between">
            <div class="card-title mb-0">مدیریت کاربران (<span id="userCount">0</span>)</div>
            <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('addUserModal').classList.add('open')"><?= icon('person_add') ?> کاربر جدید</button>
        </div>
        <div id="usersList" style="margin-top:12px;"></div>

        <hr>
        <button type="submit" class="btn btn-primary btn-block" id="saveBtn"><?= icon('save') ?> ذخیره و همگام‌سازی</button>
    </form>
</div>

<!-- Add user modal -->
<div class="modal-backdrop" id="addUserModal">
    <div class="modal-box">
        <div class="flex-between" style="margin-bottom:14px;">
            <h3 style="margin:0;">کاربر جدید</h3>
            <button class="btn btn-ghost btn-sm" onclick="document.getElementById('addUserModal').classList.remove('open')"><?= icon('close') ?></button>
        </div>
        <div class="field"><label>نام</label><input type="text" id="nu_name"></div>
        <div class="field"><label>محدودیت کل (GB) — صفر یعنی نامحدود</label><input type="number" id="nu_limit" value="0"></div>
        <div class="field"><label>محدودیت روزانه (GB) — صفر یعنی نامحدود</label><input type="number" id="nu_daily" value="0"></div>
        <div class="field"><label>اعتبار (روز) — صفر یعنی نامحدود</label><input type="number" id="nu_days" value="0"></div>
        <div class="field"><label>Proxy IP اختصاصی (اختیاری)</label><input type="text" id="nu_proxyip"></div>
        <button type="button" class="btn btn-primary btn-block" id="addUserBtn"><?= icon('add') ?> افزودن</button>
    </div>
</div>

<script>
var ACCOUNT_ID = <?= (int) $accountId ?>;
var ALL_TLS = [443, 8443, 2053, 2083, 2087, 2096];
var selectedTlsPorts = new Set(ALL_TLS);
var usersState = [];

function escapeHtml(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
}

function renderTlsPorts() {
    var el = document.getElementById('tlsPorts');
    el.innerHTML = '';
    ALL_TLS.forEach(function (port) {
        var chip = document.createElement('span');
        chip.className = 'badge' + (selectedTlsPorts.has(port) ? ' ok' : '');
        chip.style.cursor = 'pointer';
        chip.style.padding = '6px 12px';
        chip.textContent = port;
        chip.addEventListener('click', function () {
            if (selectedTlsPorts.has(port)) selectedTlsPorts.delete(port); else selectedTlsPorts.add(port);
            renderTlsPorts();
        });
        el.appendChild(chip);
    });
}

function renderUsers() {
    document.getElementById('userCount').textContent = usersState.length;
    var el = document.getElementById('usersList');
    if (!usersState.length) {
        el.innerHTML = '<div class="empty-state" style="padding:20px;"><p class="text-sm mb-0">کاربری تعریف نشده است.</p></div>';
        return;
    }
    el.innerHTML = '';
    usersState.forEach(function (u, idx) {
        var limitGB = u.limit > 0 ? Math.round(u.limit / 6000) : 0;
        var dailyGB = u.limitDailyReq > 0 ? Math.round(u.limitDailyReq / 6000) : 0;
        var days = 0;
        if (u.expiryMs > 0) {
            var remain = u.expiryMs - Date.now();
            days = remain > 0 ? Math.ceil(remain / 86400000) : 0;
        }
        var card = document.createElement('div');
        card.className = 'card';
        card.style.marginBottom = '10px';
        card.innerHTML =
            '<div class="flex-between">' +
                '<div><b>' + escapeHtml(u.name || 'بدون‌نام') + '</b>' + (u.isPaused ? ' <span class="badge" style="color:var(--red-error)">متوقف</span>' : '') +
                    '<div class="text-sm dim mono" style="direction:ltr;text-align:left;">' + escapeHtml(u.uuid) + '</div></div>' +
                '<div class="flex gap-8">' +
                    '<button type="button" class="btn btn-secondary btn-sm" data-act="fetch" data-idx="' + idx + '">' + CWP.icon('download') + ' دریافت کانفیگ</button>' +
                    '<button type="button" class="btn btn-ghost btn-sm" data-act="edit" data-idx="' + idx + '">' + CWP.icon('edit') + ' ویرایش</button>' +
                    '<button type="button" class="btn btn-ghost btn-sm" data-act="del" data-idx="' + idx + '">' + CWP.icon('delete') + ' حذف</button>' +
                '</div>' +
            '</div>' +
            '<div id="edit-' + idx + '" style="display:none;margin-top:12px;">' +
                '<div class="grid-2">' +
                    '<div class="field mb-0"><label>نام</label><input type="text" data-f="name" value="' + escapeHtml(u.name) + '"></div>' +
                    '<div class="field mb-0"><label>محدودیت کل (GB)</label><input type="number" data-f="limit" value="' + limitGB + '"></div>' +
                    '<div class="field mb-0"><label>محدودیت روزانه (GB)</label><input type="number" data-f="daily" value="' + dailyGB + '"></div>' +
                    '<div class="field mb-0"><label>اعتبار (روز)</label><input type="number" data-f="days" value="' + days + '"></div>' +
                    '<div class="field mb-0"><label>Proxy IP</label><input type="text" data-f="proxyIp" value="' + escapeHtml(u.proxyIp || '') + '"></div>' +
                    '<div class="checkbox-row" style="margin-top:22px;"><label class="switch"><input type="checkbox" data-f="paused" ' + (u.isPaused ? 'checked' : '') + '><span class="slider"></span></label><label class="mb-0">متوقف باشد</label></div>' +
                '</div>' +
                '<button type="button" class="btn btn-success btn-sm" data-act="apply" data-idx="' + idx + '" style="margin-top:10px;">اعمال تغییرات</button>' +
            '</div>';
        el.appendChild(card);
    });
}

document.getElementById('usersList').addEventListener('click', function (e) {
    var btn = e.target.closest('[data-act]');
    if (!btn) return;
    var idx = parseInt(btn.getAttribute('data-idx'), 10);
    var act = btn.getAttribute('data-act');

    if (act === 'edit') {
        var box = document.getElementById('edit-' + idx);
        box.style.display = box.style.display === 'block' ? 'none' : 'block';
    } else if (act === 'del') {
        if (confirm('این کاربر حذف شود؟ (برای اعمال، باید دکمهٔ «ذخیره و همگام‌سازی» را بزنید)')) {
            usersState.splice(idx, 1);
            renderUsers();
        }
    } else if (act === 'apply') {
        var box = document.getElementById('edit-' + idx);
        var get = function (f) { return box.querySelector('[data-f="' + f + '"]'); };
        usersState[idx].name = get('name').value.trim() || usersState[idx].name;
        usersState[idx].limit = (parseInt(get('limit').value, 10) || 0) * 6000;
        usersState[idx].limitDailyReq = (parseInt(get('daily').value, 10) || 0) * 6000;
        var days = parseInt(get('days').value, 10) || 0;
        usersState[idx].expiryMs = days > 0 ? (Date.now() + days * 86400000) : 0;
        usersState[idx].isPaused = get('paused').checked;
        usersState[idx].proxyIp = get('proxyIp').value.trim();
        renderUsers();
        CWP.toast('تغییرات محلی اعمال شد — برای ذخیرهٔ نهایی «ذخیره و همگام‌سازی» را بزنید', 'info');
    } else if (act === 'fetch') {
        var user = usersState[idx];
        CWP.runAction(btn, '/api/nahan_user_fetch.php', { account_id: ACCOUNT_ID, user_name: user.name }, { reload: false, onSuccess: function () {
            window.location.href = CWP.url('/groups.php');
        } });
    }
});

document.getElementById('addUserBtn').addEventListener('click', function () {
    var name = document.getElementById('nu_name').value.trim() || 'User';
    var limitGB = parseInt(document.getElementById('nu_limit').value, 10) || 0;
    var dailyGB = parseInt(document.getElementById('nu_daily').value, 10) || 0;
    var days = parseInt(document.getElementById('nu_days').value, 10) || 0;
    var proxyIp = document.getElementById('nu_proxyip').value.trim();
    var uuid = (crypto.randomUUID ? crypto.randomUUID() : (Date.now().toString(16) + Math.random().toString(16).slice(2)));
    usersState.push({
        name: name, uuid: uuid,
        limit: limitGB * 6000, limitDailyReq: dailyGB * 6000,
        expiryMs: days > 0 ? (Date.now() + days * 86400000) : 0,
        used: 0, reset: 0, isPaused: false, proxyIp: proxyIp,
    });
    renderUsers();
    document.getElementById('addUserModal').classList.remove('open');
    document.getElementById('nu_name').value = '';
    document.getElementById('nu_limit').value = '0';
    document.getElementById('nu_daily').value = '0';
    document.getElementById('nu_days').value = '0';
    document.getElementById('nu_proxyip').value = '';
});

CWP.apiPost('/api/nahan_settings_fetch.php', { account_id: ACCOUNT_ID }).then(function (res) {
    document.getElementById('loadingBox').style.display = 'none';
    var body = res.body || {};
    if (!body.success) {
        var box = document.getElementById('errorBox');
        box.style.display = 'block';
        box.textContent = body.message || 'دریافت تنظیمات ناموفق بود.';
        return;
    }
    var c = body.config;
    document.getElementById('protocol').value = ['VLESS', 'Trojan', 'Both'].indexOf(c.protocol) !== -1 ? c.protocol : 'Both';
    document.getElementById('apiRoute').value = c.apiRoute || 'sync';
    document.getElementById('masterKey').value = c.masterKey || 'admin';
    document.getElementById('maintenanceHost').value = c.maintenanceHost || '';
    document.getElementById('resolveIp').value = c.resolveIp || '';
    document.getElementById('customDns').value = c.customDns || '';
    document.getElementById('customRelay').value = c.customRelay || '';
    document.getElementById('backupRelay').value = c.backupRelay || '';
    document.getElementById('cleanIps').value = c.cleanIps || '';
    if (Array.isArray(c.tlsPorts) && c.tlsPorts.length) selectedTlsPorts = new Set(c.tlsPorts);
    usersState = c.users || [];
    renderTlsPorts();
    renderUsers();
    document.getElementById('mainArea').style.display = 'block';
});

document.getElementById('settingsForm').addEventListener('submit', function (e) {
    e.preventDefault();
    var config = {
        protocol: document.getElementById('protocol').value,
        apiRoute: document.getElementById('apiRoute').value.trim() || 'sync',
        masterKey: document.getElementById('masterKey').value.trim() || 'admin',
        tlsPorts: Array.from(selectedTlsPorts),
        maintenanceHost: document.getElementById('maintenanceHost').value.trim(),
        resolveIp: document.getElementById('resolveIp').value.trim(),
        customDns: document.getElementById('customDns').value.trim(),
        customRelay: document.getElementById('customRelay').value.trim(),
        backupRelay: document.getElementById('backupRelay').value.trim(),
        agent: '',
        cleanIps: document.getElementById('cleanIps').value.trim(),
        tgBotToken: '', tgChatId: '',
        users: usersState,
    };
    CWP.runAction(document.getElementById('saveBtn'), '/api/nahan_settings_save.php', { account_id: ACCOUNT_ID, config: config }, { reload: false, onSuccess: function () {
        window.location.href = CWP.url('/dashboard.php');
    } });
});
</script>

<?php require __DIR__ . '/includes/layout_footer.php'; ?>
