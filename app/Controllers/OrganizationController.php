<?php
declare(strict_types=1);
namespace Vigilancia\Controllers;

use Vigilancia\Http\JsonResponse;
use Vigilancia\Http\Request;
use Vigilancia\Middleware\CsrfMiddleware;
use Vigilancia\Services\AuthService;
use Vigilancia\Services\OrganizationService;

final class OrganizationController
{
    public function __construct(private AuthService $auth,private OrganizationService $organization){}
    public function clients():void{$this->respond('clients');}
    public function locations():void{$this->respond('locations');}
    public function accessPoints():void{$this->respond('access_points');}
    public function units():void{$this->respond('units');}
    public function residents():void{$this->respond('residents');}
    public function create(Request $request):void{CsrfMiddleware::verify($request->header('x-csrf-token'));$entity=(string)($request->body['entity']??'');$id=$this->organization->create($this->auth->current(),$entity,$request->body);JsonResponse::success('Registro creado correctamente.',['id'=>$id],201);}
    public function status(Request $request):void
    {
        CsrfMiddleware::verify($request->header('x-csrf-token'));
        if(!array_key_exists('is_active',$request->body)||empty($request->body['entity'])||(int)($request->body['id']??0)<=0)JsonResponse::error('Revisa los datos ingresados.',[],422);
        $this->organization->setActive($this->auth->current(),(string)$request->body['entity'],(int)$request->body['id'],(bool)$request->body['is_active']);
        JsonResponse::success('Estado actualizado correctamente.');
    }
    private function respond(string $entity):void{JsonResponse::success('Catálogo consultado.',['items'=>$this->organization->list($this->auth->current(),$entity)]);}
}
