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

$pageTitle = 'کاربران Zeus';
require __DIR__ . '/includes/layout_header.php';
?>

<div class="flex-between" style="margin-bottom:16px;">
    <div class="flex gap-8" style="align-items:center;">
        <a class="btn btn-ghost btn-sm" href="<?= url('/dashboard.php') ?>"><?= icon('arrow_forward') ?></a>
        <h2 style="margin:0;font-size:17px;"><?= icon('group', 'icon-sm') ?> مدیریت کاربران Zeus</h2>
    </div>
    <button class="btn btn-primary btn-sm" onclick="openAddModal()"><?= icon('person_add') ?> کاربر جدید</button>
</div>

<div id="loadingBox" class="card" style="text-align:center;"><span class="spinner"></span> در حال دریافت کاربران...</div>
<div id="errorBox" class="alert alert-error" style="display:none;"></div>
<div id="usersList"></div>

<div class="modal-backdrop" id="userModal">
    <div class="modal-box">
        <div class="flex-between" style="margin-bottom:14px;">
            <h3 style="margin:0;" id="userModalTitle">کاربر جدید</h3>
            <button class="btn btn-ghost btn-sm" onclick="closeUserModal()"><?= icon('close') ?></button>
        </div>
        <input type="hidden" id="zu_editing" value="">
        <div class="field"><label>نام کاربری</label><input type="text" id="zu_username"></div>
        <div class="field"><label>محدودیت کل (GB) — خالی یعنی نامحدود</label><input type="number" id="zu_limit"></div>
        <div class="field"><label>اعتبار (روز) — خالی یعنی نامحدود</label><input type="number" id="zu_expiry"></div>
        <div class="field">
            <label>نوع اتصال</label>
            <select id="zu_tls">
                <option value="tls">امن (TLS) — پورت 443</option>
                <option value="none">معمولی — پورت 80</option>
            </select>
        </div>
        <div class="field"><label>Fingerprint</label><input type="text" id="zu_fingerprint" value="chrome"></div>
        <button class="btn btn-primary btn-block" id="userModalSaveBtn"><?= icon('save') ?> ذخیره</button>
    </div>
</div>

<script>
var ACCOUNT_ID = <?= (int) $accountId ?>;

function escapeHtml(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
}

function loadUsers() {
    document.getElementById('loadingBox').style.display = 'block';
    document.getElementById('errorBox').style.display = 'none';
    CWP.apiPost('/api/zeus_users_list.php', { account_id: ACCOUNT_ID }).then(function (res) {
        document.getElementById('loadingBox').style.display = 'none';
        var body = res.body || {};
        if (!body.success) {
            var box = document.getElementById('errorBox');
            box.style.display = 'block';
            box.textContent = body.message || 'دریافت کاربران ناموفق بود.';
            return;
        }
        renderUsers(body.users || []);
    });
}

function renderUsers(users) {
    var el = document.getElementById('usersList');
    if (!users.length) {
        el.innerHTML = '<div class="empty-state card"><div class="icon">' + CWP.icon('person_off', 'icon-lg') + '</div><p>هنوز کاربری تعریف نشده.</p></div>';
        return;
    }
    el.innerHTML = '';
    users.forEach(function (u) {
        var used = u.used_gb || 0;
        var limit = u.limit_gb;
        var limitStr = limit != null ? limit + ' گیگابایت' : 'نامحدود';
        var progress = (limit && limit > 0) ? Math.min(1, used / limit) : 0;
        var dotColor = u.is_active === 0 ? 'var(--red-error)' : (u.is_online === 1 ? 'var(--green-ok)' : 'var(--text-dim)');

        var card = document.createElement('div');
        card.className = 'card';
        card.innerHTML =
            '<div class="flex-between" style="cursor:pointer;" data-toggle="1">' +
                '<div class="flex gap-12">' +
                    '<span style="width:10px;height:10px;border-radius:50%;background:' + dotColor + ';display:inline-block;"></span>' +
                    '<div><b>' + escapeHtml(u.username) + '</b>' +
                        '<div class="text-sm dim">مصرف: ' + Math.round(used * 1024) + ' مگابایت از ' + limitStr + '</div>' +
                    '</div>' +
                '</div>' +
                '<button type="button" class="btn btn-secondary btn-sm" data-act="toggle" data-user="' + escapeHtml(u.username) + '">' + CWP.icon(u.is_active === 1 ? 'pause' : 'play_arrow') + ' ' + (u.is_active === 1 ? 'توقف' : 'فعال') + '</button>' +
            '</div>' +
            (limit ? '<div class="progress-track" style="margin-top:10px;"><div class="progress-fill" style="width:' + (progress * 100) + '%;"></div></div>' : '') +
            '<div class="flex gap-8" style="margin-top:14px;flex-wrap:wrap;">' +
                '<button type="button" class="btn btn-secondary btn-sm" data-act="configs" data-user="' + escapeHtml(u.username) + '">' + CWP.icon('download') + ' دریافت کانفیگ</button>' +
                '<button type="button" class="btn btn-ghost btn-sm" data-act="status" data-user="' + escapeHtml(u.username) + '">' + CWP.icon('content_copy') + ' کپی لینک وضعیت</button>' +
                '<button type="button" class="btn btn-ghost btn-sm" data-act="edit" data-user="' + escapeHtml(u.username) + '" data-raw=\'' + JSON.stringify(u).replace(/'/g, '&#39;') + '\'>' + CWP.icon('edit') + ' ویرایش</button>' +
                '<button type="button" class="btn btn-ghost btn-sm" style="color:var(--red-error)" data-act="delete" data-user="' + escapeHtml(u.username) + '">' + CWP.icon('delete') + ' حذف</button>' +
            '</div>';
        el.appendChild(card);
    });
}

document.getElementById('usersList').addEventListener('click', function (e) {
    var btn = e.target.closest('[data-act]');
    if (!btn) return;
    var username = btn.getAttribute('data-user');

    if (btn.getAttribute('data-act') === 'toggle') {
        CWP.apiPost('/api/zeus_user_toggle.php', { account_id: ACCOUNT_ID, username: username }).then(function (res) {
            if (res.body && res.body.success) { loadUsers(); } else { CWP.toast('خطا در تغییر وضعیت', 'error'); }
        });
    } else if (btn.getAttribute('data-act') === 'configs') {
        CWP.runAction(btn, '/api/zeus_user_configs.php', { account_id: ACCOUNT_ID, username: username }, { reload: false, onSuccess: function () {
            window.location.href = CWP.url('/groups.php');
        } });
    } else if (btn.getAttribute('data-act') === 'status') {
        var link = '<?= h(rtrim((string) $account['zeus_worker_url'], '/')) ?>/status/' + encodeURIComponent(username);
        CWP.copyText(link);
    } else if (btn.getAttribute('data-act') === 'delete') {
        if (confirm('کاربر «' + username + '» حذف شود؟ تمام کانفیگ‌های او از دسترس خارج می‌شود.')) {
            CWP.runAction(btn, '/api/zeus_user_delete.php', { account_id: ACCOUNT_ID, username: username }, { reload: false, onSuccess: loadUsers });
        }
    } else if (btn.getAttribute('data-act') === 'edit') {
        var raw = JSON.parse(btn.getAttribute('data-raw').replace(/&#39;/g, "'"));
        openEditModal(raw);
    }
});

function openAddModal() {
    document.getElementById('userModalTitle').textContent = 'کاربر جدید';
    document.getElementById('zu_editing').value = '';
    document.getElementById('zu_username').value = '';
    document.getElementById('zu_username').readOnly = false;
    document.getElementById('zu_limit').value = '';
    document.getElementById('zu_expiry').value = '';
    document.getElementById('zu_tls').value = 'tls';
    document.getElementById('zu_fingerprint').value = 'chrome';
    document.getElementById('userModal').classList.add('open');
}

function openEditModal(u) {
    document.getElementById('userModalTitle').textContent = 'ویرایش ' + u.username;
    document.getElementById('zu_editing').value = u.username;
    document.getElementById('zu_username').value = u.username;
    document.getElementById('zu_username').readOnly = true;
    document.getElementById('zu_limit').value = u.limit_gb != null ? u.limit_gb : '';
    document.getElementById('zu_expiry').value = u.expiry_days != null ? u.expiry_days : '';
    document.getElementById('zu_tls').value = u.tls === 'none' ? 'none' : 'tls';
    document.getElementById('zu_fingerprint').value = u.fingerprint || 'chrome';
    document.getElementById('userModal').classList.add('open');
}

function closeUserModal() {
    document.getElementById('userModal').classList.remove('open');
}

document.getElementById('userModalSaveBtn').addEventListener('click', function () {
    var editing = document.getElementById('zu_editing').value;
    var tls = document.getElementById('zu_tls').value;
    var payload = {
        account_id: ACCOUNT_ID,
        username: editing || document.getElementById('zu_username').value.trim(),
        limit_gb: document.getElementById('zu_limit').value,
        expiry_days: document.getElementById('zu_expiry').value,
        tls: tls,
        port: tls === 'tls' ? '443' : '80',
        fingerprint: document.getElementById('zu_fingerprint').value.trim() || 'chrome',
    };
    if (!payload.username) { CWP.toast('نام کاربری الزامی است', 'error'); return; }
    var endpoint = editing ? '/api/zeus_user_update.php' : '/api/zeus_user_create.php';
    CWP.runAction(this, endpoint, payload, { reload: false, onSuccess: function () {
        closeUserModal();
        setTimeout(loadUsers, 1200);
    } });
});

loadUsers();
</script>

<?php require __DIR__ . '/includes/layout_footer.php'; ?>
