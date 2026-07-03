<?php
define('IS_API', true);
require_once __DIR__ . '/../includes/bootstrap.php';

[$userId, $input] = api_prologue();
$account = api_load_account($userId, $input);
$userName = trim((string) ($input['user_name'] ?? ''));

if ($userName === '') {
    echo json_encode(['success' => false, 'message' => 'نام کاربر مشخص نیست.']);
    exit;
}

$result = cw_nahan_fetch_user_nodes($account, $userName);
if (!$result['success'] || !$result['configs']) {
    echo json_encode(['success' => false, 'message' => $result['message'] ?? 'کانفیگی برای این کاربر یافت نشد.']);
    exit;
}

$nodes = [];
foreach ($result['configs'] as $uri) {
    $nodes[] = [
        'name' => 'NHN - ' . $userName,
        'uri' => $uri,
        'type' => strpos($uri, 'trojan://') === 0 ? 'trojan' : 'vless',
        'engine_type' => 'NHN',
    ];
}
$groupId = create_cloud_group($userId, (int) $account['id'], 'NHN', $userName, $nodes);
echo json_encode(['success' => true, 'message' => count($nodes) . ' کانفیگ برای ' . $userName . ' دریافت شد.', 'group_id' => $groupId, 'count' => count($nodes)]);
