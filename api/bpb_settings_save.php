<?php
define('IS_API', true);
require_once __DIR__ . '/../includes/bootstrap.php';

[$userId, $input] = api_prologue();
$account = api_load_account($userId, $input);
$settings = is_array($input['settings'] ?? null) ? $input['settings'] : [];

$update = cw_bpb_update_settings($account, $settings);
if (!$update['success']) {
    echo json_encode($update);
    exit;
}

$fetch = cw_bpb_fetch_configs($account);
if (!$fetch['success'] || !$fetch['configs']) {
    echo json_encode(['success' => false, 'message' => 'تنظیمات ذخیره شد اما دریافت کانفیگ ناموفق بود.']);
    exit;
}

$emailPrefix = strtok($account['email'] ?: 'bpb', '@');
$nodes = [];
foreach ($fetch['configs'] as $uri) {
    $nodes[] = [
        'name' => 'VLESS - ' . $emailPrefix,
        'uri' => $uri,
        'type' => strpos($uri, 'trojan://') === 0 ? 'trojan' : 'vless',
        'engine_type' => 'BPB',
    ];
}
$groupId = create_cloud_group($userId, (int) $account['id'], 'BPB', 'BPB - ' . date('Y-m-d H:i'), $nodes);

echo json_encode(['success' => true, 'message' => 'تنظیمات ذخیره و ' . count($nodes) . ' کانفیگ دریافت شد.', 'group_id' => $groupId, 'count' => count($nodes)]);
