<?php
define('IS_API', true);
require_once __DIR__ . '/../includes/bootstrap.php';

[$userId, $input] = api_prologue();
$account = api_load_account($userId, $input);

$username = trim((string) ($input['username'] ?? ''));
if ($username === '') {
    echo json_encode(['success' => false, 'message' => 'نام کاربری الزامی است.']);
    exit;
}

echo json_encode(cw_mlm_create_user($account, [
    'username' => $username,
    'limit_gb' => (string) ($input['limit_gb'] ?? ''),
    'daily_limit_gb' => (string) ($input['daily_limit_gb'] ?? ''),
    'expiry_days' => (string) ($input['expiry_days'] ?? ''),
    'ips' => $input['ips'] ?? null,
    'tls' => $input['tls'] ?? 'tls',
    'port' => $input['port'] ?? '443',
    'fingerprint' => $input['fingerprint'] ?? 'chrome',
    'proxy_ip' => trim((string) ($input['proxy_ip'] ?? '')),
]));
