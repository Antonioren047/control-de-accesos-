<?php
declare(strict_types=1);
namespace Vigilancia\Tests\Unit;
use PHPUnit\Framework\TestCase;
final class SiteUiTest extends TestCase
{
 public function testSistemaComparteAlertasConfirmacionesYCargasConBranding():void{$root=dirname(__DIR__,2);$page=file_get_contents($root.'/public/index.php');$guard=file_get_contents($root.'/public/guard-operation.php');$js=file_get_contents($root.'/public/assets/js/site-ui.js');$css=file_get_contents($root.'/public/assets/css/site-ui.css');foreach(['site-ui.css','site-ui.js']as$asset){self::assertStringContainsString($asset,$page);self::assertStringContainsString($asset,$guard);}foreach(['const alert=','const confirm=','const prompt=','const loading=']as$contract)self::assertStringContainsString($contract,$js);foreach(['.site-dialog','.site-toast','.site-loading','.site-spinner']as$selector)self::assertStringContainsString($selector,$css);}
 public function testFlujosPrincipalesYaNoUsanConfirmacionNativa():void{$root=dirname(__DIR__,2);foreach(['phase3.js','phase4.js','phase5-panel.js','phase6-panel.js','phase7-panel.js']as$file){$js=file_get_contents($root.'/public/assets/js/'.$file);self::assertDoesNotMatchRegularExpression('/(?<![A-Za-z0-9_.])confirm\(/',$js);self::assertDoesNotMatchRegularExpression('/(?<![A-Za-z0-9_.])prompt\(/',$js);}}
}
