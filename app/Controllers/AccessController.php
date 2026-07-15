<?php
declare(strict_types=1);

namespace Vigilancia\Controllers;

use Vigilancia\Http\JsonResponse;
use Vigilancia\Http\Request;
use Vigilancia\Middleware\CsrfMiddleware;
use Vigilancia\Services\AccessService;
use Vigilancia\Services\AuthService;

final class AccessController
{
    public function __construct(private AuthService $auth, private AccessService $service) {}
    public function catalog():void{JsonResponse::success('Catálogo de accesos.',$this->service->catalog($this->auth->current()));}
    public function visits():void{JsonResponse::success('Visitas autorizadas.',['items'=>$this->service->visits($this->auth->current())]);}
    public function createVisit(Request$r):void{$this->csrf($r);JsonResponse::success('Visita y QR creados.',$this->service->createVisit($this->auth->current(),$r->body),201);}
    public function visitAction(Request$r):void{$this->csrf($r);JsonResponse::success('Visita actualizada.',$this->service->visitAction($this->auth->current(),$r->body));}
    public function providers():void{JsonResponse::success('Accesos de proveedores.',['items'=>$this->service->providers($this->auth->current())]);}
    public function createProvider(Request$r):void{$this->csrf($r);JsonResponse::success('Acceso de proveedor creado.',$this->service->createProvider($this->auth->current(),$r->body),201);}
    public function validateProvider(Request$r):void{JsonResponse::success('QR de proveedor validado.',$this->service->validateProvider((string)($r->query['token']??'')));}
    public function validateVisit(Request$r):void{JsonResponse::success('QR de visita validado.',$this->service->validateVisit((string)($r->query['token']??'')));}
    public function checkInVisit(Request$r):void{$this->csrf($r);JsonResponse::success('Entrada de visitante registrada.',$this->service->checkInVisit($r->body),201);}
    public function checkOutVisit(Request$r):void{$this->csrf($r);JsonResponse::success('Salida de visitante registrada.',$this->service->checkOutVisit($r->body));}
    public function active():void{JsonResponse::success('Accesos activos.',$this->service->active());}
    public function checkInProvider(Request$r):void{$this->csrf($r);JsonResponse::success('Entrada de proveedor registrada.',$this->service->checkInProvider($r->body),201);}
    public function checkOutProvider(Request$r):void{$this->csrf($r);JsonResponse::success('Salida de proveedor registrada.',$this->service->checkOutProvider($r->body));}
    private function csrf(Request$r):void{CsrfMiddleware::verify($r->header('x-csrf-token'));}
}
