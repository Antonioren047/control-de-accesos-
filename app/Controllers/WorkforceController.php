<?php
declare(strict_types=1);
namespace Vigilancia\Controllers;

use Vigilancia\Http\JsonResponse;
use Vigilancia\Http\Request;
use Vigilancia\Middleware\CsrfMiddleware;
use Vigilancia\Services\AuthService;
use Vigilancia\Services\WorkforceService;

final class WorkforceController
{
    public function __construct(private AuthService $auth,private WorkforceService $service){}
    public function guards():void{$this->respond('guards');}
    public function shifts():void{$this->respond('shifts');}
    public function assignments():void{$this->respond('assignments');}
    public function create(Request $request):void{CsrfMiddleware::verify($request->header('x-csrf-token'));$data=$this->service->create($this->auth->current(),(string)($request->body['entity']??''),$request->body);JsonResponse::success('Registro creado correctamente.',$data,201);}
    public function action(Request $request):void{CsrfMiddleware::verify($request->header('x-csrf-token'));$data=$this->service->action($this->auth->current(),(string)($request->body['action']??''),$request->body);JsonResponse::success('Acción realizada correctamente.',$data);}
    private function respond(string $entity):void{JsonResponse::success('Información operativa consultada.',['items'=>$this->service->list($this->auth->current(),$entity)]);}
}
