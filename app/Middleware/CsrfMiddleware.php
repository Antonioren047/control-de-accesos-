<?php
declare(strict_types=1); namespace Vigilancia\Middleware; use Vigilancia\Exceptions\HttpException;
final class CsrfMiddleware{public static function token():string{if(empty($_SESSION['_csrf']))$_SESSION['_csrf']=bin2hex(random_bytes(32));return $_SESSION['_csrf'];}public static function verify(?string $token):void{if(!$token||!hash_equals($_SESSION['_csrf']??'',$token))throw new HttpException('Token CSRF inválido.',419);}}
