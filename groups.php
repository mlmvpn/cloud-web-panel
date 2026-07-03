<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$userId = current_user_id();
$groups = list_user_groups($userId);
$hasCleanIps = (bool) list_clean_ips();
$counts = count_user_uris_by_engine($userId);
$totalCount = array_sum($counts);

function cw_copy_all_row(string $engine, string $label, int $count) {
    echo '<div class="flex-between" style="padding:10px 0;border-top:1px solid var(--border-dark);">';
    echo '<span><b>' . h($label) . '</b> <span class="dim text-sm">(' . $count . ' کانفیگ)</span></span>';
    echo '<button class="btn btn-primary btn-sm" ' . ($count ? '' : 'disabled') . ' data-copy-engine="' . h($engine) . '">' . icon('content_paste') . ' کپی همه</button>';
    echo '</div>';
}

$pageTitle = 'کانفیگ‌های من';
$activeNav = 'groups';
require __DIR__ . '/includes/layout_header.php';
?>

<div class="card">
    <div class="card-title"><?= icon('content_paste', 'icon-sm') ?> کپی کل کانفیگ‌ها</div>
    <p class="text-sm muted" style="margin-top:4px;">با زدن «کپی همه»، همهٔ کانفیگ‌های آن بخش (به‌صورت متن ساده، هر خط یک کانفیگ) در کلیپ‌بورد گوشی شما کپی می‌شود. بعد داخل اپ VPN (وی‌تو‌ری / Shadowrocket / Streisand / ...) گزینهٔ «Import from Clipboard» یا «افزودن از کلیپ‌بورد» را بزنید — همهٔ کانفیگ‌ها یک‌جا اضافه می‌شوند.</p>
    <div style="margin-top:8px;">
        <?php
        cw_copy_all_row('ALL', 'همهٔ پنل‌ها', $totalCount);
        cw_copy_all_row('BPB', 'BPB', $counts['BPB']);
        cw_copy_all_row('EDG', 'EDG', $counts['EDG']);
        cw_copy_all_row('NHN', 'Nahan', $counts['NHN']);
        cw_copy_all_row('MLM', 'MLM', $counts['MLM']);
        ?>
    </div>
</div>

<?php if (!$groups): ?>
    <div class="empty-state card">
        <?= icon('inbox', 'icon') ?>
        <p>هنوز کانفیگی دریافت نکرده‌اید.</p>
        <a class="btn btn-primary" style="margin-top:10px;" href="<?= url('/dashboard.php') ?>"><?= icon('arrow_back') ?> رفتن به اکانت‌ها و دریافت کانفیگ</a>
    </div>
<?php endif; ?>

<?php foreach ($groups as $g): ?>
<div class="card" style="padding:0;overflow:hidden;">
    <div class="flex-between" style="padding:14px 16px;cursor:pointer;" data-toggle-group="<?= (int) $g['id'] ?>">
        <div>
            <div style="font-weight:600;font-size:14px;">
                <span class="badge"><?= h($g['engine_type']) ?></span>
                <?= h($g['title']) ?>
            </div>
            <div class="text-sm dim" style="margin-top:4px;"><?= h($g['account_name'] ?: $g['account_email']) ?> · <?= (int) $g['node_count'] ?> کانفیگ · <?= h($g['created_at']) ?></div>
        </div>
        <div class="flex gap-8">
            <?php if ($hasCleanIps): ?>
            <button class="btn btn-secondary btn-sm" title="ساخت یک گروه جدید با جایگزینی آدرس هرکانفیگ با IP های تمیز" onclick="event.stopPropagation();CWP.runAction(this, '/api/combine_group.php', {group_id: <?= (int) $g['id'] ?>})"><?= icon('call_merge') ?> ترکیب با IP تمیز</button>
            <?php endif; ?>
            <button class="btn btn-ghost btn-sm" onclick="event.stopPropagation();if(confirm('این گروه حذف شود؟')) CWP.runAction(this, '/api/delete_group.php', {group_id: <?= (int) $g['id'] ?>})"><?= icon('delete') ?></button>
        </div>
    </div>
    <div id="g<?= (int) $g['id'] ?>" class="cw-group-detail" style="display:none;padding:12px 16px;background:var(--bg-dark);border-top:1px solid var(--border-dark);"></div>
</div>
<?php endforeach; ?>

<?php if (!$hasCleanIps && current_user_is_admin()): ?>
    <div class="alert alert-info">برای فعال شدن دکمهٔ «ترکیب با IP تمیز» روی گروه‌ها، از <a href="<?= url('/admin_clean_ips.php') ?>">صفحهٔ مدیریت IP تمیز</a> چند IP اضافه کنید.</div>
<?php endif; ?>

<script>
(function () {
    var NODE_DISPLAY_CAP = 300;
    var loadedGroups = {};

    function renderGroupDetail(box, groupId, nodes) {
        var text = nodes.map(function (n) { return n.uri; }).join('\n');
        var html = '<div class="flex-between" style="margin-bottom:10px;">';
        html += '<span class="text-sm dim">فقط کانفیگ‌های همین گروه (' + nodes.length + '):</span>';
        html += '<button class="btn btn-secondary btn-sm" data-copy-text="' + groupId + '">' + CWP.icon('content_copy') + ' کپی همین گروه</button>';
        html += '</div>';

        var shown = nodes.slice(0, NODE_DISPLAY_CAP);
        shown.forEach(function (n) {
            html += '<div class="uri-box">';
            html += '<span class="uri-text" title="' + escapeHtml(n.name) + '">' + escapeHtml(n.name) + ' — ' + escapeHtml(n.uri) + '</span>';
            html += '<button class="btn btn-secondary btn-sm" data-copy="' + escapeHtml(n.uri) + '">' + CWP.icon('content_copy') + '</button>';
            html += '</div>';
        });
        if (nodes.length > NODE_DISPLAY_CAP) {
            html += '<p class="text-sm dim" style="margin-top:8px;">' + (nodes.length - NODE_DISPLAY_CAP) + ' کانفیگ دیگر نمایش داده نشد — برای دریافت همهٔ آن‌ها از دکمهٔ «کپی همین گروه» بالا استفاده کنید.</p>';
        }
        box.innerHTML = html;
        box.setAttribute('data-copy-text-value', text);
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    document.addEventListener('click', function (e) {
        var toggle = e.target.closest('[data-toggle-group]');
        if (toggle) {
            var groupId = toggle.getAttribute('data-toggle-group');
            var box = document.getElementById('g' + groupId);
            if (box.style.display === 'block') {
                box.style.display = 'none';
                return;
            }
            box.style.display = 'block';
            if (loadedGroups[groupId]) return;
            loadedGroups[groupId] = true;
            box.innerHTML = '<span class="spinner"></span> در حال بارگذاری...';
            CWP.apiPost('/api/group_nodes.php', { group_id: groupId }).then(function (res) {
                var body = res.body || {};
                if (body.success) {
                    renderGroupDetail(box, groupId, body.nodes || []);
                } else {
                    box.innerHTML = '<span class="text-sm" style="color:var(--red-error)">' + escapeHtml(body.message || 'خطا در بارگذاری کانفیگ‌ها') + '</span>';
                    loadedGroups[groupId] = false;
                }
            });
            return;
        }

        var copyBtn = e.target.closest('[data-copy-text]');
        if (copyBtn) {
            var gBox = document.getElementById('g' + copyBtn.getAttribute('data-copy-text'));
            CWP.copyText(gBox ? gBox.getAttribute('data-copy-text-value') || '' : '');
            return;
        }

        var engineBtn = e.target.closest('[data-copy-engine]');
        if (engineBtn) {
            var engine = engineBtn.getAttribute('data-copy-engine');
            var original = engineBtn.innerHTML;
            engineBtn.disabled = true;
            engineBtn.innerHTML = '<span class="spinner"></span>';
            CWP.apiPost('/api/copy_uris.php', { engine: engine }, 60000).then(function (res) {
                engineBtn.disabled = false;
                engineBtn.innerHTML = original;
                var body = res.body || {};
                if (body.success) {
                    CWP.copyText(body.text || '');
                } else {
                    CWP.toast(body.message || 'خطا در دریافت کانفیگ‌ها', 'error');
                }
            });
        }
    });
})();
</script>

<?php require __DIR__ . '/includes/layout_footer.php'; ?>
