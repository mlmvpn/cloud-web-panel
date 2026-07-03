<?php
define('IS_API', true);
require_once __DIR__ . '/../includes/bootstrap.php';

[$userId, $input] = api_prologue();
$groupId = (int) ($input['group_id'] ?? 0);

if (delete_group($groupId, $userId)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'گروه یافت نشد.']);
}
