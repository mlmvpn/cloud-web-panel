<?php
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function csrf_check(): bool {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    return !empty($_SESSION['csrf_token']) && !empty($token) && hash_equals($_SESSION['csrf_token'], (string) $token);
}

function current_user_id(): ?int {
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

function is_authenticated(): bool {
    return current_user_id() !== null && !empty($_SESSION['data_key']);
}

function current_user_is_admin(): bool {
    $id = current_user_id();
    if (!$id) {
        return false;
    }
    static $firstUserId = null;
    if ($firstUserId === null) {
        $firstUserId = (int) db()->query('SELECT MIN(id) FROM users')->fetchColumn();
    }
    return $id === $firstUserId;
}

function require_admin(): void {
    require_login();
    if (!current_user_is_admin()) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'دسترسی غیرمجاز — این بخش فقط برای مدیر سایت است.';
        exit;
    }
}

function api_require_admin(): int {
    $id = api_require_login();
    if (!current_user_is_admin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'این بخش فقط برای مدیر سایت است.']);
        exit;
    }
    return $id;
}

function current_user(): ?array {
    static $cached = null;
    static $fetched = false;
    if ($fetched) {
        return $cached;
    }
    $fetched = true;
    $id = current_user_id();
    if (!$id) {
        return null;
    }
    $stmt = db()->prepare('SELECT id, email, created_at FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    $cached = $row ?: null;
    return $cached;
}

function login_user(int $userId, string $dataKey): void {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['data_key'] = bin2hex($dataKey);
}

function logout_user(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function require_login(): void {
    if (!is_authenticated()) {
        $return = urlencode($_SERVER['REQUEST_URI'] ?? url('/dashboard.php'));
        header('Location: ' . url('/login.php') . '?return=' . $return);
        exit;
    }
}

function api_require_login(): int {
    if (!is_authenticated()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'نشست شما نامعتبر است. دوباره وارد حساب کاربری خود شوید.']);
        exit;
    }
    return current_user_id();
}

function api_prologue(): array {
    header('Content-Type: application/json; charset=utf-8');
    $userId = api_require_login();
    api_check_csrf();
    $input = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = [];
    }
    return [$userId, $input];
}

function api_check_csrf(): void {
    if (!csrf_check()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'درخواست نامعتبر. لطفاً صفحه را رفرش کنید.']);
        exit;
    }
}

function flash(string $type, string $message): void {
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array {
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

function valid_email(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function attempt_login(string $email, string $password): ?array {
    $stmt = db()->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        return $user;
    }
    return null;
}

function register_user(string $email, string $password): array {
    if (!valid_email($email)) {
        return ['success' => false, 'message' => 'ایمیل وارد شده معتبر نیست.'];
    }
    if (strlen($password) < 6) {
        return ['success' => false, 'message' => 'رمز عبور باید حداقل ۶ کاراکتر باشد.'];
    }
    $stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'این ایمیل قبلاً ثبت‌نام کرده است.'];
    }
    $kdfSalt = generate_kdf_salt();
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = db()->prepare('INSERT INTO users (email, password_hash, kdf_salt) VALUES (?, ?, ?)');
    $stmt->execute([$email, $hash, $kdfSalt]);
    return [
        'success' => true,
        'id' => (int) db()->lastInsertId(),
        'data_key' => derive_data_key($password, $kdfSalt),
    ];
}

function ensure_user_data_key(array $user, string $password): string {
    if (!empty($user['kdf_salt'])) {
        return derive_data_key($password, $user['kdf_salt']);
    }

    $salt = generate_kdf_salt();
    $newKey = derive_data_key($password, $salt);
    migrate_user_secrets_to_new_key((int) $user['id'], $newKey);

    $stmt = db()->prepare('UPDATE users SET kdf_salt = ? WHERE id = ?');
    $stmt->execute([$salt, (int) $user['id']]);

    return $newKey;
}

function migrate_user_secrets_to_new_key(int $userId, string $newKey): void {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $accStmt = $pdo->prepare('SELECT id, token, oauth_refresh_token FROM cloud_accounts WHERE user_id = ?');
        $accStmt->execute([$userId]);
        $updAcc = $pdo->prepare('UPDATE cloud_accounts SET token = ?, oauth_refresh_token = ? WHERE id = ?');
        foreach ($accStmt->fetchAll() as $row) {
            $plainToken = legacy_decrypt_with_global_key($row['token']);
            $newToken = $plainToken !== '' ? encrypt_with_key($plainToken, $newKey) : $row['token'];

            $newRefresh = $row['oauth_refresh_token'];
            $plainRefresh = legacy_decrypt_with_global_key($row['oauth_refresh_token'] ?? '');
            if ($plainRefresh !== '') {
                $newRefresh = encrypt_with_key($plainRefresh, $newKey);
            }

            $updAcc->execute([$newToken, $newRefresh, $row['id']]);
        }

        $nodeStmt = $pdo->prepare('
            SELECT n.id, n.uri
            FROM cloud_group_nodes n
            INNER JOIN cloud_groups g ON g.id = n.group_id
            WHERE g.user_id = ?
        ');
        $nodeStmt->execute([$userId]);
        $updNode = $pdo->prepare('UPDATE cloud_group_nodes SET uri = ? WHERE id = ?');
        foreach ($nodeStmt->fetchAll() as $row) {
            $updNode->execute([encrypt_with_key($row['uri'], $newKey), $row['id']]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
