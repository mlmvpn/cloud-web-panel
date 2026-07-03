<?php
define('CF_OAUTH_AUTHORIZE_URL', 'https://dash.cloudflare.com/oauth2/auth');
define('CF_OAUTH_TOKEN_URL', 'https://dash.cloudflare.com/oauth2/token');

function cf_oauth_is_configured(): bool {
    return defined('OAUTH_CLIENT_ID') && defined('OAUTH_CLIENT_SECRET') && OAUTH_CLIENT_ID !== '' && OAUTH_CLIENT_SECRET !== '';
}

function cf_oauth_redirect_uri(): string {
    return full_url('/api/oauth_callback.php');
}

function cf_oauth_authorize_url(): ?string {
    if (!cf_oauth_is_configured()) {
        return null;
    }
    $state = bin2hex(random_bytes(24));
    $_SESSION['oauth_state'] = $state;

    $params = [
        'response_type' => 'code',
        'client_id' => OAUTH_CLIENT_ID,
        'redirect_uri' => cf_oauth_redirect_uri(),
        'state' => $state,
    ];
    return CF_OAUTH_AUTHORIZE_URL . '?' . http_build_query($params);
}

function cf_oauth_exchange_code(string $code): array {
    if (!cf_oauth_is_configured()) {
        return ['success' => false, 'message' => 'اتصال با Cloudflare OAuth روی این سایت پیکربندی نشده است.'];
    }
    $params = [
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => cf_oauth_redirect_uri(),
        'client_id' => OAUTH_CLIENT_ID,
        'client_secret' => OAUTH_CLIENT_SECRET,
    ];
    $res = http_request('POST', CF_OAUTH_TOKEN_URL, ['Content-Type: application/x-www-form-urlencoded'], http_build_query($params), 20);
    if (!$res['ok'] || !$res['json'] || empty($res['json']['access_token'])) {
        return ['success' => false, 'message' => 'دریافت توکن از کلادفلر ناموفق بود: ' . cf_first_error($res)];
    }
    return ['success' => true] + $res['json'];
}

function cf_oauth_refresh(string $refreshToken): array {
    if (!cf_oauth_is_configured()) {
        return ['success' => false, 'message' => 'اتصال با Cloudflare OAuth روی این سایت پیکربندی نشده است.'];
    }
    $params = [
        'grant_type' => 'refresh_token',
        'refresh_token' => $refreshToken,
        'client_id' => OAUTH_CLIENT_ID,
        'client_secret' => OAUTH_CLIENT_SECRET,
    ];
    $res = http_request('POST', CF_OAUTH_TOKEN_URL, ['Content-Type: application/x-www-form-urlencoded'], http_build_query($params), 20);
    if (!$res['ok'] || !$res['json'] || empty($res['json']['access_token'])) {
        return ['success' => false, 'message' => 'تمدید توکن ناموفق بود: ' . cf_first_error($res)];
    }
    return ['success' => true] + $res['json'];
}

function oauth_get_valid_access_token(array $account): string {
    static $cache = [];
    $id = (int) $account['id'];
    if (isset($cache[$id])) {
        return $cache[$id];
    }

    $expiresAt = $account['oauth_expires_at'] ?? null;
    $needsRefresh = !$expiresAt || strtotime($expiresAt) <= (time() + 60);

    if (!$needsRefresh) {
        $cache[$id] = decrypt_for_user($account['token']);
        return $cache[$id];
    }

    $refreshToken = decrypt_for_user($account['oauth_refresh_token'] ?? '');
    if ($refreshToken === '') {
        $cache[$id] = decrypt_for_user($account['token']);
        return $cache[$id];
    }

    $result = cf_oauth_refresh($refreshToken);
    if (!$result['success']) {
        $cache[$id] = decrypt_for_user($account['token']);
        return $cache[$id];
    }

    update_account_fields($id, (int) $account['user_id'], [
        'token' => encrypt_for_user($result['access_token']),
        'oauth_refresh_token' => encrypt_for_user($result['refresh_token'] ?? $refreshToken),
        'oauth_expires_at' => date('Y-m-d H:i:s', time() + (int) ($result['expires_in'] ?? 3600)),
    ]);

    $cache[$id] = $result['access_token'];
    return $cache[$id];
}

function cf_oauth_link_account(int $userId, array $tokenResult): array {
    $accessToken = $tokenResult['access_token'];

    $accResp = cf_api('GET', 'https://api.cloudflare.com/client/v4/accounts', $accessToken, '');
    if (!$accResp['ok'] || !$accResp['json'] || empty($accResp['json']['success']) || empty($accResp['json']['result'])) {
        return ['success' => false, 'message' => 'دریافت اطلاعات اکانت کلادفلر ناموفق بود.'];
    }
    $first = $accResp['json']['result'][0];
    $accountName = $first['name'] ?? 'Unknown';
    $accountId = $first['id'] ?? '';

    $userResp = cf_api('GET', 'https://api.cloudflare.com/client/v4/user', $accessToken, '');
    $email = $userResp['json']['result']['email'] ?? '';

    $expiresAt = date('Y-m-d H:i:s', time() + (int) ($tokenResult['expires_in'] ?? 3600));
    $encToken = encrypt_for_user($accessToken);
    $encRefresh = encrypt_for_user($tokenResult['refresh_token'] ?? '');

    $existing = db()->prepare('SELECT id FROM cloud_accounts WHERE user_id = ? AND account_id = ?');
    $existing->execute([$userId, $accountId]);
    $row = $existing->fetch();

    if ($row) {
        update_account_fields((int) $row['id'], $userId, [
            'auth_type' => 'oauth',
            'token' => $encToken,
            'oauth_refresh_token' => $encRefresh,
            'oauth_expires_at' => $expiresAt,
            'email' => $email,
            'name' => $accountName,
        ]);
        return ['success' => true, 'message' => 'اکانت «' . $accountName . '» دوباره متصل شد.'];
    }

    $status = cf_check_account_status($accessToken, '', $accountId);
    $stmt = db()->prepare('INSERT INTO cloud_accounts
        (user_id, email, token, name, account_id, status, is_email_verified, has_subdomain, auth_type, oauth_refresh_token, oauth_expires_at)
        VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?)');
    $stmt->execute([
        $userId, $email, $encToken, $accountName, $accountId, 'active',
        $status['has_subdomain'] ? 1 : 0,
        'oauth', $encRefresh, $expiresAt,
    ]);

    return ['success' => true, 'message' => 'اکانت «' . $accountName . '» با موفقیت از طریق Cloudflare متصل شد.'];
}
