<?php
declare(strict_types=1);$root=require dirname(__DIR__,2).'/bootstrap/app.php';use Vigilancia\Http\Request;use Vigilancia\Http\Router;$router=new Router();require $root.'/routes/api.php';$router->dispatch(Request::capture());
