<?php
declare(strict_types=1);
namespace Vigilancia\Controllers;
use Vigilancia\Http\JsonResponse;use Vigilancia\Http\Request;use Vigilancia\Middleware\CsrfMiddleware;use Vigilancia\Services\AuthService;use Vigilancia\Services\NotificationService;
final class NotificationController
{
 public function __construct(private AuthService$auth,private NotificationService$service){}
 public function notifications(Request$r):void{JsonResponse::success('Notificaciones propias.',$this->service->notifications($this->auth->current(),($r->query['all']??'')==='1'));}
 public function read(Request$r):void{CsrfMiddleware::verify($r->header('x-csrf-token'));JsonResponse::success('Notificación actualizada.',$this->service->read($this->auth->current(),$r->body));}
 public function dashboard(Request$r):void{JsonResponse::success('Dashboard actualizado.',$this->service->dashboard($this->auth->current(),$r->query));}
 public function guardNotifications(Request$r):void{JsonResponse::success('Notificaciones del vigilante.',$this->service->guardNotifications(($r->query['all']??'')==='1'));}
 public function guardRead(Request$r):void{CsrfMiddleware::verify($r->header('x-csrf-token'));JsonResponse::success('Notificación actualizada.',$this->service->guardRead($r->body));}
 public function guardDashboard(Request$r):void{JsonResponse::success('Dashboard del vigilante.',$this->service->guardDashboard($r->query));}
}
