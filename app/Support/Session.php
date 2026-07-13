<?php
declare(strict_types=1); namespace Vigilancia\Support;
final class Session{public static function start():void{if(session_status()===PHP_SESSION_ACTIVE)return;session_name((string)Env::get('SESSION_NAME','vigilancia_session'));session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'),'httponly'=>true,'samesite'=>'Lax']);ini_set('session.use_strict_mode','1');session_start();}}
