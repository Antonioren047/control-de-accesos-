<?php
declare(strict_types=1); namespace Vigilancia\Support;
final class Logger{public function __construct(private string $file){}public function error(string $message,array $context=[]):void{$safe=array_diff_key($context,array_flip(['password','token','secret']));@file_put_contents($this->file,sprintf("[%s] ERROR %s %s\n",gmdate('c'),$message,json_encode($safe)),FILE_APPEND|LOCK_EX);}}
