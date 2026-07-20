<?php
declare(strict_types=1);

return static function (PDO $pdo): void {
    $statements = [
        "CREATE TABLE IF NOT EXISTS event_types (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, code VARCHAR(60) NOT NULL UNIQUE,
            name VARCHAR(120) NOT NULL, sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 100,
            default_priority VARCHAR(20) NOT NULL DEFAULT 'normal', evidence_required TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            INDEX idx_event_types_active(is_active,sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS events (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, event_type_id BIGINT UNSIGNED NOT NULL,
            operational_session_id BIGINT UNSIGNED NULL, client_id BIGINT UNSIGNED NOT NULL,
            location_id BIGINT UNSIGNED NOT NULL, access_point_id BIGINT UNSIGNED NULL,
            guard_user_id BIGINT UNSIGNED NULL, description TEXT NOT NULL,
            priority VARCHAR(20) NOT NULL DEFAULT 'normal', status VARCHAR(24) NOT NULL DEFAULT 'submitted',
            occurred_at DATETIME NOT NULL, cancelled_by BIGINT UNSIGNED NULL, cancellation_reason VARCHAR(500) NULL,
            cancelled_at DATETIME NULL, created_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            CONSTRAINT fk_event_type FOREIGN KEY(event_type_id) REFERENCES event_types(id),
            CONSTRAINT fk_event_session FOREIGN KEY(operational_session_id) REFERENCES operational_sessions(id),
            CONSTRAINT fk_event_client FOREIGN KEY(client_id) REFERENCES clients(id),
            CONSTRAINT fk_event_location FOREIGN KEY(location_id) REFERENCES locations(id),
            CONSTRAINT fk_event_point FOREIGN KEY(access_point_id) REFERENCES access_points(id),
            CONSTRAINT fk_event_guard FOREIGN KEY(guard_user_id) REFERENCES users(id),
            CONSTRAINT fk_event_creator FOREIGN KEY(created_by) REFERENCES users(id),
            CONSTRAINT fk_event_cancelled_by FOREIGN KEY(cancelled_by) REFERENCES users(id),
            INDEX idx_events_scope(location_id,status,occurred_at), INDEX idx_events_guard(guard_user_id,occurred_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS event_comments (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, event_id BIGINT UNSIGNED NOT NULL,
            author_user_id BIGINT UNSIGNED NOT NULL, comment TEXT NOT NULL, created_at DATETIME NOT NULL,
            CONSTRAINT fk_event_comment_event FOREIGN KEY(event_id) REFERENCES events(id),
            CONSTRAINT fk_event_comment_author FOREIGN KEY(author_user_id) REFERENCES users(id),
            INDEX idx_event_comments(event_id,created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS event_evidence (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, event_id BIGINT UNSIGNED NOT NULL,
            uploaded_by BIGINT UNSIGNED NOT NULL, media_type VARCHAR(10) NOT NULL, mime_type VARCHAR(80) NOT NULL,
            file_path VARCHAR(255) NOT NULL, file_size BIGINT UNSIGNED NOT NULL, duration_seconds DECIMAL(6,2) NULL,
            watermark_text VARCHAR(500) NOT NULL, captured_at DATETIME NOT NULL, created_at DATETIME NOT NULL,
            CONSTRAINT fk_event_evidence_event FOREIGN KEY(event_id) REFERENCES events(id),
            CONSTRAINT fk_event_evidence_user FOREIGN KEY(uploaded_by) REFERENCES users(id),
            INDEX idx_event_evidence(event_id,captured_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS round_policies (
            location_id BIGINT UNSIGNED PRIMARY KEY, max_duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 60,
            minimum_per_shift SMALLINT UNSIGNED NOT NULL DEFAULT 1, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            CONSTRAINT fk_round_policy_location FOREIGN KEY(location_id) REFERENCES locations(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS rounds (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, event_id BIGINT UNSIGNED NOT NULL UNIQUE,
            operational_session_id BIGINT UNSIGNED NOT NULL, client_id BIGINT UNSIGNED NOT NULL,
            location_id BIGINT UNSIGNED NOT NULL, access_point_id BIGINT UNSIGNED NOT NULL,
            guard_user_id BIGINT UNSIGNED NOT NULL, round_kind VARCHAR(20) NOT NULL DEFAULT 'free',
            status VARCHAR(24) NOT NULL DEFAULT 'open', started_at DATETIME NOT NULL, ended_at DATETIME NULL,
            observations TEXT NULL, closed_by BIGINT UNSIGNED NULL, supervisor_comment VARCHAR(500) NULL,
            created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            CONSTRAINT fk_round_event FOREIGN KEY(event_id) REFERENCES events(id),
            CONSTRAINT fk_round_session FOREIGN KEY(operational_session_id) REFERENCES operational_sessions(id),
            CONSTRAINT fk_round_client FOREIGN KEY(client_id) REFERENCES clients(id),
            CONSTRAINT fk_round_location FOREIGN KEY(location_id) REFERENCES locations(id),
            CONSTRAINT fk_round_point FOREIGN KEY(access_point_id) REFERENCES access_points(id),
            CONSTRAINT fk_round_guard FOREIGN KEY(guard_user_id) REFERENCES users(id),
            CONSTRAINT fk_round_closed_by FOREIGN KEY(closed_by) REFERENCES users(id),
            INDEX idx_round_open(guard_user_id,status), INDEX idx_round_scope(location_id,started_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS shift_novelties (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, operational_session_id BIGINT UNSIGNED NOT NULL UNIQUE,
            event_id BIGINT UNSIGNED NOT NULL UNIQUE, guard_user_id BIGINT UNSIGNED NOT NULL,
            client_id BIGINT UNSIGNED NOT NULL, location_id BIGINT UNSIGNED NOT NULL,
            access_point_id BIGINT UNSIGNED NOT NULL, novelty_status VARCHAR(20) NOT NULL,
            description TEXT NOT NULL, created_at DATETIME NOT NULL,
            CONSTRAINT fk_novelty_session FOREIGN KEY(operational_session_id) REFERENCES operational_sessions(id),
            CONSTRAINT fk_novelty_event FOREIGN KEY(event_id) REFERENCES events(id),
            CONSTRAINT fk_novelty_guard FOREIGN KEY(guard_user_id) REFERENCES users(id),
            CONSTRAINT fk_novelty_client FOREIGN KEY(client_id) REFERENCES clients(id),
            CONSTRAINT fk_novelty_location FOREIGN KEY(location_id) REFERENCES locations(id),
            CONSTRAINT fk_novelty_point FOREIGN KEY(access_point_id) REFERENCES access_points(id),
            INDEX idx_novelty_previous(location_id,access_point_id,created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];
    foreach ($statements as $statement) $pdo->exec($statement);

    $types = [
        ['incident','Incidencia',10,'normal',0], ['provider_entry','Entrada de proveedor',20,'normal',0],
        ['round','Recorrido',30,'normal',1], ['visit','Visita',40,'normal',0], ['shift_novelty','Novedad de turno',50,'normal',0],
    ];
    $insert = $pdo->prepare("INSERT INTO event_types(code,name,sort_order,default_priority,evidence_required,is_active,created_at,updated_at) VALUES(?,?,?,?,?,1,UTC_TIMESTAMP(),UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE name=VALUES(name),updated_at=UTC_TIMESTAMP()");
    foreach ($types as $type) $insert->execute($type);
    $pdo->exec("INSERT IGNORE INTO round_policies(location_id,created_at,updated_at) SELECT id,UTC_TIMESTAMP(),UTC_TIMESTAMP() FROM locations");
    $pdo->exec("INSERT INTO system_settings(setting_key,setting_value,value_type,is_public,created_at,updated_at) VALUES('rounds.default_max_duration_minutes','60','integer',0,UTC_TIMESTAMP(),UTC_TIMESTAMP()),('rounds.default_minimum_per_shift','1','integer',0,UTC_TIMESTAMP(),UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_at=UTC_TIMESTAMP()");

    foreach ([
        ['events.manage','events','manage','Administrar eventos'], ['events.review','events','review','Revisar y comentar eventos'],
        ['events.create','events','create','Registrar eventos'], ['rounds.view','rounds','view','Consultar recorridos'],
        ['rounds.review','rounds','review','Revisar recorridos'], ['rounds.execute','rounds','execute','Ejecutar recorridos'],
        ['shifts.novelty','shifts','novelty','Registrar novedades de turno'],
    ] as $permission) {
        $statement=$pdo->prepare("INSERT INTO permissions(code,module,action,name,created_at) VALUES(?,?,?,?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE name=VALUES(name)");
        $statement->execute($permission);
    }
    $defaults=['superadmin'=>['events.manage','events.review','events.create','rounds.view','rounds.review','rounds.execute','shifts.novelty'],'admin'=>['events.manage','rounds.view'],'supervisor'=>['events.review','rounds.review','rounds.view'],'guard'=>['events.create','rounds.execute','shifts.novelty']];
    foreach($defaults as$role=>$codes){$marks=implode(',',array_fill(0,count($codes),'?'));$s=$pdo->prepare("INSERT IGNORE INTO role_permissions(role_id,permission_id,created_at) SELECT r.id,p.id,UTC_TIMESTAMP() FROM roles r JOIN permissions p WHERE r.code=? AND p.code IN ($marks)");$s->execute(array_merge([$role],$codes));}
};
