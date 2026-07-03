<?php
define('IS_API', true);
require_once __DIR__ . '/../includes/bootstrap.php';

[$userId, $input] = api_prologue();

$engine = (string) ($input['engine'] ?? 'ALL');
$engineType = in_array($engine, ['BPB', 'EDG', 'NHN', 'MLM', 'ZEUS'], true) ? $engine : null;

$uris = list_user_all_uris($userId, $engineType);
echo json_encode(['success' => true, 'text' => implode("\n", $uris), 'count' => count($uris)]);
