<?php
declare(strict_types=1);
namespace Vigilancia\Services;

use PDO;
use PDOException;
use Vigilancia\Exceptions\HttpException;
use Vigilancia\Repositories\PermissionRepository;
use Vigilancia\Repositories\SecurityLogRepository;
use Vigilancia\Repositories\WorkforceRepository;
use Vigilancia\Support\ClientInfo;
use Vigilancia\Validation\Validator;

final class WorkforceService
{
    private AuthorizationService $authorization;
    private ScopeService $scope;
    public function __construct(private PDO $pdo,private WorkforceRepository $repository,private CredentialAssetService $assets,private SecurityLogRepository $logs){$this->authorization=new AuthorizationService(new PermissionRepository($pdo));$this->scope=new ScopeService($pdo);}

    public function list(array $actor,string $entity):array{return match($entity){
        'guards'=>$this->authorizedList($actor,['guards.manage','guards.view'],fn()=>$this->repository->guards($actor)),
        'shifts'=>$this->authorizedList($actor,['shifts.manage','shifts.view'],fn()=>$this->repository->shifts($actor)),
        'assignments'=>$this->authorizedList($actor,['assignments.manage','assignments.view'],fn()=>$this->repository->assignments($actor)),
        default=>throw new HttpException('El catálogo solicitado no existe.',404),
    };}

    public function create(array $actor,string $entity,array $input):array
    {
        try{return match($entity){'guard'=>$this->createGuard($actor,$input),'shift'=>['id'=>$this->createShift($actor,$input)],'assignment'=>['id'=>$this->createAssignment($actor,$input)],default=>throw new HttpException('La entidad indicada no existe.',404)};}
        catch(PDOException $e){if((string)$e->getCode()==='23000')throw new HttpException('El número de empleado, correo o registro indicado ya existe.',409);throw $e;}
    }

    public function action(array $actor,string $action,array $input):array
    {
        $guardId=(int)($input['guard_id']??0);$assignmentId=(int)($input['assignment_id']??0);
        return match($action){
            'reset_pin'=>$this->resetPin($actor,$guardId),
            'regenerate_credential'=>$this->regenerateCredential($actor,$guardId),
            'revoke_credential'=>$this->revokeCredential($actor,$guardId),
            'cancel_assignment'=>$this->cancelAssignment($actor,$assignmentId),
            'request_change'=>$this->requestChange($actor,$assignmentId,$input),
            default=>throw new HttpException('La acción indicada no existe.',404),
        };
    }

    private function createGuard(array $actor,array $input):array
    {
        $this->authorization->require($actor,'guards.manage');$this->requireAdmin($actor);
        $required=['full_name','employee_number','photo_data','phone','address_line','hire_date','emergency_contact_name','emergency_contact_phone'];$errors=Validator::required($input,$required);if($errors)throw new HttpException('Completa los datos obligatorios del vigilante.',422,$errors);
        $employee=strtoupper(trim((string)$input['employee_number']));if(!preg_match('/^[A-Z0-9_-]{2,40}$/',$employee))throw new HttpException('El número de empleado no es válido.',422);
        $email=strtolower(trim((string)($input['email']??'')));if($email!==''&&!Validator::email($email))throw new HttpException('El correo no es válido.',422);
        $curp=strtoupper(trim((string)($input['curp']??'')));if($curp!==''&&!preg_match('/^[A-Z0-9]{18}$/',$curp))throw new HttpException('La CURP debe contener 18 caracteres.',422);
        $photo=$this->assets->savePhoto((string)$input['photo_data']);$pin=$this->newPin();$token=bin2hex(random_bytes(32));
        $this->pdo->beginTransaction();try{$guard=$this->repository->createGuard(['full_name'=>trim((string)$input['full_name']),'employee_number'=>$employee,'photo_path'=>$photo,'phone'=>trim((string)$input['phone']),'email'=>$email,'curp'=>$curp,'address_line'=>trim((string)$input['address_line']),'hire_date'=>(string)$input['hire_date'],'emergency_contact_name'=>trim((string)$input['emergency_contact_name']),'emergency_contact_phone'=>trim((string)$input['emergency_contact_phone']),'pin_hash'=>password_hash($pin,PASSWORD_DEFAULT)],(int)$actor['surveillance_company_id'],(int)$actor['id']);$credential=$this->repository->createCredential($guard['id'],hash('sha256',$token),substr($token,0,12));$this->pdo->commit();}catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
        $qr=$this->assets->createQr($token,$credential);if($qr)$this->repository->setQrPath($credential,$qr);$this->audit($actor,'workforce.guard_created',['guard_id'=>$guard['id'],'credential_id'=>$credential]);
        return ['id'=>$guard['id'],'pin'=>$pin,'credential_id'=>$credential,'qr_available'=>$qr!==null];
    }

    private function createShift(array $actor,array $input):int
    {
        $this->authorization->require($actor,'shifts.manage');$this->requireAdmin($actor);$errors=Validator::required($input,['name','start_time','end_time','days','location_ids']);if($errors)throw new HttpException('Completa los datos obligatorios del turno.',422,$errors);
        $days=$this->days($input['days']);$locations=$this->ids($input['location_ids']);if(!$locations)throw new HttpException('Selecciona al menos un lugar.',422);foreach($locations as $location)if(!$this->scope->location($actor,$location))throw new HttpException('Uno de los lugares está fuera de tu alcance.',403);
        foreach(['start_time','end_time']as$f)if(!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',(string)$input[$f]))throw new HttpException('La hora del turno no es válida.',422);
        $data=['name'=>trim((string)$input['name']),'start_time'=>$input['start_time'],'end_time'=>$input['end_time'],'crosses_midnight'=>$input['end_time']<=$input['start_time'],'tolerance_minutes'=>$this->minutes($input['tolerance_minutes']??10),'early_tolerance'=>$this->minutes($input['early_departure_tolerance_minutes']??0),'overtime_minutes'=>$this->minutes($input['overtime_after_minutes']??15),'days'=>$days,'location_ids'=>$locations];
        $this->pdo->beginTransaction();try{$id=$this->repository->createShift($data,(int)$actor['surveillance_company_id'],(int)$actor['id']);$this->pdo->commit();}catch(\Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}$this->audit($actor,'workforce.shift_created',['shift_id'=>$id]);return$id;
    }

    private function createAssignment(array $actor,array $input):int
    {
        $this->authorization->require($actor,'assignments.manage');$this->requireAdmin($actor);$errors=Validator::required($input,['guard_user_id','client_id','location_id','access_point_id','shift_id','start_date','days']);if($errors)throw new HttpException('Completa los datos obligatorios de la asignación.',422,$errors);
        $d=['guard_user_id'=>(int)$input['guard_user_id'],'client_id'=>(int)$input['client_id'],'location_id'=>(int)$input['location_id'],'access_point_id'=>(int)$input['access_point_id'],'shift_id'=>(int)$input['shift_id'],'start_date'=>(string)$input['start_date'],'end_date'=>trim((string)($input['end_date']??'')),'days'=>$this->days($input['days']),'assignment_type'=>(string)($input['assignment_type']??'regular'),'rotation_pattern'=>trim((string)($input['rotation_pattern']??'')),'replaces_assignment_id'=>(int)($input['replaces_assignment_id']??0)];
        if(!in_array($d['assignment_type'],['regular','rotation','temporary'],true))throw new HttpException('El tipo de asignación no es válido.',422);if(!$this->scope->location($actor,$d['location_id'])||!$this->scope->accessPoint($actor,$d['access_point_id']))throw new HttpException('El alcance de la asignación no está autorizado.',403);if(!$this->repository->guardInCompany($d['guard_user_id'],(int)$actor['surveillance_company_id']))throw new HttpException('El vigilante no pertenece a la empresa.',422);if(!$this->repository->assignmentRelations($d['client_id'],$d['location_id'],$d['access_point_id'],$d['shift_id']))throw new HttpException('Cliente, lugar, punto y turno no corresponden entre sí.',422);if($d['end_date']!==''&&$d['end_date']<$d['start_date'])throw new HttpException('La fecha final no puede ser anterior a la inicial.',422);if($this->repository->overlappingAssignments($d['guard_user_id'],$d['start_date'],$d['end_date']?:null,$d['days']))throw new HttpException('El vigilante ya tiene una asignación activa que se traslapa.',409);
        $id=$this->repository->createAssignment($d,(int)$actor['id']);$this->audit($actor,'workforce.assignment_created',['assignment_id'=>$id]);return$id;
    }

    private function resetPin(array $actor,int $guardId):array{$this->authorization->require($actor,'guards.pin_reset');$this->requireGuard($actor,$guardId);$pin=$this->newPin();$this->repository->resetPin($guardId,password_hash($pin,PASSWORD_DEFAULT));$this->audit($actor,'workforce.pin_reset',['guard_id'=>$guardId]);return['pin'=>$pin];}
    private function regenerateCredential(array $actor,int $guardId):array{$this->authorization->require($actor,'guards.credential');$this->requireGuard($actor,$guardId);$old=$this->repository->activeCredentialId($guardId);$token=bin2hex(random_bytes(32));$this->pdo->beginTransaction();try{$this->repository->revokeCredentials($guardId,(int)$actor['id']);$id=$this->repository->createCredential($guardId,hash('sha256',$token),substr($token,0,12),$old);$this->pdo->commit();}catch(\Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}$qr=$this->assets->createQr($token,$id);if($qr)$this->repository->setQrPath($id,$qr);$this->audit($actor,'workforce.credential_regenerated',['guard_id'=>$guardId,'credential_id'=>$id]);return['credential_id'=>$id,'qr_available'=>$qr!==null];}
    private function revokeCredential(array $actor,int $guardId):array{$this->authorization->require($actor,'guards.credential');$this->requireGuard($actor,$guardId);$this->repository->revokeCredentials($guardId,(int)$actor['id']);$this->audit($actor,'workforce.credential_revoked',['guard_id'=>$guardId]);return[];}
    private function cancelAssignment(array $actor,int $id):array{$this->authorization->require($actor,'assignments.manage');$this->requireAdmin($actor);if(!$this->repository->assignmentInScope($id,$actor))throw new HttpException('La asignación no existe o está fuera de tu alcance.',404);$this->repository->cancelAssignment($id,(int)$actor['id']);$this->audit($actor,'workforce.assignment_cancelled',['assignment_id'=>$id]);return[];}
    private function requestChange(array $actor,int $id,array $input):array{$this->authorization->require($actor,'assignments.request_change');if(!$this->repository->assignmentInScope($id,$actor))throw new HttpException('La asignación no existe o está fuera de tu alcance.',404);$comment=trim((string)($input['comment']??''));if($comment===''||mb_strlen($comment)>500)throw new HttpException('Describe el cambio solicitado en un máximo de 500 caracteres.',422);$type=(string)($input['request_type']??'change');if(!in_array($type,['change','replacement','cancel'],true))throw new HttpException('El tipo de solicitud no es válido.',422);$requestId=$this->repository->requestAssignmentChange($id,(int)$actor['id'],$type,$comment);$this->audit($actor,'workforce.assignment_change_requested',['assignment_id'=>$id,'request_id'=>$requestId]);return['request_id'=>$requestId];}
    private function authorizedList(array $actor,array $permissions,callable $callback):array{if(array_intersect($permissions,$this->authorization->permissionsFor($actor))===[])throw new HttpException('No cuentas con permiso para consultar este módulo.',403);return$callback();}
    private function requireAdmin(array $actor):void{if(!in_array($actor['role_code'],['superadmin','admin'],true))throw new HttpException('Esta acción solo corresponde a Superadministrador o Administrador.',403);}
    private function requireGuard(array $actor,int $id):void{$this->requireAdmin($actor);if($id<=0||!$this->repository->guardInCompany($id,(int)$actor['surveillance_company_id']))throw new HttpException('El vigilante no existe.',404);}
    private function ids(mixed $value):array{$items=is_array($value)?$value:explode(',',(string)$value);return array_values(array_unique(array_filter(array_map('intval',$items),fn($v)=>$v>0)));}
    private function days(mixed $value):array{$days=$this->ids($value);sort($days);if(!$days||array_diff($days,[1,2,3,4,5,6,7]))throw new HttpException('Los días deben indicarse del 1 (lunes) al 7 (domingo).',422);return$days;}
    private function minutes(mixed $value):int{$n=filter_var($value,FILTER_VALIDATE_INT);if($n===false||$n<0||$n>720)throw new HttpException('La tolerancia debe estar entre 0 y 720 minutos.',422);return$n;}
    private function newPin():string{return str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT);}
    private function audit(array $actor,string $event,array $context):void{$this->logs->record((int)$actor['id'],$event,ClientInfo::ip(),ClientInfo::userAgent(),$context);}
}
