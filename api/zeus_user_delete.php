<?php
define('IS_API', true);
require_once __DIR__ . '/../includes/bootstrap.php';

[$userId, $input] = api_prologue();
$account = api_load_account($userId, $input);
$username = trim((string) ($input['username'] ?? ''));

echo json_encode(cw_zeus_delete_user($account, $username));
