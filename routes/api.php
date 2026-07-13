<?php
declare(strict_types=1); use Vigilancia\Controllers\HealthController;use Vigilancia\Http\JsonResponse;
$router->get('/health',new HealthController());$router->get('/',static fn()=>JsonResponse::success('API de Control de Accesos',['version'=>'1.0.0','documentation'=>'../docs/']));
