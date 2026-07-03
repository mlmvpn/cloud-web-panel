<?php
define('IS_API', true);
require_once __DIR__ . '/../includes/bootstrap.php';

[$userId, $input] = api_prologue();
$account = api_load_account($userId, $input);

$result = cw_nahan_fetch_admin_nodes($account);
if (!$result['success'] || !$result['configs']) {
    echo json_encode(['success' => false, 'message' => $result['message'] ?? 'کانفیگی دریافت نشد.']);
    exit;
}

$nodes = [];
foreach ($result['configs'] as $uri) {
    $fragment = '';
    if (($hashPos = strpos($uri, '#')) !== false) {
        $fragment = urldecode(substr($uri, $hashPos + 1));
    }
    $nodes[] = [
        'name' => $fragment !== '' ? $fragment : 'NHN Node',
        'uri' => $uri,
        'type' => strpos($uri, 'trojan://') === 0 ? 'trojan' : 'vless',
        'engine_type' => 'NHN',
    ];
}

$groupId = create_cloud_group($userId, (int) $account['id'], 'NHN', 'Nahan - ' . date('Y-m-d H:i'), $nodes);
echo json_encode(['success' => true, 'message' => count($nodes) . ' کانفیگ دریافت شد.', 'group_id' => $groupId, 'count' => count($nodes)]);
