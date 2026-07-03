<?php
define('IS_API', true);
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
api_require_admin();
api_check_csrf();

db()->exec('TRUNCATE TABLE error_logs');
echo json_encode(['success' => true, 'message' => 'گزارش‌ها پاک شد.']);
