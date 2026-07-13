<?php
declare(strict_types=1);namespace Vigilancia\Tests\Unit;use PHPUnit\Framework\TestCase;final class MigrationTest extends TestCase{public function testMigracionFundacionalEsEjecutable():void{$migration=require dirname(__DIR__,2).'/database/migrations/001_foundation.php';$this->assertIsCallable($migration);}}
