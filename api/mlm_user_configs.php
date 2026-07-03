<?php
define('IS_API', true);
require_once __DIR__ . '/../includes/bootstrap.php';

[$userId, $input] = api_prologue();
$account = api_load_account($userId, $input);
$username = trim((string) ($input['username'] ?? ''));

if ($username === '') {
    echo json_encode(['success' => false, 'message' => 'نام کاربری مشخص نیست.']);
    exit;
}

$result = cw_mlm_get_user_configs($account, $username);
if (!$result['success'] || !$result['configs']) {
    echo json_encode(['success' => false, 'message' => $result['message'] ?? 'کانفیگی یافت نشد.']);
    exit;
}

$nodes = [];
foreach ($result['configs'] as $uri) {
    $fragment = '';
    if (($hashPos = strpos($uri, '#')) !== false) {
        $fragment = urldecode(substr($uri, $hashPos + 1));
    }
    $nodes[] = [
        'name' => $fragment !== '' ? $fragment : ('MLM ' . $username),
        'uri' => $uri,
        'type' => strpos($uri, 'vless') === 0 ? 'vless' : 'trojan',
        'engine_type' => 'MLM',
    ];
}
$groupId = create_cloud_group($userId, (int) $account['id'], 'MLM', 'کانفیگ‌های کاربر: ' . $username, $nodes);
echo json_encode(['success' => true, 'message' => 'کانفیگ‌ها به بخش «کانفیگ‌های من» افزوده شدند.', 'group_id' => $groupId, 'count' => count($nodes)]);
