<?php
declare(strict_types=1);
namespace Vigilancia\Services;
use Vigilancia\Repositories\MaintenanceRepository;use Vigilancia\Repositories\PermissionRepository;use Vigilancia\Repositories\SecurityLogRepository;use Vigilancia\Support\ClientInfo;
final class MaintenanceService
{
 private AuthorizationService$auth;public function __construct(private MaintenanceRepository$repo,PermissionRepository$p,private CronService$cron,private SecurityLogRepository$logs){$this->auth=new AuthorizationService($p);}
 public function monitor(array$a):array{$this->auth->require($a,'maintenance.manage');return['runs'=>$this->repo->latest(),'storage'=>$this->repo->storage(),'command'=>'/usr/local/bin/php '.dirname(__DIR__,2).'/cron/run.php'];}
 public function run(array$a):array{$this->auth->require($a,'maintenance.manage');$result=$this->cron->run();$this->logs->record((int)$a['id'],'maintenance.run',ClientInfo::ip(),ClientInfo::userAgent(),$result);return$result;}
}
