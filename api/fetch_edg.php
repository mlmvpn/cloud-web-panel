<?php
define('IS_API', true);
require_once __DIR__ . '/../includes/bootstrap.php';

[$userId, $input] = api_prologue();
$account = api_load_account($userId, $input);

$result = cw_edg_fetch_configs($account);
if (!$result['success'] || !$result['configs']) {
    echo json_encode(['success' => false, 'message' => $result['message'] ?? 'کانفیگی دریافت نشد.']);
    exit;
}

$nodes = [];
foreach ($result['configs'] as $i => $uri) {
    $nodes[] = [
        'name' => 'EDG Node ' . ($i + 1),
        'uri' => $uri,
        'type' => strpos($uri, 'trojan://') === 0 ? 'trojan' : 'vless',
        'engine_type' => 'EDG',
    ];
}

$groupId = create_cloud_group($userId, (int) $account['id'], 'EDG', 'EDG - ' . date('Y-m-d H:i'), $nodes);
echo json_encode(['success' => true, 'message' => count($nodes) . ' کانفیگ دریافت شد.', 'group_id' => $groupId, 'count' => count($nodes)]);
