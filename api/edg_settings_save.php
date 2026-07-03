<?php
define('IS_API', true);
require_once __DIR__ . '/../includes/bootstrap.php';

[$userId, $input] = api_prologue();
$account = api_load_account($userId, $input);
$proxyIp = trim((string) ($input['proxy_ip'] ?? ''));

echo json_encode(cw_edg_update_proxy_ip($account, $proxyIp));
