<?php
declare(strict_types=1); namespace Vigilancia\Controllers; use Vigilancia\Database\Connection;use Vigilancia\Http\JsonResponse;use Vigilancia\Support\Config;
final class HealthController{public function __invoke():void{$db='not_configured';try{if(Config::database()['database']!==''){Connection::make(Config::database())->query('SELECT 1');$db='connected';}}catch(\Throwable){$db='unavailable';}JsonResponse::success('Servicio disponible',['service'=>'Sistema de Vigilancia','version'=>'2.0.0','environment'=>Config::app()['env'],'database'=>$db,'timestamp'=>gmdate('c')]);}}
