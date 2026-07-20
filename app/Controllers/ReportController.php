<?php
declare(strict_types=1);
namespace Vigilancia\Controllers;
use Vigilancia\Http\JsonResponse;use Vigilancia\Http\Request;use Vigilancia\Middleware\CsrfMiddleware;use Vigilancia\Services\AuthService;use Vigilancia\Services\ReportService;
final class ReportController
{
 public function __construct(private AuthService$auth,private ReportService$service){}
 public function catalog():void{JsonResponse::success('Catálogo de reportes.',$this->service->catalog($this->auth->current()));}
 public function create(Request$r):void{CsrfMiddleware::verify($r->header('x-csrf-token'));JsonResponse::success('Reporte preparado.',$this->service->create($this->auth->current(),$r->body),201);}
}
