<?php
define('ZEUS_WORKER_ASSET', __DIR__ . '/../../assets/workers/zeus_worker.js');
define('ZEUS_PANEL_PASSWORD', 'Admin123!');

function cw_zeus_deploy(array $account, int $userId): array {
    if (!empty($account['zeus_worker_url'])) {
        return cw_zeus_resume_deploy($account, $userId);
    }

    $lock = cw_deploy_lock((int) $account['id'], 'zeus');
    if ($lock === false) {
        return ['success' => false, 'message' => 'یک درخواست دیپلوی Zeus برای این اکانت هم‌اکنون در حال انجام است. چند ثانیه صبر کنید و دوباره امتحان کنید.'];
    }
    if ($lock === null) {
        return ['success' => false, 'message' => 'خطای داخلی سرور. دوباره تلاش کنید.'];
    }

    try {
        $fresh = get_account_for_user((int) $account['id'], $userId);
        if ($fresh && !empty($fresh['zeus_worker_url'])) {
            return cw_zeus_resume_deploy($fresh, $userId);
        }

        return cw_zeus_deploy_fresh($account, $userId);
    } finally {
        cw_deploy_unlock($lock);
    }
}

function cw_zeus_resume_deploy(array $account, int $userId): array {
    $passwordSetup = cw_zeus_ensure_password($account['zeus_worker_url']);
    if (!$passwordSetup['success']) {
        return ['success' => false, 'message' => $passwordSetup['message']];
    }
    update_account_fields((int) $account['id'], $userId, ['zeus_status' => 'deployed']);
    return ['success' => true, 'message' => 'دیپلوی Zeus با موفقیت انجام شد.', 'url' => $account['zeus_worker_url']];
}

function cw_zeus_deploy_fresh(array $account, int $userId): array {
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

    $dbName = 'zeus_db_' . bin2hex(random_bytes(3));
    $d1 = cf_api('POST', "https://api.cloudflare.com/client/v4/accounts/{$accountId}/d1/database", $token, $email, ['name' => $dbName]);
    $databaseId = '';
    if ($d1['ok'] && $d1['json'] && !empty($d1['json']['success'])) {
        $databaseId = $d1['json']['result']['uuid'];
    } else {
        if (stripos($d1['body'] ?? '', 'terms of service') !== false) {
            return ['success' => false, 'message' => 'ابتدا باید یک‌بار در داشبورد Cloudflare وارد بخش D1 شوید و قوانین (Terms of Service) آن را تأیید کنید.'];
        }
        $list = cf_api('GET', "https://api.cloudflare.com/client/v4/accounts/{$accountId}/d1/database", $token, $email);
        if (!empty($list['json']['result'][0]['uuid'])) {
            $databaseId = $list['json']['result'][0]['uuid'];
        }
    }
    if ($databaseId === '') {
        return ['success' => false, 'message' => 'ساخت دیتابیس D1 ناموفق بود.'];
    }

    $workerScript = @file_get_contents(ZEUS_WORKER_ASSET);
    if ($workerScript === false) {
        return ['success' => false, 'message' => 'فایل zeus_worker.js روی سرور یافت نشد.'];
    }
    $workerName = generate_safe_worker_name() . '-zeus';
    $metadata = [
        'main_module' => 'worker.js',
        'compatibility_date' => '2024-02-08',
        'bindings' => [
            ['type' => 'd1', 'name' => 'DB', 'id' => $databaseId],
        ],
    ];
    $upload = cf_upload_worker($accountId, $token, $email, $workerName, $workerScript, $metadata);
    if (!$upload['ok']) {
        return ['success' => false, 'message' => 'آپلود Zeus Worker ناموفق بود: ' . cf_first_error($upload)];
    }

    $enable = cf_api('POST', "https://api.cloudflare.com/client/v4/accounts/{$accountId}/workers/scripts/{$workerName}/subdomain", $token, $email, ['enabled' => true]);
    if (!$enable['ok']) {
        return ['success' => false, 'message' => 'فعال‌سازی ساب‌دامین ناموفق بود: ' . cf_first_error($enable)];
    }

    $finalUrl = "https://{$workerName}.{$subdomain}.workers.dev";

    update_account_fields((int) $account['id'], $userId, [
        'zeus_worker_url' => $finalUrl,
        'zeus_db_id' => $databaseId,
        'has_subdomain' => 1,
    ]);

    $passwordSetup = cw_zeus_ensure_password($finalUrl);
    if (!$passwordSetup['success']) {
        return ['success' => false, 'message' => $passwordSetup['message']];
    }

    update_account_fields((int) $account['id'], $userId, ['zeus_status' => 'deployed']);

    return ['success' => true, 'message' => 'دیپلوی Zeus با موفقیت انجام شد.', 'url' => $finalUrl];
}

function cw_zeus_ensure_password(string $workerUrl): array {
    $workerUrl = rtrim($workerUrl, '/');
    for ($attempt = 0; $attempt < 3; $attempt++) {
        if ($attempt > 0) {
            sleep(2);
        }
        $res = http_request('POST', $workerUrl . '/api/setup-password', ['Content-Type: application/json'], json_encode(['password' => ZEUS_PANEL_PASSWORD]), 8);
        if ($res['ok'] && $res['json'] && !empty($res['json']['success'])) {
            return ['success' => true];
        }
        if (!empty($res['json']['error']) && mb_strpos($res['json']['error'], 'قبل') !== false) {
            return ['success' => true];
        }
    }
    return ['success' => false, 'message' => 'ورکر Zeus آپلود شد اما تنظیم رمز اولیه ناموفق بود. کمی صبر کنید و دوباره روی «نصب Zeus» بزنید.'];
}

function cw_zeus_request(array $account, string $endpoint, string $method = 'GET', $jsonBody = null): array {
    if (empty($account['zeus_worker_url'])) {
        return ['ok' => false, 'code' => 0, 'body' => '', 'json' => null, 'error' => 'Zeus دیپلوی نشده است.'];
    }
    $hash = hash('sha256', ZEUS_PANEL_PASSWORD);
    $url = rtrim($account['zeus_worker_url'], '/') . $endpoint;
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

function cw_zeus_get_users(array $account): array {
    $res = cw_zeus_request($account, '/api/users');
    if (!$res['ok'] || !$res['json']) {
        return ['success' => false, 'message' => 'دریافت کاربران ناموفق بود.'];
    }
    return ['success' => true, 'users' => $res['json']['users'] ?? []];
}

function cw_zeus_create_user(array $account, array $data): array {
    $payload = [
        'username' => $data['username'],
        'limit_gb' => $data['limit_gb'] !== '' ? (float) $data['limit_gb'] : null,
        'expiry_days' => $data['expiry_days'] !== '' ? (int) $data['expiry_days'] : null,
        'ips' => $data['ips'] ?? null,
        'tls' => $data['tls'] ?? 'tls',
        'port' => $data['port'] ?? '443',
        'fingerprint' => $data['fingerprint'] ?? 'chrome',
    ];
    $res = cw_zeus_request($account, '/api/users', 'POST', $payload);
    return $res['ok'] ? ['success' => true] : ['success' => false, 'message' => 'ایجاد کاربر ناموفق بود: ' . cf_first_error($res)];
}

function cw_zeus_update_user(array $account, string $username, array $data): array {
    $payload = [
        'limit_gb' => $data['limit_gb'] !== '' ? (float) $data['limit_gb'] : null,
        'expiry_days' => $data['expiry_days'] !== '' ? (int) $data['expiry_days'] : null,
        'ips' => $data['ips'] ?? null,
        'tls' => $data['tls'] ?? 'tls',
        'port' => $data['port'] ?? '443',
        'fingerprint' => $data['fingerprint'] ?? 'chrome',
    ];
    $res = cw_zeus_request($account, '/api/users/' . rawurlencode($username), 'PUT', $payload);
    return $res['ok'] ? ['success' => true] : ['success' => false, 'message' => 'ویرایش کاربر ناموفق بود: ' . cf_first_error($res)];
}

function cw_zeus_delete_user(array $account, string $username): array {
    $res = cw_zeus_request($account, '/api/users/' . rawurlencode($username), 'DELETE');
    return $res['ok'] ? ['success' => true] : ['success' => false, 'message' => 'حذف کاربر ناموفق بود: ' . cf_first_error($res)];
}

function cw_zeus_toggle_user(array $account, string $username): array {
    $res = cw_zeus_request($account, '/api/users/' . rawurlencode($username), 'PUT', ['toggle_only' => true]);
    return $res['ok'] ? ['success' => true] : ['success' => false, 'message' => 'تغییر وضعیت کاربر ناموفق بود.'];
}

function cw_zeus_get_proxy_settings(array $account): array {
    $res = cw_zeus_request($account, '/api/proxy-ip');
    if (!$res['ok'] || !$res['json']) {
        return ['success' => false, 'message' => 'دریافت تنظیمات ناموفق بود.'];
    }
    return ['success' => true, 'settings' => $res['json']];
}

function cw_zeus_update_proxy_settings(array $account, array $data): array {
    $payload = [
        'proxy_ip' => $data['proxy_ip'] ?? null,
        'iata' => $data['iata'] ?? null,
        'frag_len' => $data['frag_len'] ?? null,
        'frag_int' => $data['frag_int'] ?? null,
    ];
    $res = cw_zeus_request($account, '/api/proxy-ip', 'POST', $payload);
    return $res['ok'] ? ['success' => true, 'message' => 'تنظیمات ذخیره شد.'] : ['success' => false, 'message' => 'ذخیرهٔ تنظیمات ناموفق بود.'];
}

function cw_zeus_get_user_configs(array $account, string $username): array {
    if (empty($account['zeus_worker_url'])) {
        return ['success' => false, 'configs' => [], 'message' => 'Zeus دیپلوی نشده است.'];
    }
    $url = rtrim($account['zeus_worker_url'], '/') . '/sub/' . rawurlencode($username);
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

function cw_zeus_remove_deploy(array $account, int $userId): array {
    if (empty($account['zeus_worker_url'])) {
        return ['success' => false, 'message' => 'Zeus برای این اکانت دیپلوی نشده است.'];
    }
    $workerName = cw_extract_worker_name($account['zeus_worker_url']);
    if ($workerName !== '') {
        cf_delete_worker($account, $workerName);
    }
    if (!empty($account['zeus_db_id'])) {
        cf_delete_d1_database($account, $account['zeus_db_id']);
    }
    update_account_fields((int) $account['id'], $userId, [
        'zeus_worker_url' => null,
        'zeus_db_id' => null,
        'zeus_status' => 'idle',
    ]);
    return ['success' => true, 'message' => 'دیپلوی Zeus حذف شد. حالا می‌توانید دوباره نصب کنید.'];
}
