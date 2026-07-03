<?php
function list_clean_ips(): array {
    return db()->query('SELECT * FROM clean_ips ORDER BY id ASC')->fetchAll();
}

function add_clean_ip(string $ip, string $label): void {
    $stmt = db()->prepare('INSERT INTO clean_ips (ip, label) VALUES (?, ?)');
    $stmt->execute([$ip, $label]);
}

function add_clean_ips_bulk(string $text): int {
    $count = 0;
    foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $parts = array_map('trim', explode(',', $line, 2));
        $ip = $parts[0];
        $label = $parts[1] ?? '';
        if ($ip !== '') {
            add_clean_ip($ip, $label);
            $count++;
        }
    }
    return $count;
}

function delete_clean_ip(int $id): void {
    $stmt = db()->prepare('DELETE FROM clean_ips WHERE id = ?');
    $stmt->execute([$id]);
}

function cw_insert_query_param(string $uri, string $key, string $value): string {
    $qIndex = strpos($uri, '?');
    $hIndex = strpos($uri, '#');
    if ($qIndex !== false) {
        $insertPos = $qIndex + 1;
        $prefix = '';
        $suffix = '&';
    } elseif ($hIndex !== false) {
        $insertPos = $hIndex;
        $prefix = '?';
        $suffix = '';
    } else {
        $insertPos = strlen($uri);
        $prefix = '?';
        $suffix = '';
    }
    return substr($uri, 0, $insertPos) . $prefix . $key . '=' . $value . $suffix . substr($uri, $insertPos);
}

function combine_uri_with_ip(string $uri, string $ip, string $baseName): array {
    $newUri = $uri;
    $newName = $baseName . ' [' . $ip . ']';

    $schemeIndex = strpos($newUri, '://');
    if ($schemeIndex === false) {
        return ['uri' => $newUri, 'name' => $newName];
    }
    $atIndex = strpos($newUri, '@', $schemeIndex);
    if ($atIndex === false) {
        return ['uri' => $newUri, 'name' => $newName];
    }

    $candidates = [];
    foreach (['/', '?', '#'] as $ch) {
        $pos = strpos($newUri, $ch, $atIndex);
        if ($pos !== false) {
            $candidates[] = $pos;
        }
    }
    $endOfHostPortIndex = $candidates ? min($candidates) : strlen($newUri);
    $hostPort = substr($newUri, $atIndex + 1, $endOfHostPortIndex - ($atIndex + 1));
    $portIndex = strrpos($hostPort, ':');
    $originalHost = $portIndex !== false ? substr($hostPort, 0, $portIndex) : $hostPort;
    $port = $portIndex !== false ? substr($hostPort, $portIndex + 1) : '443';
    $newHostPort = (strpos($ip, ':') !== false) ? "[{$ip}]:{$port}" : "{$ip}:{$port}";

    $newUri = substr($newUri, 0, $atIndex + 1) . $newHostPort . substr($newUri, $endOfHostPortIndex);

    if (strpos($newUri, 'sni=') === false) {
        $newUri = cw_insert_query_param($newUri, 'sni', $originalHost);
    }
    if (strpos($newUri, 'host=') === false) {
        $newUri = cw_insert_query_param($newUri, 'host', $originalHost);
    }

    $hashIndex = strpos($newUri, '#');
    $encodedName = rawurlencode($newName);
    if ($hashIndex !== false) {
        $newUri = substr($newUri, 0, $hashIndex) . '#' . $encodedName;
    } else {
        $newUri .= '#' . $encodedName;
    }

    return ['uri' => $newUri, 'name' => $newName];
}

function combine_group(int $groupId, int $userId): array {
    $group = get_group_with_nodes($groupId, $userId);
    if (!$group) {
        return ['success' => false, 'message' => 'گروه یافت نشد.'];
    }
    $ips = list_clean_ips();
    if (!$ips) {
        return ['success' => false, 'message' => 'ادمین سایت هنوز هیچ IP تمیزی وارد نکرده است.'];
    }

    $newNodes = [];
    foreach ($group['nodes'] as $node) {
        foreach ($ips as $ipRow) {
            $combined = combine_uri_with_ip($node['uri'], $ipRow['ip'], $node['name']);
            $newNodes[] = [
                'name' => $combined['name'],
                'uri' => $combined['uri'],
                'type' => $node['type'],
                'engine_type' => $node['engine_type'],
            ];
        }
    }
    if (!$newNodes) {
        return ['success' => false, 'message' => 'کانفیگی برای ترکیب یافت نشد.'];
    }

    $newGroupId = create_cloud_group($userId, (int) $group['account_id'], $group['engine_type'], $group['title'] . ' + Clean IP', $newNodes);
    return ['success' => true, 'group_id' => $newGroupId, 'count' => count($newNodes)];
}
