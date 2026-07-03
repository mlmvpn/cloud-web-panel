<?php
define('IS_API', true);
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
api_require_admin();
api_check_csrf();

$ok = @file_put_contents(__DIR__ . '/../includes/asset_version.txt', (string) time());
if ($ok === false) {
    echo json_encode(['success' => false, 'message' => 'نوشتن نسخهٔ کش ناموفق بود. دسترسی نوشتن پوشهٔ includes را بررسی کنید.']);
} else {
    echo json_encode(['success' => true, 'message' => 'کش سایت پاک شد. حالا همهٔ کاربران آخرین نسخه را می‌بینند.']);
}
