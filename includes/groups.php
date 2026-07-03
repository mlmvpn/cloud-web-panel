<?php
function create_cloud_group(int $userId, int $accountRowId, string $engineType, string $title, array $nodes): int {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('INSERT INTO cloud_groups (user_id, account_id, engine_type, title) VALUES (?, ?, ?, ?)');
        $stmt->execute([$userId, $accountRowId, $engineType, $title]);
        $groupId = (int) $pdo->lastInsertId();

        $nodeStmt = $pdo->prepare('INSERT INTO cloud_group_nodes (group_id, name, uri, type, engine_type) VALUES (?, ?, ?, ?, ?)');
        foreach ($nodes as $node) {
            $nodeStmt->execute([
                $groupId,
                $node['name'] ?? '',
                encrypt_for_user($node['uri']),
                $node['type'] ?? 'vless',
                $node['engine_type'] ?? $engineType,
            ]);
        }
        $pdo->commit();
        return $groupId;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function list_user_groups(int $userId): array {
    $stmt = db()->prepare('
        SELECT g.*, a.name AS account_name, a.email AS account_email,
               (SELECT COUNT(*) FROM cloud_group_nodes n WHERE n.group_id = g.id) AS node_count
        FROM cloud_groups g
        LEFT JOIN cloud_accounts a ON a.id = g.account_id
        WHERE g.user_id = ?
        ORDER BY g.created_at DESC
    ');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function get_group_with_nodes(int $groupId, int $userId): ?array {
    $stmt = db()->prepare('SELECT g.*, a.name AS account_name FROM cloud_groups g LEFT JOIN cloud_accounts a ON a.id = g.account_id WHERE g.id = ? AND g.user_id = ?');
    $stmt->execute([$groupId, $userId]);
    $group = $stmt->fetch();
    if (!$group) {
        return null;
    }
    $nodeStmt = db()->prepare('SELECT * FROM cloud_group_nodes WHERE group_id = ? ORDER BY id ASC');
    $nodeStmt->execute([$groupId]);
    $nodes = $nodeStmt->fetchAll();
    foreach ($nodes as &$node) {
        $node['uri'] = decrypt_for_user($node['uri']);
    }
    unset($node);
    $group['nodes'] = $nodes;
    return $group;
}

function delete_group(int $groupId, int $userId): bool {
    $stmt = db()->prepare('DELETE FROM cloud_groups WHERE id = ? AND user_id = ?');
    $stmt->execute([$groupId, $userId]);
    return $stmt->rowCount() > 0;
}

function list_user_all_uris(int $userId, ?string $engineType = null): array {
    $sql = '
        SELECT n.uri
        FROM cloud_group_nodes n
        INNER JOIN cloud_groups g ON g.id = n.group_id
        WHERE g.user_id = ?';
    $params = [$userId];
    if ($engineType !== null) {
        $sql .= ' AND n.engine_type = ?';
        $params[] = $engineType;
    }
    $sql .= ' ORDER BY n.id DESC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $out = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $out[] = decrypt_for_user($row['uri']);
    }
    return $out;
}

function count_user_uris_by_engine(int $userId): array {
    $stmt = db()->prepare('
        SELECT n.engine_type, COUNT(*) AS c
        FROM cloud_group_nodes n
        INNER JOIN cloud_groups g ON g.id = n.group_id
        WHERE g.user_id = ?
        GROUP BY n.engine_type
    ');
    $stmt->execute([$userId]);
    $out = ['BPB' => 0, 'EDG' => 0, 'NHN' => 0, 'MLM' => 0, 'ZEUS' => 0];
    foreach ($stmt->fetchAll() as $row) {
        $out[$row['engine_type']] = (int) $row['c'];
    }
    return $out;
}
