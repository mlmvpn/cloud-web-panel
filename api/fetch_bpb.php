<?php
define('IS_API', true);
require_once __DIR__ . '/../includes/bootstrap.php';

[$userId, $input] = api_prologue();
$account = api_load_account($userId, $input);

$result = cw_bpb_fetch_configs($account);
if (!$result['success'] || !$result['configs']) {
    echo json_encode(['success' => false, 'message' => $result['message'] ?? 'کانفیگی دریافت نشد.']);
    exit;
}

$emailPrefix = strtok($account['email'] ?: 'bpb', '@');
$nodes = [];
foreach ($result['configs'] as $uri) {
    $nodes[] = [
        'name' => 'VLESS - ' . $emailPrefix,
        'uri' => $uri,
        'type' => strpos($uri, 'trojan://') === 0 ? 'trojan' : 'vless',
        'engine_type' => 'BPB',
    ];
}

$groupId = create_cloud_group($userId, (int) $account['id'], 'BPB', 'BPB - ' . date('Y-m-d H:i'), $nodes);
echo json_encode(['success' => true, 'message' => count($nodes) . ' کانفیگ دریافت شد.', 'group_id' => $groupId, 'count' => count($nodes)]);
