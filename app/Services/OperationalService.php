<?php
declare(strict_types=1);
namespace Vigilancia\Services;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Throwable;
use Vigilancia\Auth\BackoffPolicy;
use Vigilancia\Exceptions\HttpException;
use Vigilancia\Operations\AttendanceClassifier;
use Vigilancia\Repositories\OperationalRepository;
use Vigilancia\Repositories\OfflineRepository;
use Vigilancia\Repositories\PermissionRepository;
use Vigilancia\Repositories\SecurityLogRepository;
use Vigilancia\Support\ClientInfo;

final class OperationalService
{
    private AuthorizationService $authorization;
    public function __construct(private PDO $pdo,private OperationalRepository $repository,private OperationalPhotoService $photos,private SecurityLogRepository $logs,private OfflineRepository $offline){$this->authorization=new AuthorizationService(new PermissionRepository($pdo));}
    public function catalog():array{return['points'=>$this->repository->catalog()];}
    public function start(array $in):array
    {
        foreach(['client_id','location_id','access_point_id','qr_token','pin','photo_data','device_identifier'] as $f)if(trim((string)($in[$f]??''))==='')throw new HttpException('Completa los datos requeridos para iniciar.',422);
        $pin=(string)$in['pin'];if(!preg_match('/^\d{6}$/',$pin))throw new HttpException('El PIN debe contener 6 dÃ­gitos.',422);
        $credential=$this->findCredential((string)$in['qr_token']);if(!$credential||!(bool)$credential['is_active']||$credential['guard_status']!=='active')throw new HttpException('La credencial o el PIN no son vÃ¡lidos.',401);
        $nowUtc=new DateTimeImmutable('now',new DateTimeZone('UTC'));
        if($credential['pin_blocked_until']&&$nowUtc<new DateTimeImmutable($credential['pin_blocked_until'],new DateTimeZone('UTC')))throw new HttpException('El PIN estÃ¡ temporalmente bloqueado. Intenta mÃ¡s tarde.',429);
        if(!password_verify($pin,$credential['pin_hash'])){$attempts=(int)$credential['pin_failed_attempts']+1;$delay=BackoffPolicy::seconds($attempts);$blocked=$delay?gmdate('Y-m-d H:i:s',time()+$delay):null;$this->repository->failedPin((int)$credential['guard_user_id'],$attempts,$blocked);$this->logs->record((int)$credential['guard_user_id'],'operations.pin_failed',ClientInfo::ip(),ClientInfo::userAgent(),['attempts'=>$attempts]);throw new HttpException($delay?'PIN incorrecto. El acceso quedÃ³ temporalmente bloqueado.':'La credencial o el PIN no son vÃ¡lidos.',401);}
        $this->repository->resetPinFailures((int)$credential['guard_user_id']);
        $assignment=$this->resolveAssignment((int)$credential['guard_user_id'],(int)$in['client_id'],(int)$in['location_id'],(int)$in['access_point_id']);
        if(!$assignment)throw new HttpException('No existe una asignaciÃ³n activa para este vigilante, lugar y punto de acceso.',403);
        if($this->repository->activeForGuard((int)$credential['guard_user_id']))throw new HttpException('Ya tienes una sesiÃ³n operativa activa.',409);
        if($this->repository->activeForPoint((int)$in['access_point_id']))throw new HttpException('El punto conserva una sesiÃ³n activa. Un supervisor debe cerrarla con comentario antes de continuar.',409);
        $entry=AttendanceClassifier::entry($assignment['now_local'],$assignment['start_local'],$assignment['end_local'],(int)$assignment['tolerance_minutes']);
        $photo=$this->photos->save((string)$in['photo_data']);$data=['guard_user_id'=>(int)$credential['guard_user_id'],'assignment_id'=>(int)$assignment['id'],'shift_id'=>(int)$assignment['shift_id'],'client_id'=>(int)$in['client_id'],'location_id'=>(int)$in['location_id'],'access_point_id'=>(int)$in['access_point_id'],'now_utc'=>$nowUtc->format('Y-m-d H:i:s'),'scheduled_start'=>$assignment['start_local']->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),'scheduled_end'=>$assignment['end_local']->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),'photo'=>$photo,'entry_classification'=>$entry['classification'],'minutes_late'=>$entry['minutes_late'],'device_type'=>substr(trim((string)($in['device_type']??'unknown')),0,30),'browser'=>substr(trim((string)($in['browser']??'unknown')),0,80),'os'=>substr(trim((string)($in['operating_system']??'unknown')),0,80),'ip'=>ClientInfo::ip(),'device_identifier'=>hash('sha256',(string)$in['device_identifier']),'connection'=>'online'];
        $this->pdo->beginTransaction();try{if($this->repository->activeForGuard($data['guard_user_id'],true)||$this->repository->activeForPoint($data['access_point_id'],true))throw new HttpException('No es posible abrir la sesiÃ³n porque ya existe otra sesiÃ³n activa.',409);$id=$this->repository->create($data);$this->pdo->commit();}catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
        session_regenerate_id(true);$_SESSION['operational_session_id']=$id;$_SESSION['operational_guard_id']=$data['guard_user_id'];$offlineToken=bin2hex(random_bytes(32));$this->offline->authorize($data['guard_user_id'],$data['device_identifier'],hash('sha256',$offlineToken));$_SESSION['offline_sync_token']=$offlineToken;$this->logs->record($data['guard_user_id'],'operations.session_started',ClientInfo::ip(),ClientInfo::userAgent(),['session_id'=>$id,'classification'=>$entry['classification']]);return$this->current();
    }
    public function current():array{$id=(int)($_SESSION['operational_session_id']??0);if(!$id)throw new HttpException('No existe una sesiÃ³n operativa activa.',401);$row=$this->repository->session($id);if(!$row||$row['status']!=='active'||(int)$row['guard_user_id']!==(int)($_SESSION['operational_guard_id']??0)){$this->clear();throw new HttpException('La sesiÃ³n operativa ya no estÃ¡ activa.',401);}$row['offline']=['token'=>(string)($_SESSION['offline_sync_token']??''),'expires_in_hours'=>24];return$row;}
    public function close(array $in):array
    {
        $row=$this->current();$token=trim((string)($in['qr_token']??''));$credential=$token===''?null:$this->findCredential($token);if(!$credential||(int)$credential['guard_user_id']!==(int)$row['guard_user_id'])throw new HttpException('Escanea el QR o ingresa la referencia vigente del vigilante para cerrar.',401);
        if(!$this->repository->hasShiftNovelty((int)$row['id']))throw new HttpException('Registra la novedad de entrega antes de cerrar el turno.',409);
        $now=new DateTimeImmutable('now',new DateTimeZone('UTC'));$exit=AttendanceClassifier::exit($now,new DateTimeImmutable($row['scheduled_end_at'],new DateTimeZone('UTC')),(int)$row['early_departure_tolerance_minutes'],(int)$row['overtime_after_minutes']);$this->repository->close((int)$row['id'],$now->format('Y-m-d H:i:s'),$exit['classification'],$exit['minutes_early'],$exit['overtime_minutes'],'qr',(int)$row['guard_user_id'],null);$this->logs->record((int)$row['guard_user_id'],'operations.session_closed',ClientInfo::ip(),ClientInfo::userAgent(),['session_id'=>$row['id'],'classification'=>$exit['classification']]);$this->clear();return['classification'=>$exit['classification']];
    }
    public function list(array $actor,bool $attendance):array{$this->authorization->require($actor,$attendance?($actor['role_code']==='guard'?'attendance.own':'attendance.view'):'operations.view');return$this->repository->list($actor,$attendance);}
    public function manualClose(array $actor,array $in):array{$this->authorization->require($actor,'operational_sessions.close');$id=(int)($in['session_id']??0);$comment=trim((string)($in['comment']??''));if(mb_strlen($comment)<10||mb_strlen($comment)>500)throw new HttpException('El comentario obligatorio debe contener entre 10 y 500 caracteres.',422);if(!$this->repository->inScope($id,$actor))throw new HttpException('La sesiÃ³n no existe, ya cerrÃ³ o estÃ¡ fuera de tu alcance.',404);$now=gmdate('Y-m-d H:i:s');$this->repository->close($id,$now,'incomplete_shift',0,0,'supervisor',(int)$actor['id'],$comment,'manual_closed');$this->logs->record((int)$actor['id'],'operations.session_manual_close',ClientInfo::ip(),ClientInfo::userAgent(),['session_id'=>$id,'comment'=>$comment]);return[];}
    private function resolveAssignment(int$guard,int$client,int$location,int$point):?array{$tz=new DateTimeZone(date_default_timezone_get());$now=new DateTimeImmutable('now',$tz);foreach($this->repository->assignments($guard,$client,$location,$point)as$row){$cross=(bool)$row['crosses_midnight'];$date=$cross&&$now->format('H:i:s')<(string)$row['end_time']?$now->modify('-1 day')->format('Y-m-d'):$now->format('Y-m-d');$days=json_decode((string)$row['applicable_days'],true)?:[];if($date<(string)$row['start_date']||($row['end_date']&&$date>(string)$row['end_date'])||!in_array((int)(new DateTimeImmutable($date,$tz))->format('N'),array_map('intval',$days),true))continue;$start=new DateTimeImmutable($date.' '.$row['start_time'],$tz);$end=new DateTimeImmutable(($cross?(new DateTimeImmutable($date,$tz))->modify('+1 day')->format('Y-m-d'):$date).' '.$row['end_time'],$tz);$row['now_local']=$now;$row['start_local']=$start;$row['end_local']=$end;return$row;}return null;}
    private function findCredential(string$input):?array{$value=strtolower(trim($input));return preg_match('/^[a-f0-9]{12}$/',$value)?$this->repository->credentialByReference($value):$this->repository->credential(hash('sha256',$value));}
    private function clear():void{unset($_SESSION['operational_session_id'],$_SESSION['operational_guard_id']);session_regenerate_id(true);}
}
