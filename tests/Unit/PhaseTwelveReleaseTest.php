<?php
declare(strict_types=1);
namespace Vigilancia\Tests\Unit;
use PHPUnit\Framework\TestCase;
final class PhaseTwelveReleaseTest extends TestCase
{
 public function testIntegracionVisualFinalIncluyeAccesibilidadYResponsive():void{$root=dirname(__DIR__,2);$page=file_get_contents($root.'/public/index.php');$css=file_get_contents($root.'/public/assets/css/phase12.css');$js=file_get_contents($root.'/public/assets/js/app.js');self::assertStringContainsString('class="skip-link"',$page);self::assertStringContainsString('id="mainContent"',$page);self::assertStringContainsString('sidebarBackdrop',$js);self::assertStringContainsString('@media(max-width:620px)',$css);self::assertStringContainsString('.top-actions{width:100%', $css);}
 public function testPublicacionTieneVerificadorYDocumentosFinales():void{$root=dirname(__DIR__,2);$script=file_get_contents($root.'/scripts/release_check.php');foreach(['installed.lock','Content-Security-Policy','014_reports_audit_storage_cron','Cinco roles definitivos']as$value)self::assertStringContainsString($value,$script);self::assertFileExists($root.'/docs/manual-tecnico/despliegue-final.md');self::assertFileExists($root.'/docs/manual-usuario/manual-final-por-rol.md');self::assertFileExists($root.'/docs/checklist-fase12.md');}
 public function testMenusFinalesRespetanRolesBase():void{$config=require dirname(__DIR__,2).'/config/modules.php';$visible=static function(string$role)use($config):array{$permissions=$config['roles'][$role];return array_keys(array_filter($config['modules'],static fn(array$m):bool=>array_intersect($m['permissions'],$permissions)!==[]&&(!isset($m['roles'])||in_array($role,$m['roles'],true))));};self::assertContains('auditoria',$visible('admin'));self::assertNotContains('mantenimiento',$visible('admin'));self::assertContains('reportes',$visible('supervisor'));self::assertNotContains('reportes',$visible('guard'));self::assertSame(['mis_unidades','visitas','proveedores','reportes'],$visible('resident'));}
}
