<?php
define('NAHAN_WORKER_ASSET', __DIR__ . '/../../assets/workers/nahan_worker.js');

function cw_nahan_deploy(array $account, int $userId): array {
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

    $dbName = 'nhn_db_' . bin2hex(random_bytes(3));
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

    $workerScript = @file_get_contents(NAHAN_WORKER_ASSET);
    if ($workerScript === false) {
        return ['success' => false, 'message' => 'فایل nahan_worker.js روی سرور یافت نشد.'];
    }
    $workerName = generate_safe_worker_name() . '-nhn';
    $metadata = [
        'main_module' => 'worker.js',
        'compatibility_date' => '2024-03-03',
        'bindings' => [
            ['type' => 'd1', 'name' => 'IOT_DB', 'id' => $databaseId],
        ],
    ];
    $upload = cf_upload_worker($accountId, $token, $email, $workerName, $workerScript, $metadata);
    if (!$upload['ok']) {
        return ['success' => false, 'message' => 'آپلود Nahan Worker ناموفق بود: ' . cf_first_error($upload)];
    }

    $enable = cf_api('POST', "https://api.cloudflare.com/client/v4/accounts/{$accountId}/workers/scripts/{$workerName}/subdomain", $token, $email, ['enabled' => true]);
    if (!$enable['ok']) {
        return ['success' => false, 'message' => 'فعال‌سازی ساب‌دامین ناموفق بود: ' . cf_first_error($enable)];
    }

    $finalUrl = "https://{$workerName}.{$subdomain}.workers.dev";
    update_account_fields((int) $account['id'], $userId, [
        'nahan_status' => 'deployed',
        'nahan_worker_url' => $finalUrl,
        'nahan_db_id' => $databaseId,
        'nahan_master_key' => 'admin',
        'nahan_api_route' => 'sync',
        'has_subdomain' => 1,
    ]);

    return ['success' => true, 'message' => 'دیپلوی Nahan با موفقیت انجام شد.', 'url' => $finalUrl];
}

function cw_nahan_default_config(): array {
    return [
        'mode' => 'both',
        'apiRoute' => 'sync',
        'masterKey' => '',
        'socketPorts' => '443, 8443, 2053, 2083, 2087, 2096',
        'maintenanceHost' => 'ubuntu.com',
        'resolveIp' => '1.1.1.1',
        'agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'customRelay' => '',
        'backupRelay' => '',
        'customDns' => 'https://cloudflare-dns.com/dns-query',
        'cleanIps' => '',
        'tgToken' => '',
        'tgChatId' => '',
        'users' => [],
    ];
}

function cw_nahan_config_from_json(array $json): array {
    $defaults = cw_nahan_default_config();
    $json = array_merge($defaults, $json);

    $tlsPorts = array_values(array_filter(array_map(function ($p) {
        $p = trim($p);
        return $p === '' ? null : (int) $p;
    }, explode(',', $json['socketPorts'])), function ($v) { return $v !== null; }));

    $users = [];
    foreach (($json['users'] ?? []) as $u) {
        $users[] = [
            'name' => $u['name'] ?? '',
            'uuid' => $u['id'] ?? ($u['uuid'] ?? ''),
            'limit' => (int) ($u['limitTotalReq'] ?? ($u['limit'] ?? 0)),
            'reset' => (int) ($u['reset'] ?? 0),
            'used' => (int) ($u['used'] ?? 0),
            'expiryMs' => (int) ($u['expiryMs'] ?? 0),
            'limitDailyReq' => (int) ($u['limitDailyReq'] ?? 0),
            'isPaused' => (bool) ($u['isPaused'] ?? false),
            'proxyIp' => $u['proxyIp'] ?? '',
        ];
    }

    return [
        'protocol' => ucfirst(strtolower($json['mode'] ?: 'both')),
        'apiRoute' => $json['apiRoute'] ?: 'sync',
        'masterKey' => $json['masterKey'] ?: '',
        'tlsPorts' => $tlsPorts ?: [443, 8443, 2053, 2083, 2087, 2096],
        'maintenanceHost' => $json['maintenanceHost'],
        'resolveIp' => $json['resolveIp'],
        'customDns' => $json['customDns'],
        'customRelay' => $json['customRelay'],
        'backupRelay' => $json['backupRelay'],
        'agent' => $json['agent'],
        'cleanIps' => $json['cleanIps'],
        'tgBotToken' => $json['tgToken'],
        'tgChatId' => $json['tgChatId'],
        'users' => $users,
    ];
}

function cw_nahan_config_to_json(array $config): array {
    $users = [];
    foreach (($config['users'] ?? []) as $u) {
        $users[] = [
            'name' => $u['name'],
            'id' => $u['uuid'],
            'limitTotalReq' => (int) $u['limit'],
            'reset' => (int) ($u['reset'] ?? 0),
            'used' => (int) ($u['used'] ?? 0),
            'expiryMs' => (int) $u['expiryMs'],
            'limitDailyReq' => (int) $u['limitDailyReq'],
            'isPaused' => (bool) $u['isPaused'],
            'proxyIp' => $u['proxyIp'] ?? '',
        ];
    }
    return [
        'mode' => strtolower($config['protocol']),
        'apiRoute' => $config['apiRoute'],
        'masterKey' => $config['masterKey'],
        'socketPorts' => implode(',', $config['tlsPorts']),
        'maintenanceHost' => $config['maintenanceHost'],
        'resolveIp' => $config['resolveIp'],
        'agent' => $config['agent'],
        'customRelay' => $config['customRelay'],
        'backupRelay' => $config['backupRelay'],
        'customDns' => $config['customDns'],
        'users' => $users,
        'cleanIps' => $config['cleanIps'],
        'tgToken' => $config['tgBotToken'],
        'tgChatId' => $config['tgChatId'],
    ];
}

function cw_nahan_auth_key(array $account): string {
    return !empty($account['nahan_master_key']) ? $account['nahan_master_key'] : 'admin';
}

function cw_nahan_fetch_settings(array $account): array {
    if (empty($account['nahan_worker_url'])) {
        return ['success' => false, 'message' => 'Nahan دیپلوی نشده است.'];
    }
    $baseUrl = rtrim($account['nahan_worker_url'], '/');
    $apiRoute = trim($account['nahan_api_route'] ?: 'sync', '/');
    $res = http_request('POST', "{$baseUrl}/{$apiRoute}/api/auth", ['Content-Type: application/json'], json_encode(['key' => cw_nahan_auth_key($account)]), 15);
    if (!$res['ok'] || !$res['json'] || empty($res['json']['success'])) {
        return ['success' => false, 'message' => 'دریافت تنظیمات Nahan ناموفق بود.'];
    }
    $configObj = $res['json']['config'] ?? null;
    if (!$configObj) {
        return ['success' => false, 'message' => 'پاسخ ورکر فاقد config بود.'];
    }
    return ['success' => true, 'config' => cw_nahan_config_from_json($configObj)];
}

function cw_nahan_sync_settings(array $account, array $config): array {
    if (empty($account['nahan_worker_url'])) {
        return ['success' => false, 'message' => 'Nahan دیپلوی نشده است.'];
    }
    $baseUrl = rtrim($account['nahan_worker_url'], '/');
    $apiRoute = trim($config['apiRoute'] ?: 'sync', '/');
    $payload = ['key' => $config['masterKey'], 'config' => cw_nahan_config_to_json($config)];
    $res = http_request('POST', "{$baseUrl}/{$apiRoute}/api/sync", ['Content-Type: application/json'], json_encode($payload, JSON_UNESCAPED_UNICODE), 20);
    if (!$res['ok'] || !$res['json'] || empty($res['json']['success'])) {
        return ['success' => false, 'message' => 'ذخیرهٔ تنظیمات Nahan ناموفق بود.'];
    }
    return ['success' => true, 'message' => 'تنظیمات Nahan ذخیره و همگام شد.'];
}

function cw_nahan_fetch_admin_nodes(array $account): array {
    if (empty($account['nahan_worker_url'])) {
        return ['success' => false, 'configs' => [], 'message' => 'Nahan دیپلوی نشده است.'];
    }
    $baseUrl = rtrim($account['nahan_worker_url'], '/');
    $apiRoute = trim($account['nahan_api_route'] ?: 'sync', '/');
    $res = http_request('GET', "{$baseUrl}/{$apiRoute}?flag=a", [], null, 15);

    if ($res['ok']) {
        $decoded = base64_decode(trim($res['body']), true);
        $configs = [];
        if ($decoded !== false) {
            foreach (preg_split('/\r\n|\r|\n/', $decoded) as $line) {
                $line = trim($line);
                if ($line !== '') {
                    $configs[] = apply_sni_camouflage($line);
                }
            }
        }
        return ['success' => true, 'configs' => $configs];
    }

    if ($res['code'] === 403) {
        $settings = cw_nahan_fetch_settings($account);
        $configs = [];
        if ($settings['success']) {
            foreach ($settings['config']['users'] as $user) {
                if ($user['isPaused']) {
                    continue;
                }
                $userRes = cw_nahan_fetch_user_nodes($account, $user['name']);
                if ($userRes['success']) {
                    $configs = array_merge($configs, $userRes['configs']);
                }
            }
        }
        return ['success' => true, 'configs' => $configs];
    }

    return ['success' => false, 'configs' => [], 'message' => 'دریافت کانفیگ ناموفق بود (کد ' . $res['code'] . ').'];
}

function cw_nahan_fetch_user_nodes(array $account, string $userName): array {
    if (empty($account['nahan_worker_url'])) {
        return ['success' => false, 'configs' => [], 'message' => 'Nahan دیپلوی نشده است.'];
    }
    $baseUrl = rtrim($account['nahan_worker_url'], '/');
    $apiRoute = trim($account['nahan_api_route'] ?: 'sync', '/');
    $encodedName = rawurlencode($userName);
    $lastError = 'خطای ناشناخته در دریافت نودها';

    for ($retries = 3; $retries > 0; $retries--) {
        $res = http_request('GET', "{$baseUrl}/{$apiRoute}?sub={$encodedName}&flag=a", [], null, 15);
        if ($res['ok']) {
            $decoded = base64_decode(trim($res['body']), true);
            if ($decoded !== false) {
                $validNodes = [];
                foreach (preg_split('/\r\n|\r|\n/', $decoded) as $line) {
                    $line = trim($line);
                    if ($line !== '' && (strpos($line, 'vless://') === 0 || strpos($line, 'trojan://') === 0)) {
                        $validNodes[] = apply_sni_camouflage($line);
                    }
                }
                if ($validNodes) {
                    return ['success' => true, 'configs' => $validNodes];
                }
            }
            $lastError = 'خروجی نامعتبر است.';
        } elseif ($res['code'] === 403) {
            return ['success' => false, 'configs' => [], 'message' => 'خطای دسترسی (کد ۴۰۳).'];
        } else {
            $lastError = 'خطا در دریافت اطلاعات (کد ' . $res['code'] . ').';
        }
        if ($retries > 1) {
            usleep(700000);
        }
    }
    return ['success' => false, 'configs' => [], 'message' => $lastError];
}
