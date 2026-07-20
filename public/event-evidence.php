<?php
declare(strict_types=1);
$root=require dirname(__DIR__).'/bootstrap/app.php';
use Vigilancia\Database\Connection;use Vigilancia\Repositories\EventRepository;use Vigilancia\Services\AuthService;use Vigilancia\Support\Config;use Vigilancia\Support\Session;
Session::start();$pdo=Connection::make(Config::database());$repo=new EventRepository($pdo);$id=(int)($_GET['id']??0);$file=$repo->evidence($id);if(!$file){http_response_code(404);exit('Evidencia no encontrada.');}
$allowed=false;$auth=new AuthService($pdo);$actor=$auth->currentOrNull();if($actor){$event=$repo->event((int)$file['event_id']);$allowed=$event&&$repo->inScope($actor,$event);}elseif((int)($_SESSION['operational_guard_id']??0)===(int)$file['guard_user_id']){$allowed=true;}
if(!$allowed){http_response_code(403);exit('Acceso denegado.');}$path=$root.'/'.ltrim((string)$file['file_path'],'/');$storage=realpath($root.'/storage/events');$real=realpath($path);if(!is_file($path)||!$storage||!$real||!str_starts_with($real,$storage.DIRECTORY_SEPARATOR)){http_response_code(404);exit('Archivo no disponible.');}
header('Content-Type: '.$file['mime_type']);header('Content-Length: '.filesize($path));header('Content-Disposition: inline; filename="evidencia-'.$id.'.'.pathinfo($path,PATHINFO_EXTENSION).'"');header('X-Content-Type-Options: nosniff');readfile($path);
