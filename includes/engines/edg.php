<?php
define('EDG_WORKER_ASSET', __DIR__ . '/../../assets/workers/edg_worker.js');
define('EDG_DEFAULT_PROXY_IP', 'proxyip.cmliussss.net');

function cw_edg_deploy(array $account, int $userId): array {
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

    $edgUuid = cw_uuid_v4();
    $edgAdminPass = !empty($account['edg_admin_pass']) ? $account['edg_admin_pass'] : bin2hex(random_bytes(4));
    $proxyIp = EDG_DEFAULT_PROXY_IP;

    $kvTitle = 'edg_' . bin2hex(random_bytes(4));
    $kvCreate = cf_api('POST', "https://api.cloudflare.com/client/v4/accounts/{$accountId}/storage/kv/namespaces", $token, $email, ['title' => $kvTitle]);
    $kvId = '';
    if ($kvCreate['ok'] && $kvCreate['json'] && !empty($kvCreate['json']['success'])) {
        $kvId = $kvCreate['json']['result']['id'];
    }
    if ($kvId === '') {
        return ['success' => false, 'message' => 'ساخت KV Namespace برای EDG ناموفق بود.'];
    }
    update_account_fields((int) $account['id'], $userId, ['edg_kv_namespace_id' => $kvId]);

    $workerScript = @file_get_contents(EDG_WORKER_ASSET);
    if ($workerScript === false) {
        return ['success' => false, 'message' => 'فایل edg_worker.js روی سرور یافت نشد.'];
    }
    $workerName = generate_safe_worker_name() . '-edg';
    $metadata = [
        'main_module' => 'worker.js',
        'compatibility_date' => '2024-03-03',
        'bindings' => [
            ['type' => 'plain_text', 'name' => 'UUID', 'text' => $edgUuid],
            ['type' => 'plain_text', 'name' => 'PROXYIP', 'text' => $proxyIp],
            ['type' => 'plain_text', 'name' => 'ADMIN', 'text' => $edgAdminPass],
            ['type' => 'kv_namespace', 'name' => 'KV', 'namespace_id' => $kvId],
        ],
    ];
    $upload = cf_upload_worker($accountId, $token, $email, $workerName, $workerScript, $metadata);
    if (!$upload['ok']) {
        return ['success' => false, 'message' => 'آپلود EDG Worker ناموفق بود: ' . cf_first_error($upload)];
    }

    $enable = cf_api('POST', "https://api.cloudflare.com/client/v4/accounts/{$accountId}/workers/scripts/{$workerName}/subdomain", $token, $email, ['enabled' => true]);
    if (!$enable['ok']) {
        return ['success' => false, 'message' => 'فعال‌سازی ساب‌دامین ناموفق بود: ' . cf_first_error($enable)];
    }

    $finalUrl = "https://{$workerName}.{$subdomain}.workers.dev";
    update_account_fields((int) $account['id'], $userId, [
        'edg_status' => 'deployed',
        'edg_worker_url' => $finalUrl,
        'edg_uuid' => $edgUuid,
        'edg_admin_pass' => $edgAdminPass,
        'has_subdomain' => 1,
    ]);

    return ['success' => true, 'message' => 'دیپلوی EDG با موفقیت انجام شد.', 'url' => $finalUrl];
}

function cw_edg_fetch_configs(array $account): array {
    if (empty($account['edg_worker_url']) || empty($account['edg_uuid'])) {
        return ['success' => false, 'configs' => [], 'message' => 'ابتدا باید EDG را دیپلوی کنید.'];
    }
    $workerHost = preg_replace('#^https?://#', '', rtrim($account['edg_worker_url'], '/'));
    $uuid = $account['edg_uuid'];

    $proxyIpStr = '';
    if (!empty($account['edg_kv_namespace_id']) && !empty($account['account_id'])) {
        $token = account_auth_token($account);
        $email = $account['email'];
        $res = cf_api('GET', "https://api.cloudflare.com/client/v4/accounts/{$account['account_id']}/storage/kv/namespaces/{$account['edg_kv_namespace_id']}/values/config.json", $token, $email);
        if ($res['ok'] && $res['json']) {
            $fanDai = $res['json']['反代'] ?? null;
            if ($fanDai) {
                $ip = $fanDai['PROXYIP'] ?? '';
                if ($ip !== '' && $ip !== 'auto') {
                    $proxyIpStr = 'proxyip=' . $ip . '&';
                }
            }
        }
    }

    $queryParam = $proxyIpStr !== '' ? "?{$proxyIpStr}ed=2560" : '?ed=2560';
    $encodedPath = urlencode('/' . $queryParam);

    $variants = [
        ['suffix' => 'EDG-Auto', 'address' => $workerHost],
        ['suffix' => 'EDG-CF1', 'address' => '104.21.5.155'],
        ['suffix' => 'EDG-CF2', 'address' => '172.67.13.12'],
    ];
    $configs = [];
    foreach ($variants as $v) {
        $uri = "vless://{$uuid}@{$v['address']}:443?encryption=none&security=tls&sni={$workerHost}&type=ws&host={$workerHost}&path={$encodedPath}#{$v['suffix']}";
        $configs[] = apply_sni_camouflage($uri);
    }
    return ['success' => true, 'configs' => $configs];
}

function cw_edg_fetch_config_json(array $account): array {
    if (empty($account['edg_kv_namespace_id'])) {
        return ['success' => false, 'message' => 'این اکانت با نسخهٔ قدیمی EDG دیپلوی شده و قابل تنظیم نیست. دوباره دیپلوی کنید.'];
    }
    $token = account_auth_token($account);
    $email = $account['email'];
    $res = cf_api('GET', "https://api.cloudflare.com/client/v4/accounts/{$account['account_id']}/storage/kv/namespaces/{$account['edg_kv_namespace_id']}/values/config.json", $token, $email);
    if (!$res['ok']) {
        return ['success' => true, 'config' => [], 'proxy_ip' => ''];
    }
    $json = $res['json'] ?? [];
    $proxyIp = $json['反代']['PROXYIP'] ?? '';
    if ($proxyIp === 'auto') {
        $proxyIp = '';
    }
    return ['success' => true, 'config' => $json, 'proxy_ip' => $proxyIp];
}

function cw_edg_update_proxy_ip(array $account, string $proxyIp): array {
    if (empty($account['edg_kv_namespace_id'])) {
        return ['success' => false, 'message' => 'این اکانت قابل تنظیم نیست.'];
    }
    $current = cw_edg_fetch_config_json($account);
    $config = $current['config'] ?? [];
    if (!isset($config['反代']) || !is_array($config['反代'])) {
        $config['反代'] = [];
    }
    $config['反代']['PROXYIP'] = $proxyIp !== '' ? $proxyIp : 'auto';

    $token = account_auth_token($account);
    $email = $account['email'];
    $res = cf_api('PUT', "https://api.cloudflare.com/client/v4/accounts/{$account['account_id']}/storage/kv/namespaces/{$account['edg_kv_namespace_id']}/values/config.json", $token, $email, $config);
    if (!$res['ok']) {
        return ['success' => false, 'message' => 'ذخیرهٔ تنظیمات ناموفق بود: ' . cf_first_error($res)];
    }
    return ['success' => true, 'message' => 'تنظیمات EDG ذخیره شد.'];
}
