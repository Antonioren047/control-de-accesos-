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
    <link rel="stylesheet" href="assets/css/phase5.css?v=5.1.0">
    <link rel="stylesheet" href="assets/css/phase6.css">
    <link rel="stylesheet" href="assets/css/phase7.css">
    <link rel="stylesheet" href="assets/css/phase8.css?v=8.0.2">
    <link rel="stylesheet" href="assets/css/phase9.css?v=9.0.0">
    <link rel="stylesheet" href="assets/css/phase10.css?v=10.0.0">
    <link rel="stylesheet" href="assets/css/phase11.css?v=11.0.0">
    <link rel="stylesheet" href="assets/css/phase12.css?v=12.2.0">
    <link rel="stylesheet" href="assets/css/site-ui.css?v=1.0.0">
    <link rel="stylesheet" href="assets/css/ui-map.css?v=1.0.0">
    <script src="assets/js/site-ui.js?v=1.0.1"></script>
    <script src="assets/js/ui-map.js?v=1.0.1"></script>
</head>
<body>
<a class="skip-link" href="#mainContent">Saltar al contenido principal</a>
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
        <div class="sidebar-foot">Sistema completo · Versión 12</div>
    </aside>
    <button class="sidebar-backdrop" id="sidebarBackdrop" type="button" aria-label="Cerrar menÃº" tabindex="-1"></button>
    <main id="mainContent" tabindex="-1">
        <header class="topbar">
            <button class="mobile-menu" id="mobileMenu" aria-label="Abrir menú">☰</button>
            <div><p class="eyebrow" id="viewEyebrow">Acceso autenticado</p><h1 id="viewTitle">Panel principal</h1></div>
            <div class="top-actions">
                <span class="connection"><i></i>Sesión activa</span>
                <div class="notification-center">
                    <button class="notification-button" id="notificationButton" type="button" aria-label="Abrir notificaciones" aria-expanded="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg><span id="notificationBadge" hidden>0</span></button>
                    <div class="notification-dropdown" id="notificationDropdown" hidden><div class="notification-dropdown-head"><strong>Notificaciones</strong><button type="button" id="readAllNotifications">Marcar todas</button></div><div id="notificationPreview"><p class="muted">Consultando…</p></div></div>
                </div>
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
            <section class="phase10-dashboard" id="roleDashboard">
                <div class="section-head"><div><p class="eyebrow">DASHBOARD POR ROL</p><h2>Indicadores de operación</h2></div><small id="dashboardUpdated">Consultando…</small></div>
                <div class="dashboard-filters"><label>Fecha<input type="date" id="dashboardDate" value="<?= gmdate('Y-m-d') ?>"></label><label>Cliente<select id="dashboardClient"><option value="">Todos</option></select></label><label>Lugar<select id="dashboardLocation"><option value="">Todos</option></select></label><label>Turno<select id="dashboardShift"><option value="">Todos</option></select></label><button class="ghost-button" id="dashboardRefresh" type="button">Actualizar</button></div>
                <div class="dashboard-metrics" id="dashboardMetrics"><article class="security-card"><p class="muted">Calculando indicadores…</p></article></div>
            </section>
        </section>

        <?php foreach ($availableModules as $moduleId => $module):
            $grantedModulePermissions = array_values(array_intersect($module['permissions'], $profile['permissions']));
            $isPhaseThreeModule = in_array($moduleId, ['clientes', 'sitios', 'mis_unidades', 'turnos'], true)
                || ($moduleId === 'usuarios' && array_intersect(['residents.manage','guards.manage','guards.view'], $profile['permissions']));
            $isPhaseFiveModule = in_array($moduleId, ['operacion','asistencias','mi_actividad'], true);
            $isPhaseSixModule = $moduleId === 'sincronizacion';
            $isPhaseSevenModule = in_array($moduleId, ['visitas','proveedores'], true);
            $isPhaseEightModule = in_array($moduleId, ['eventos','recorridos'], true);
            $isPhaseNineModule = $moduleId === 'supervisiones';
            $isPhaseElevenModule = in_array($moduleId,['reportes','auditoria','mantenimiento'],true);
            $statusEntities=[];
            if($moduleId==='clientes'&&in_array('clients.manage',$profile['permissions'],true))$statusEntities[]='client';
            if($moduleId==='sitios'){if(in_array('locations.manage',$profile['permissions'],true))$statusEntities[]='location';if(in_array('access_points.manage',$profile['permissions'],true))$statusEntities[]='access_point';if(in_array('units.manage',$profile['permissions'],true))$statusEntities[]='unit';}
            if($moduleId==='usuarios'&&in_array('residents.manage',$profile['permissions'],true))$statusEntities[]='resident';
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
                        data-can-manage-users="<?= $profile['role']['code']==='superadmin'?'1':'0' ?>"
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
                                <?php if ($moduleId === 'usuarios' && $profile['role']['code'] === 'superadmin'): ?><button class="submit" type="button" data-workforce-create="user">Nuevo usuario administrativo</button><?php endif; ?>
                                <?php if ($moduleId === 'turnos' && in_array('shifts.manage', $profile['permissions'], true)): ?><button class="ghost-button" type="button" data-workforce-create="shift">Nuevo turno</button><?php endif; ?>
                                <?php if ($moduleId === 'turnos' && in_array('assignments.manage', $profile['permissions'], true)): ?><button class="submit" type="button" data-workforce-create="assignment">Nueva asignación</button><?php endif; ?>
                            </div>
                        </div>
                        <div class="organization-content" data-organization-content data-status-entities="<?= htmlspecialchars(implode(',',$statusEntities)) ?>"><article class="security-card"><p class="muted">Consultando registros…</p></article></div>
                    </section>
                <?php elseif ($isPhaseFiveModule): ?>
                    <section class="phase5-workspace" data-phase5-module="<?= htmlspecialchars($moduleId) ?>" data-can-close="<?= in_array('operational_sessions.close',$profile['permissions'],true)?'1':'0' ?>">
                        <div class="organization-toolbar"><div><p class="eyebrow">Fase 5 activa</p><h3><?= $moduleId==='operacion'?'Sesiones operativas':($moduleId==='asistencias'?'Control de asistencias':'Mi historial operativo') ?></h3></div>
                        <?php if($moduleId==='operacion' && $profile['role']['code']==='guard'): ?><a class="submit" href="guard-access.php">Abrir acceso operativo</a><?php endif; ?></div>
                        <div class="organization-content" data-phase5-content><article class="security-card"><p class="muted">Consultando registros…</p></article></div>
                    </section>
                <?php elseif ($isPhaseSixModule): ?>
                    <section class="phase6-workspace" data-phase6-module="sincronizacion">
                        <div class="organization-toolbar">
                            <div><p class="eyebrow">Fase 6 activa</p><h3>Registros que requieren revisión</h3></div>
                            <button class="ghost-button" type="button" data-phase6-refresh>Actualizar</button>
                        </div>
                        <p class="muted">Los conflictos y registros vencidos se conservan; un supervisor debe aceptarlos o rechazarlos con comentario.</p>
                        <div class="organization-content" data-phase6-content><article class="security-card"><p class="muted">Consultando registros…</p></article></div>
                    </section>
                <?php elseif ($isPhaseSevenModule): ?>
                    <section class="phase7-workspace" data-phase7-module="<?= htmlspecialchars($moduleId) ?>" data-role="<?= htmlspecialchars($profile['role']['code']) ?>">
                        <div class="organization-toolbar"><div><p class="eyebrow">Fase 7 activa</p><h3><?= $moduleId==='visitas'?'Autorizaciones de visitantes':'Accesos de proveedores' ?></h3></div><div class="organization-actions">
                            <?php if($moduleId==='visitas' && $profile['role']['code']==='resident'): ?><button class="submit" type="button" data-phase7-create="visit">Nueva visita</button><?php endif; ?>
                            <?php if($moduleId==='proveedores' && in_array($profile['role']['code'],['resident','admin','supervisor','superadmin'],true)): ?><button class="submit" type="button" data-phase7-create="provider">Nuevo acceso</button><?php endif; ?>
                            <button class="ghost-button" type="button" data-phase7-refresh>Actualizar</button>
                        </div></div>
                        <div class="organization-content" data-phase7-content><article class="security-card"><p class="muted">Consultando accesos…</p></article></div>
                    </section>
                <?php elseif ($isPhaseEightModule): ?>
                    <section class="phase8-workspace" data-phase8-module="<?= htmlspecialchars($moduleId) ?>"
                        data-can-manage="<?= in_array('events.manage',$profile['permissions'],true)?'1':'0' ?>"
                        data-can-review="<?= in_array('events.review',$profile['permissions'],true)?'1':'0' ?>"
                        data-can-round-review="<?= in_array('rounds.review',$profile['permissions'],true)?'1':'0' ?>">
                        <div class="organization-toolbar"><div><p class="eyebrow">Fase 8 activa</p><h3><?= $moduleId==='eventos'?'Incidencias, evidencias y comentarios':'Seguimiento de recorridos' ?></h3></div><div class="organization-actions">
                            <?php if($moduleId==='eventos' && in_array('events.manage',$profile['permissions'],true)): ?><button class="submit" type="button" data-phase8-types>Tipos de incidencia</button><?php endif; ?>
                            <button class="ghost-button" type="button" data-phase8-refresh>Actualizar</button>
                        </div></div>
                        <div class="organization-content" data-phase8-content><article class="security-card"><p class="muted">Consultando registros…</p></article></div>
                    </section>
                <?php elseif ($isPhaseNineModule): ?>
                    <section class="phase9-workspace" data-phase9-module="supervisiones" data-can-schedule="<?= in_array('supervisions.schedule',$profile['permissions'],true)?'1':'0' ?>">
                        <div class="organization-toolbar"><div><p class="eyebrow">Fase 9 activa</p><h3>Programación y ejecución de supervisiones</h3></div><div class="organization-actions">
                            <?php if(in_array('supervisions.schedule',$profile['permissions'],true)): ?><button class="ghost-button" type="button" data-phase9-schedule>Programar</button><?php endif; ?>
                            <button class="ghost-button" type="button" data-phase9-pin>Configurar PIN</button><button class="submit" type="button" data-phase9-start>Iniciar manual</button><button class="ghost-button" type="button" data-phase9-refresh>Actualizar</button>
                        </div></div>
                        <div class="organization-content" data-phase9-content><article class="security-card"><p class="muted">Consultando supervisiones…</p></article></div>
                    </section>
                <?php elseif ($isPhaseElevenModule): ?>
                    <section class="phase11-workspace" data-phase11-module="<?= htmlspecialchars($moduleId) ?>">
                        <div class="organization-toolbar"><div><p class="eyebrow">Fase 11 activa</p><h3><?= $moduleId==='reportes'?'GeneraciÃ³n protegida de documentos PDF':($moduleId==='auditoria'?'Trazabilidad permanente de acciones':'Almacenamiento y procesos automÃ¡ticos') ?></h3></div><button class="ghost-button" type="button" data-phase11-refresh>Actualizar</button></div>
                        <?php if($moduleId==='reportes'): ?><form class="phase11-filters" data-report-form><label>Reporte<select name="report_type" required></select></label><label>Desde<input name="date_from" type="date" value="<?= gmdate('Y-m-d',strtotime('-30 days')) ?>" required></label><label>Hasta<input name="date_to" type="date" value="<?= gmdate('Y-m-d') ?>" required></label><label>Cliente<select name="client_id"><option value="">Todos</option></select></label><label>Lugar<select name="location_id"><option value="">Todos</option></select></label><label>Punto<select name="access_point_id"><option value="">Todos</option></select></label><label>Vigilante<select name="guard_id"><option value="">Todos</option></select></label><label>Turno<select name="shift_id"><option value="">Todos</option></select></label><button class="submit" type="submit">Generar PDF</button></form><?php endif; ?>
                        <?php if($moduleId==='auditoria'): ?><form class="phase11-filters" data-audit-form><label>Desde<input name="date_from" type="date" value="<?= gmdate('Y-m-d',strtotime('-30 days')) ?>"></label><label>Hasta<input name="date_to" type="date" value="<?= gmdate('Y-m-d') ?>"></label><label>MÃ³dulo<select name="module"><option value="">Todos</option></select></label><label>Usuario<select name="user_id"><option value="">Todos</option></select></label><button class="submit" type="submit">Consultar</button><a class="ghost-button" data-audit-pdf href="audit-report.php" target="_blank">Exportar PDF</a></form><?php endif; ?>
                        <?php if($moduleId==='mantenimiento'): ?><div class="phase11-cron-actions"><button class="submit" type="button" data-cron-run>Ejecutar procesos ahora</button><small>La ejecuciÃ³n es idempotente y cuenta con bloqueo de concurrencia.</small></div><?php endif; ?>
                        <div class="organization-content" data-phase11-content><article class="security-card"><p class="muted">Consultando informaciÃ³nâ€¦</p></article></div>
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
        <footer>© 2026 Sistema de Vigilancia · Versión 12 · Integración final</footer>
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
<dialog class="organization-dialog phase7-dialog" id="phase7Dialog">
    <form method="dialog" id="phase7Form"><div class="card-heading"><div><p class="eyebrow">Fase 7</p><h2 id="phase7DialogTitle">Nuevo acceso</h2></div><button class="ghost-button" type="button" id="closePhase7Dialog">Cerrar</button></div><div class="organization-form-grid" id="phase7Fields"></div><div class="form-message" id="phase7Message" role="alert"></div><button class="submit" type="submit">Guardar y generar QR</button></form>
</dialog>
<dialog class="organization-dialog phase8-dialog" id="phase8Dialog">
    <form method="dialog" id="phase8Form"><div class="card-heading"><div><p class="eyebrow">Fase 8</p><h2 id="phase8DialogTitle">Detalle</h2></div><button class="ghost-button" type="button" id="closePhase8Dialog">Cerrar</button></div><div id="phase8DialogBody"></div><div class="form-message" id="phase8Message" role="alert"></div></form>
</dialog>
<dialog class="organization-dialog phase9-dialog" id="phase9Dialog">
    <form method="dialog" id="phase9Form"><div class="card-heading"><div><p class="eyebrow">Fase 9</p><h2 id="phase9DialogTitle">Supervisión</h2></div><button class="ghost-button" type="button" id="closePhase9Dialog">Cerrar</button></div><div id="phase9DialogBody"></div><div class="form-message" id="phase9Message" role="alert"></div></form>
</dialog>
<dialog class="phase9-camera" id="phase9CameraDialog"><div><video id="phase9Video" autoplay playsinline muted></video><canvas id="phase9Canvas" hidden></canvas><div class="close-actions"><button class="submit" id="phase9Capture" type="button">Capturar fotografía</button><button class="ghost-button" id="phase9CloseCamera" type="button">Cancelar</button></div></div></dialog>
<script type="module" src="assets/js/app.js?v=12.0.2"></script>
<script type="module" src="assets/js/phase3.js?v=4.2.0"></script>
<script type="module" src="assets/js/phase4.js?v=4.2.2"></script>
<script type="module" src="assets/js/phase5-panel.js?v=5.1.0"></script>
<script type="module" src="assets/js/phase6-panel.js?v=6.1.0"></script>
<script type="module" src="assets/js/phase7-panel.js?v=7.1.0"></script>
<script type="module" src="assets/js/phase8-panel.js?v=8.1.0"></script>
<script type="module" src="assets/js/phase9-panel.js?v=9.0.0"></script>
<script type="module" src="assets/js/phase10.js?v=10.0.0"></script>
<script type="module" src="assets/js/phase11.js?v=11.0.0"></script>
<script type="module" src="assets/js/phase12.js?v=12.0.0"></script>
</body>
</html>
