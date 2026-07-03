<?php
define('IS_API', true);
require_once __DIR__ . '/../includes/bootstrap.php';

[$userId, $input] = api_prologue();
$accountRowId = (int) ($input['account_id'] ?? 0);

if (delete_cloud_account($accountRowId, $userId)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'اکانت یافت نشد.']);
}
