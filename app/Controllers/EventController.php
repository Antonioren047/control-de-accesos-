<?php
declare(strict_types=1);
namespace Vigilancia\Controllers;
use Vigilancia\Http\JsonResponse;use Vigilancia\Http\Request;use Vigilancia\Middleware\CsrfMiddleware;use Vigilancia\Services\AuthService;use Vigilancia\Services\EventService;
final class EventController
{
 public function __construct(private AuthService$auth,private EventService$service){}
 public function types(Request$r):void{JsonResponse::success('Tipos de evento.',['items'=>$this->service->types($this->auth->current(),($r->query['all']??'')==='1')]);}
 public function saveType(Request$r):void{$this->csrf($r);JsonResponse::success('Tipo de evento guardado.',$this->service->saveType($this->auth->current(),$r->body));}
 public function events():void{JsonResponse::success('Eventos dentro del alcance.',['items'=>$this->service->events($this->auth->current())]);}
 public function details(Request$r):void{JsonResponse::success('Detalle del evento.',$this->service->details($this->auth->current(),(int)($r->query['id']??0)));}
 public function create(Request$r):void{$this->csrf($r);JsonResponse::success('Evento registrado.',$this->service->createGuardEvent($r->body),201);}
 public function evidence(Request$r):void{$this->csrf($r);JsonResponse::success('Evidencia agregada.',$this->service->addEvidence($r->body),201);}
 public function comment(Request$r):void{$this->csrf($r);JsonResponse::success('Comentario registrado.',$this->service->comment($this->auth->current(),$r->body),201);}
 public function cancel(Request$r):void{$this->csrf($r);JsonResponse::success('Evento cancelado.',$this->service->cancel($this->auth->current(),$r->body));}
 public function guardDashboard():void{JsonResponse::success('Operación de Fase 8.',$this->service->guardDashboard());}
 public function startRound(Request$r):void{$this->csrf($r);JsonResponse::success('Recorrido iniciado.',$this->service->startRound($r->body),201);}
 public function finishRound(Request$r):void{$this->csrf($r);JsonResponse::success('Recorrido finalizado.',$this->service->finishRound($r->body));}
 public function rounds():void{JsonResponse::success('Recorridos dentro del alcance.',['items'=>$this->service->rounds($this->auth->current())]);}
 public function closeRound(Request$r):void{$this->csrf($r);JsonResponse::success('Recorrido cerrado por supervisión.',$this->service->supervisorCloseRound($this->auth->current(),$r->body));}
 public function novelty(Request$r):void{$this->csrf($r);JsonResponse::success('Novedad de turno registrada.',$this->service->createNovelty($r->body),201);}
 private function csrf(Request$r):void{CsrfMiddleware::verify($r->header('x-csrf-token'));}
}
