<?php
define('IS_API', true);
require_once __DIR__ . '/../includes/bootstrap.php';

[$userId, $input] = api_prologue();
$account = api_load_account($userId, $input);

echo json_encode(cw_nahan_fetch_settings($account));
