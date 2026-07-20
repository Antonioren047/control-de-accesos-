<?php
declare(strict_types=1);
namespace Vigilancia\Controllers;
use Vigilancia\Http\JsonResponse;use Vigilancia\Http\Request;use Vigilancia\Middleware\CsrfMiddleware;use Vigilancia\Services\AuthService;use Vigilancia\Services\SupervisionService;
final class SupervisionController
{
 public function __construct(private AuthService$auth,private SupervisionService$service){}
 public function catalog():void{JsonResponse::success('Catálogo de supervisiones.',$this->service->catalog($this->auth->current()));}
 public function lists():void{JsonResponse::success('Supervisiones dentro del alcance.',['items'=>$this->service->lists($this->auth->current())]);}
 public function detail(Request$r):void{JsonResponse::success('Detalle de supervisión.',$this->service->detail($this->auth->current(),(int)($r->query['id']??0)));}
 public function schedule(Request$r):void{$this->csrf($r);JsonResponse::success('Supervisión programada.',$this->service->schedule($this->auth->current(),$r->body),201);}
 public function start(Request$r):void{$this->csrf($r);JsonResponse::success('Supervisión iniciada.',$this->service->start($this->auth->current(),$r->body),201);}
 public function evidence(Request$r):void{$this->csrf($r);JsonResponse::success('Evidencia agregada.',$this->service->evidence($this->auth->current(),$r->body),201);}
 public function finish(Request$r):void{$this->csrf($r);JsonResponse::success('Supervisión finalizada.',$this->service->finish($this->auth->current(),$r->body));}
 public function comment(Request$r):void{$this->csrf($r);JsonResponse::success('Comentario agregado.',$this->service->comment($this->auth->current(),$r->body),201);}
 public function pin(Request$r):void{$this->csrf($r);JsonResponse::success('PIN de confirmación actualizado.',$this->service->setPin($this->auth->current(),$r->body));}
 private function csrf(Request$r):void{CsrfMiddleware::verify($r->header('x-csrf-token'));}
}
