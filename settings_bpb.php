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

$proxyIpsList = [
    '213.108.198.116', '213.108.20.161', '49.12.237.71', '62.60.245.255', '92.246.136.38',
    '91.149.233.78', '62.60.216.169', '167.71.45.93', '94.159.103.41', '185.66.165.51',
    '91.107.255.196', '93.123.84.194', '94.159.97.247', '91.107.148.154', '79.132.138.87',
    'bpb.yousef.isegaro.com', '188.245.161.141', '89.169.12.101', '109.122.198.64', '172.86.95.236',
];
$nat64Prefixes = ['[2a02:898:146:64::]', '[2602:fc59:b0:64::]', '[2602:fc59:11:64::]'];

$pageTitle = 'تنظیمات BPB';
require __DIR__ . '/includes/layout_header.php';
?>

<div class="flex gap-8" style="margin-bottom:16px;">
    <a class="btn btn-ghost btn-sm" href="<?= url('/dashboard.php') ?>"><?= icon('arrow_forward') ?></a>
    <h2 style="margin:0;font-size:17px;"><?= icon('tune', 'icon-sm') ?> تنظیمات پنل BPB</h2>
</div>

<div id="loadingBox" class="card" style="text-align:center;"><span class="spinner"></span> در حال دریافت تنظیمات فعلی...</div>
<div id="errorBox" class="alert alert-error" style="display:none;"></div>

<form id="settingsForm" class="card" style="display:none;">
    <div class="card-title">عمومی</div>
    <div class="checkbox-row" style="margin:12px 0;justify-content:space-between;">
        <label class="mb-0">اجازهٔ اتصال از شبکهٔ محلی (LAN)</label>
        <label class="switch"><input type="checkbox" id="allowLAN"><span class="slider"></span></label>
    </div>
    <div class="checkbox-row" style="margin:12px 0;justify-content:space-between;">
        <label class="mb-0">فعال‌سازی IPv6</label>
        <label class="switch"><input type="checkbox" id="enableIPv6"><span class="slider"></span></label>
    </div>

    <hr>
    <div class="card-title">پروتکل‌ها</div>
    <div class="flex gap-12" style="margin:12px 0;">
        <label class="checkbox-row mb-0"><input type="checkbox" id="vlConfigs"> VLESS</label>
        <label class="checkbox-row mb-0"><input type="checkbox" id="trConfigs"> Trojan</label>
    </div>

    <hr>
    <div class="card-title">پورت‌های TLS</div>
    <div class="flex gap-8" id="tlsPorts" style="flex-wrap:wrap;margin:10px 0;"></div>
    <div class="card-title">پورت‌های غیر TLS</div>
    <div class="flex gap-8" id="nonTlsPorts" style="flex-wrap:wrap;margin:10px 0;"></div>

    <hr>
    <div class="field">
        <label>Clean IP (Ingress) — اختیاری</label>
        <input type="text" id="cleanIp" placeholder="مثلاً 104.16.0.0">
    </div>

    <hr>
    <div class="card-title">حالت Proxy IP</div>
    <div class="flex gap-12" style="margin:12px 0;">
        <label class="radio-row mb-0"><input type="radio" name="proxyMode" value="proxyip" checked> Proxy IP</label>
        <label class="radio-row mb-0"><input type="radio" name="proxyMode" value="prefix"> NAT64</label>
    </div>
    <div class="field" id="proxyIpField">
        <label>Proxy IP / دامنه</label>
        <input type="text" id="proxyIp" list="proxyIpList" placeholder="bpb.yousef.isegaro.com">
        <datalist id="proxyIpList">
            <?php foreach ($proxyIpsList as $ip): ?><option value="<?= h($ip) ?>"><?php endforeach; ?>
        </datalist>
    </div>
    <div class="field" id="prefixField" style="display:none;">
        <label>پیشوند NAT64</label>
        <input type="text" id="prefix" list="prefixList">
        <datalist id="prefixList">
            <?php foreach ($nat64Prefixes as $p): ?><option value="<?= h($p) ?>"><?php endforeach; ?>
        </datalist>
    </div>

    <button type="submit" class="btn btn-primary btn-block" id="saveBtn"><?= icon('save') ?> ذخیره و دریافت کانفیگ جدید</button>
</form>

<script>
var ACCOUNT_ID = <?= (int) $accountId ?>;
var ALL_TLS = ['443', '8443', '2053', '2083', '2087', '2096'];
var ALL_NON_TLS = ['80', '8080', '8880', '2052', '2082', '2086', '2095'];
var selectedTls = new Set(['443']);
var selectedNonTls = new Set();

function renderPorts(containerId, all, selectedSet) {
    var el = document.getElementById(containerId);
    el.innerHTML = '';
    all.forEach(function (port) {
        var chip = document.createElement('span');
        chip.className = 'badge';
        chip.style.cursor = 'pointer';
        chip.style.padding = '6px 12px';
        chip.textContent = port;
        function refresh() {
            if (selectedSet.has(port)) { chip.classList.add('ok'); } else { chip.classList.remove('ok'); }
        }
        chip.addEventListener('click', function () {
            if (selectedSet.has(port)) selectedSet.delete(port); else selectedSet.add(port);
            refresh();
        });
        refresh();
        el.appendChild(chip);
    });
}

document.querySelectorAll('input[name=proxyMode]').forEach(function (r) {
    r.addEventListener('change', function () {
        document.getElementById('proxyIpField').style.display = this.value === 'proxyip' ? 'block' : 'none';
        document.getElementById('prefixField').style.display = this.value === 'prefix' ? 'block' : 'none';
    });
});

CWP.apiPost('/api/bpb_settings_fetch.php', { account_id: ACCOUNT_ID }, 40000).then(function (res) {
    document.getElementById('loadingBox').style.display = 'none';
    var body = res.body || {};
    if (!body.success) {
        var box = document.getElementById('errorBox');
        box.style.display = 'block';
        box.textContent = body.message || 'دریافت تنظیمات ناموفق بود.';
        return;
    }
    var s = body.settings || {};
    document.getElementById('allowLAN').checked = !!s.allowLANConnection;
    document.getElementById('enableIPv6').checked = !!s.enableIPv6;
    document.getElementById('vlConfigs').checked = s.VLConfigs !== false;
    document.getElementById('trConfigs').checked = s.TRConfigs !== false;
    document.getElementById('cleanIp').value = (s.cleanIPs && s.cleanIPs[0]) || '';

    if (Array.isArray(s.ports) && s.ports.length) {
        selectedTls = new Set(s.ports.map(String).filter(function (p) { return ALL_TLS.indexOf(p) !== -1; }));
        selectedNonTls = new Set(s.ports.map(String).filter(function (p) { return ALL_NON_TLS.indexOf(p) !== -1; }));
        if (!selectedTls.size && !selectedNonTls.size) selectedTls = new Set(['443']);
    }
    renderPorts('tlsPorts', ALL_TLS, selectedTls);
    renderPorts('nonTlsPorts', ALL_NON_TLS, selectedNonTls);

    var mode = s.proxyIPMode || 'proxyip';
    document.querySelector('input[name=proxyMode][value="' + mode + '"]').checked = true;
    document.getElementById('proxyIpField').style.display = mode === 'proxyip' ? 'block' : 'none';
    document.getElementById('prefixField').style.display = mode === 'prefix' ? 'block' : 'none';
    document.getElementById('proxyIp').value = (s.proxyIPs && s.proxyIPs[0]) || '';
    document.getElementById('prefix').value = (s.prefixes && s.prefixes[0]) || '';

    document.getElementById('settingsForm').style.display = 'block';
});

document.getElementById('settingsForm').addEventListener('submit', function (e) {
    e.preventDefault();
    var mode = document.querySelector('input[name=proxyMode]:checked').value;
    var settings = {
        allowLANConnection: document.getElementById('allowLAN').checked,
        enableIPv6: document.getElementById('enableIPv6').checked,
        VLConfigs: document.getElementById('vlConfigs').checked,
        TRConfigs: document.getElementById('trConfigs').checked,
        ports: Array.from(selectedTls).concat(Array.from(selectedNonTls)).map(Number),
        proxyIPMode: mode,
        cleanIPs: document.getElementById('cleanIp').value.trim() ? [document.getElementById('cleanIp').value.trim()] : [],
    };
    if (mode === 'proxyip') {
        var pip = document.getElementById('proxyIp').value.trim();
        settings.proxyIPs = pip ? [pip] : [];
    } else {
        var pfx = document.getElementById('prefix').value.trim();
        settings.prefixes = pfx ? [pfx] : [];
    }
    CWP.runAction(document.getElementById('saveBtn'), '/api/bpb_settings_save.php', { account_id: ACCOUNT_ID, settings: settings }, { reload: false, onSuccess: function () {
        window.location.href = CWP.url('/groups.php');
    } });
});
</script>

<?php require __DIR__ . '/includes/layout_footer.php'; ?>
