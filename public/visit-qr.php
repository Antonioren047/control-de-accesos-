<?php
declare(strict_types=1);

$root = require dirname(__DIR__) . '/bootstrap/app.php';

use Vigilancia\Database\Connection;
use Vigilancia\Middleware\CsrfMiddleware;
use Vigilancia\Repositories\PermissionRepository;
use Vigilancia\Services\AuthService;
use Vigilancia\Services\AuthorizationService;
use Vigilancia\Services\ScopeService;
use Vigilancia\Support\Config;
use Vigilancia\Support\Session;

Session::start();
$pdo = Connection::make(Config::database());
$actor = (new AuthService($pdo))->current();
$type = ($_GET['type'] ?? 'visit') === 'provider' ? 'provider' : 'visit';
$id = (int) ($_GET['id'] ?? 0);

if ($type === 'visit') {
    $statement = $pdo->prepare("SELECT vp.id,vp.resident_user_id owner_id,vp.location_id,vp.visitor_name display_name,vp.qr_reference,vp.qr_asset_path,u.name unit_name,l.name location_name FROM visitor_passes vp JOIN units u ON u.id=vp.unit_id JOIN locations l ON l.id=vp.location_id WHERE vp.id=?");
} else {
    $statement = $pdo->prepare("SELECT pa.id,COALESCE(pa.resident_user_id,pa.created_by) owner_id,pa.location_id,pa.responsible_name display_name,pa.qr_reference,pa.qr_asset_path,COALESCE(u.name,'Sin unidad') unit_name,l.name location_name FROM provider_accesses pa LEFT JOIN units u ON u.id=pa.unit_id JOIN locations l ON l.id=pa.location_id WHERE pa.id=?");
}
$statement->execute([$id]);
$row = $statement->fetch();
if (!$row) { http_response_code(404); exit('QR no encontrado.'); }

$allowed = (int) $row['owner_id'] === (int) $actor['id'];
if (!$allowed && in_array($actor['role_code'], ['superadmin','admin','supervisor'], true)) {
    $permission = $type === 'visit' ? 'visits.view' : 'providers.manage';
    try {
        (new AuthorizationService(new PermissionRepository($pdo)))->require($actor, $permission);
        $allowed = (new ScopeService($pdo))->location($actor, (int) $row['location_id']);
    } catch (Throwable) { $allowed = false; }
}
if (!$allowed) { http_response_code(403); exit('Acceso denegado.'); }

$path = $row['qr_asset_path'] ? $root . '/' . $row['qr_asset_path'] : '';
if (!$path || !is_file($path)) { http_response_code(404); exit('La imagen QR no está disponible.'); }
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'image/svg+xml';
if (isset($_GET['raw'])) {
    header('Content-Type: ' . $mime);
    header('Content-Disposition: ' . (isset($_GET['download']) ? 'attachment' : 'inline') . '; filename="qr-' . $type . '-' . $id . '.' . ($mime === 'image/png' ? 'png' : 'svg') . '"');
    header('Cache-Control: private, no-store');
    readfile($path);
    exit;
}

$csrf = CsrfMiddleware::token();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf) ?>">
    <title>Código QR de acceso</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/phase7.css">
    <link rel="stylesheet" href="assets/css/ui-map.css?v=1.0.0">
    <style>.qr-sheet{max-width:520px;margin:30px auto;text-align:center}.qr-sheet img{width:min(360px,90%);background:#fff;padding:16px;border-radius:18px}.qr-actions{display:flex;justify-content:center;gap:8px;flex-wrap:wrap;margin-top:18px}</style>
</head>
<body>
<main class="qr-sheet security-card" data-qr-type="<?= htmlspecialchars($type) ?>" data-qr-id="<?= $id ?>" data-display-name="<?= htmlspecialchars($row['display_name']) ?>" data-location-name="<?= htmlspecialchars($row['location_name']) ?>" data-unit-name="<?= htmlspecialchars($row['unit_name']) ?>" data-reference="<?= htmlspecialchars((string) $row['qr_reference']) ?>">
    <p class="eyebrow">ACCESO SEGURO CON CÓDIGO QR</p>
    <h1><?= htmlspecialchars($row['display_name']) ?></h1>
    <p><?= htmlspecialchars($row['location_name'] . ' · ' . $row['unit_name']) ?></p>
    <img id="qrImage" src="?type=<?= urlencode($type) ?>&amp;id=<?= $id ?>&amp;raw=1" alt="Código QR">
    <p><strong>Referencia:</strong> <?= htmlspecialchars((string) $row['qr_reference']) ?></p>
    <div class="qr-actions">
        <button class="ghost-button" type="button" id="downloadQr">Descargar imagen</button>
        <button class="submit" type="button" id="nativeShare">Compartir</button>
    </div>
    <canvas id="shareCanvas" width="900" height="1120" hidden></canvas>
</main>
<script src="assets/js/ui-map.js?v=1.0.1"></script>
<script src="assets/js/visit-qr.js?v=7.0.2" defer></script>
</body>
</html>
