<?php
define('IS_API', true);
require_once __DIR__ . '/../includes/bootstrap.php';

[$userId, $input] = api_prologue();

$engine = (string) ($input['engine'] ?? 'ALL');
$engineType = in_array($engine, ['BPB', 'EDG', 'NHN', 'MLM', 'ZEUS'], true) ? $engine : null;

$counts = count_user_uris_by_engine($userId);
$total = $engineType !== null ? ($counts[$engineType] ?? 0) : array_sum($counts);
if ($total > 20000) {
    echo json_encode(['success' => false, 'message' => 'تعداد کانفیگ‌های این بخش (' . $total . ' عدد) برای کپی یک‌جا خیلی زیاد است. چند گروه قدیمی یا ترکیبی را از همین صفحه حذف کنید و دوباره امتحان کنید.']);
    exit;
}

$uris = list_user_all_uris($userId, $engineType);
echo json_encode(['success' => true, 'text' => implode("\n", $uris), 'count' => count($uris)]);
