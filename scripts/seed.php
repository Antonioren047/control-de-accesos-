<?php
declare(strict_types=1);$root=require dirname(__DIR__).'/bootstrap/app.php';use Vigilancia\Database\Connection;use Vigilancia\Database\SeederRunner;use Vigilancia\Support\Config;$demo=in_array('--demo',$argv??[],true);$ran=(new SeederRunner(Connection::make(Config::database()),$root.'/database/seeds'))->run($demo);echo 'Seeds: '.implode(', ',$ran).PHP_EOL;
