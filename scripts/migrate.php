<?php
declare(strict_types=1);$root=require dirname(__DIR__).'/bootstrap/app.php';use Vigilancia\Database\Connection;use Vigilancia\Database\MigrationRunner;use Vigilancia\Support\Config;$applied=(new MigrationRunner(Connection::make(Config::database()),$root.'/database/migrations'))->run();echo $applied?'Aplicadas: '.implode(', ',$applied).PHP_EOL:'Sin migraciones pendientes.'.PHP_EOL;
