<?php
declare(strict_types=1);

return static function (PDO $pdo): void {
    $statements = [
        "CREATE TABLE IF NOT EXISTS clients (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            surveillance_company_id BIGINT UNSIGNED NOT NULL,
            code VARCHAR(40) NOT NULL,
            name VARCHAR(180) NOT NULL,
            legal_name VARCHAR(180) NULL,
            timezone VARCHAR(80) NOT NULL DEFAULT 'America/Mexico_City',
            storage_limit_gb INT UNSIGNED NOT NULL DEFAULT 10,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_clients_company_code(surveillance_company_id,code),
            CONSTRAINT fk_clients_company FOREIGN KEY(surveillance_company_id) REFERENCES surveillance_companies(id),
            CONSTRAINT fk_clients_created_by FOREIGN KEY(created_by) REFERENCES users(id),
            CONSTRAINT fk_clients_updated_by FOREIGN KEY(updated_by) REFERENCES users(id),
            INDEX idx_clients_company_active(surveillance_company_id,is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS locations (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id BIGINT UNSIGNED NOT NULL,
            code VARCHAR(40) NOT NULL, name VARCHAR(180) NOT NULL,
            address_line VARCHAR(255) NOT NULL, city VARCHAR(100) NULL,
            state VARCHAR(100) NULL, postal_code VARCHAR(12) NULL,
            timezone VARCHAR(80) NOT NULL DEFAULT 'America/Mexico_City',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_locations_client_code(client_id,code),
            CONSTRAINT fk_locations_client FOREIGN KEY(client_id) REFERENCES clients(id),
            CONSTRAINT fk_locations_created_by FOREIGN KEY(created_by) REFERENCES users(id),
            CONSTRAINT fk_locations_updated_by FOREIGN KEY(updated_by) REFERENCES users(id),
            INDEX idx_locations_client_active(client_id,is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS access_points (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            location_id BIGINT UNSIGNED NOT NULL, code VARCHAR(40) NOT NULL,
            name VARCHAR(180) NOT NULL, point_type VARCHAR(30) NOT NULL DEFAULT 'main',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_access_points_location_code(location_id,code),
            CONSTRAINT fk_access_points_location FOREIGN KEY(location_id) REFERENCES locations(id),
            CONSTRAINT fk_access_points_created_by FOREIGN KEY(created_by) REFERENCES users(id),
            CONSTRAINT fk_access_points_updated_by FOREIGN KEY(updated_by) REFERENCES users(id),
            INDEX idx_access_points_location_active(location_id,is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS units (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            location_id BIGINT UNSIGNED NOT NULL, code VARCHAR(40) NOT NULL,
            name VARCHAR(180) NOT NULL, unit_type VARCHAR(30) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_units_location_code(location_id,code),
            CONSTRAINT fk_units_location FOREIGN KEY(location_id) REFERENCES locations(id),
            CONSTRAINT fk_units_created_by FOREIGN KEY(created_by) REFERENCES users(id),
            CONSTRAINT fk_units_updated_by FOREIGN KEY(updated_by) REFERENCES users(id),
            INDEX idx_units_location_active(location_id,is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS resident_profiles (
            user_id BIGINT UNSIGNED PRIMARY KEY, phone VARCHAR(30) NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            CONSTRAINT fk_resident_profiles_user FOREIGN KEY(user_id) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS resident_units (
            resident_user_id BIGINT UNSIGNED NOT NULL, unit_id BIGINT UNSIGNED NOT NULL,
            is_primary TINYINT(1) NOT NULL DEFAULT 0, is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_by BIGINT UNSIGNED NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            PRIMARY KEY(resident_user_id,unit_id),
            CONSTRAINT fk_resident_units_user FOREIGN KEY(resident_user_id) REFERENCES users(id),
            CONSTRAINT fk_resident_units_unit FOREIGN KEY(unit_id) REFERENCES units(id),
            CONSTRAINT fk_resident_units_created_by FOREIGN KEY(created_by) REFERENCES users(id),
            INDEX idx_resident_units_unit_active(unit_id,is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS user_client_scopes (
            user_id BIGINT UNSIGNED NOT NULL, client_id BIGINT UNSIGNED NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1, created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            PRIMARY KEY(user_id,client_id),
            CONSTRAINT fk_user_client_scope_user FOREIGN KEY(user_id) REFERENCES users(id),
            CONSTRAINT fk_user_client_scope_client FOREIGN KEY(client_id) REFERENCES clients(id),
            CONSTRAINT fk_user_client_scope_creator FOREIGN KEY(created_by) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS user_location_scopes (
            user_id BIGINT UNSIGNED NOT NULL, location_id BIGINT UNSIGNED NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1, created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            PRIMARY KEY(user_id,location_id),
            CONSTRAINT fk_user_location_scope_user FOREIGN KEY(user_id) REFERENCES users(id),
            CONSTRAINT fk_user_location_scope_location FOREIGN KEY(location_id) REFERENCES locations(id),
            CONSTRAINT fk_user_location_scope_creator FOREIGN KEY(created_by) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS user_access_point_scopes (
            user_id BIGINT UNSIGNED NOT NULL, access_point_id BIGINT UNSIGNED NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1, created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            PRIMARY KEY(user_id,access_point_id),
            CONSTRAINT fk_user_point_scope_user FOREIGN KEY(user_id) REFERENCES users(id),
            CONSTRAINT fk_user_point_scope_point FOREIGN KEY(access_point_id) REFERENCES access_points(id),
            CONSTRAINT fk_user_point_scope_creator FOREIGN KEY(created_by) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
    foreach ($statements as $statement) $pdo->exec($statement);
};
