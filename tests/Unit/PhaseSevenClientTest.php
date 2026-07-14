<?php
declare(strict_types=1);
namespace Vigilancia\Tests\Unit;
use PHPUnit\Framework\TestCase;

final class PhaseSevenClientTest extends TestCase
{
    public function testCapturaUsaCamaraSinSelectorDeArchivos():void
    {
        $portal=file_get_contents(dirname(__DIR__,2).'/public/guard-operation.php');$client=file_get_contents(dirname(__DIR__,2).'/public/assets/js/phase7-guard.js');self::assertStringContainsString('getUserMedia',$client);self::assertStringContainsString('toDataURL',$client);self::assertStringNotContainsString('type="file"',$portal);
    }

    public function testQrSeComparteComoArchivoYWhatsAppNoAfirmaEnvio():void
    {
        $page=file_get_contents(dirname(__DIR__,2).'/public/visit-qr.php');$client=file_get_contents(dirname(__DIR__,2).'/public/assets/js/visit-qr.js');self::assertStringContainsString('new File',$client);self::assertStringContainsString('navigator.share',$client);self::assertStringContainsString('no afirma que haya sido enviada',$page);self::assertStringNotContainsString('<script>',$page);
    }
}
