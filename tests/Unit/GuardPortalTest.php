<?php
declare(strict_types=1);
namespace Vigilancia\Tests\Unit;
use PHPUnit\Framework\TestCase;
final class GuardPortalTest extends TestCase
{
 public function testPortalOperativoIncluyeNavegacionTurnoRecorridosYSalida():void{$html=file_get_contents(dirname(__DIR__,2).'/public/guard-operation.php');self::assertStringContainsString('Portal del vigilante',$html);self::assertStringContainsString('data-guard-view="turno"',$html);self::assertStringContainsString('data-guard-view="recorridos"',$html);self::assertStringContainsString('data-guard-view="salida"',$html);self::assertStringContainsString('id="closeSession"',$html);}
 public function testPortalOperativoIncluyeColapsadoContrasteYSelectorDeTema():void{$root=dirname(__DIR__,2);$html=file_get_contents($root.'/public/guard-operation.php');$css=file_get_contents($root.'/public/assets/css/guard-portal.css');$theme=file_get_contents($root.'/public/assets/js/guard-theme.js');self::assertStringContainsString('guard-theme.css',$html);self::assertStringContainsString('guard-theme.js',$html);self::assertStringContainsString('site-ui.css',$html);self::assertStringContainsString('id="guardThemeSelect"',$html);self::assertStringContainsString('class="guard-top-actions"',$html);self::assertStringContainsString('.guard-portal.sidebar-collapsed',$css);self::assertStringContainsString("createElement('button')",$theme);self::assertStringContainsString('vigilancia_guard_sidebar',$theme);self::assertStringContainsString('themeSelect.addEventListener',$theme);}
}
