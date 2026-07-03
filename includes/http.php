<?php
function cf_auth_headers(string $token, string $email): array {
    $isCfat = strpos($token, 'cfat_') === 0 || $email === '';
    if ($isCfat) {
        return ['Authorization: Bearer ' . $token];
    }
    return ['X-Auth-Email: ' . $email, 'X-Auth-Key: ' . $token];
}

function http_request(string $method, string $url, array $headers = [], $body = null, int $timeout = 20, int $connectTimeout = 15): array {
    if ($url === '' || ($ch = curl_init($url)) === false) {
        return ['ok' => false, 'code' => 0, 'body' => '', 'json' => null, 'error' => 'آدرس درخواست نامعتبر است.'];
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => $connectTimeout,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) CloudWebPanel/1.0',
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $raw = curl_exec($ch);
    if ($raw === false) {
        $err = curl_error($ch);
        curl_close($ch);
        return ['ok' => false, 'code' => 0, 'body' => '', 'json' => null, 'error' => $err];
    }
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $json = null;
    $trimmed = ltrim($raw);
    if ($trimmed !== '' && ($trimmed[0] === '{' || $trimmed[0] === '[')) {
        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $json = $decoded;
        }
    }
    return ['ok' => $code >= 200 && $code < 300, 'code' => $code, 'body' => $raw, 'json' => $json, 'error' => null];
}

function cf_api(string $method, string $url, string $token, string $email, $jsonBody = null, int $timeout = 20): array {
    $headers = cf_auth_headers($token, $email);
    $headers[] = 'Content-Type: application/json';
    $body = $jsonBody !== null ? json_encode($jsonBody, JSON_UNESCAPED_UNICODE) : null;
    return http_request($method, $url, $headers, $body, $timeout);
}

function build_multipart(array $parts, string $boundary): string {
    $body = '';
    foreach ($parts as $part) {
        $body .= "--{$boundary}\r\n";
        $disp = 'Content-Disposition: form-data; name="' . $part['name'] . '"';
        if (!empty($part['filename'])) {
            $disp .= '; filename="' . $part['filename'] . '"';
        }
        $body .= $disp . "\r\n";
        if (!empty($part['contentType'])) {
            $body .= 'Content-Type: ' . $part['contentType'] . "\r\n";
        }
        $body .= "\r\n" . $part['content'] . "\r\n";
    }
    $body .= "--{$boundary}--\r\n";
    return $body;
}

function cf_upload_worker(string $accountId, string $token, string $email, string $workerName, string $scriptContent, array $metadata): array {
    $boundary = '----CloudWebPanel' . bin2hex(random_bytes(16));
    $body = build_multipart([
        ['name' => 'metadata', 'filename' => 'metadata.json', 'contentType' => 'application/json', 'content' => json_encode($metadata, JSON_UNESCAPED_UNICODE)],
        ['name' => 'worker.js', 'filename' => 'worker.js', 'contentType' => 'application/javascript+module', 'content' => $scriptContent],
    ], $boundary);

    $headers = cf_auth_headers($token, $email);
    $headers[] = 'Content-Type: multipart/form-data; boundary=' . $boundary;

    $url = "https://api.cloudflare.com/client/v4/accounts/{$accountId}/workers/scripts/{$workerName}";
    return http_request('PUT', $url, $headers, $body, 30, 15);
}

function cf_raw(string $method, string $url, string $token, string $email, ?string $rawBody = null, string $contentType = 'text/plain', int $timeout = 20): array {
    $headers = cf_auth_headers($token, $email);
    if ($rawBody !== null) {
        $headers[] = 'Content-Type: ' . $contentType;
    }
    return http_request($method, $url, $headers, $rawBody, $timeout);
}

function http_request_with_headers(string $method, string $url, array $headers = [], $body = null, int $timeout = 20, int $connectTimeout = 15): array {
    if ($url === '' || ($ch = curl_init($url)) === false) {
        return ['ok' => false, 'code' => 0, 'body' => '', 'json' => null, 'error' => 'آدرس درخواست نامعتبر است.', 'headers' => []];
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => $connectTimeout,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) CloudWebPanel/1.0',
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $raw = curl_exec($ch);
    if ($raw === false) {
        $err = curl_error($ch);
        curl_close($ch);
        return ['ok' => false, 'code' => 0, 'body' => '', 'json' => null, 'error' => $err, 'headers' => []];
    }
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    $rawHeaders = substr($raw, 0, $headerSize);
    $rawBody = substr($raw, $headerSize);

    $headersOut = [];
    foreach (explode("\r\n", $rawHeaders) as $line) {
        if (strpos($line, ':') === false) {
            continue;
        }
        [$name, $value] = explode(':', $line, 2);
        $name = strtolower(trim($name));
        $headersOut[$name][] = trim($value);
    }

    $json = null;
    $trimmed = ltrim($rawBody);
    if ($trimmed !== '' && ($trimmed[0] === '{' || $trimmed[0] === '[')) {
        $decoded = json_decode($rawBody, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $json = $decoded;
        }
    }
    return ['ok' => $code >= 200 && $code < 300, 'code' => $code, 'body' => $rawBody, 'json' => $json, 'error' => null, 'headers' => $headersOut];
}

function build_cookie_header(array $setCookieHeaders): string {
    $pairs = [];
    foreach ($setCookieHeaders as $sc) {
        $pairs[] = trim(explode(';', $sc)[0]);
    }
    return implode('; ', $pairs);
}

function cf_first_error(array $result): string {
    if ($result['json'] !== null && !empty($result['json']['errors'][0]['message'])) {
        return $result['json']['errors'][0]['message'];
    }
    if ($result['error']) {
        return $result['error'];
    }
    if ($result['body']) {
        return mb_substr($result['body'], 0, 200);
    }
    return 'خطای ناشناخته (کد ' . $result['code'] . ')';
}
