<?php
function list_user_accounts(int $userId): array {
    $stmt = db()->prepare('SELECT * FROM cloud_accounts WHERE user_id = ? ORDER BY added_at DESC');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function get_account_for_user(int $accountRowId, int $userId): ?array {
    $stmt = db()->prepare('SELECT * FROM cloud_accounts WHERE id = ? AND user_id = ?');
    $stmt->execute([$accountRowId, $userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function api_load_account(int $userId, array $input): array {
    $accountRowId = (int) ($input['account_id'] ?? 0);
    $account = get_account_for_user($accountRowId, $userId);
    if (!$account) {
        echo json_encode(['success' => false, 'message' => 'اکانت یافت نشد.']);
        exit;
    }
    return $account;
}

function decrypt_account_token(array $account): string {
    return decrypt_for_user($account['token']);
}

function account_auth_token(array $account): string {
    if (($account['auth_type'] ?? 'key') === 'oauth') {
        return oauth_get_valid_access_token($account);
    }
    return decrypt_account_token($account);
}

function account_auth_email(array $account): string {
    if (($account['auth_type'] ?? 'key') === 'oauth') {
        return '';
    }
    return $account['email'] ?? '';
}

function mask_token(string $token): string {
    return mb_substr($token, 0, 8) . '••••';
}

$__CLOUD_ACCOUNT_COLUMNS = [
    'status', 'is_email_verified', 'has_subdomain', 'auth_type',
    'oauth_refresh_token', 'oauth_expires_at',
    'worker_url', 'uuid', 'tr_pass', 'sub_path',
    'edg_worker_url', 'edg_uuid', 'edg_admin_pass', 'edg_kv_namespace_id', 'edg_status',
    'nahan_worker_url', 'nahan_db_id', 'nahan_api_route', 'nahan_master_key', 'nahan_status',
    'mlm_worker_url', 'mlm_db_id', 'mlm_admin_password', 'mlm_status',
    'zeus_worker_url', 'zeus_db_id', 'zeus_status',
    'token', 'email', 'name',
];

function update_account_fields(int $accountRowId, int $userId, array $fields): void {
    global $__CLOUD_ACCOUNT_COLUMNS;
    $set = [];
    $params = [];
    foreach ($fields as $col => $val) {
        if (!in_array($col, $__CLOUD_ACCOUNT_COLUMNS, true)) {
            continue;
        }
        $set[] = "`$col` = ?";
        $params[] = $val;
    }
    if (!$set) {
        return;
    }
    $params[] = $accountRowId;
    $params[] = $userId;
    $sql = 'UPDATE cloud_accounts SET ' . implode(', ', $set) . ' WHERE id = ? AND user_id = ?';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
}

function delete_cloud_account(int $accountRowId, int $userId): bool {
    $stmt = db()->prepare('DELETE FROM cloud_accounts WHERE id = ? AND user_id = ?');
    $stmt->execute([$accountRowId, $userId]);
    return $stmt->rowCount() > 0;
}

function cf_check_account_status(string $token, string $email, string $accountId): array {
    $hasSubdomain = false;

    $sub = cf_api('GET', "https://api.cloudflare.com/client/v4/accounts/{$accountId}/workers/subdomain", $token, $email);
    if ($sub['ok'] && $sub['json'] && !empty($sub['json']['success'])) {
        $subdomainName = $sub['json']['result']['subdomain'] ?? '';
        if ($subdomainName !== '') {
            $hasSubdomain = true;
        }
    }

    return ['has_subdomain' => $hasSubdomain];
}

function cf_add_account(int $userId, string $rawToken, string $rawEmail): array {
    $token = preg_replace('/[^a-zA-Z0-9_-]/', '', $rawToken);
    $email = trim($rawEmail);

    if ($token === '') {
        return ['success' => false, 'message' => 'Global API Key نمی‌تواند خالی باشد.'];
    }

    $isCfat = strpos($token, 'cfat_') === 0;

    if ($isCfat || $email === '') {
        $verify = cf_api('GET', 'https://api.cloudflare.com/client/v4/user/tokens/verify', $token, $email);
        if (!$verify['ok'] || !$verify['json'] || empty($verify['json']['success'])) {
            return ['success' => false, 'message' => 'API Token نامعتبر است: ' . cf_first_error($verify)];
        }
    } else {
        $verify = cf_api('GET', 'https://api.cloudflare.com/client/v4/user', $token, $email);
        if (!$verify['ok'] || !$verify['json'] || empty($verify['json']['success'])) {
            return ['success' => false, 'message' => 'ایمیل یا Global API Key نادرست است: ' . cf_first_error($verify)];
        }
    }

    $accResp = cf_api('GET', 'https://api.cloudflare.com/client/v4/accounts', $token, $email);
    if (!$accResp['ok'] || !$accResp['json'] || empty($accResp['json']['success'])) {
        return ['success' => false, 'message' => 'دریافت اطلاعات اکانت ناموفق بود.'];
    }
    $results = $accResp['json']['result'] ?? [];
    if (!$results) {
        return ['success' => false, 'message' => 'هیچ اکانت کلادفلری برای این کلید یافت نشد.'];
    }
    $first = $results[0];
    $accountName = $first['name'] ?? 'Unknown';
    $accountId = $first['id'] ?? '';

    $dupCheck = db()->prepare('SELECT id FROM cloud_accounts WHERE user_id = ? AND account_id = ?');
    $dupCheck->execute([$userId, $accountId]);
    if ($dupCheck->fetch()) {
        return ['success' => false, 'message' => 'این اکانت کلادفلر قبلاً اضافه شده است.'];
    }

    $status = cf_check_account_status($token, $email, $accountId);

    $stmt = db()->prepare('INSERT INTO cloud_accounts
        (user_id, email, token, name, account_id, status, is_email_verified, has_subdomain)
        VALUES (?, ?, ?, ?, ?, ?, 1, ?)');
    $stmt->execute([
        $userId,
        $email,
        encrypt_for_user($token),
        $accountName,
        $accountId,
        'active',
        $status['has_subdomain'] ? 1 : 0,
    ]);

    return ['success' => true, 'message' => 'اکانت با موفقیت اضافه شد: ' . $accountName, 'id' => (int) db()->lastInsertId()];
}

function cf_create_subdomain_only(string $token, string $email, string $accountId): array {
    $sub = cf_api('GET', "https://api.cloudflare.com/client/v4/accounts/{$accountId}/workers/subdomain", $token, $email);
    if ($sub['ok'] && $sub['json'] && !empty($sub['json']['success'])) {
        $existing = $sub['json']['result']['subdomain'] ?? '';
        if ($existing !== '') {
            return ['success' => true, 'subdomain' => $existing];
        }
    }

    $randomSub = generate_safe_subdomain();
    $create = cf_api('PUT', "https://api.cloudflare.com/client/v4/accounts/{$accountId}/workers/subdomain", $token, $email, ['subdomain' => $randomSub]);
    if ($create['ok']) {
        return ['success' => true, 'subdomain' => $randomSub];
    }
    if (strpos($create['body'], '10007') !== false) {
        return ['success' => true, 'subdomain' => 'already_exists'];
    }
    return ['success' => false, 'message' => 'ساخت ساب‌دامین ناموفق بود: ' . cf_first_error($create)];
}

function cf_get_usage(array $account): array {
    $token = account_auth_token($account);
    $email = account_auth_email($account);
    $accountId = $account['account_id'];

    $end = gmdate('Y-m-d\TH:i:s\Z');
    $start = gmdate('Y-m-d\T00:00:00\Z');

    $query = [
        'query' => 'query GetWorkersAnalytics($accountTag: String!, $datetimeStart: String!, $datetimeEnd: String!) {
            viewer {
                accounts(filter: {accountTag: $accountTag}) {
                    workersInvocationsAdaptive(limit: 10000, filter: {datetime_geq: $datetimeStart, datetime_leq: $datetimeEnd}) {
                        sum { requests }
                    }
                }
            }
        }',
        'variables' => ['accountTag' => $accountId, 'datetimeStart' => $start, 'datetimeEnd' => $end],
    ];

    $res = cf_api('POST', 'https://api.cloudflare.com/client/v4/graphql', $token, $email, $query);
    if (!$res['ok'] || !$res['json']) {
        return ['success' => false, 'message' => 'HTTP ' . $res['code']];
    }
    if (!empty($res['json']['errors'])) {
        return ['success' => false, 'message' => 'GraphQL Error: ' . ($res['json']['errors'][0]['message'] ?? '')];
    }
    $accountsArr = $res['json']['data']['viewer']['accounts'] ?? [];
    if (!$accountsArr) {
        return ['success' => true, 'requests' => 0];
    }
    $adaptive = $accountsArr[0]['workersInvocationsAdaptive'] ?? [];
    if (!$adaptive) {
        return ['success' => true, 'requests' => 0];
    }
    $requests = $adaptive[0]['sum']['requests'] ?? 0;
    return ['success' => true, 'requests' => (int) $requests];
}
