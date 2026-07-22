<?php
declare(strict_types=1);
namespace Vigilancia\Tests\Unit;
use PHPUnit\Framework\TestCase;

final class UiMapTest extends TestCase
{
    public function testPaginasInteractivasCarganElMapaVisual(): void
    {
        $root = dirname(__DIR__, 2);
        foreach (['index.php','login.php','guard-access.php','guard-operation.php','visit-qr.php','credential.php'] as $file) {
            $page = (string) file_get_contents($root . '/public/' . $file);
            self::assertStringContainsString('assets/css/ui-map.css?v=1.0.0', $page, $file);
            self::assertStringContainsString('assets/js/ui-map.js?v=1.0.1', $page, $file);
        }
    }

    public function testMapaIncluyeModulosAccionesCamaraYEvidencias(): void
    {
        $root = dirname(__DIR__, 2);
        $js = (string) file_get_contents($root . '/public/assets/js/ui-map.js');
        $css = (string) file_get_contents($root . '/public/assets/css/ui-map.css');
        foreach (['clientes','sitios','usuarios','turnos','visitas','proveedores','eventos','recorridos','supervisiones'] as $module) {
            self::assertStringContainsString($module . ':', $js);
        }
        foreach (['[data-capture]','[data-p8-photo]','[data-p8-video]','#takePhoto','#phase9Capture'] as $selector) {
            self::assertStringContainsString($selector, $js);
        }
        foreach (['.camera-capture','.p8-capture-dialog','.phase9-camera','.phase8-evidence','.ui-action[data-ui-icon=camera]'] as $selector) {
            self::assertStringContainsString($selector, $css);
        }
    }

    public function testServiceWorkerPublicaLosRecursosCompartidos(): void
    {
        $worker = (string) file_get_contents(dirname(__DIR__, 2) . '/public/service-worker.js');
        self::assertStringContainsString("vigilancia-fase10-v9", $worker);
        self::assertStringContainsString("./assets/css/ui-map.css", $worker);
        self::assertStringContainsString("./assets/js/ui-map.js", $worker);
    }
}
