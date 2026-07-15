<?php
declare(strict_types=1);
$root=require dirname(__DIR__).'/bootstrap/app.php';

use Vigilancia\Database\Connection;
use Vigilancia\Repositories\PermissionRepository;
use Vigilancia\Repositories\WorkforceRepository;
use Vigilancia\Services\AuthService;
use Vigilancia\Services\AuthorizationService;
use Vigilancia\Support\Config;
use Vigilancia\Support\Session;

Session::start();$pdo=Connection::make(Config::database());$auth=new AuthService($pdo);$actor=$auth->current();
(new AuthorizationService(new PermissionRepository($pdo)))->require($actor,'guards.credential');
$credential=(new WorkforceRepository($pdo))->credential((int)($_GET['id']??0));
if(!$credential){http_response_code(404);exit('Credencial no encontrada.');}
$company=(int)$pdo->query('SELECT surveillance_company_id FROM users WHERE id='.(int)$credential['guard_user_id'])->fetchColumn();
if($company!==(int)$actor['surveillance_company_id']){http_response_code(403);exit('Acceso denegado.');}
$asset=static function(?string $path)use($root):string{if(!$path||!is_file($root.'/'.$path))return'';$mime=(new finfo(FILEINFO_MIME_TYPE))->file($root.'/'.$path);return'data:'.$mime.';base64,'.base64_encode((string)file_get_contents($root.'/'.$path));};
$photo=$asset($credential['photo_path']);$qr=$asset($credential['qr_asset_path']);$status=$credential['status']==='active'&&$credential['guard_status']==='active'?'VIGENTE':'NO VIGENTE';$issued=date('d/m/Y',strtotime($credential['issued_at']));$isPdf=($_GET['format']??'')==='pdf';
ob_start();
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Credencial de <?=htmlspecialchars($credential['full_name'])?></title><style>
@page{margin:18mm}body{font-family:DejaVu Sans,Arial;color:#0b1d3a;background:#f5f7fa}.sheet{max-width:780px;margin:20px auto}.toolbar{text-align:right;margin-bottom:14px}.toolbar button,.toolbar a{display:inline-block;background:#0b1d3a;color:#fff;border:0;border-radius:8px;padding:10px 18px;text-decoration:none;cursor:pointer;margin-left:6px}.credential{background:#fff;border:2px solid #163d73;border-radius:22px;overflow:hidden;box-shadow:0 14px 35px #0b1d3a22}.header{background:#0b1d3a;color:#fff;padding:26px 30px}.header small{color:#8fb8e9;letter-spacing:2px}.body{padding:28px 30px;display:table;width:100%;box-sizing:border-box}.photo,.details,.qr{display:table-cell;vertical-align:middle}.photo{width:145px}.photo img{width:125px;height:150px;object-fit:cover;border-radius:14px}.details h1{font-size:24px;margin:0 0 10px}.details p{margin:7px 0}.qr{width:170px;text-align:center}.qr img{width:145px;height:145px}.missing{width:145px;height:145px;background:#e6e9ef;display:flex;align-items:center;justify-content:center;color:#486a92}.status{display:inline-block;border-radius:99px;padding:6px 12px;background:#e3f5e9;color:#176337;font-weight:bold}.footer{border-top:1px solid #dce2ea;padding:18px 30px;font-size:12px;color:#486a92}.warning{margin-top:14px;padding:12px;background:#fff4d6;border-radius:8px}@media print{body{background:#fff}.toolbar,.warning{display:none}.credential{box-shadow:none}}
</style></head><body><div class="sheet">
<?php if(!$isPdf):?><div class="toolbar"><a href="?id=<?=(int)$credential['id']?>&amp;format=pdf">Descargar PDF</a><button type="button" id="printCredential">Imprimir credencial</button></div><?php endif;?>
<section class="credential"><div class="header"><small>CONTROL DE ACCESOS</small><h2>Sistema de Vigilancia</h2></div><div class="body"><div class="photo"><?php if($photo):?><img src="<?=htmlspecialchars($photo)?>" alt="Fotografía"><?php else:?><div class="missing">Sin foto</div><?php endif;?></div><div class="details"><h1><?=htmlspecialchars($credential['full_name'])?></h1><p><strong>No. de empleado:</strong> <?=htmlspecialchars($credential['employee_number'])?></p><p><strong>Emitida:</strong> <?=htmlspecialchars($issued)?></p><p><strong>Vigencia:</strong> Permanente hasta revocación</p><span class="status"><?=htmlspecialchars($status)?></span></div><div class="qr"><?php if($qr):?><img src="<?=htmlspecialchars($qr)?>" alt="Código QR"><small>Referencia <?=htmlspecialchars($credential['token_reference'])?></small><?php else:?><div class="missing">QR pendiente</div><?php endif;?></div></div><div class="footer">El código contiene un token aleatorio; no almacena nombre, teléfono ni otros datos personales.</div></section>
<?php if(!$qr):?><p class="warning">Regenera la credencial para producir el QR.</p><?php endif;?></div>
<?php if(!$isPdf):?><script src="assets/js/credential.js?v=4.0.3" defer></script><?php endif;?></body></html><?php
$html=(string)ob_get_clean();
if($isPdf){if(!class_exists(\Dompdf\Dompdf::class)){http_response_code(503);exit('PDF no disponible: instala las dependencias de la Fase 4.');}$dompdf=new \Dompdf\Dompdf(['isRemoteEnabled'=>false]);$dompdf->loadHtml($html,'UTF-8');$dompdf->setPaper('letter','landscape');$dompdf->render();$dompdf->stream('credencial-'.$credential['employee_number'].'.pdf',['Attachment'=>true]);exit;}
header('Content-Type: text/html; charset=utf-8');echo$html;
