<?php
declare(strict_types=1);
namespace Vigilancia\Controllers;
use Vigilancia\Http\JsonResponse;use Vigilancia\Http\Request;use Vigilancia\Middleware\CsrfMiddleware;use Vigilancia\Services\AuthService;use Vigilancia\Services\OperationalService;
final class OperationalController
{
 public function __construct(private AuthService$auth,private OperationalService$service){}
 public function catalog():void{JsonResponse::success('CatÃ¡logo operativo.',$this->service->catalog());}
 public function start(Request$r):void{CsrfMiddleware::verify($r->header('x-csrf-token'));JsonResponse::success('SesiÃ³n operativa iniciada.',$this->service->start($r->body),201);}
 public function current():void{JsonResponse::success('SesiÃ³n operativa activa.',$this->service->current());}
 public function close(Request$r):void{CsrfMiddleware::verify($r->header('x-csrf-token'));JsonResponse::success('SesiÃ³n operativa cerrada.',$this->service->close($r->body));}
 public function sessions():void{JsonResponse::success('Sesiones operativas.',['items'=>$this->service->list($this->auth->current(),false)]);}
 public function attendance():void{JsonResponse::success('Registros de asistencia.',['items'=>$this->service->list($this->auth->current(),true)]);}
 public function manualClose(Request$r):void{CsrfMiddleware::verify($r->header('x-csrf-token'));JsonResponse::success('SesiÃ³n cerrada por supervisiÃ³n.',$this->service->manualClose($this->auth->current(),$r->body));}
}
