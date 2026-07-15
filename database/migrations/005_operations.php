<?php
declare(strict_types=1);

return static function(PDO $pdo):void{
    $statements=[
        "CREATE TABLE IF NOT EXISTS operational_sessions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            guard_user_id BIGINT UNSIGNED NOT NULL, assignment_id BIGINT UNSIGNED NOT NULL,
            shift_id BIGINT UNSIGNED NOT NULL, client_id BIGINT UNSIGNED NOT NULL,
            location_id BIGINT UNSIGNED NOT NULL, access_point_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'active', started_at DATETIME NOT NULL,
            ended_at DATETIME NULL, entry_photo_path VARCHAR(255) NOT NULL,
            entry_classification VARCHAR(30) NOT NULL, exit_classification VARCHAR(30) NULL,
            device_type VARCHAR(30) NOT NULL, browser_name VARCHAR(80) NOT NULL,
            operating_system VARCHAR(80) NOT NULL, ip_address VARCHAR(45) NULL,
            device_identifier CHAR(64) NOT NULL, connection_status VARCHAR(20) NOT NULL DEFAULT 'online',
            close_method VARCHAR(20) NULL, close_comment VARCHAR(500) NULL, closed_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            CONSTRAINT fk_operational_guard FOREIGN KEY(guard_user_id) REFERENCES users(id),
            CONSTRAINT fk_operational_assignment FOREIGN KEY(assignment_id) REFERENCES guard_assignments(id),
            CONSTRAINT fk_operational_shift FOREIGN KEY(shift_id) REFERENCES shifts(id),
            CONSTRAINT fk_operational_client FOREIGN KEY(client_id) REFERENCES clients(id),
            CONSTRAINT fk_operational_location FOREIGN KEY(location_id) REFERENCES locations(id),
            CONSTRAINT fk_operational_point FOREIGN KEY(access_point_id) REFERENCES access_points(id),
            CONSTRAINT fk_operational_closed_by FOREIGN KEY(closed_by) REFERENCES users(id),
            INDEX idx_operational_guard_active(guard_user_id,status,started_at),
            INDEX idx_operational_point_active(access_point_id,status,started_at),
            INDEX idx_operational_scope(client_id,location_id,access_point_id,status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS attendance_records (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, operational_session_id BIGINT UNSIGNED NOT NULL UNIQUE,
            guard_user_id BIGINT UNSIGNED NOT NULL, assignment_id BIGINT UNSIGNED NOT NULL,
            shift_id BIGINT UNSIGNED NOT NULL, client_id BIGINT UNSIGNED NOT NULL,
            location_id BIGINT UNSIGNED NOT NULL, access_point_id BIGINT UNSIGNED NOT NULL,
            scheduled_start_at DATETIME NOT NULL, scheduled_end_at DATETIME NOT NULL,
            actual_entry_at DATETIME NOT NULL, actual_exit_at DATETIME NULL,
            entry_classification VARCHAR(30) NOT NULL, exit_classification VARCHAR(30) NULL,
            minutes_late INT UNSIGNED NOT NULL DEFAULT 0, minutes_early INT UNSIGNED NOT NULL DEFAULT 0,
            overtime_minutes INT UNSIGNED NOT NULL DEFAULT 0, status VARCHAR(20) NOT NULL DEFAULT 'open',
            created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            CONSTRAINT fk_attendance_session FOREIGN KEY(operational_session_id) REFERENCES operational_sessions(id),
            CONSTRAINT fk_attendance_guard FOREIGN KEY(guard_user_id) REFERENCES users(id),
            CONSTRAINT fk_attendance_assignment FOREIGN KEY(assignment_id) REFERENCES guard_assignments(id),
            CONSTRAINT fk_attendance_shift FOREIGN KEY(shift_id) REFERENCES shifts(id),
            CONSTRAINT fk_attendance_client FOREIGN KEY(client_id) REFERENCES clients(id),
            CONSTRAINT fk_attendance_location FOREIGN KEY(location_id) REFERENCES locations(id),
            CONSTRAINT fk_attendance_point FOREIGN KEY(access_point_id) REFERENCES access_points(id),
            INDEX idx_attendance_guard_date(guard_user_id,actual_entry_at),
            INDEX idx_attendance_scope_date(client_id,location_id,actual_entry_at),
            INDEX idx_attendance_status(status,scheduled_end_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];foreach($statements as $statement)$pdo->exec($statement);
};
