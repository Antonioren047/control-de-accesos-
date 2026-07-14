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
if ($auth->currentOrNull()) {
    header('Location: ./');
    exit;
}
if (session_status() !== PHP_SESSION_ACTIVE) Session::start();
$csrf = CsrfMiddleware::token();
?>
<!doctype html>
<html lang="es" data-theme="auto">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="description" content="Inicio de sesión del Sistema de Vigilancia">
    <title>Iniciar sesión · Sistema de Vigilancia</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/phase2.css">
</head>
<body class="login-page">
<main class="login-shell">
    <section class="login-brand" aria-label="Sistema de Vigilancia">
        <div class="brand-lockup"><img src="assets/images/logo.svg" alt=""><span><strong>Sistema de Vigilancia</strong><small>Control de Accesos</small></span></div>
        <div class="login-brand-copy"><p class="hero-kicker">SEGURIDAD INSTITUCIONAL</p><h1>Acceso seguro<br>con código QR</h1><p>Control, confianza y tecnología para proteger lo que importa.</p></div>
        <ul class="brand-values"><li>Seguridad</li><li>Control</li><li>Confianza</li><li>Tecnología</li></ul>
    </section>
    <section class="login-content">
        <div class="login-toolbar">
            <label class="theme-control">Tema
                <select id="themeSelect" aria-label="Tema"><option value="auto">Automático</option><option value="light">Claro</option><option value="dark">Oscuro</option></select>
            </label>
        </div>
        <form class="login-card" id="loginForm">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <p class="eyebrow">Acceso autorizado</p>
            <h2>Iniciar sesión</h2>
            <p class="login-intro">Ingresa con la cuenta asignada por el administrador.</p>
            <label>Correo electrónico<input type="email" name="email" autocomplete="username" required autofocus></label>
            <label>Contraseña
                <span class="password-field"><input type="password" name="password" autocomplete="current-password" required><button id="togglePassword" type="button" aria-label="Mostrar contraseña">Ver</button></span>
            </label>
            <div class="login-links"><span>¿Olvidaste tu contraseña?</span><small>Próximamente</small></div>
            <div class="form-message" id="loginMessage" role="alert" aria-live="polite"></div>
            <button class="submit login-submit" type="submit">Iniciar sesión <span>→</span></button>
            <a class="ghost-button" style="text-align:center;margin-top:10px" href="guard-access.php">Acceso operativo del vigilante</a>
            <p class="session-note">La sesión puede permanecer activa hasta 24 horas.</p>
            <div class="protected-note"><span>✓</span> Acceso protegido con controles de seguridad institucional.</div>
        </form>
    </section>
</main>
<script type="module" src="assets/js/login.js"></script>
</body>
</html>
