<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_admin();

$ips = list_clean_ips();

$pageTitle = 'مدیریت IP های تمیز';
$activeNav = 'admin_clean_ips';
require __DIR__ . '/includes/layout_header.php';
?>

<div class="flex gap-8" style="margin-bottom:16px;align-items:center;">
    <a class="btn btn-ghost btn-sm" href="<?= url('/dashboard.php') ?>"><?= icon('arrow_forward') ?></a>
    <h2 style="margin:0;font-size:17px;"><?= icon('shield', 'icon-sm') ?> مدیریت IP های تمیز (ادمین)</h2>
</div>

<?= alert_box('info', 'این لیست سراسری است و روی همهٔ کاربران سایت اثر می‌گذارد. وقتی کاربری روی دکمهٔ «ترکیب» یک گروه کانفیگ می‌زند، یک نسخهٔ جدید از آن گروه ساخته می‌شود که در آن آدرس هر کانفیگ با هرکدام از این IP ها جایگزین شده (sni/host اصلی حفظ می‌شود تا کار کند).') ?>

<div class="card">
    <div class="card-title"><?= icon('add_location_alt', 'icon-sm') ?> افزودن IP جدید</div>
    <p class="text-sm muted" style="margin-top:4px;">هر خط یک IP یا دامنه؛ اختیاری با کاما یک برچسب هم اضافه کنید. مثال: <code>104.16.0.1, آلمان</code></p>
    <div class="field" style="margin-top:12px;">
        <textarea id="bulkInput" rows="5" placeholder="104.16.0.1, آلمان
proxyip.example.com"></textarea>
    </div>
    <button class="btn btn-primary" id="addBtn"><?= icon('add') ?> افزودن</button>
</div>

<div class="card">
    <div class="card-title"><?= icon('list', 'icon-sm') ?> لیست فعلی (<?= count($ips) ?>)</div>
    <?php if (!$ips): ?>
        <div class="empty-state" style="padding:32px 20px;">
            <div class="icon material-symbols-outlined" style="font-size:32px;">location_off</div>
            <p class="text-sm muted mb-0">هنوز هیچ IP تمیزی اضافه نشده است.</p>
        </div>
    <?php else: ?>
        <table style="margin-top:10px;">
            <thead><tr><th>IP / دامنه</th><th>برچسب</th><th></th></tr></thead>
            <tbody id="ipsTable">
                <?php foreach ($ips as $row): ?>
                <tr id="ip-row-<?= (int) $row['id'] ?>">
                    <td class="mono" style="direction:ltr;text-align:left;"><?= h($row['ip']) ?></td>
                    <td><?= h($row['label']) ?></td>
                    <td><button class="btn btn-ghost btn-sm" data-del="<?= (int) $row['id'] ?>"><?= icon('delete') ?></button></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script>
document.getElementById('addBtn').addEventListener('click', function () {
    var text = document.getElementById('bulkInput').value;
    if (!text.trim()) { CWP.toast('چیزی وارد نکردید', 'error'); return; }
    CWP.runAction(this, '/api/admin_clean_ips_add.php', { text: text });
});

document.getElementById('ipsTable') && document.getElementById('ipsTable').addEventListener('click', function (e) {
    var btn = e.target.closest('[data-del]');
    if (!btn) return;
    if (!confirm('این IP حذف شود؟')) return;
    CWP.runAction(btn, '/api/admin_clean_ips_delete.php', { id: btn.getAttribute('data-del') });
});
</script>

<?php require __DIR__ . '/includes/layout_footer.php'; ?>
