<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

$__appRootFs = realpath(__DIR__ . '/..');
$__docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
$__base = '';
if ($__docRoot && $__appRootFs && strpos($__appRootFs, $__docRoot) === 0) {
    $__base = substr($__appRootFs, strlen($__docRoot));
    $__base = str_replace('\\', '/', $__base);
    $__base = rtrim($__base, '/');
}
define('APP_BASE', $__base);

function url(string $path = ''): string {
    return APP_BASE . $path;
}

function full_url(string $path = ''): string {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return 'https://' . $host . url($path);
}

function h($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function icon(string $name, string $extraClass = ''): string {
    static $cache = [];
    if (!isset($cache[$name])) {
        $safeName = preg_replace('/[^a-z0-9_]/', '', $name);
        $file = __DIR__ . '/../assets/icons/' . $safeName . '.svg';
        $cache[$name] = is_file($file) ? file_get_contents($file) : '';
    }
    $svg = $cache[$name];
    if ($svg === '') {
        return '';
    }
    $class = 'cw-icon' . ($extraClass !== '' ? ' ' . $extraClass : '');
    return preg_replace('/<svg /', '<svg class="' . h($class) . '" aria-hidden="true" ', $svg, 1);
}

const CW_ALERT_ICONS = [
    'success' => 'check_circle',
    'error' => 'error',
    'warning' => 'warning',
    'info' => 'info',
];

function alert_box(string $type, string $message): string {
    $iconName = CW_ALERT_ICONS[$type] ?? 'info';
    return '<div class="alert alert-' . h($type) . '">' . icon($iconName) . '<span>' . h($message) . '</span></div>';
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => (APP_BASE !== '' ? APP_BASE : '') . '/',
        'domain'   => '',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('cwp_session');
    session_start();
}

$__configPath = __DIR__ . '/../config.php';
if (!file_exists($__configPath)) {
    if (defined('IS_API')) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'برنامه هنوز نصب نشده است. ابتدا install.php را اجرا کنید.']);
    } else {
        header('Location: ' . url('/install.php'));
    }
    exit;
}
require_once $__configPath;

require_once __DIR__ . '/logger.php';
install_global_error_logging();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/crypto.php';
require_once __DIR__ . '/http.php';
require_once __DIR__ . '/anti_dpi.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/accounts.php';
require_once __DIR__ . '/oauth.php';
require_once __DIR__ . '/groups.php';
require_once __DIR__ . '/clean_ips.php';
require_once __DIR__ . '/migrate.php';
require_once __DIR__ . '/engines/bpb.php';
require_once __DIR__ . '/engines/edg.php';
require_once __DIR__ . '/engines/nahan.php';
require_once __DIR__ . '/engines/mlm.php';

ensure_schema();
