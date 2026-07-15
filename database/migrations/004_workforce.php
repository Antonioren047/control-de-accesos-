<?php
declare(strict_types=1);

return static function(PDO $pdo):void{
    $statements=[
        "CREATE TABLE IF NOT EXISTS guard_profiles (
            user_id BIGINT UNSIGNED PRIMARY KEY, employee_number VARCHAR(40) NOT NULL UNIQUE,
            photo_path VARCHAR(255) NOT NULL, phone VARCHAR(30) NOT NULL, curp VARCHAR(18) NULL,
            address_line VARCHAR(255) NOT NULL, hire_date DATE NOT NULL,
            emergency_contact_name VARCHAR(180) NOT NULL, emergency_contact_phone VARCHAR(30) NOT NULL,
            pin_hash VARCHAR(255) NOT NULL, pin_failed_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            pin_blocked_until DATETIME NULL, status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            CONSTRAINT fk_guard_profiles_user FOREIGN KEY(user_id) REFERENCES users(id),
            INDEX idx_guard_status(status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS guard_credentials (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, guard_user_id BIGINT UNSIGNED NOT NULL,
            token_hash CHAR(64) NOT NULL UNIQUE, token_reference CHAR(12) NOT NULL,
            qr_asset_path VARCHAR(255) NULL, status VARCHAR(20) NOT NULL DEFAULT 'active',
            issued_at DATETIME NOT NULL, expires_at DATETIME NULL, revoked_at DATETIME NULL,
            revoked_by BIGINT UNSIGNED NULL, regenerated_from_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            CONSTRAINT fk_guard_credentials_user FOREIGN KEY(guard_user_id) REFERENCES users(id),
            CONSTRAINT fk_guard_credentials_revoked_by FOREIGN KEY(revoked_by) REFERENCES users(id),
            CONSTRAINT fk_guard_credentials_previous FOREIGN KEY(regenerated_from_id) REFERENCES guard_credentials(id),
            INDEX idx_guard_credential_active(guard_user_id,status,expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS shifts (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, surveillance_company_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(120) NOT NULL, start_time TIME NOT NULL, end_time TIME NOT NULL,
            crosses_midnight TINYINT(1) NOT NULL DEFAULT 0, tolerance_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 10,
            early_departure_tolerance_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            overtime_after_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 15,
            applicable_days JSON NOT NULL, is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            CONSTRAINT fk_shifts_company FOREIGN KEY(surveillance_company_id) REFERENCES surveillance_companies(id),
            CONSTRAINT fk_shifts_created_by FOREIGN KEY(created_by) REFERENCES users(id),
            CONSTRAINT fk_shifts_updated_by FOREIGN KEY(updated_by) REFERENCES users(id),
            INDEX idx_shifts_company_active(surveillance_company_id,is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS shift_locations (
            shift_id BIGINT UNSIGNED NOT NULL, location_id BIGINT UNSIGNED NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL,
            PRIMARY KEY(shift_id,location_id),
            CONSTRAINT fk_shift_locations_shift FOREIGN KEY(shift_id) REFERENCES shifts(id),
            CONSTRAINT fk_shift_locations_location FOREIGN KEY(location_id) REFERENCES locations(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS guard_assignments (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, guard_user_id BIGINT UNSIGNED NOT NULL,
            client_id BIGINT UNSIGNED NOT NULL, location_id BIGINT UNSIGNED NOT NULL,
            access_point_id BIGINT UNSIGNED NOT NULL, shift_id BIGINT UNSIGNED NOT NULL,
            start_date DATE NOT NULL, end_date DATE NULL, applicable_days JSON NOT NULL,
            assignment_type VARCHAR(20) NOT NULL DEFAULT 'regular', rotation_pattern VARCHAR(80) NULL,
            replaces_assignment_id BIGINT UNSIGNED NULL, status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            CONSTRAINT fk_assignments_guard FOREIGN KEY(guard_user_id) REFERENCES users(id),
            CONSTRAINT fk_assignments_client FOREIGN KEY(client_id) REFERENCES clients(id),
            CONSTRAINT fk_assignments_location FOREIGN KEY(location_id) REFERENCES locations(id),
            CONSTRAINT fk_assignments_point FOREIGN KEY(access_point_id) REFERENCES access_points(id),
            CONSTRAINT fk_assignments_shift FOREIGN KEY(shift_id) REFERENCES shifts(id),
            CONSTRAINT fk_assignments_replaced FOREIGN KEY(replaces_assignment_id) REFERENCES guard_assignments(id),
            CONSTRAINT fk_assignments_created_by FOREIGN KEY(created_by) REFERENCES users(id),
            CONSTRAINT fk_assignments_updated_by FOREIGN KEY(updated_by) REFERENCES users(id),
            INDEX idx_assignments_guard_dates(guard_user_id,start_date,end_date,status),
            INDEX idx_assignments_point_dates(access_point_id,start_date,end_date,status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS assignment_history (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, assignment_id BIGINT UNSIGNED NOT NULL,
            action VARCHAR(30) NOT NULL, snapshot_json JSON NOT NULL, performed_by BIGINT UNSIGNED NULL,
            occurred_at DATETIME NOT NULL,
            CONSTRAINT fk_assignment_history_assignment FOREIGN KEY(assignment_id) REFERENCES guard_assignments(id),
            CONSTRAINT fk_assignment_history_user FOREIGN KEY(performed_by) REFERENCES users(id),
            INDEX idx_assignment_history_date(assignment_id,occurred_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS assignment_change_requests (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, assignment_id BIGINT UNSIGNED NOT NULL,
            requested_by BIGINT UNSIGNED NOT NULL, request_type VARCHAR(30) NOT NULL,
            comment VARCHAR(500) NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'pending',
            reviewed_by BIGINT UNSIGNED NULL, reviewed_at DATETIME NULL,
            created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            CONSTRAINT fk_assignment_request_assignment FOREIGN KEY(assignment_id) REFERENCES guard_assignments(id),
            CONSTRAINT fk_assignment_request_requested_by FOREIGN KEY(requested_by) REFERENCES users(id),
            CONSTRAINT fk_assignment_request_reviewed_by FOREIGN KEY(reviewed_by) REFERENCES users(id),
            INDEX idx_assignment_requests_status(status,created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];foreach($statements as $statement)$pdo->exec($statement);
};
