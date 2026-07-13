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
            <a href="#perfil" data-view-target="perfil"><span aria-hidden="true">◇</span><span>Mi perfil</span></a>
            <a href="#seguridad" data-view-target="seguridad"><span aria-hidden="true">⌾</span><span>Seguridad</span></a>
            <a href="docs/"><span aria-hidden="true">▤</span><span>API</span></a>
        </nav>
        <button class="collapse" id="collapse" type="button" aria-label="Colapsar menú">‹</button>
        <div class="sidebar-foot">Fase 2 · Sesión protegida</div>
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

        <section class="app-view" data-view="inicio">
            <section class="hero phase-two">
                <div>
                    <span class="hero-kicker">SESIÓN SEGURA · PERMISOS EN BACKEND</span>
                    <h2>Bienvenido,<br><?= htmlspecialchars(explode(' ', $profile['full_name'])[0]) ?></h2>
                    <p>Tu acceso está protegido y limitado por el rol y los permisos configurados para tu cuenta.</p>
                    <a class="primary-button" href="#seguridad" data-view-target="seguridad">Revisar seguridad <span>→</span></a>
                </div>
                <img src="assets/images/logo.svg" alt="Escudo del Sistema de Vigilancia">
            </section>
            <section class="section-head">
                <div><p class="eyebrow">Resumen</p><h2>Estado de tu acceso</h2></div>
            </section>
            <section class="status-grid auth-grid">
                <article class="status-card"><span class="card-icon">✓</span><div><small>ESTADO</small><h3>Sesión activa</h3><p>Expiración máxima de 24 horas</p></div><span class="badge success">Protegida</span></article>
                <article class="status-card"><span class="card-icon">◇</span><div><small>ROL BASE</small><h3><?= htmlspecialchars($profile['role']['name']) ?></h3><p><?= htmlspecialchars($profile['company']['name']) ?></p></div><span class="badge neutral"><?= count($profile['permissions']) ?> permisos</span></article>
            </section>
        </section>

        <section class="app-view" data-view="perfil" hidden>
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
        </section>

        <section class="app-view" data-view="seguridad" hidden>
            <section class="page-intro compact">
                <div><p class="eyebrow">Protección de la cuenta</p><h2>Seguridad</h2><p>Administra tu contraseña, sesiones activas y permisos efectivos.</p></div>
            </section>
            <section class="security-layout">
                <article class="security-card">
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
                </article>
                <article class="security-card permission-card">
                    <p class="eyebrow">Autorización efectiva</p>
                    <h2>Permisos de la sesión</h2>
                    <ul>
                        <?php foreach ($profile['permissions'] as $permission): ?>
                            <li><span>✓</span><?= htmlspecialchars($permission) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </article>
                <?php if (in_array('auth.sessions.view', $profile['permissions'], true)): ?>
                    <article class="security-card sessions-card">
                        <div class="card-heading"><div><p class="eyebrow">Dispositivos</p><h2>Sesiones activas</h2></div><button class="ghost-button" id="refreshSessions" type="button">Actualizar</button></div>
                        <p>Puedes revocar accesos abiertos en otros navegadores o dispositivos.</p>
                        <div class="sessions-list" id="sessionsList" aria-live="polite"><p class="muted">Consultando sesiones…</p></div>
                    </article>
                <?php endif; ?>
            </section>
        </section>
        <footer>© 2026 Sistema de Vigilancia · Fase 2 · Autenticación y permisos</footer>
    </main>
</div>
<div class="toast" id="toast" role="status" aria-live="polite"></div>
<script type="module" src="assets/js/app.js"></script>
</body>
</html>
