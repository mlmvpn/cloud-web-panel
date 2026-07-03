<?php
function ensure_schema(): void {
    $markerFile = __DIR__ . '/.schema_v3';
    if (!file_exists($markerFile)) {
        $pdo = db();

        $pdo->exec("CREATE TABLE IF NOT EXISTS clean_ips (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip VARCHAR(64) NOT NULL,
            label VARCHAR(190) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        ensure_column('users', 'kdf_salt', 'VARCHAR(32) DEFAULT NULL');
        ensure_column('cloud_accounts', 'auth_type', "VARCHAR(16) NOT NULL DEFAULT 'key'");
        ensure_column('cloud_accounts', 'oauth_refresh_token', 'TEXT DEFAULT NULL');
        ensure_column('cloud_accounts', 'oauth_expires_at', 'DATETIME DEFAULT NULL');

        @file_put_contents($markerFile, date('c'));
    }

    $markerFileV4 = __DIR__ . '/.schema_v4';
    if (!file_exists($markerFileV4)) {
        $pdo = db();

        $pdo->exec("CREATE TABLE IF NOT EXISTS error_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            level VARCHAR(16) NOT NULL DEFAULT 'error',
            user_id INT DEFAULT NULL,
            message TEXT NOT NULL,
            context TEXT DEFAULT NULL,
            KEY idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        @file_put_contents($markerFileV4, date('c'));
    }
}

function ensure_column(string $table, string $column, string $definition): void {
    $pdo = db();
    $stmt = $pdo->prepare('
        SELECT COUNT(*) FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?
    ');
    $stmt->execute([$table, $column]);
    if ((int) $stmt->fetchColumn() > 0) {
        return;
    }
    $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
}
