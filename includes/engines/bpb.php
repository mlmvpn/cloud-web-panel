<?php
define('BPB_WORKER_ASSET', __DIR__ . '/../../assets/workers/worker.js');

function cw_bpb_deploy(array $account, int $userId): array {
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
            return ['success' => false, 'message' => 'ساخت ساب‌دامین Workers ناموفق بود. لطفاً از داشبورد Cloudflare دستی بسازید.'];
        }
    }

    $workerUuid = cw_uuid_v4();
    $trPass = bin2hex(random_bytes(16));
    $subPath = bin2hex(random_bytes(4));

    $namespaceId = '';
    $kvCreate = cf_api('POST', "https://api.cloudflare.com/client/v4/accounts/{$accountId}/storage/kv/namespaces", $token, $email, ['title' => 'mlmvpn']);
    if ($kvCreate['ok'] && $kvCreate['json'] && !empty($kvCreate['json']['success'])) {
        $namespaceId = $kvCreate['json']['result']['id'];
    } else {
        $list = cf_api('GET', "https://api.cloudflare.com/client/v4/accounts/{$accountId}/storage/kv/namespaces", $token, $email);
        foreach (($list['json']['result'] ?? []) as $item) {
            if (strpos($item['title'], 'mlmvpn') !== false) {
                $namespaceId = $item['id'];
                break;
            }
        }
    }
    if ($namespaceId === '') {
        return ['success' => false, 'message' => 'ساخت KV Namespace ناموفق بود.'];
    }

    $workerScript = @file_get_contents(BPB_WORKER_ASSET);
    if ($workerScript === false) {
        return ['success' => false, 'message' => 'فایل worker.js روی سرور یافت نشد.'];
    }
    $workerName = generate_safe_worker_name();
    $metadata = [
        'main_module' => 'worker.js',
        'compatibility_date' => '2024-03-03',
        'bindings' => [
            ['type' => 'kv_namespace', 'name' => 'kv', 'namespace_id' => $namespaceId],
            ['type' => 'secret_text', 'name' => 'UUID', 'text' => $workerUuid],
            ['type' => 'secret_text', 'name' => 'TR_PASS', 'text' => $trPass],
            ['type' => 'secret_text', 'name' => 'SUB_PATH', 'text' => $subPath],
        ],
    ];
    $upload = cf_upload_worker($accountId, $token, $email, $workerName, $workerScript, $metadata);
    if (!$upload['ok']) {
        return ['success' => false, 'message' => 'آپلود Worker ناموفق بود: ' . cf_first_error($upload)];
    }

    $proxySettings = [
        'remoteDNS' => 'https://8.8.8.8/dns-query',
        'localDNS' => '8.8.8.8',
        'antiSanctionDNS' => '178.22.122.100',
        'enableIPv6' => true,
        'allowLANConnection' => false,
        'proxyIPMode' => 'proxyip',
        'proxyIPs' => ['bpb.yousef.isegaro.com'],
        'prefixes' => [],
        'cleanIPs' => [],
        'VLConfigs' => true,
        'TRConfigs' => true,
        'ports' => [443],
        'fingerprint' => 'chrome',
        'bypassIran' => false,
        'blockAds' => false,
        'blockPorn' => false,
        'panelVersion' => '4.2.2',
    ];
    cf_api('PUT', "https://api.cloudflare.com/client/v4/accounts/{$accountId}/storage/kv/namespaces/{$namespaceId}/values/proxySettings", $token, $email, $proxySettings);

    $enable = cf_api('POST', "https://api.cloudflare.com/client/v4/accounts/{$accountId}/workers/scripts/{$workerName}/subdomain", $token, $email, ['enabled' => true]);
    if (!$enable['ok']) {
        return ['success' => false, 'message' => 'فعال‌سازی ساب‌دامین ناموفق بود: ' . cf_first_error($enable)];
    }

    $finalUrl = "https://{$workerName}.{$subdomain}.workers.dev";
    cf_raw('PUT', "https://api.cloudflare.com/client/v4/accounts/{$accountId}/storage/kv/namespaces/{$namespaceId}/values/pwd", $token, $email, 'Admin123!', 'text/plain');

    update_account_fields((int) $account['id'], $userId, [
        'status' => 'deployed',
        'worker_url' => $finalUrl,
        'uuid' => $workerUuid,
        'tr_pass' => $trPass,
        'sub_path' => $subPath,
        'has_subdomain' => 1,
    ]);

    return ['success' => true, 'message' => 'دیپلوی BPB با موفقیت انجام شد.', 'url' => $finalUrl];
}

function cw_bpb_fetch_configs(array $account): array {
    if (empty($account['worker_url']) || empty($account['sub_path']) || empty($account['tr_pass'])) {
        return ['success' => false, 'configs' => [], 'message' => 'ابتدا باید BPB را دیپلوی کنید.'];
    }
    $subUrl = rtrim($account['worker_url'], '/') . '/sub/raw/' . $account['sub_path'] . '?app=xray';
    $res = http_request('GET', $subUrl, [], null, 20);
    if (!$res['ok']) {
        return ['success' => false, 'configs' => [], 'message' => 'دریافت از ورکر ناموفق بود (کد ' . $res['code'] . ').'];
    }
    $decoded = base64_decode(trim($res['body']), true);
    if ($decoded === false) {
        return ['success' => false, 'configs' => [], 'message' => 'رمزگشایی Base64 ناموفق بود.'];
    }
    $configs = [];
    foreach (preg_split('/\r\n|\r|\n/', $decoded) as $line) {
        $line = trim(str_replace('💦', 'mlmvpn', $line));
        if ($line !== '') {
            $configs[] = apply_sni_camouflage($line);
        }
    }
    return ['success' => true, 'configs' => $configs];
}

function cw_bpb_ensure_login(array $account): array {
    $workerUrl = rtrim($account['worker_url'], '/');

    $res = http_request_with_headers('POST', $workerUrl . '/login/authenticate', ['Content-Type: text/plain'], 'Admin123!', 8);
    if ($res['ok'] && !empty($res['headers']['set-cookie'])) {
        return ['success' => true, 'cookie' => build_cookie_header($res['headers']['set-cookie'])];
    }

    cw_bpb_force_reset_password($account);
    sleep(1);

    $res = http_request_with_headers('POST', $workerUrl . '/login/authenticate', ['Content-Type: text/plain'], 'Admin123!', 8);
    if ($res['ok'] && !empty($res['headers']['set-cookie'])) {
        return ['success' => true, 'cookie' => build_cookie_header($res['headers']['set-cookie'])];
    }

    if ($res['code'] === 0) {
        return ['success' => false, 'message' => 'اتصال به ورکر برقرار نشد. اگر همین الان BPB را دیپلوی کرده‌اید، گواهی SSL ساب‌دامین ممکن است هنوز فعال نشده باشد — ۱ تا ۲ دقیقه صبر کنید و دوباره تلاش کنید.'];
    }
    return ['success' => false, 'message' => 'ورود به پنل ناموفق بود (کد ' . $res['code'] . '). کمی صبر کنید و دوباره تلاش کنید.'];
}

function cw_bpb_force_reset_password(array $account): void {
    $token = account_auth_token($account);
    $email = $account['email'];
    $accountId = $account['account_id'];

    $list = cf_api('GET', "https://api.cloudflare.com/client/v4/accounts/{$accountId}/storage/kv/namespaces", $token, $email, null, 10);
    $namespaceId = '';
    foreach (($list['json']['result'] ?? []) as $item) {
        if (strpos($item['title'], 'mlmvpn') !== false) {
            $namespaceId = $item['id'];
            break;
        }
    }
    if ($namespaceId !== '') {
        cf_raw('PUT', "https://api.cloudflare.com/client/v4/accounts/{$accountId}/storage/kv/namespaces/{$namespaceId}/values/pwd", $token, $email, 'Admin123!', 'text/plain', 10);
    }
}

function cw_bpb_fetch_settings(array $account): array {
    if (empty($account['worker_url'])) {
        return ['success' => false, 'message' => 'BPB دیپلوی نشده است.'];
    }
    $login = cw_bpb_ensure_login($account);
    if (!$login['success']) {
        return ['success' => false, 'message' => $login['message']];
    }
    $res = http_request('GET', rtrim($account['worker_url'], '/') . '/panel/settings', ['Cookie: ' . $login['cookie']], null, 15);
    if (!$res['ok'] || !$res['json'] || empty($res['json']['success'])) {
        return ['success' => false, 'message' => 'دریافت تنظیمات ناموفق بود.'];
    }
    return ['success' => true, 'settings' => $res['json']['body']['proxySettings'] ?? []];
}

function cw_bpb_update_settings(array $account, array $settings): array {
    if (empty($account['worker_url'])) {
        return ['success' => false, 'message' => 'BPB دیپلوی نشده است.'];
    }
    $login = cw_bpb_ensure_login($account);
    if (!$login['success']) {
        return ['success' => false, 'message' => 'ورود ناموفق: ' . $login['message']];
    }
    $res = http_request('PUT', rtrim($account['worker_url'], '/') . '/panel/update-settings', [
        'Cookie: ' . $login['cookie'],
        'Content-Type: application/json',
    ], json_encode($settings, JSON_UNESCAPED_UNICODE), 15);
    if (!$res['ok']) {
        return ['success' => false, 'message' => 'به‌روزرسانی تنظیمات ناموفق بود (کد ' . $res['code'] . ').'];
    }
    return ['success' => true, 'message' => 'تنظیمات با موفقیت به‌روزرسانی شد.'];
}

function cw_uuid_v4(): string {
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}
