<?php
declare(strict_types=1);

namespace Vigilancia\Services;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Throwable;
use Vigilancia\Exceptions\HttpException;
use Vigilancia\Repositories\AccessRepository;
use Vigilancia\Repositories\OperationalRepository;
use Vigilancia\Repositories\PermissionRepository;
use Vigilancia\Repositories\SecurityLogRepository;
use Vigilancia\Support\ClientInfo;
use Vigilancia\Validation\Validator;

final class AccessService
{
    private AuthorizationService $authorization;

    public function __construct(
        private PDO $pdo,
        private AccessRepository $repository,
        private OperationalRepository $operations,
        private AccessAssetService $assets,
        private SecurityLogRepository $logs
    ) { $this->authorization = new AuthorizationService(new PermissionRepository($pdo)); }

    public function catalog(array $actor): array { return ['units' => $this->repository->catalog($actor)]; }

    public function visits(array $actor): array
    {
        $this->requireAny($actor, ['visits.manage','visits.view']);
        return $this->repository->visits($actor);
    }

    public function createVisit(array $actor, array $input): array
    {
        $this->authorization->require($actor, 'visits.manage');
        if ($actor['role_code'] !== 'resident') throw new HttpException('Solo el residente puede generar visitas.', 403);
        $data = $this->visitData($actor, $input);
        $policy = $data['policy'];
        if ($this->repository->activeVisitCount((int)$actor['id']) >= (int)$policy['max_active_visits_per_resident']) throw new HttpException('Alcanzaste el máximo de QR de visita activos.', 409);
        if ($this->repository->duplicateVisit((int)$actor['id'], $data['location_id'], $data['visitor_name'], $data['scheduled_at'])) throw new HttpException('Ya existe una visita para la misma persona, lugar y horario.', 409);
        $token = bin2hex(random_bytes(32)); $data['token_hash'] = hash('sha256', $token); $data['reference'] = substr(hash('sha256', $token . random_bytes(8)), 0, 12);
        $id = $this->repository->createVisit($data);
        $qr = $this->assets->createQr($token, 'visit', $id); $this->repository->setVisitQr($id, $qr);
        $this->audit($actor, 'visits.created', ['visit_id'=>$id]);
        return ['id'=>$id,'reference'=>$data['reference'],'qr_url'=>'visit-qr.php?type=visit&id='.$id];
    }

    public function visitAction(array $actor, array $input): array
    {
        $this->authorization->require($actor, 'visits.manage');
        if ($actor['role_code'] !== 'resident') throw new HttpException('Solo el residente puede modificar sus visitas.', 403);
        $id=(int)($input['id']??0);$visit=$this->repository->visitById($id);
        if(!$visit||(int)$visit['resident_user_id']!==(int)$actor['id'])throw new HttpException('La visita no existe o no te pertenece.',404);
        $action=(string)($input['action']??'');
        if(in_array($action,['cancel','edit'],true)&&($visit['status']!=='pending'||$visit['first_used_at']))throw new HttpException('La visita ya fue utilizada, cancelada o vencida y no puede modificarse.',409);
        if($action==='cancel'){$this->repository->cancelVisit($id,(int)$actor['id']);$this->audit($actor,'visits.cancelled',['visit_id'=>$id]);return[];}
        if($action==='share'){$channel=(string)($input['channel']??'native');if(!in_array($channel,['native','whatsapp','download'],true))throw new HttpException('El canal de compartir no es válido.',422);$this->repository->shareVisit($id,(int)$actor['id'],$channel,ClientInfo::ip(),ClientInfo::userAgent());return[];}
        if($action==='edit'){$data=$this->visitData($actor,$input);if($this->repository->duplicateVisit((int)$actor['id'],$data['location_id'],$data['visitor_name'],$data['scheduled_at'],$id))throw new HttpException('Ya existe una visita duplicada para ese horario.',409);$this->repository->updateVisit($id,$data);$this->audit($actor,'visits.updated',['visit_id'=>$id]);return[];}
        throw new HttpException('La acción solicitada no existe.',422);
    }

    public function validateVisit(string $token): array
    {
        $session=$this->guardSession();$this->repository->expire();$visit=$this->repository->visitByToken($token);
        if(!$visit||(int)$visit['location_id']!==(int)$session['location_id'])throw new HttpException('El QR no corresponde a una visita de este lugar.',404);
        if($visit['status']==='inside')return['mode'=>'checkout','visit'=>$this->publicVisit($visit)];
        if($visit['status']!=='pending')throw new HttpException('La visita está vencida, cancelada o ya fue utilizada.',409);
        $now=time();if($now<strtotime($visit['valid_from']))throw new HttpException('La visita todavía no se encuentra dentro del horario autorizado.',409);if($now>strtotime($visit['valid_until']))throw new HttpException('La visita ha vencido.',409);
        return['mode'=>'checkin','visit'=>$this->publicVisit($visit),'privacy_version'=>$visit['privacy_notice_version'],'minor_exempt'=>(bool)$visit['minors_identification_exempt']];
    }

    public function checkInVisit(array$input):array
    {
        $session=$this->guardSession();$token=trim((string)($input['qr_token']??''));$validation=$this->validateVisit($token);if($validation['mode']!=='checkin')throw new HttpException('La visita ya se encuentra dentro.',409);$visit=$this->repository->visitByToken($token);
        if(empty($input['privacy_accepted']))throw new HttpException('Debes confirmar la aceptación del aviso de privacidad.',422);
        $minor=!empty($input['is_minor'])&&(bool)$visit['minors_identification_exempt'];$type=trim((string)($input['identification_type']??''));$number=trim((string)($input['identification_number']??''));if(!$minor&&($type===''||$number===''))throw new HttpException('El tipo y número de identificación son obligatorios.',422);
        $visitorPhoto=$this->assets->saveImage((string)($input['visitor_photo']??''),'visits/people');$idPhoto=$this->assets->saveImage((string)($input['identification_photo']??''),'visits/identifications',!$minor);
        $this->pdo->beginTransaction();try{$accessId=$this->repository->checkInVisit(['visit_id'=>(int)$visit['id'],'session_id'=>(int)$session['id'],'point_id'=>(int)$session['access_point_id'],'guard_id'=>(int)$session['guard_user_id'],'identification_type'=>$type?:null,'identification_number'=>$number?:null,'identification_photo'=>$idPhoto,'visitor_photo'=>$visitorPhoto,'privacy_version'=>$visit['privacy_notice_version'],'ip'=>ClientInfo::ip(),'device'=>(string)($input['device_identifier']??'')]);$this->pdo->commit();}catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
        $this->logs->record((int)$session['guard_user_id'],'visits.checked_in',ClientInfo::ip(),ClientInfo::userAgent(),['visit_id'=>$visit['id'],'access_id'=>$accessId]);return[];
    }

    public function checkOutVisit(array$input):array
    {
        $session=$this->guardSession();$visit=$this->repository->visitByToken(trim((string)($input['qr_token']??'')));if(!$visit||(int)$visit['location_id']!==(int)$session['location_id']||$visit['status']!=='inside')throw new HttpException('No existe una visita activa para registrar la salida.',404);
        $this->repository->checkOutVisit((int)$visit['id'],(int)$session['guard_user_id'],(int)$session['access_point_id']);$this->logs->record((int)$session['guard_user_id'],'visits.checked_out',ClientInfo::ip(),ClientInfo::userAgent(),['visit_id'=>$visit['id']]);return[];
    }

    public function active(): array { $session=$this->guardSession();return['visits'=>$this->repository->activeVisits((int)$session['location_id']),'providers'=>$this->repository->activeProviders((int)$session['location_id'])]; }

    public function providers(array$actor):array{$this->requireAny($actor,['providers.create','providers.manage']);return$this->repository->providers($actor);}
    public function validateProvider(string$token):array{$session=$this->guardSession();$provider=$this->repository->providerByToken(trim($token));if(!$provider||(int)$provider['location_id']!==(int)$session['location_id'])throw new HttpException('El QR no corresponde a un proveedor de este lugar.',404);if($provider['status']!=='pending')throw new HttpException('El acceso del proveedor ya fue utilizado, cancelado o cerrado.',409);return['id'=>(int)$provider['id'],'provider_company'=>$provider['provider_company'],'service_type'=>$provider['service_type'],'responsible_name'=>$provider['responsible_name'],'materials'=>$provider['materials'],'tools'=>$provider['tools']];}
    public function createProvider(array$actor,array$input):array
    {
        $permission=$actor['role_code']==='resident'?'providers.create':'providers.manage';$this->authorization->require($actor,$permission);$this->required($input,['unit_id','provider_company','service_type','responsible_name']);$unit=null;foreach($this->repository->catalog($actor)as$row)if((int)$row['unit_id']===(int)$input['unit_id'])$unit=$row;if(!$unit)throw new HttpException('La unidad está fuera de tu alcance.',403);
        $token=bin2hex(random_bytes(32));$data=['resident_id'=>$actor['role_code']==='resident'?(int)$actor['id']:0,'creator'=>(int)$actor['id'],'unit_id'=>(int)$unit['unit_id'],'location_id'=>(int)$unit['location_id'],'company'=>trim($input['provider_company']),'service'=>trim($input['service_type']),'responsible'=>trim($input['responsible_name']),'materials'=>trim((string)($input['materials']??'')),'tools'=>trim((string)($input['tools']??'')),'scheduled_at'=>$this->optionalUtc((string)($input['scheduled_at']??''),(string)$unit['timezone']),'token_hash'=>hash('sha256',$token),'reference'=>substr(hash('sha256',$token.random_bytes(8)),0,12)];$id=$this->repository->createProvider($data);$this->repository->setProviderQr($id,$this->assets->createQr($token,'provider',$id));$this->audit($actor,'providers.created',['provider_id'=>$id]);return['id'=>$id,'reference'=>$data['reference'],'qr_url'=>'visit-qr.php?type=provider&id='.$id];
    }

    public function checkInProvider(array$input):array
    {
        $session=$this->guardSession();$token=trim((string)($input['qr_token']??''));$provider=$token!==''?$this->repository->providerByToken($token):null;if($provider&&((int)$provider['location_id']!==(int)$session['location_id']||$provider['status']!=='pending'))throw new HttpException('El QR de proveedor no es válido para este lugar o ya fue utilizado.',409);
        if(!$provider){$this->required($input,['provider_company','service_type','responsible_name']);$id=$this->repository->createProvider(['resident_id'=>0,'creator'=>(int)$session['guard_user_id'],'unit_id'=>(int)($input['unit_id']??0),'location_id'=>(int)$session['location_id'],'company'=>trim($input['provider_company']),'service'=>trim($input['service_type']),'responsible'=>trim($input['responsible_name']),'materials'=>trim((string)($input['materials']??'')),'tools'=>trim((string)($input['tools']??'')),'scheduled_at'=>null,'token_hash'=>null,'reference'=>null]);$provider=$this->repository->providerById($id);}
        if(empty($input['privacy_accepted']))throw new HttpException('Debes confirmar el aviso de privacidad.',422);$type=trim((string)($input['identification_type']??''));$number=trim((string)($input['identification_number']??''));if($type===''||$number==='')throw new HttpException('La identificación del responsable es obligatoria.',422);$policy=$this->repository->policyForLocation((int)$session['location_id']);
        $this->repository->checkInProvider((int)$provider['id'],['session_id'=>(int)$session['id'],'point_id'=>(int)$session['access_point_id'],'guard_id'=>(int)$session['guard_user_id'],'identification_type'=>$type,'identification_number'=>$number,'identification_photo'=>$this->assets->saveImage((string)($input['identification_photo']??''),'providers/identifications'),'person_photo'=>$this->assets->saveImage((string)($input['person_photo']??''),'providers/people'),'privacy_version'=>$policy['privacy_notice_version']??'1.0','ip'=>ClientInfo::ip()]);$this->logs->record((int)$session['guard_user_id'],'providers.checked_in',ClientInfo::ip(),ClientInfo::userAgent(),['provider_id'=>$provider['id']]);return[];
    }

    public function checkOutProvider(array$input):array{$session=$this->guardSession();$id=(int)($input['provider_id']??0);$provider=$this->repository->providerById($id);if(!$provider||(int)$provider['location_id']!==(int)$session['location_id']||$provider['status']!=='inside')throw new HttpException('El proveedor no tiene una entrada activa en este lugar.',404);$this->repository->checkOutProvider($id,(int)$session['guard_user_id']);$this->logs->record((int)$session['guard_user_id'],'providers.checked_out',ClientInfo::ip(),ClientInfo::userAgent(),['provider_id'=>$id]);return[];}

    private function visitData(array$actor,array$input):array{$this->required($input,['unit_id','visitor_name','host_name','reason','scheduled_at']);$unit=$this->repository->unitForResident((int)$input['unit_id'],(int)$actor['id']);if(!$unit)throw new HttpException('La unidad está fuera de tu alcance.',403);$scheduled=$this->utc((string)$input['scheduled_at'],(string)$unit['timezone']);$now=new DateTimeImmutable('now',new DateTimeZone('UTC'));if($scheduled<$now->modify('-5 minutes'))throw new HttpException('El horario de la visita no puede estar en el pasado.',422);if($scheduled>$now->modify('+'.(int)$unit['max_advance_days'].' days'))throw new HttpException('La visita supera la anticipación máxima permitida.',422);return['resident_id'=>(int)$actor['id'],'unit_id'=>(int)$unit['unit_id'],'location_id'=>(int)$unit['location_id'],'visitor_name'=>trim($input['visitor_name']),'phone'=>trim((string)($input['phone']??'')),'identification_type'=>trim((string)($input['identification_type']??'')),'identification_number'=>trim((string)($input['identification_number']??'')),'company'=>trim((string)($input['company']??'')),'host_name'=>trim($input['host_name']),'reason'=>trim($input['reason']),'license_plate'=>strtoupper(trim((string)($input['license_plate']??''))),'vehicle'=>trim((string)($input['vehicle']??'')),'scheduled_at'=>$scheduled->format('Y-m-d H:i:s'),'valid_from'=>$scheduled->format('Y-m-d H:i:s'),'valid_until'=>$scheduled->modify('+'.(int)$unit['visit_duration_minutes'].' minutes')->format('Y-m-d H:i:s'),'policy'=>$unit];}
    private function publicVisit(array$v):array{return['id'=>(int)$v['id'],'visitor_name'=>$v['visitor_name'],'host_name'=>$v['host_name'],'reason'=>$v['reason'],'company'=>$v['company'],'license_plate'=>$v['license_plate'],'vehicle'=>$v['vehicle'],'valid_until'=>$v['valid_until']];}
    private function guardSession():array{$id=(int)($_SESSION['operational_session_id']??0);$guard=(int)($_SESSION['operational_guard_id']??0);$row=$id?$this->operations->session($id):null;if(!$row||$row['status']!=='active'||(int)$row['guard_user_id']!==$guard)throw new HttpException('Se requiere una sesión operativa activa.',401);return$row;}
    private function required(array$input,array$fields):void{$errors=Validator::required($input,$fields);if($errors)throw new HttpException('Completa los datos requeridos.',422,$errors);}
    private function utc(string$value,string$timezone):DateTimeImmutable{try{return(new DateTimeImmutable($value,new DateTimeZone($timezone)))->setTimezone(new DateTimeZone('UTC'));}catch(Throwable){throw new HttpException('La fecha y hora no son válidas.',422);}}
    private function optionalUtc(string$value,string$timezone):?string{return trim($value)===''?null:$this->utc($value,$timezone)->format('Y-m-d H:i:s');}
    private function requireAny(array$actor,array$permissions):void{if(array_intersect($permissions,$this->authorization->permissionsFor($actor))===[])throw new HttpException('No cuentas con permiso para consultar este módulo.',403);}
    private function audit(array$actor,string$event,array$context):void{$this->logs->record((int)$actor['id'],$event,ClientInfo::ip(),ClientInfo::userAgent(),$context);}
}
