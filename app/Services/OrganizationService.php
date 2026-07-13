<?php
declare(strict_types=1);
namespace Vigilancia\Services;

use PDO;
use PDOException;
use Vigilancia\Auth\PasswordPolicy;
use Vigilancia\Exceptions\HttpException;
use Vigilancia\Repositories\OrganizationRepository;
use Vigilancia\Repositories\PermissionRepository;
use Vigilancia\Repositories\SecurityLogRepository;
use Vigilancia\Support\ClientInfo;
use Vigilancia\Validation\Validator;

final class OrganizationService
{
    private AuthorizationService $authorization;
    private ScopeService $scope;
    public function __construct(
        private PDO $pdo,
        private OrganizationRepository $organization,
        private SecurityLogRepository $logs
    ) {
        $this->authorization=new AuthorizationService(new PermissionRepository($pdo));
        $this->scope=new ScopeService($pdo);
    }

    public function list(array $actor, string $entity): array
    {
        return match($entity){
            'clients'=>$this->authorizedList($actor,['clients.manage'],fn()=>$this->organization->clients($actor)),
            'locations'=>$this->authorizedList($actor,['locations.manage','locations.view'],fn()=>$this->organization->locations($actor)),
            'access_points'=>$this->authorizedList($actor,['access_points.manage','access_points.view'],fn()=>$this->organization->accessPoints($actor)),
            'units'=>$this->authorizedList($actor,['units.manage','units.view','units.view_own'],fn()=>$this->organization->units($actor)),
            'residents'=>$this->authorizedList($actor,['residents.manage'],fn()=>$this->organization->residents($actor)),
            default=>throw new HttpException('El catálogo solicitado no existe.',404),
        };
    }

    public function create(array $actor, string $entity, array $input): int
    {
        try {
            $id=match($entity){
                'client'=>$this->createClient($actor,$input),
                'location'=>$this->createLocation($actor,$input),
                'access_point'=>$this->createPoint($actor,$input),
                'unit'=>$this->createUnit($actor,$input),
                'resident'=>$this->createResident($actor,$input),
                default=>throw new HttpException('La entidad indicada no existe.',404),
            };
        } catch(PDOException $exception) {
            if((string)$exception->getCode()==='23000') throw new HttpException('El código o correo ya está registrado dentro de este alcance.',409);
            throw $exception;
        }
        $this->audit($actor,'organization.'.$entity.'_created',['record_id'=>$id]);
        return $id;
    }

    public function setActive(array $actor,string $entity,int $id,bool $active):void
    {
        $permissions=['client'=>'clients.manage','location'=>'locations.manage','access_point'=>'access_points.manage','unit'=>'units.manage'];
        if(!isset($permissions[$entity])) throw new HttpException('La entidad indicada no existe.',404);
        $this->authorization->require($actor,$permissions[$entity]);
        if($entity==='client' && $actor['role_code']!=='superadmin') throw new HttpException('Solo el Superadministrador puede cambiar el estado de un cliente.',403);
        $allowed=match($entity){'client'=>$this->scope->client($actor,$id),'location'=>$this->scope->location($actor,$id),'access_point'=>$this->scope->accessPoint($actor,$id),'unit'=>$this->scope->unit($actor,$id)};
        if(!$allowed) throw new HttpException('El registro está fuera de tu alcance.',403);
        $this->organization->setActive($entity,$id,$active,(int)$actor['id']);
        $this->audit($actor,'organization.'.$entity.'_status_changed',['record_id'=>$id,'is_active'=>$active]);
    }

    private function createClient(array $actor,array $input):int
    {
        $this->authorization->require($actor,'clients.manage');
        if($actor['role_code']!=='superadmin') throw new HttpException('Solo el Superadministrador puede crear clientes.',403);
        $this->validate($input,['code','name','timezone']);$this->validateCode((string)$input['code']);
        return $this->organization->createClient(['code'=>strtoupper(trim($input['code'])),'name'=>trim($input['name']),'legal_name'=>trim((string)($input['legal_name']??'')),'timezone'=>(string)$input['timezone']],(int)$actor['surveillance_company_id'],(int)$actor['id']);
    }

    private function createLocation(array $actor,array $input):int
    {
        $this->authorization->require($actor,'locations.manage');$this->validate($input,['client_id','code','name','address_line','timezone']);
        if(!$this->scope->client($actor,(int)$input['client_id'])) throw new HttpException('El cliente está fuera de tu alcance.',403);$this->validateCode((string)$input['code']);
        $data=['client_id'=>(int)$input['client_id'],'code'=>strtoupper(trim($input['code'])),'name'=>trim($input['name']),'address_line'=>trim($input['address_line']),'city'=>trim((string)($input['city']??'')),'state'=>trim((string)($input['state']??'')),'postal_code'=>trim((string)($input['postal_code']??'')),'timezone'=>(string)$input['timezone']];
        return $this->organization->createLocation($data,(int)$actor['id']);
    }

    private function createPoint(array $actor,array $input):int
    {
        $this->authorization->require($actor,'access_points.manage');$this->validate($input,['location_id','code','name','point_type']);
        if(!$this->scope->location($actor,(int)$input['location_id'])) throw new HttpException('El lugar está fuera de tu alcance.',403);$this->validateCode((string)$input['code']);
        if(!in_array($input['point_type'],['main','pedestrian','vehicle','service'],true)) throw new HttpException('El tipo de punto no es válido.',422);
        return $this->organization->createAccessPoint(['location_id'=>(int)$input['location_id'],'code'=>strtoupper(trim($input['code'])),'name'=>trim($input['name']),'point_type'=>$input['point_type']],(int)$actor['id']);
    }

    private function createUnit(array $actor,array $input):int
    {
        $this->authorization->require($actor,'units.manage');$this->validate($input,['location_id','code','name','unit_type']);
        if(!$this->scope->location($actor,(int)$input['location_id'])) throw new HttpException('El lugar está fuera de tu alcance.',403);$this->validateCode((string)$input['code']);
        if(!in_array($input['unit_type'],['house','apartment','lot','warehouse'],true)) throw new HttpException('El tipo de unidad no es válido.',422);
        return $this->organization->createUnit(['location_id'=>(int)$input['location_id'],'code'=>strtoupper(trim($input['code'])),'name'=>trim($input['name']),'unit_type'=>$input['unit_type']],(int)$actor['id']);
    }

    private function createResident(array $actor,array $input):int
    {
        $this->authorization->require($actor,'residents.manage');$this->validate($input,['full_name','email','password','unit_id']);
        if(!$this->scope->unit($actor,(int)$input['unit_id'])) throw new HttpException('La unidad está fuera de tu alcance.',403);
        if(!Validator::email((string)$input['email'])) throw new HttpException('El correo no es válido.',422);
        $errors=PasswordPolicy::errors((string)$input['password']);if($errors) throw new HttpException('La contraseña no cumple la política.',422,['password'=>$errors]);
        $this->pdo->beginTransaction();
        try{$id=$this->organization->createResident(['full_name'=>trim($input['full_name']),'email'=>strtolower(trim($input['email'])),'password_hash'=>password_hash($input['password'],PASSWORD_DEFAULT),'phone'=>trim((string)($input['phone']??'')),'unit_id'=>(int)$input['unit_id']],(int)$actor['surveillance_company_id'],(int)$actor['id']);$this->pdo->commit();return $id;}catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    private function authorizedList(array $actor,array $permissions,callable $callback):array
    {
        if(array_intersect($permissions,$this->authorization->permissionsFor($actor))===[]) throw new HttpException('No cuentas con permiso para consultar este módulo.',403);
        return $callback();
    }
    private function validate(array $input,array $fields):void{$errors=Validator::required($input,$fields);if($errors)throw new HttpException('Revisa los datos ingresados.',422,$errors);}
    private function validateCode(string $code):void{if(!preg_match('/^[A-Za-z0-9_-]{2,40}$/',$code))throw new HttpException('El código debe usar de 2 a 40 letras, números, guion o guion bajo.',422);}
    private function audit(array $actor,string $event,array $context):void{$this->logs->record((int)$actor['id'],$event,ClientInfo::ip(),ClientInfo::userAgent(),$context);}
}
