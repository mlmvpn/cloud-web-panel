<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$userId = current_user_id();
$groups = list_user_groups($userId);
$hasCleanIps = (bool) list_clean_ips();

function cw_copy_all_row(string $id, string $label, array $uris) {
    $text = implode("\n", $uris);
    echo '<div class="flex-between" style="padding:10px 0;border-top:1px solid var(--border-dark);">';
    echo '<span><b>' . h($label) . '</b> <span class="dim text-sm">(' . count($uris) . ' کانفیگ)</span></span>';
    echo '<button class="btn btn-primary btn-sm" ' . (count($uris) ? '' : 'disabled') . ' onclick="CWP.copyText(document.getElementById(\'' . h($id) . '\').value)">' . icon('content_paste') . ' کپی همه</button>';
    echo '</div>';
    echo '<textarea id="' . h($id) . '" style="display:none;">' . h($text) . '</textarea>';
}

$allUris = list_user_all_uris($userId);

$pageTitle = 'کانفیگ‌های من';
$activeNav = 'groups';
require __DIR__ . '/includes/layout_header.php';
?>

<div class="card">
    <div class="card-title"><?= icon('content_paste', 'icon-sm') ?> کپی کل کانفیگ‌ها</div>
    <p class="text-sm muted" style="margin-top:4px;">با زدن «کپی همه»، همهٔ کانفیگ‌های آن بخش (به‌صورت متن ساده، هر خط یک کانفیگ) در کلیپ‌بورد گوشی شما کپی می‌شود. بعد داخل اپ VPN (وی‌تو‌ری / Shadowrocket / Streisand / ...) گزینهٔ «Import from Clipboard» یا «افزودن از کلیپ‌بورد» را بزنید — همهٔ کانفیگ‌ها یک‌جا اضافه می‌شوند.</p>
    <div style="margin-top:8px;">
        <?php
        cw_copy_all_row('copyAll_ALL', 'همهٔ پنل‌ها', $allUris);
        cw_copy_all_row('copyAll_BPB', 'BPB', list_user_all_uris($userId, 'BPB'));
        cw_copy_all_row('copyAll_EDG', 'EDG', list_user_all_uris($userId, 'EDG'));
        cw_copy_all_row('copyAll_NHN', 'Nahan', list_user_all_uris($userId, 'NHN'));
        cw_copy_all_row('copyAll_MLM', 'MLM', list_user_all_uris($userId, 'MLM'));
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
    <div class="flex-between" style="padding:14px 16px;cursor:pointer;" onclick="var b=document.getElementById('g<?= (int) $g['id'] ?>'); b.style.display = b.style.display==='block' ? 'none' : 'block';">
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
    <div id="g<?= (int) $g['id'] ?>" style="display:none;padding:12px 16px;background:var(--bg-dark);border-top:1px solid var(--border-dark);">
        <?php $detail = get_group_with_nodes((int) $g['id'], $userId); ?>
        <?php $groupUris = array_column($detail['nodes'], 'uri'); ?>
        <div class="flex-between" style="margin-bottom:10px;">
            <span class="text-sm dim">فقط کانفیگ‌های همین گروه:</span>
            <button class="btn btn-secondary btn-sm" data-copy="<?= h(implode("\n", $groupUris)) ?>"><?= icon('content_paste') ?> کپی همین گروه</button>
        </div>
        <?php foreach ($detail['nodes'] as $n): ?>
            <div class="uri-box">
                <span class="uri-text" title="<?= h($n['name']) ?>"><?= h($n['name']) ?> — <?= h($n['uri']) ?></span>
                <button class="btn btn-secondary btn-sm" data-copy="<?= h($n['uri']) ?>"><?= icon('content_copy') ?></button>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>

<?php if (!$hasCleanIps && current_user_is_admin()): ?>
    <div class="alert alert-info">برای فعال شدن دکمهٔ «ترکیب با IP تمیز» روی گروه‌ها، از <a href="<?= url('/admin_clean_ips.php') ?>">صفحهٔ مدیریت IP تمیز</a> چند IP اضافه کنید.</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout_footer.php'; ?>
