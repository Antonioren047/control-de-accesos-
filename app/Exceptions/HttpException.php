<?php
declare(strict_types=1); namespace Vigilancia\Exceptions; use RuntimeException; final class HttpException extends RuntimeException{public function __construct(string $message,public int $status=400,public array $errors=[]){parent::__construct($message);}}
