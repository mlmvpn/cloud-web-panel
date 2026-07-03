<?php
define('IS_API', true);
require_once __DIR__ . '/../includes/bootstrap.php';

[$userId, $input] = api_prologue();
$account = api_load_account($userId, $input);

$status = cf_check_account_status(account_auth_token($account), $account['email'], $account['account_id']);
update_account_fields((int) $account['id'], $userId, [
    'has_subdomain' => $status['has_subdomain'] ? 1 : 0,
]);

echo json_encode([
    'success' => true,
    'has_subdomain' => $status['has_subdomain'],
]);
