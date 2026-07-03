<?php
const ANTI_DPI_BLACKLIST = [
    'bpb', 'panel', 'vpn', 'proxy', 'tunnel', 'v2ray', 'xray',
    'trojan', 'clash', 'surge', 'shadowsocks', 'ss', 'ssr',
    'vless', 'vmess', 'wireguard', 'warp', 'filter', 'bypass',
    'freedom', 'gfw', 'censorship', 'mlm',
];

const ANTI_DPI_SAFE_PREFIXES = [
    'app-core', 'edge-relay', 'main-thunder', 'cloud-sync',
    'data-stream', 'net-bridge', 'api-hub', 'web-flow',
    'fast-route', 'smart-gate', 'node-link', 'micro-svc',
    'auto-scale', 'load-bal', 'cdn-edge', 'cache-opt',
    'log-svc', 'auth-api', 'user-svc', 'task-run',
    'event-bus', 'msg-queue', 'file-io', 'db-proxy',
];

const ANTI_DPI_SAFE_SUBDOMAIN_PREFIXES = [
    'dev-team', 'eng-ops', 'platform', 'infra-core',
    'sre-tools', 'ci-runner', 'build-svc', 'deploy-agent',
    'monitor-hub', 'test-env', 'staging-api', 'prod-relay',
];

function anti_dpi_hex(int $length): string {
    return substr(bin2hex(random_bytes((int) ceil($length / 2))), 0, $length);
}

function generate_safe_worker_name(): string {
    $prefix = ANTI_DPI_SAFE_PREFIXES[array_rand(ANTI_DPI_SAFE_PREFIXES)];
    return $prefix . '-' . anti_dpi_hex(6);
}

function generate_safe_subdomain(): string {
    $prefix = ANTI_DPI_SAFE_SUBDOMAIN_PREFIXES[array_rand(ANTI_DPI_SAFE_SUBDOMAIN_PREFIXES)];
    return $prefix . '-' . anti_dpi_hex(6);
}

function generate_mixed_case_sni(?string $domain): ?string {
    if ($domain === null || $domain === '') {
        return $domain;
    }
    $out = '';
    $len = strlen($domain);
    for ($i = 0; $i < $len; $i++) {
        $c = $domain[$i];
        if (ctype_alpha($c)) {
            $out .= (random_int(0, 1) === 1) ? strtoupper($c) : strtolower($c);
        } else {
            $out .= $c;
        }
    }
    return $out;
}

function apply_sni_camouflage(?string $uri): string {
    if ($uri === null || $uri === '') {
        return '';
    }
    if (strpos($uri, 'vless://') !== 0 && strpos($uri, 'trojan://') !== 0) {
        return $uri;
    }
    $hashPos = strpos($uri, '#');
    $fragment = $hashPos !== false ? substr($uri, $hashPos) : '';
    $withoutHash = $hashPos !== false ? substr($uri, 0, $hashPos) : $uri;

    $qPos = strpos($withoutHash, '?');
    if ($qPos === false) {
        return $uri;
    }
    $base = substr($withoutHash, 0, $qPos);
    $query = substr($withoutHash, $qPos + 1);

    $newParams = [];
    foreach (explode('&', $query) as $param) {
        $parts = explode('=', $param, 2);
        if (count($parts) === 2 && ($parts[0] === 'sni' || $parts[0] === 'host')) {
            $newParams[] = $parts[0] . '=' . generate_mixed_case_sni($parts[1]);
        } else {
            $newParams[] = $param;
        }
    }
    return $base . '?' . implode('&', $newParams) . $fragment;
}

function contains_blacklisted_keyword(?string $name): bool {
    if ($name === null || $name === '') {
        return false;
    }
    $lower = strtolower($name);
    foreach (ANTI_DPI_BLACKLIST as $kw) {
        if (strpos($lower, $kw) !== false) {
            return true;
        }
    }
    return false;
}
