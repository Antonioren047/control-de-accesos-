<?php
declare(strict_types=1);
namespace Vigilancia\Controllers;
use Vigilancia\Http\JsonResponse;use Vigilancia\Http\Request;use Vigilancia\Services\AuditService;use Vigilancia\Services\AuthService;
final class AuditController{public function __construct(private AuthService$auth,private AuditService$service){}public function list(Request$r):void{JsonResponse::success('Auditoría dentro del alcance.',$this->service->list($this->auth->current(),$r->query));}}
