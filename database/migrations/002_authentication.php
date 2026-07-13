<?php
declare(strict_types=1);

return static function (PDO $pdo): void {
    $statements = [
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS theme_preference VARCHAR(20) NOT NULL DEFAULT 'auto' AFTER is_active",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS force_password_change TINYINT(1) NOT NULL DEFAULT 0 AFTER theme_preference",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login_at DATETIME NULL AFTER force_password_change",
        "CREATE TABLE IF NOT EXISTS auth_attempts (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            identifier_hash CHAR(64) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            failed_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            blocked_until DATETIME NULL,
            last_attempt_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_auth_attempt_identifier_ip(identifier_hash, ip_address),
            INDEX idx_auth_attempt_blocked(blocked_until)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];

    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }

    $index = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.statistics
         WHERE table_schema=DATABASE() AND table_name='user_sessions'
           AND index_name='idx_sessions_token_active'"
    )->fetchColumn();
    if ((int) $index === 0) {
        $pdo->exec(
            "ALTER TABLE user_sessions
             ADD INDEX idx_sessions_token_active(session_token_hash,revoked_at,expires_at)"
        );
    }
};
