<?php
define('MLM_WORKER_ASSET', __DIR__ . '/../../assets/workers/mlm_worker.js');

function cw_mlm_deploy(array $account, int $userId): array {
    $token = account_auth_token($account);
    $email = $account['email'];
    $accountId = $account['account_id'];

    $subdomain = '';
    $sub = cf_api('GET', "https://api.cloudflare.com/client/v4/accounts/{$accountId}/workers/subdomain", $token, $email);
    if ($sub['ok'] && $sub['json'] && !empty($sub['json']['success'])) {
        $subdomain = $sub['json']['result']['subdomain'] ?? '';
    }
    if ($subdomain === '') {
        $created = false;
        for ($attempt = 0; $attempt < 3 && !$created; $attempt++) {
            $randomSub = generate_safe_subdomain();
            $createRes = cf_api('PUT', "https://api.cloudflare.com/client/v4/accounts/{$accountId}/workers/subdomain", $token, $email, ['subdomain' => $randomSub]);
            if ($createRes['ok']) {
                $subdomain = $randomSub;
                $created = true;
            } elseif (strpos($createRes['body'], '10007') !== false) {
                return ['success' => false, 'message' => 'ERR_ACCOUNT_HAS_SUBDOMAIN'];
            }
        }
        if (!$created) {
            return ['success' => false, 'message' => 'ساخت ساب‌دامین Workers ناموفق بود.'];
        }
    }

    $dbName = 'mlm_db_' . bin2hex(random_bytes(3));
    $d1 = cf_api('POST', "https://api.cloudflare.com/client/v4/accounts/{$accountId}/d1/database", $token, $email, ['name' => $dbName]);
    $databaseId = '';
    if ($d1['ok'] && $d1['json'] && !empty($d1['json']['success'])) {
        $databaseId = $d1['json']['result']['uuid'];
    } else {
        $list = cf_api('GET', "https://api.cloudflare.com/client/v4/accounts/{$accountId}/d1/database", $token, $email);
        if (!empty($list['json']['result'][0]['uuid'])) {
            $databaseId = $list['json']['result'][0]['uuid'];
        }
    }
    if ($databaseId === '') {
        return ['success' => false, 'message' => 'ساخت دیتابیس D1 ناموفق بود.'];
    }

    $workerScript = @file_get_contents(MLM_WORKER_ASSET);
    if ($workerScript === false) {
        return ['success' => false, 'message' => 'فایل mlm_worker.js روی سرور یافت نشد.'];
    }
    $adminPass = !empty($account['mlm_admin_password']) ? $account['mlm_admin_password'] : 'admin';
    $workerName = generate_safe_worker_name() . '-mlm';
    $metadata = [
        'main_module' => 'worker.js',
        'compatibility_date' => date('Y-m-d'),
        'bindings' => [
            ['type' => 'd1', 'name' => 'DB', 'id' => $databaseId],
            ['type' => 'plain_text', 'name' => 'ADMIN_PASSWORD', 'text' => $adminPass],
            ['type' => 'plain_text', 'name' => 'DEBUG', 'text' => '1'],
        ],
    ];
    $upload = cf_upload_worker($accountId, $token, $email, $workerName, $workerScript, $metadata);
    if (!$upload['ok']) {
        return ['success' => false, 'message' => 'آپلود MLM Worker ناموفق بود: ' . cf_first_error($upload)];
    }

    $enable = cf_api('POST', "https://api.cloudflare.com/client/v4/accounts/{$accountId}/workers/scripts/{$workerName}/subdomain", $token, $email, ['enabled' => true]);
    if (!$enable['ok']) {
        return ['success' => false, 'message' => 'فعال‌سازی ساب‌دامین ناموفق بود: ' . cf_first_error($enable)];
    }

    $finalUrl = "https://{$workerName}.{$subdomain}.workers.dev";
    update_account_fields((int) $account['id'], $userId, [
        'mlm_status' => 'deployed',
        'mlm_worker_url' => $finalUrl,
        'mlm_db_id' => $databaseId,
        'mlm_admin_password' => $adminPass,
        'has_subdomain' => 1,
    ]);

    return ['success' => true, 'message' => 'دیپلوی MLM با موفقیت انجام شد.', 'url' => $finalUrl];
}

function cw_mlm_request(array $account, string $endpoint, string $method = 'GET', $jsonBody = null): array {
    if (empty($account['mlm_worker_url'])) {
        return ['ok' => false, 'code' => 0, 'body' => '', 'json' => null, 'error' => 'MLM دیپلوی نشده است.'];
    }
    $password = !empty($account['mlm_admin_password']) ? $account['mlm_admin_password'] : 'admin';
    $hash = hash('sha256', $password);
    $url = rtrim($account['mlm_worker_url'], '/') . $endpoint;
    $headers = [
        'Cookie: panel_session=' . $hash,
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36',
    ];
    $body = null;
    if ($jsonBody !== null) {
        $headers[] = 'Content-Type: application/json';
        $body = json_encode($jsonBody, JSON_UNESCAPED_UNICODE);
    }
    return http_request($method, $url, $headers, $body, 15);
}

function cw_mlm_get_users(array $account): array {
    $res = cw_mlm_request($account, '/api/users');
    if (!$res['ok'] || !$res['json']) {
        return ['success' => false, 'message' => 'دریافت کاربران ناموفق بود.'];
    }
    return ['success' => true, 'users' => $res['json']['users'] ?? []];
}

function cw_mlm_create_user(array $account, array $data): array {
    $payload = [
        'username' => $data['username'],
        'limit_gb' => $data['limit_gb'] !== '' ? (float) $data['limit_gb'] : null,
        'daily_limit_gb' => $data['daily_limit_gb'] !== '' ? (float) $data['daily_limit_gb'] : null,
        'expiry_days' => $data['expiry_days'] !== '' ? (int) $data['expiry_days'] : null,
        'ips' => $data['ips'] ?? null,
        'tls' => $data['tls'] ?? 'tls',
        'port' => $data['port'] ?? '443',
        'fingerprint' => $data['fingerprint'] ?? 'chrome',
        'proxy_ip' => $data['proxy_ip'] ?: null,
    ];
    $res = cw_mlm_request($account, '/api/users', 'POST', $payload);
    return $res['ok'] ? ['success' => true] : ['success' => false, 'message' => 'ایجاد کاربر ناموفق بود: ' . cf_first_error($res)];
}

function cw_mlm_update_user(array $account, string $username, array $data): array {
    $payload = [
        'limit_gb' => $data['limit_gb'] !== '' ? (float) $data['limit_gb'] : null,
        'daily_limit_gb' => $data['daily_limit_gb'] !== '' ? (float) $data['daily_limit_gb'] : null,
        'expiry_days' => $data['expiry_days'] !== '' ? (int) $data['expiry_days'] : null,
        'ips' => $data['ips'] ?? null,
        'tls' => $data['tls'] ?? 'tls',
        'port' => $data['port'] ?? '443',
        'fingerprint' => $data['fingerprint'] ?? 'chrome',
        'proxy_ip' => $data['proxy_ip'] ?: null,
    ];
    $res = cw_mlm_request($account, '/api/users/' . rawurlencode($username), 'PUT', $payload);
    return $res['ok'] ? ['success' => true] : ['success' => false, 'message' => 'ویرایش کاربر ناموفق بود: ' . cf_first_error($res)];
}

function cw_mlm_delete_user(array $account, string $username): array {
    $res = cw_mlm_request($account, '/api/users/' . rawurlencode($username), 'DELETE');
    return $res['ok'] ? ['success' => true] : ['success' => false, 'message' => 'حذف کاربر ناموفق بود: ' . cf_first_error($res)];
}

function cw_mlm_toggle_user(array $account, string $username): array {
    $res = cw_mlm_request($account, '/api/users/' . rawurlencode($username), 'PUT', ['toggle_only' => true]);
    return $res['ok'] ? ['success' => true] : ['success' => false, 'message' => 'تغییر وضعیت کاربر ناموفق بود.'];
}

function cw_mlm_get_proxy_settings(array $account): array {
    $res = cw_mlm_request($account, '/api/proxy-ip');
    if (!$res['ok'] || !$res['json']) {
        return ['success' => false, 'message' => 'دریافت تنظیمات ناموفق بود.'];
    }
    return ['success' => true, 'settings' => $res['json']];
}

function cw_mlm_update_proxy_settings(array $account, array $data): array {
    $payload = [
        'proxy_ip' => $data['proxy_ip'] ?? null,
        'iata' => $data['iata'] ?? null,
        'frag_len' => $data['frag_len'] ?? null,
        'frag_int' => $data['frag_int'] ?? null,
    ];
    $res = cw_mlm_request($account, '/api/proxy-ip', 'POST', $payload);
    return $res['ok'] ? ['success' => true, 'message' => 'تنظیمات ذخیره شد.'] : ['success' => false, 'message' => 'ذخیرهٔ تنظیمات ناموفق بود.'];
}

function cw_mlm_get_user_configs(array $account, string $username): array {
    if (empty($account['mlm_worker_url'])) {
        return ['success' => false, 'configs' => [], 'message' => 'MLM دیپلوی نشده است.'];
    }
    $url = rtrim($account['mlm_worker_url'], '/') . '/sub/' . rawurlencode($username) . '?txt=1';
    $res = http_request('GET', $url, [], null, 15);
    if (!$res['ok']) {
        return ['success' => false, 'configs' => [], 'message' => 'دریافت کانفیگ ناموفق بود.'];
    }
    $body = trim($res['body']);
    $text = $body;
    if (strpos($body, '://') === false) {
        $decoded = base64_decode($body, true);
        if ($decoded !== false) {
            $text = $decoded;
        }
    }
    $configs = [];
    foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
        $line = trim($line);
        if ($line !== '' && strpos($line, '://') !== false) {
            $configs[] = $line;
        }
    }
    return ['success' => true, 'configs' => $configs];
}
