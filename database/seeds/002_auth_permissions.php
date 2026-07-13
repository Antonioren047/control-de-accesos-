<?php
declare(strict_types=1);

return static function (PDO $pdo, bool $demo = false): void {
    $permissions = [
        ['auth.profile.view', 'auth', 'view', 'Consultar perfil propio'],
        ['auth.password.change', 'auth', 'change', 'Cambiar contraseña propia'],
        ['auth.sessions.view', 'auth', 'view_sessions', 'Consultar sesiones'],
        ['auth.sessions.revoke', 'auth', 'revoke_sessions', 'Revocar sesiones'],
        ['users.password_reset', 'users', 'password_reset', 'Restablecer contraseñas'],
    ];

    $permission = $pdo->prepare(
        "INSERT INTO permissions(code,module,action,name,created_at)
         VALUES(?,?,?,?,UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE name=VALUES(name)"
    );
    foreach ($permissions as $row) {
        $permission->execute($row);
    }

    $pdo->exec(
        "INSERT IGNORE INTO role_permissions(role_id,permission_id,created_at)
         SELECT r.id,p.id,UTC_TIMESTAMP()
         FROM roles r CROSS JOIN permissions p
         WHERE r.code='superadmin'"
    );

    $pdo->exec(
        "INSERT IGNORE INTO role_permissions(role_id,permission_id,created_at)
         SELECT r.id,p.id,UTC_TIMESTAMP()
         FROM roles r JOIN permissions p ON p.code IN (
             'auth.profile.view','auth.password.change','auth.sessions.view',
             'auth.sessions.revoke','users.password_reset'
         ) WHERE r.code='admin'"
    );

    $pdo->exec(
        "INSERT IGNORE INTO role_permissions(role_id,permission_id,created_at)
         SELECT r.id,p.id,UTC_TIMESTAMP()
         FROM roles r JOIN permissions p ON p.code IN ('auth.profile.view','auth.password.change')
         WHERE r.code IN ('supervisor','guard','resident')"
    );

    $settings = [
        ['security.max_user_sessions', '5', 'integer', 0],
        ['security.login_backoff_seconds', '60', 'integer', 0],
        ['security.login_max_backoff_seconds', '3600', 'integer', 0],
    ];
    $setting = $pdo->prepare(
        "INSERT INTO system_settings(setting_key,setting_value,value_type,is_public,created_at,updated_at)
         VALUES(?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_at=UTC_TIMESTAMP()"
    );
    foreach ($settings as $row) {
        $setting->execute($row);
    }
};
