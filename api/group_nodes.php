<?php
define('IS_API', true);
require_once __DIR__ . '/../includes/bootstrap.php';

[$userId, $input] = api_prologue();

$groupId = (int) ($input['group_id'] ?? 0);
$detail = get_group_with_nodes($groupId, $userId);
if (!$detail) {
    echo json_encode(['success' => false, 'message' => 'گروه یافت نشد.']);
    exit;
}

$nodes = array_map(function ($n) {
    return ['name' => $n['name'], 'uri' => $n['uri']];
}, $detail['nodes']);

echo json_encode(['success' => true, 'nodes' => $nodes]);
