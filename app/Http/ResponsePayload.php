<?php
declare(strict_types=1); namespace Vigilancia\Http; final class ResponsePayload{public static function success(string $message,mixed $data=[]):array{return ['success'=>true,'message'=>$message,'data'=>$data];}public static function error(string $message,array $errors=[]):array{return ['success'=>false,'message'=>$message,'errors'=>$errors];}}
