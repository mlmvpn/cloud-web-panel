<?php
define('IS_API', true);
require_once __DIR__ . '/../includes/bootstrap.php';

[$userId, $input] = api_prologue();

$email = trim((string) ($input['email'] ?? ''));
$token = trim((string) ($input['token'] ?? ''));

if ($token === '') {
    echo json_encode(['success' => false, 'message' => 'Global API Key را وارد کنید.']);
    exit;
}

$result = cf_add_account($userId, $token, $email);
echo json_encode($result);
