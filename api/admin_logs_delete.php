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

$id = (int) ($input['id'] ?? 0);
$stmt = db()->prepare('DELETE FROM error_logs WHERE id = ?');
$stmt->execute([$id]);
echo json_encode(['success' => true]);
