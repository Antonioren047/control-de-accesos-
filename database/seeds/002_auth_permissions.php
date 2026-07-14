<?php
declare(strict_types=1);

return static function (PDO $pdo, bool $demo = false): void {
    $moduleConfig = require dirname(__DIR__, 2) . '/config/modules.php';
    $permissions = array_merge([
        ['auth.profile.view', 'auth', 'view', 'Consultar perfil propio'],
        ['auth.password.change', 'auth', 'change', 'Cambiar contraseña propia'],
        ['auth.sessions.view', 'auth', 'view_sessions', 'Consultar sesiones'],
        ['auth.sessions.revoke', 'auth', 'revoke_sessions', 'Revocar sesiones'],
        ['users.password_reset', 'users', 'password_reset', 'Restablecer contraseñas'],
        ['permissions.manage', 'permissions', 'manage', 'Administrar permisos por rol'],
    ], $moduleConfig['permissions']);

    $permission = $pdo->prepare(
        "INSERT INTO permissions(code,module,action,name,created_at)
         VALUES(?,?,?,?,UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE name=VALUES(name)"
    );
    foreach ($permissions as $row) {
        $permission->execute($row);
    }

    $defaultsSeeded = (bool) $pdo->query(
        "SELECT COUNT(*) FROM system_settings WHERE setting_key='authorization.defaults_seeded'"
    )->fetchColumn();

    if (!$defaultsSeeded) {
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
             'auth.sessions.revoke','users.password_reset','system.view','users.manage'
         ) WHERE r.code='admin'"
    );

        $pdo->exec(
        "INSERT IGNORE INTO role_permissions(role_id,permission_id,created_at)
         SELECT r.id,p.id,UTC_TIMESTAMP()
         FROM roles r JOIN permissions p ON p.code IN (
             'auth.profile.view','auth.password.change','auth.sessions.view','auth.sessions.revoke',
             'system.view','operations.view'
         ) WHERE r.code='supervisor'"
    );

        $pdo->exec(
        "INSERT IGNORE INTO role_permissions(role_id,permission_id,created_at)
         SELECT r.id,p.id,UTC_TIMESTAMP()
         FROM roles r JOIN permissions p ON p.code IN (
             'auth.profile.view','system.view','visits.manage'
         ) WHERE r.code='resident'"
    );

        $pdo->exec(
        "INSERT IGNORE INTO role_permissions(role_id,permission_id,created_at)
         SELECT r.id,p.id,UTC_TIMESTAMP()
         FROM roles r JOIN permissions p ON p.code IN (
             'auth.profile.view','auth.password.change','system.view','operations.view'
         )
         WHERE r.code='guard'"
    );
        $pdo->exec(
        "INSERT INTO system_settings(setting_key,setting_value,value_type,is_public,created_at,updated_at)
         VALUES('authorization.defaults_seeded','1','boolean',0,UTC_TIMESTAMP(),UTC_TIMESTAMP())"
    );
    }

    $moduleDefaultsSeeded = (bool) $pdo->query(
        "SELECT COUNT(*) FROM system_settings WHERE setting_key='authorization.module_defaults_v1'"
    )->fetchColumn();
    if (!$moduleDefaultsSeeded) {
        $roleDefaults = $moduleConfig['roles'];
        $roleDefaults['superadmin'] = array_column($moduleConfig['permissions'], 0);
        foreach ($roleDefaults as $roleCode => $permissionCodes) {
            $placeholders = implode(',', array_fill(0, count($permissionCodes), '?'));
            $assign = $pdo->prepare(
                "INSERT IGNORE INTO role_permissions(role_id,permission_id,created_at)
                 SELECT r.id,p.id,UTC_TIMESTAMP() FROM roles r JOIN permissions p
                 WHERE r.code=? AND p.code IN ($placeholders)"
            );
            $assign->execute(array_merge([$roleCode], $permissionCodes));
        }
        $pdo->exec(
            "INSERT INTO system_settings(setting_key,setting_value,value_type,is_public,created_at,updated_at)
             VALUES('authorization.module_defaults_v1','1','boolean',0,UTC_TIMESTAMP(),UTC_TIMESTAMP())"
        );
    }

    $phaseThreeDefaults = (bool) $pdo->query(
        "SELECT COUNT(*) FROM system_settings WHERE setting_key='authorization.phase3_scope_defaults_v1'"
    )->fetchColumn();
    if (!$phaseThreeDefaults) {
        $scopeDefaults = [
            'superadmin' => ['locations.view', 'access_points.view', 'units.view', 'units.view_own'],
            'supervisor' => ['locations.view', 'access_points.view', 'units.view'],
            'resident' => ['units.view_own'],
        ];
        foreach ($scopeDefaults as $roleCode => $permissionCodes) {
            $placeholders = implode(',', array_fill(0, count($permissionCodes), '?'));
            $assign = $pdo->prepare(
                "INSERT IGNORE INTO role_permissions(role_id,permission_id,created_at)
                 SELECT r.id,p.id,UTC_TIMESTAMP() FROM roles r JOIN permissions p
                 WHERE r.code=? AND p.code IN ($placeholders)"
            );
            $assign->execute(array_merge([$roleCode], $permissionCodes));
        }
        $pdo->exec(
            "INSERT INTO system_settings(setting_key,setting_value,value_type,is_public,created_at,updated_at)
             VALUES('authorization.phase3_scope_defaults_v1','1','boolean',0,UTC_TIMESTAMP(),UTC_TIMESTAMP())"
        );
    }

    $phaseFourDefaults = (bool) $pdo->query(
        "SELECT COUNT(*) FROM system_settings WHERE setting_key='authorization.phase4_workforce_defaults_v1'"
    )->fetchColumn();
    if (!$phaseFourDefaults) {
        $defaults = [
            'superadmin' => ['guards.manage','guards.view','guards.credential','guards.pin_reset','shifts.manage','shifts.view','assignments.manage','assignments.view'],
            'admin' => ['guards.manage','guards.credential','guards.pin_reset','shifts.manage','assignments.manage'],
            'supervisor' => ['guards.view','shifts.view','assignments.view','assignments.request_change'],
            'guard' => ['shifts.view'],
        ];
        foreach ($defaults as $roleCode => $permissionCodes) {
            $placeholders = implode(',', array_fill(0, count($permissionCodes), '?'));
            $assign = $pdo->prepare("INSERT IGNORE INTO role_permissions(role_id,permission_id,created_at) SELECT r.id,p.id,UTC_TIMESTAMP() FROM roles r JOIN permissions p WHERE r.code=? AND p.code IN ($placeholders)");
            $assign->execute(array_merge([$roleCode], $permissionCodes));
        }
        $pdo->exec("INSERT INTO system_settings(setting_key,setting_value,value_type,is_public,created_at,updated_at) VALUES('authorization.phase4_workforce_defaults_v1','1','boolean',0,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
    }

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

    // Desactivado por defecto. En desarrollo permite validar el rol Vigilante
    // con correo y contraseña mientras se implementa el acceso definitivo QR + PIN.
    $pdo->exec(
        "INSERT IGNORE INTO system_settings(
            setting_key,setting_value,value_type,is_public,created_at,updated_at
         ) VALUES('security.guard_web_login_enabled','0','boolean',0,UTC_TIMESTAMP(),UTC_TIMESTAMP())"
    );
};
