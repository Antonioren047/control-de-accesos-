<?php
declare(strict_types=1);
namespace Vigilancia\Tests\Unit;
use PHPUnit\Framework\TestCase;
final class GuardPortalTest extends TestCase
{
 public function testPortalOperativoIncluyeNavegacionTurnoRecorridosYSalida():void{$html=file_get_contents(dirname(__DIR__,2).'/public/guard-operation.php');self::assertStringContainsString('Portal del vigilante',$html);self::assertStringContainsString('data-guard-view="turno"',$html);self::assertStringContainsString('data-guard-view="recorridos"',$html);self::assertStringContainsString('data-guard-view="salida"',$html);self::assertStringContainsString('id="closeSession"',$html);}
}
