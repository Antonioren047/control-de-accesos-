<?php
declare(strict_types=1);
namespace Vigilancia\Tests\Unit;
use PHPUnit\Framework\TestCase;
final class PhaseEightClientTest extends TestCase
{
 public function testPortalUsaCapturaDirectaSinGaleria():void{$page=file_get_contents(dirname(__DIR__,2).'/public/guard-operation.php');$client=file_get_contents(dirname(__DIR__,2).'/public/assets/js/phase8-guard.js');self::assertStringContainsString('getUserMedia',$client);self::assertStringContainsString('MediaRecorder',$client);self::assertStringContainsString("toDataURL('image/jpeg'",$client);self::assertStringNotContainsString('type="file"',$page);}
 public function testPortalIncluyeEventosRecorridosYNovedades():void{$page=file_get_contents(dirname(__DIR__,2).'/public/guard-operation.php');self::assertStringContainsString('data-guard-section="eventos"',$page);self::assertStringContainsString('data-guard-section="recorridos"',$page);self::assertStringContainsString('data-guard-section="novedades"',$page);self::assertStringContainsString('phase8-guard.js',$page);}
 public function testClienteLimitaGrabacionATreintaSegundos():void{$client=file_get_contents(dirname(__DIR__,2).'/public/assets/js/phase8-guard.js');self::assertStringContainsString('30000',$client);self::assertStringContainsString('duration_seconds',$client);}
 public function testRecorridoMuestraVistasPreviasSinAbrirOtraPestana():void{$client=file_get_contents(dirname(__DIR__,2).'/public/assets/js/phase8-panel.js');self::assertStringContainsString('data-round-detail',$client);self::assertStringContainsString('<video src=', $client);self::assertStringContainsString('<img src=', $client);self::assertStringNotContainsString('target="_blank"',$client);}
}
