<?php
function generate_kdf_salt(): string {
    return bin2hex(random_bytes(16));
}

function derive_data_key(string $password, string $saltHex): string {
    $salt = hex2bin($saltHex);
    return hash_pbkdf2('sha256', $password, $salt, 100000, 32, true);
}

function encrypt_with_key(string $plaintext, string $rawKey): string {
    $iv = random_bytes(16);
    $cipher = openssl_encrypt($plaintext, 'aes-256-cbc', $rawKey, OPENSSL_RAW_DATA, $iv);
    if ($cipher === false) {
        throw new RuntimeException('رمزنگاری با خطا مواجه شد.');
    }
    return base64_encode($iv . $cipher);
}

function decrypt_with_key(?string $encoded, string $rawKey): string {
    if ($encoded === null || $encoded === '') {
        return '';
    }
    $raw = base64_decode($encoded, true);
    if ($raw === false || strlen($raw) < 17) {
        return '';
    }
    $iv = substr($raw, 0, 16);
    $cipher = substr($raw, 16);
    $plain = openssl_decrypt($cipher, 'aes-256-cbc', $rawKey, OPENSSL_RAW_DATA, $iv);
    return $plain === false ? '' : $plain;
}

function current_data_key(): string {
    if (empty($_SESSION['data_key'])) {
        return '';
    }
    return hex2bin($_SESSION['data_key']);
}

function encrypt_for_user(string $plaintext): string {
    $key = current_data_key();
    if ($key === '' || $plaintext === '') {
        return '';
    }
    return encrypt_with_key($plaintext, $key);
}

function decrypt_for_user(?string $encoded): string {
    $key = current_data_key();
    if ($key === '' || $encoded === null || $encoded === '') {
        return '';
    }
    return decrypt_with_key($encoded, $key);
}

function legacy_decrypt_with_global_key(?string $encoded): string {
    if (!defined('ENCRYPTION_KEY') || $encoded === null || $encoded === '') {
        return '';
    }
    $key = hash('sha256', ENCRYPTION_KEY, true);
    return decrypt_with_key($encoded, $key);
}
