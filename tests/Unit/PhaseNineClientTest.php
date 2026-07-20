<?php
declare(strict_types=1);
namespace Vigilancia\Tests\Unit;
use PHPUnit\Framework\TestCase;
final class PhaseNineClientTest extends TestCase
{
 public function testInterfazIncluyeProgramacionEjecucionYCamara():void{$page=file_get_contents(dirname(__DIR__,2).'/public/index.php');$client=file_get_contents(dirname(__DIR__,2).'/public/assets/js/phase9-panel.js');self::assertStringContainsString('data-phase9-schedule',$page);self::assertStringContainsString('data-phase9-start',$page);self::assertStringContainsString('getUserMedia',$client);self::assertStringContainsString("toDataURL('image/jpeg'",$client);}
 public function testFinalizacionSolicitaDobleConfirmacionYAusencia():void{$client=file_get_contents(dirname(__DIR__,2).'/public/assets/js/phase9-panel.js');self::assertStringContainsString('supervisor_pin',$client);self::assertStringContainsString('responsible_pin',$client);self::assertStringContainsString('responsible_absent',$client);self::assertStringContainsString('absence_reason',$client);}
 public function testReporteYPuntoProtegidoExisten():void{self::assertFileExists(dirname(__DIR__,2).'/public/supervision-report.php');self::assertFileExists(dirname(__DIR__,2).'/public/supervision-evidence.php');}
}
