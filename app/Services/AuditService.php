<?php
declare(strict_types=1);
namespace Vigilancia\Services;
use Vigilancia\Repositories\AuditRepository;use Vigilancia\Repositories\PermissionRepository;
final class AuditService
{
 private AuthorizationService$auth;public function __construct(private AuditRepository$repo,PermissionRepository$p){$this->auth=new AuthorizationService($p);}
 public function list(array$a,array$f,int$limit=300):array{$this->auth->require($a,'audit.view');return['items'=>$this->repo->list($a,$f,$limit),'catalog'=>$this->repo->catalog($a)];}
}
