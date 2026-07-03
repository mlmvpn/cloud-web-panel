<?php
define('IS_API', true);
require_once __DIR__ . '/../includes/bootstrap.php';

[$userId, $input] = api_prologue();
$account = api_load_account($userId, $input);

if ($account['edg_status'] === 'deployed') {
    echo json_encode(['success' => true, 'message' => 'قبلاً دیپلوی شده است.', 'url' => $account['edg_worker_url']]);
    exit;
}

echo json_encode(cw_edg_deploy($account, $userId));
