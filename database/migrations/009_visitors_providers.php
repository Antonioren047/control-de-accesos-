<?php
declare(strict_types=1);

return static function (PDO $pdo): void {
    $statements = [
        "CREATE TABLE IF NOT EXISTS access_policies (
            client_id BIGINT UNSIGNED PRIMARY KEY,
            visit_duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 120,
            max_advance_days SMALLINT UNSIGNED NOT NULL DEFAULT 30,
            max_active_visits_per_resident SMALLINT UNSIGNED NOT NULL DEFAULT 10,
            identification_retention_days SMALLINT UNSIGNED NOT NULL DEFAULT 90,
            minors_identification_exempt TINYINT(1) NOT NULL DEFAULT 0,
            privacy_notice_version VARCHAR(30) NOT NULL DEFAULT '1.0',
            created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            CONSTRAINT fk_access_policy_client FOREIGN KEY(client_id) REFERENCES clients(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS visitor_passes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            resident_user_id BIGINT UNSIGNED NOT NULL, unit_id BIGINT UNSIGNED NOT NULL,
            location_id BIGINT UNSIGNED NOT NULL, visitor_name VARCHAR(180) NOT NULL,
            phone VARCHAR(30) NULL, identification_type VARCHAR(40) NULL,
            identification_number VARCHAR(80) NULL, company VARCHAR(180) NULL,
            host_name VARCHAR(180) NOT NULL, reason VARCHAR(255) NOT NULL,
            license_plate VARCHAR(30) NULL, vehicle VARCHAR(120) NULL,
            scheduled_at DATETIME NOT NULL, valid_from DATETIME NOT NULL, valid_until DATETIME NOT NULL,
            qr_token_hash CHAR(64) NOT NULL UNIQUE, qr_reference CHAR(12) NOT NULL UNIQUE,
            qr_asset_path VARCHAR(255) NULL, status VARCHAR(24) NOT NULL DEFAULT 'pending',
            first_used_at DATETIME NULL, cancelled_at DATETIME NULL, cancelled_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            CONSTRAINT fk_visit_resident FOREIGN KEY(resident_user_id) REFERENCES users(id),
            CONSTRAINT fk_visit_unit FOREIGN KEY(unit_id) REFERENCES units(id),
            CONSTRAINT fk_visit_location FOREIGN KEY(location_id) REFERENCES locations(id),
            CONSTRAINT fk_visit_cancelled_by FOREIGN KEY(cancelled_by) REFERENCES users(id),
            INDEX idx_visit_resident_status(resident_user_id,status,scheduled_at),
            INDEX idx_visit_location_status(location_id,status,scheduled_at),
            INDEX idx_visit_duplicate(visitor_name,location_id,scheduled_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS visitor_accesses (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, visitor_pass_id BIGINT UNSIGNED NOT NULL UNIQUE,
            operational_session_id BIGINT UNSIGNED NOT NULL, access_point_id BIGINT UNSIGNED NOT NULL,
            entry_guard_user_id BIGINT UNSIGNED NOT NULL, entry_at DATETIME NOT NULL,
            identification_type VARCHAR(40) NULL, identification_number VARCHAR(80) NULL,
            identification_photo_path VARCHAR(255) NULL, visitor_photo_path VARCHAR(255) NOT NULL,
            privacy_notice_version VARCHAR(30) NOT NULL, privacy_accepted_at DATETIME NOT NULL,
            privacy_ip_address VARCHAR(45) NULL, privacy_device_identifier VARCHAR(120) NULL,
            exit_guard_user_id BIGINT UNSIGNED NULL, exit_access_point_id BIGINT UNSIGNED NULL,
            exit_at DATETIME NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            CONSTRAINT fk_visit_access_pass FOREIGN KEY(visitor_pass_id) REFERENCES visitor_passes(id),
            CONSTRAINT fk_visit_access_session FOREIGN KEY(operational_session_id) REFERENCES operational_sessions(id),
            CONSTRAINT fk_visit_access_point FOREIGN KEY(access_point_id) REFERENCES access_points(id),
            CONSTRAINT fk_visit_access_entry_guard FOREIGN KEY(entry_guard_user_id) REFERENCES users(id),
            CONSTRAINT fk_visit_access_exit_guard FOREIGN KEY(exit_guard_user_id) REFERENCES users(id),
            CONSTRAINT fk_visit_access_exit_point FOREIGN KEY(exit_access_point_id) REFERENCES access_points(id),
            INDEX idx_visit_access_active(access_point_id,exit_at,entry_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS visitor_share_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, visitor_pass_id BIGINT UNSIGNED NOT NULL,
            resident_user_id BIGINT UNSIGNED NOT NULL, channel VARCHAR(20) NOT NULL,
            pressed_at DATETIME NOT NULL, ip_address VARCHAR(45) NULL, user_agent VARCHAR(255) NULL,
            CONSTRAINT fk_visit_share_pass FOREIGN KEY(visitor_pass_id) REFERENCES visitor_passes(id),
            CONSTRAINT fk_visit_share_resident FOREIGN KEY(resident_user_id) REFERENCES users(id),
            INDEX idx_visit_share_pass(visitor_pass_id,pressed_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS provider_accesses (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            resident_user_id BIGINT UNSIGNED NULL, created_by BIGINT UNSIGNED NOT NULL,
            unit_id BIGINT UNSIGNED NULL, location_id BIGINT UNSIGNED NOT NULL,
            operational_session_id BIGINT UNSIGNED NULL, access_point_id BIGINT UNSIGNED NULL,
            provider_company VARCHAR(180) NOT NULL, service_type VARCHAR(120) NOT NULL,
            responsible_name VARCHAR(180) NOT NULL, materials TEXT NULL, tools TEXT NULL,
            identification_type VARCHAR(40) NULL, identification_number VARCHAR(80) NULL,
            identification_photo_path VARCHAR(255) NULL, person_photo_path VARCHAR(255) NULL,
            scheduled_at DATETIME NULL, qr_token_hash CHAR(64) NULL UNIQUE, qr_reference CHAR(12) NULL UNIQUE,
            qr_asset_path VARCHAR(255) NULL, privacy_notice_version VARCHAR(30) NULL,
            privacy_accepted_at DATETIME NULL, privacy_ip_address VARCHAR(45) NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'pending', entry_guard_user_id BIGINT UNSIGNED NULL,
            entry_at DATETIME NULL, exit_guard_user_id BIGINT UNSIGNED NULL, exit_at DATETIME NULL,
            cancelled_at DATETIME NULL, cancelled_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            CONSTRAINT fk_provider_resident FOREIGN KEY(resident_user_id) REFERENCES users(id),
            CONSTRAINT fk_provider_creator FOREIGN KEY(created_by) REFERENCES users(id),
            CONSTRAINT fk_provider_unit FOREIGN KEY(unit_id) REFERENCES units(id),
            CONSTRAINT fk_provider_location FOREIGN KEY(location_id) REFERENCES locations(id),
            CONSTRAINT fk_provider_session FOREIGN KEY(operational_session_id) REFERENCES operational_sessions(id),
            CONSTRAINT fk_provider_point FOREIGN KEY(access_point_id) REFERENCES access_points(id),
            CONSTRAINT fk_provider_entry_guard FOREIGN KEY(entry_guard_user_id) REFERENCES users(id),
            CONSTRAINT fk_provider_exit_guard FOREIGN KEY(exit_guard_user_id) REFERENCES users(id),
            CONSTRAINT fk_provider_cancelled_by FOREIGN KEY(cancelled_by) REFERENCES users(id),
            INDEX idx_provider_location_status(location_id,status,scheduled_at),
            INDEX idx_provider_resident_status(resident_user_id,status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];
    foreach ($statements as $statement) $pdo->exec($statement);

    $pdo->exec("INSERT IGNORE INTO access_policies(client_id,created_at,updated_at) SELECT id,UTC_TIMESTAMP(),UTC_TIMESTAMP() FROM clients");
    $pdo->exec("INSERT INTO permissions(code,module,action,name,created_at) VALUES('access_identifications.view','access_identifications','view','Consultar identificaciones capturadas',UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE name=VALUES(name)");
    $pdo->exec("INSERT IGNORE INTO role_permissions(role_id,permission_id,created_at) SELECT r.id,p.id,UTC_TIMESTAMP() FROM roles r JOIN permissions p ON p.code='access_identifications.view' WHERE r.code IN ('superadmin','admin','supervisor')");
};
