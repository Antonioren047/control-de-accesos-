<?php
declare(strict_types=1);
use Vigilancia\Exceptions\ErrorHandler;
use Vigilancia\Support\Env;
$root=dirname(__DIR__); $composer=$root.'/vendor/autoload.php';
if(is_file($composer)){require $composer;}else{spl_autoload_register(static function(string $class)use($root):void{$prefix='Vigilancia\\';if(!str_starts_with($class,$prefix))return;$file=$root.'/app/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($file))require $file;});}
Env::load($root.'/.env'); date_default_timezone_set((string)Env::get('APP_TIMEZONE','America/Mexico_City')); ErrorHandler::register(Env::bool('APP_DEBUG',false),$root.'/storage/logs/app.log'); return $root;
