<?php
declare(strict_types=1);

$root = require dirname(__DIR__, 2) . '/bootstrap/app.php';

use Vigilancia\Middleware\CsrfMiddleware;
use Vigilancia\Services\InstallerService;
use Vigilancia\Support\Session;

Session::start();

$locked = is_file($root . '/storage/installed.lock');
$error = '';
$result = null;
$requirements = [
    'PHP 8.1 o superior' => version_compare(PHP_VERSION, '8.1.0', '>='),
    'PDO MySQL' => extension_loaded('pdo_mysql'),
    'JSON' => extension_loaded('json'),
    'Mbstring' => extension_loaded('mbstring'),
    'DOM' => extension_loaded('dom'),
    'Fileinfo' => extension_loaded('fileinfo'),
    'OpenSSL' => extension_loaded('openssl'),
    '.env escribible' => is_writable($root),
    'Storage escribible' => is_writable($root . '/storage'),
];

$secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
$scheme = $secure ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$publicPath = str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/install/index.php')));
$publicPath = $publicPath === '/' ? '' : rtrim($publicPath, '/');
$defaultAppUrl = $scheme . '://' . $host . $publicPath;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$locked) {
    try {
        CsrfMiddleware::verify($_POST['_csrf'] ?? null);
        $result = (new InstallerService($root))->install($_POST);
        $locked = true;
    } catch (Throwable $e) {
        $decoded = json_decode($e->getMessage(), true);
        $error = is_array($decoded)
            ? implode(' ', array_merge(...array_values($decoded)))
            : $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Instalador de producción · Sistema de Vigilancia</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>
<main class="panel">
    <a href="../">← Volver al inicio</a>
    <section class="wizard-card">
        <header class="wizard-head">
            <img src="../assets/images/logo.svg" alt="Sistema de Vigilancia">
            <p class="eyebrow">Control de Accesos</p>
            <h1>Instalador de producción</h1>
            <p>Configuración segura para servidor cPanel.</p>
            <div class="steps" aria-label="5 pasos">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <span class="step <?= ($locked || $i <= 2) ? 'active' : '' ?>" title="Paso <?= $i ?>"></span>
                <?php endfor; ?>
            </div>
        </header>

        <div class="wizard-body">
            <?php if ($locked): ?>
                <div class="notice">
                    <strong>Instalador bloqueado.</strong>
                    <p><?= $result
                        ? 'La instalación de producción terminó correctamente.'
                        : 'El sistema ya fue instalado y se bloqueó una nueva ejecución.' ?></p>
                </div>
                <?php if ($result): ?>
                    <p>
                        Migraciones: <?= htmlspecialchars(implode(', ', $result['migrations'])) ?> ·
                        Catálogos base: <?= htmlspecialchars(implode(', ', $result['seeds'])) ?>
                    </p>
                    <p>Se creó únicamente el usuario global indicado. No se cargó información de prueba.</p>
                <?php endif; ?>
                <a class="primary-button" href="../">Ir al panel <span>→</span></a>
            <?php else: ?>
                <h2>Requisitos y configuración</h2>
                <div class="requirements">
                    <?php foreach ($requirements as $name => $ok): ?>
                        <div class="requirement <?= $ok ? 'ok' : 'fail' ?>">
                            <?= $ok ? '✓' : '×' ?> <?= htmlspecialchars($name) ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if (in_array(false, $requirements, true)): ?>
                    <div class="notice error">Corrige los requisitos marcados antes de instalar.</div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="notice error" role="alert"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="post" autocomplete="off">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(CsrfMiddleware::token()) ?>">
                    <div class="form-grid">
                        <div class="field">
                            <label for="db_host">1. Host de base de datos</label>
                            <input id="db_host" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>" required>
                        </div>
                        <div class="field">
                            <label for="db_port">Puerto</label>
                            <input id="db_port" name="db_port" type="number" value="<?= htmlspecialchars($_POST['db_port'] ?? '3306') ?>" required>
                        </div>
                        <div class="field">
                            <label for="db_name">Base de datos vacía</label>
                            <input id="db_name" name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? '') ?>" pattern="[A-Za-z0-9_]+" required>
                        </div>
                        <div class="field">
                            <label for="db_user">Usuario MySQL</label>
                            <input id="db_user" name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>" required>
                        </div>
                        <div class="field full">
                            <label for="db_password">Contraseña MySQL</label>
                            <input id="db_password" name="db_password" type="password" autocomplete="new-password">
                            <small>La base debe existir, estar vacía y tener todos los privilegios asignados al usuario.</small>
                        </div>
                        <div class="field full">
                            <label for="company_name">2. Empresa de vigilancia</label>
                            <input id="company_name" name="company_name" value="<?= htmlspecialchars($_POST['company_name'] ?? '') ?>" required>
                        </div>
                        <div class="field">
                            <label for="admin_name">3. Nombre del usuario global</label>
                            <input id="admin_name" name="admin_name" value="<?= htmlspecialchars($_POST['admin_name'] ?? '') ?>" required>
                        </div>
                        <div class="field">
                            <label for="admin_email">Correo del usuario global</label>
                            <input id="admin_email" name="admin_email" type="email" value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>" required>
                        </div>
                        <div class="field full">
                            <label for="admin_password">Contraseña segura del usuario global</label>
                            <input id="admin_password" name="admin_password" type="password" minlength="12" autocomplete="new-password" required>
                            <small>Mínimo 12 caracteres, mayúscula, minúscula, número y símbolo.</small>
                        </div>
                        <div class="field">
                            <label for="timezone">4. Zona horaria</label>
                            <select id="timezone" name="timezone">
                                <option>America/Mexico_City</option>
                                <option>UTC</option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="app_url">URL pública del sistema</label>
                            <input id="app_url" name="app_url" type="url" value="<?= htmlspecialchars($_POST['app_url'] ?? $defaultAppUrl) ?>" required>
                        </div>
                    </div>

                    <div class="notice">
                        <strong>5. Instalación limpia y bloqueo</strong>
                        <p>Se crearán la estructura, los catálogos indispensables, la empresa y un único usuario global. No se cargarán clientes, residentes, vigilantes, lugares ni registros de prueba.</p>
                    </div>
                    <button class="submit" type="submit" <?= in_array(false, $requirements, true) ? 'disabled' : '' ?>>
                        Instalar Sistema de Vigilancia
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </section>
    <p class="notice">Cron en cPanel: <code>/usr/local/bin/php -q <?= htmlspecialchars($root) ?>/cron/run.php</code></p>
</main>
</body>
</html>
