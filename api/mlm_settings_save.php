<?php
define('IS_API', true);
require_once __DIR__ . '/../includes/bootstrap.php';

[$userId, $input] = api_prologue();
$account = api_load_account($userId, $input);

echo json_encode(cw_mlm_update_proxy_settings($account, [
    'proxy_ip' => trim((string) ($input['proxy_ip'] ?? '')),
    'iata' => trim((string) ($input['iata'] ?? '')),
    'frag_len' => trim((string) ($input['frag_len'] ?? '')),
    'frag_int' => trim((string) ($input['frag_int'] ?? '')),
]));
