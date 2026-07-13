<?php
declare(strict_types=1); namespace Vigilancia\Database; use PDO;use RuntimeException;
final class SeederRunner{public function __construct(private PDO $pdo,private string $path){}public function run(bool $demo=false):array{$ran=[];foreach(glob($this->path.'/*.php')?:[] as $file){$seed=require $file;if(!is_callable($seed))throw new RuntimeException('Seed inválido: '.basename($file));$seed($this->pdo,$demo);$ran[]=basename($file,'.php');}return $ran;}}
