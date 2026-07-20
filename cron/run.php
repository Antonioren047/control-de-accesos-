<?php
declare(strict_types=1);
$root=require dirname(__DIR__).'/bootstrap/app.php';
use Vigilancia\Database\Connection;use Vigilancia\Repositories\MaintenanceRepository;use Vigilancia\Services\CronService;use Vigilancia\Support\Config;
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
try{$result=(new CronService(new MaintenanceRepository(Connection::make(Config::database())),$root))->run();echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;exit(isset($result['tasks'])&&array_filter($result['tasks'],fn($x)=>isset($x['error']))?1:0);}catch(Throwable$e){fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);}
