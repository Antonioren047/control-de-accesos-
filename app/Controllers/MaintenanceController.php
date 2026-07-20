<?php
declare(strict_types=1);
namespace Vigilancia\Controllers;
use Vigilancia\Http\JsonResponse;use Vigilancia\Http\Request;use Vigilancia\Middleware\CsrfMiddleware;use Vigilancia\Services\AuthService;use Vigilancia\Services\MaintenanceService;
final class MaintenanceController
{public function __construct(private AuthService$a,private MaintenanceService$s){}public function monitor():void{JsonResponse::success('Monitoreo de mantenimiento.',$this->s->monitor($this->a->current()));}public function run(Request$r):void{CsrfMiddleware::verify($r->header('x-csrf-token'));JsonResponse::success('Procesos ejecutados.',$this->s->run($this->a->current()));}}
