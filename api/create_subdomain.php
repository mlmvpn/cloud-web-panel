<?php
define('IS_API', true);
require_once __DIR__ . '/../includes/bootstrap.php';

[$userId, $input] = api_prologue();
$account = api_load_account($userId, $input);

$res = cf_create_subdomain_only(account_auth_token($account), $account['email'], $account['account_id']);
if ($res['success']) {
    update_account_fields((int) $account['id'], $userId, ['has_subdomain' => 1]);
} elseif (strpos($res['message'] ?? '', 'verify') !== false || stripos($res['message'] ?? '', 'verified') !== false) {
    update_account_fields((int) $account['id'], $userId, ['is_email_verified' => 0]);
}

echo json_encode($res);
