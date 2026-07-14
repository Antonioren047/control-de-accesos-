<?php
declare(strict_types=1);

$root = require dirname(__DIR__) . '/bootstrap/app.php';

use Vigilancia\Database\Connection;
use Vigilancia\Middleware\CsrfMiddleware;
use Vigilancia\Services\AuthService;
use Vigilancia\Support\Config;
use Vigilancia\Support\Session;

if (!is_file($root . '/storage/installed.lock')) {
    header('Location: install/');
    exit;
}

Session::start();
$auth = new AuthService(Connection::make(Config::database()));
$user = $auth->currentOrNull();
if (!$user) {
    header('Location: login.php');
    exit;
}
$profile = $auth->publicUser($user);
$csrf = CsrfMiddleware::token();
$canProfile = in_array('auth.profile.view', $profile['permissions'], true);
$canChangePassword = in_array('auth.password.change', $profile['permissions'], true);
$canViewSessions = in_array('auth.sessions.view', $profile['permissions'], true);
$canSecurity = $canChangePassword || $canViewSessions;
$canManagePermissions = in_array('permissions.manage', $profile['permissions'], true);
$canViewApi = in_array('system.configure', $profile['permissions'], true);
$moduleConfig = require $root . '/config/modules.php';
$permissionNames = [];
foreach ($moduleConfig['permissions'] as $permissionDefinition) {
    $permissionNames[$permissionDefinition[0]] = $permissionDefinition[3];
}
$availableModules = array_filter(
    $moduleConfig['modules'],
    static fn (array $module): bool => array_intersect($module['permissions'], $profile['permissions']) !== []
        && (!isset($module['roles']) || in_array($profile['role']['code'], $module['roles'], true))
);
$names = preg_split('/\s+/', trim($profile['full_name'])) ?: [];
$initials = strtoupper(substr($names[0] ?? 'U', 0, 1) . substr($names[1] ?? '', 0, 1));
$themeLabels = ['auto' => 'Automático', 'light' => 'Claro', 'dark' => 'Oscuro'];
$formatDate = static function (?string $value): string {
    if (!$value) return 'Sin registro';
    try {
        return (new DateTimeImmutable($value, new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone(date_default_timezone_get()))
            ->format('d/m/Y H:i');
    } catch (Throwable) {
        return 'Sin registro';
    }
};
?>
<!doctype html>
<html lang="es" data-theme="<?= htmlspecialchars($profile['theme']) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="description" content="Panel seguro del Sistema de Vigilancia">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf) ?>">
    <title>Panel · Sistema de Vigilancia</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/phase2.css">
    <link rel="stylesheet" href="assets/css/phase3.css">
    <link rel="stylesheet" href="assets/css/phase4.css">
    <link rel="stylesheet" href="assets/css/phase5.css">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <a class="brand" href="./">
            <img src="assets/images/logo.svg" alt="">
            <span><strong>Sistema de Vigilancia</strong><small>Control de Accesos</small></span>
        </a>
        <nav aria-label="Principal">
            <a class="active" href="#inicio" data-view-target="inicio"><span aria-hidden="true">⌂</span><span>Inicio</span></a>
            <?php foreach ($availableModules as $moduleId => $module): ?>
                <a href="#<?= htmlspecialchars($moduleId) ?>" data-view-target="<?= htmlspecialchars($moduleId) ?>" title="<?= htmlspecialchars($module['label']) ?>">
                    <span aria-hidden="true"><?= htmlspecialchars($module['icon']) ?></span><span><?= htmlspecialchars($module['label']) ?></span>
                </a>
            <?php endforeach; ?>
            <?php if ($canProfile): ?><a href="#perfil" data-view-target="perfil"><span aria-hidden="true">◇</span><span>Mi perfil</span></a><?php endif; ?>
            <?php if ($canSecurity): ?><a href="#seguridad" data-view-target="seguridad"><span aria-hidden="true">⌾</span><span>Seguridad</span></a><?php endif; ?>
            <?php if ($canManagePermissions): ?><a href="#permisos" data-view-target="permisos"><span aria-hidden="true">▦</span><span>Permisos</span></a><?php endif; ?>
            <?php if ($canViewApi): ?><a href="docs/"><span aria-hidden="true">▤</span><span>API</span></a><?php endif; ?>
        </nav>
        <button class="collapse" id="collapse" type="button" aria-label="Colapsar menú">‹</button>
        <div class="sidebar-foot">Fase 5 · Sesión operativa</div>
    </aside>
    <main>
        <header class="topbar">
            <button class="mobile-menu" id="mobileMenu" aria-label="Abrir menú">☰</button>
            <div><p class="eyebrow" id="viewEyebrow">Acceso autenticado</p><h1 id="viewTitle">Panel principal</h1></div>
            <div class="top-actions">
                <span class="connection"><i></i>Sesión activa</span>
                <label class="theme-control">Tema
                    <select id="themeSelect" aria-label="Tema">
                        <option value="auto">Automático</option><option value="light">Claro</option><option value="dark">Oscuro</option>
                    </select>
                </label>
                <div class="user-chip" title="<?= htmlspecialchars($profile['email']) ?>">
                    <span><?= htmlspecialchars($initials) ?></span>
                    <div><strong><?= htmlspecialchars($profile['full_name']) ?></strong><small><?= htmlspecialchars($profile['role']['name']) ?></small></div>
                </div>
                <button class="ghost-button" id="logoutButton" type="button">Salir</button>
            </div>
        </header>

        <?php if ($profile['force_password_change']): ?>
            <div class="security-banner" role="alert">Debes cambiar la contraseña temporal antes de continuar.</div>
        <?php endif; ?>
        <?php if ($profile['role']['code'] === 'guard'): ?>
            <div class="security-banner validation-banner" role="status">Acceso web del Vigilante habilitado únicamente para validación local. El acceso operativo definitivo utilizará QR + PIN.</div>
        <?php endif; ?>

        <section class="app-view" data-view="inicio">
            <section class="hero phase-two">
                <div>
                    <span class="hero-kicker">SESIÓN SEGURA · PERMISOS EN BACKEND</span>
                    <h2>Bienvenido,<br><?= htmlspecialchars(explode(' ', $profile['full_name'])[0]) ?></h2>
                    <p>Tu acceso está protegido y limitado por el rol y los permisos configurados para tu cuenta.</p>
                    <?php if ($canSecurity): ?><a class="primary-button" href="#seguridad" data-view-target="seguridad">Revisar seguridad <span>→</span></a><?php endif; ?>
                </div>
                <img src="assets/images/logo.svg" alt="Escudo del Sistema de Vigilancia">
            </section>
            <section class="section-head">
                <div><p class="eyebrow">Resumen</p><h2>Estado de tu acceso</h2></div>
            </section>
            <section class="status-grid auth-grid">
                <article class="status-card"><span class="card-icon">✓</span><div><small>ESTADO</small><h3>Sesión activa</h3><p>Expiración máxima de 24 horas</p></div><span class="badge success">Protegida</span></article>
                <article class="status-card"><span class="card-icon">◇</span><div><small>ROL BASE</small><h3><?= htmlspecialchars($profile['role']['name']) ?></h3><p><?= htmlspecialchars($profile['company']['name']) ?></p></div><span class="badge neutral"><?= count($profile['permissions']) ?> permisos</span></article>
                <article class="status-card"><span class="card-icon">▦</span><div><small>MÓDULOS</small><h3><?= count($availableModules) ?> autorizados</h3><p>Visibles según tu rol y permisos efectivos</p></div><span class="badge success">Restringidos</span></article>
            </section>
        </section>

        <?php foreach ($availableModules as $moduleId => $module):
            $grantedModulePermissions = array_values(array_intersect($module['permissions'], $profile['permissions']));
            $isPhaseThreeModule = in_array($moduleId, ['clientes', 'sitios', 'mis_unidades', 'turnos'], true)
                || ($moduleId === 'usuarios' && array_intersect(['residents.manage','guards.manage','guards.view'], $profile['permissions']));
            $isPhaseFiveModule = in_array($moduleId, ['operacion','asistencias','mi_actividad'], true);
        ?>
            <section class="app-view" data-view="<?= htmlspecialchars($moduleId) ?>" data-view-eyebrow="<?= htmlspecialchars($module['eyebrow']) ?>" data-view-title="<?= htmlspecialchars($module['title']) ?>" hidden>
                <section class="page-intro module-intro">
                    <div>
                        <p class="eyebrow"><?= htmlspecialchars($module['eyebrow']) ?></p>
                        <h2><?= htmlspecialchars($module['title']) ?></h2>
                        <p><?= htmlspecialchars($module['description']) ?></p>
                    </div>
                    <span class="module-icon" aria-hidden="true"><?= htmlspecialchars($module['icon']) ?></span>
                </section>
                <?php if ($isPhaseThreeModule): ?>
                    <section class="organization-workspace" data-organization-module="<?= htmlspecialchars($moduleId) ?>"
                        data-can-residents="<?= in_array('residents.manage',$profile['permissions'],true)?'1':'0' ?>"
                        data-can-guards="<?= array_intersect(['guards.manage','guards.view'],$profile['permissions'])?'1':'0' ?>"
                        data-can-manage-guards="<?= in_array('guards.manage',$profile['permissions'],true)?'1':'0' ?>"
                        data-can-manage-shifts="<?= in_array('shifts.manage',$profile['permissions'],true)?'1':'0' ?>"
                        data-can-manage-assignments="<?= in_array('assignments.manage',$profile['permissions'],true)?'1':'0' ?>"
                        data-can-request-assignment="<?= in_array('assignments.request_change',$profile['permissions'],true)?'1':'0' ?>">
                        <div class="organization-toolbar">
                            <div><p class="eyebrow"><?= $moduleId==='turnos'?'Fase 4 activa':'Módulo activo' ?></p><h3>Registros dentro de tu alcance</h3></div>
                            <div class="organization-actions">
                                <?php if ($moduleId === 'clientes' && $profile['role']['code'] === 'superadmin'): ?><button class="submit" type="button" data-organization-create="client">Nuevo cliente</button><?php endif; ?>
                                <?php if ($moduleId === 'sitios' && in_array('locations.manage', $profile['permissions'], true)): ?><button class="ghost-button" type="button" data-organization-create="location">Nuevo lugar</button><?php endif; ?>
                                <?php if ($moduleId === 'sitios' && in_array('access_points.manage', $profile['permissions'], true)): ?><button class="ghost-button" type="button" data-organization-create="access_point">Nuevo punto</button><?php endif; ?>
                                <?php if ($moduleId === 'sitios' && in_array('units.manage', $profile['permissions'], true)): ?><button class="ghost-button" type="button" data-organization-create="unit">Nueva unidad</button><?php endif; ?>
                                <?php if ($moduleId === 'usuarios' && in_array('residents.manage', $profile['permissions'], true)): ?><button class="submit" type="button" data-organization-create="resident">Nuevo residente</button><?php endif; ?>
                                <?php if ($moduleId === 'usuarios' && in_array('guards.manage', $profile['permissions'], true)): ?><button class="submit" type="button" data-workforce-create="guard">Nuevo vigilante</button><?php endif; ?>
                                <?php if ($moduleId === 'turnos' && in_array('shifts.manage', $profile['permissions'], true)): ?><button class="ghost-button" type="button" data-workforce-create="shift">Nuevo turno</button><?php endif; ?>
                                <?php if ($moduleId === 'turnos' && in_array('assignments.manage', $profile['permissions'], true)): ?><button class="submit" type="button" data-workforce-create="assignment">Nueva asignación</button><?php endif; ?>
                            </div>
                        </div>
                        <div class="organization-content" data-organization-content data-status-entities="<?= $moduleId === 'clientes' && $profile['role']['code'] === 'superadmin' ? 'client' : ($moduleId === 'sitios' && in_array('locations.manage', $profile['permissions'], true) ? 'location,access_point,unit' : '') ?>"><article class="security-card"><p class="muted">Consultando registros…</p></article></div>
                    </section>
                <?php elseif ($isPhaseFiveModule): ?>
                    <section class="phase5-workspace" data-phase5-module="<?= htmlspecialchars($moduleId) ?>" data-can-close="<?= in_array('operational_sessions.close',$profile['permissions'],true)?'1':'0' ?>">
                        <div class="organization-toolbar"><div><p class="eyebrow">Fase 5 activa</p><h3><?= $moduleId==='operacion'?'Sesiones operativas':($moduleId==='asistencias'?'Control de asistencias':'Mi historial operativo') ?></h3></div>
                        <?php if($moduleId==='operacion' && $profile['role']['code']==='guard'): ?><a class="submit" href="guard-access.php">Abrir acceso operativo</a><?php endif; ?></div>
                        <div class="organization-content" data-phase5-content><article class="security-card"><p class="muted">Consultando registros…</p></article></div>
                    </section>
                <?php else: ?><section class="module-shell">
                    <article class="security-card module-status-card">
                        <div><p class="eyebrow">Acceso concedido</p><h3>Módulo disponible para <?= htmlspecialchars($profile['role']['name']) ?></h3></div>
                        <span class="badge success">Autorizado</span>
                    </article>
                    <article class="security-card">
                        <p class="eyebrow">Acciones permitidas</p>
                        <div class="module-actions">
                            <?php foreach ($grantedModulePermissions as $permissionCode): ?>
                                <span><strong><?= htmlspecialchars($permissionNames[$permissionCode] ?? $permissionCode) ?></strong><small><?= htmlspecialchars($permissionCode) ?></small></span>
                            <?php endforeach; ?>
                        </div>
                    </article>
                    <article class="security-card phase-notice">
                        <p class="eyebrow">Desarrollo por fases</p>
                        <h3>Interfaz de acceso preparada</h3>
                        <p>La operación funcional de este módulo corresponde a la Fase <?= (int) $module['phase'] ?>. Su acceso ya queda gobernado por permisos y no se muestra a usuarios no autorizados.</p>
                    </article>
                </section><?php endif; ?>
            </section>
        <?php endforeach; ?>

        <?php if ($canProfile): ?><section class="app-view" data-view="perfil" hidden>
            <section class="page-intro">
                <div><p class="eyebrow">Tu cuenta</p><h2>Mi perfil</h2><p>Información de identidad y alcance asociada a tu sesión.</p></div>
                <span class="profile-avatar"><?= htmlspecialchars($initials) ?></span>
            </section>
            <section class="profile-grid">
                <article class="profile-card"><small>NOMBRE COMPLETO</small><strong><?= htmlspecialchars($profile['full_name']) ?></strong></article>
                <article class="profile-card"><small>CORREO ELECTRÓNICO</small><strong><?= htmlspecialchars($profile['email']) ?></strong></article>
                <article class="profile-card"><small>ROL BASE</small><strong><?= htmlspecialchars($profile['role']['name']) ?></strong></article>
                <article class="profile-card"><small>EMPRESA</small><strong><?= htmlspecialchars($profile['company']['name']) ?></strong></article>
                <article class="profile-card"><small>ESTADO</small><strong class="success-text"><?= $profile['is_active'] ? 'Cuenta activa' : 'Cuenta inactiva' ?></strong></article>
                <article class="profile-card"><small>ÚLTIMO ACCESO</small><strong><?= htmlspecialchars($formatDate($profile['last_login_at'])) ?></strong></article>
                <article class="profile-card"><small>CONTRASEÑA ACTUALIZADA</small><strong><?= htmlspecialchars($formatDate($profile['password_changed_at'])) ?></strong></article>
                <article class="profile-card"><small>PREFERENCIA VISUAL</small><strong id="profileTheme"><?= htmlspecialchars($themeLabels[$profile['theme']] ?? $profile['theme']) ?></strong></article>
            </section>
        </section><?php endif; ?>

        <?php if ($canSecurity): ?><section class="app-view" data-view="seguridad" hidden>
            <section class="page-intro compact">
                <div><p class="eyebrow">Protección de la cuenta</p><h2>Seguridad</h2><p>Administra tu contraseña, sesiones activas y permisos efectivos.</p></div>
            </section>
            <section class="security-layout">
                <?php if ($canChangePassword): ?><article class="security-card">
                    <p class="eyebrow">Credenciales</p>
                    <h2>Cambiar contraseña</h2>
                    <p>Al actualizarla se cerrarán todas tus demás sesiones activas.</p>
                    <form id="passwordForm">
                        <label>Contraseña actual<input type="password" name="current_password" autocomplete="current-password" required></label>
                        <label>Nueva contraseña<input type="password" name="new_password" autocomplete="new-password" minlength="12" required></label>
                        <small>12 caracteres, mayúscula, minúscula, número y símbolo.</small>
                        <div class="form-message" id="passwordMessage" role="status"></div>
                        <button class="submit" type="submit">Actualizar contraseña</button>
                    </form>
                </article><?php endif; ?>
                <article class="security-card permission-card">
                    <p class="eyebrow">Autorización efectiva</p>
                    <h2>Permisos de la sesión</h2>
                    <ul>
                        <?php foreach ($profile['permissions'] as $permission): ?>
                            <li><span>✓</span><?= htmlspecialchars($permission) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </article>
                <?php if ($canViewSessions): ?>
                    <article class="security-card sessions-card">
                        <div class="card-heading"><div><p class="eyebrow">Dispositivos</p><h2>Sesiones activas</h2></div><button class="ghost-button" id="refreshSessions" type="button">Actualizar</button></div>
                        <p>Puedes revocar accesos abiertos en otros navegadores o dispositivos.</p>
                        <div class="sessions-list" id="sessionsList" aria-live="polite"><p class="muted">Consultando sesiones…</p></div>
                    </article>
                <?php endif; ?>
            </section>
        </section><?php endif; ?>

        <?php if ($canManagePermissions): ?><section class="app-view" data-view="permisos" hidden>
            <section class="page-intro compact">
                <div><p class="eyebrow">Control de autorización</p><h2>Permisos por rol</h2><p>Define qué módulos y acciones puede utilizar cada nivel de acceso.</p></div>
            </section>
            <section class="permission-admin">
                <article class="security-card role-selector">
                    <label for="permissionRole">Rol a configurar</label>
                    <select id="permissionRole" aria-describedby="permissionSummary"></select>
                    <p id="permissionSummary" class="muted">Consultando matriz de permisos…</p>
                </article>
                <div class="permission-modules" id="permissionMatrix" aria-live="polite">
                    <article class="security-card"><p class="muted">Consultando permisos…</p></article>
                </div>
            </section>
        </section><?php endif; ?>
        <footer>© 2026 Sistema de Vigilancia · Fase 5 · Sesiones y asistencias</footer>
    </main>
</div>
<div class="toast" id="toast" role="status" aria-live="polite"></div>
<dialog class="organization-dialog" id="organizationDialog">
    <form method="dialog" id="organizationForm">
        <div class="card-heading"><div><p class="eyebrow">Fase 3</p><h2 id="organizationDialogTitle">Nuevo registro</h2></div><button class="ghost-button" value="cancel" type="button" id="closeOrganizationDialog">Cerrar</button></div>
        <div class="organization-form-grid" id="organizationFields"></div>
        <div class="form-message" id="organizationMessage" role="status"></div>
        <button class="submit" type="submit">Guardar registro</button>
    </form>
</dialog>
<dialog class="organization-dialog" id="workforceDialog">
    <form method="dialog" id="workforceForm">
        <div class="card-heading"><div><p class="eyebrow">Fase 4</p><h2 id="workforceDialogTitle">Nuevo registro</h2></div><button class="ghost-button" value="cancel" type="button" id="closeWorkforceDialog">Cerrar</button></div>
        <div class="organization-form-grid" id="workforceFields"></div>
        <div class="form-message" id="workforceMessage" role="status"></div>
        <button class="submit" type="submit">Guardar registro</button>
    </form>
</dialog>
<script type="module" src="assets/js/app.js?v=4.0.2"></script>
<script type="module" src="assets/js/phase3.js?v=4.0.2"></script>
<script type="module" src="assets/js/phase4.js?v=4.0.3"></script>
<script type="module" src="assets/js/phase5-panel.js?v=5.0.0"></script>
</body>
</html>
