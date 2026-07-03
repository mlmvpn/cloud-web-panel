<?php
define('IS_API', true);
require_once __DIR__ . '/../includes/bootstrap.php';

[$userId, $input] = api_prologue();
$account = api_load_account($userId, $input);
$config = is_array($input['config'] ?? null) ? $input['config'] : [];

$config = array_merge(cw_nahan_config_from_json([]), $config);

$result = cw_nahan_sync_settings($account, $config);
if ($result['success']) {
    update_account_fields((int) $account['id'], $userId, [
        'nahan_api_route' => $config['apiRoute'] ?: 'sync',
        'nahan_master_key' => $config['masterKey'] ?: 'admin',
    ]);
}
echo json_encode($result);
