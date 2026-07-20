<?php
declare(strict_types=1);
namespace Vigilancia\Services;
use Vigilancia\Exceptions\HttpException;use Vigilancia\Repositories\NotificationRepository;use Vigilancia\Repositories\PermissionRepository;
final class NotificationService
{
 private AuthorizationService$auth;
 public function __construct(private NotificationRepository$repo,PermissionRepository$permissions){$this->auth=new AuthorizationService($permissions);}
 public function notifications(array$a,bool$all=false):array{$this->auth->require($a,'notifications.view');$this->repo->sync($a);return['items'=>$this->repo->latest((int)$a['id'],$all?100:10),'unread'=>$this->repo->unread((int)$a['id'])];}
 public function read(array$a,array$in):array{$this->auth->require($a,'notifications.view');if(!empty($in['all'])){$this->repo->readAll((int)$a['id']);return[];}$id=(int)($in['id']??0);if(!$id||!$this->repo->read((int)$a['id'],$id))throw new HttpException('La notificación no existe.',404);return[];}
 public function dashboard(array$a,array$f):array{$this->auth->require($a,'dashboards.view');return['role'=>$a['role_code'],'filters'=>$this->repo->filters($a),'metrics'=>$this->repo->dashboard($a,$f),'refreshed_at'=>gmdate('Y-m-d H:i:s')];}
 public function guardNotifications(bool$all=false):array{return$this->notifications($this->guardActor(),$all);}
 public function guardRead(array$in):array{return$this->read($this->guardActor(),$in);}
 public function guardDashboard(array$f):array{return$this->dashboard($this->guardActor(),$f);}
 private function guardActor():array{$id=(int)($_SESSION['operational_guard_id']??0);$actor=$id?$this->repo->actor($id):null;if(!$actor||$actor['role_code']!=='guard')throw new HttpException('Se requiere una sesión operativa activa.',401);return$actor;}
}
