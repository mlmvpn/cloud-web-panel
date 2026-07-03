<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_admin();

$levelFilter = isset($_GET['level']) ? trim((string) $_GET['level']) : '';
$where = '';
$params = [];
if ($levelFilter !== '') {
    $where = 'WHERE level = ?';
    $params[] = $levelFilter;
}

$stmt = db()->prepare("SELECT id, created_at, level, user_id, message, context FROM error_logs {$where} ORDER BY id DESC LIMIT 200");
$stmt->execute($params);
$logs = $stmt->fetchAll();

$levelsStmt = db()->query('SELECT level, COUNT(*) AS c FROM error_logs GROUP BY level ORDER BY c DESC');
$levelCounts = $levelsStmt->fetchAll();

$copyLines = [];
foreach ($logs as $row) {
    $copyLines[] = '[' . $row['created_at'] . '] [' . $row['level'] . '] user:' . ($row['user_id'] ?: '-');
    $copyLines[] = 'پیام: ' . $row['message'];
    if ($row['context']) {
        $copyLines[] = 'جزئیات: ' . $row['context'];
    }
    $copyLines[] = '---';
}
$copyText = implode("\n", $copyLines);

$pageTitle = 'گزارش خطاها';
$activeNav = 'admin_logs';
require __DIR__ . '/includes/layout_header.php';
?>

<div class="flex gap-8" style="margin-bottom:16px;align-items:center;">
    <a class="btn btn-ghost btn-sm" href="<?= url('/dashboard.php') ?>"><?= icon('arrow_forward') ?></a>
    <h2 style="margin:0;font-size:17px;"><?= icon('monitoring', 'icon-sm') ?> گزارش خطاها (ادمین)</h2>
</div>

<?= alert_box('info', 'این لیست خطاهایی است که در سیستم رخ داده — چه خطاهای برنامه و چه خطاهای ارتباط با Cloudflare. برای بررسی روزانه از همین صفحه استفاده کنید.') ?>

<div class="card">
    <div class="flex gap-8" style="flex-wrap:wrap;align-items:center;">
        <a class="btn btn-sm <?= $levelFilter === '' ? 'btn-primary' : 'btn-secondary' ?>" href="<?= url('/admin_logs.php') ?>">همه (<?= array_sum(array_column($levelCounts, 'c')) ?>)</a>
        <?php foreach ($levelCounts as $lc): ?>
            <a class="btn btn-sm <?= $levelFilter === $lc['level'] ? 'btn-primary' : 'btn-secondary' ?>" href="<?= url('/admin_logs.php?level=' . urlencode($lc['level'])) ?>"><?= h($lc['level']) ?> (<?= (int) $lc['c'] ?>)</a>
        <?php endforeach; ?>
        <button class="btn btn-secondary btn-sm" style="margin-inline-start:auto;" <?= $logs ? '' : 'disabled' ?> data-copy="<?= h($copyText) ?>"><?= icon('content_paste') ?> کپی همه (با جزئیات)</button>
        <button class="btn btn-ghost btn-sm" onclick="if(confirm('همهٔ گزارش‌ها حذف شوند؟')) CWP.runAction(this, '/api/admin_logs_clear.php', {})"><?= icon('delete') ?> پاک‌کردن همه</button>
    </div>
</div>

<div class="card">
    <div class="card-title"><?= icon('list', 'icon-sm') ?> ۲۰۰ مورد اخیر</div>
    <?php if (!$logs): ?>
        <div class="empty-state" style="padding:32px 20px;">
            <?= icon('check_circle', 'icon') ?>
            <p class="text-sm muted mb-0">هیچ خطایی ثبت نشده است.</p>
        </div>
    <?php else: ?>
        <table style="margin-top:10px;">
            <thead><tr><th>زمان</th><th>نوع</th><th>کاربر</th><th>پیام</th><th></th></tr></thead>
            <tbody id="logsTable">
                <?php foreach ($logs as $row): ?>
                <tr id="log-row-<?= (int) $row['id'] ?>">
                    <td class="text-sm dim" style="white-space:nowrap;"><?= h($row['created_at']) ?></td>
                    <td><span class="badge"><?= h($row['level']) ?></span></td>
                    <td class="text-sm dim"><?= $row['user_id'] ? (int) $row['user_id'] : '—' ?></td>
                    <td class="text-sm" style="max-width:520px;">
                        <?= h($row['message']) ?>
                        <?php if ($row['context']): ?>
                            <details style="margin-top:4px;">
                                <summary class="text-sm dim" style="cursor:pointer;">جزئیات</summary>
                                <pre class="mono text-sm" style="direction:ltr;text-align:left;white-space:pre-wrap;word-break:break-all;margin-top:6px;"><?= h($row['context']) ?></pre>
                            </details>
                        <?php endif; ?>
                    </td>
                    <td><button class="btn btn-ghost btn-sm" data-del="<?= (int) $row['id'] ?>"><?= icon('delete') ?></button></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script>
document.getElementById('logsTable') && document.getElementById('logsTable').addEventListener('click', function (e) {
    var btn = e.target.closest('[data-del]');
    if (!btn) return;
    CWP.runAction(btn, '/api/admin_logs_delete.php', { id: btn.getAttribute('data-del') });
});
</script>

<?php require __DIR__ . '/includes/layout_footer.php'; ?>
