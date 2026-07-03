<?php
define('IS_API', true);
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
api_require_admin();
api_check_csrf();

$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = [];
}

$text = (string) ($input['text'] ?? '');
$count = add_clean_ips_bulk($text);

if ($count > 0) {
    echo json_encode(['success' => true, 'message' => $count . ' IP اضافه شد.']);
} else {
    echo json_encode(['success' => false, 'message' => 'هیچ IP معتبری پیدا نشد.']);
}
