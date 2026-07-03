<?php
define('IS_API', true);
require_once __DIR__ . '/../includes/bootstrap.php';

[$userId, $input] = api_prologue();
$groupId = (int) ($input['group_id'] ?? 0);

$result = combine_group($groupId, $userId);
if ($result['success']) {
    echo json_encode(['success' => true, 'message' => $result['count'] . ' کانفیگ ترکیبی ساخته شد.', 'group_id' => $result['group_id']]);
} else {
    echo json_encode($result);
}
