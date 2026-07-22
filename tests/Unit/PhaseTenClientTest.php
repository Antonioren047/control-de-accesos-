<?php
declare(strict_types=1);
namespace Vigilancia\Tests\Unit;
use PHPUnit\Framework\TestCase;
final class PhaseTenClientTest extends TestCase
{
 public function testTodosUsanCampanaSinModuloCompleto():void{$page=file_get_contents(dirname(__DIR__,2).'/public/index.php');$config=require dirname(__DIR__,2).'/config/modules.php';self::assertStringContainsString('id="notificationBadge"',$page);self::assertStringContainsString('id="notificationDropdown"',$page);self::assertStringContainsString('<svg viewBox="0 0 24 24"',$page);self::assertStringNotContainsString('data-notification-panel',$page);self::assertArrayNotHasKey('notificaciones',$config['modules']);}
 public function testDashboardTieneFiltrosYActualizacionCadaMinuto():void{$page=file_get_contents(dirname(__DIR__,2).'/public/index.php');$client=file_get_contents(dirname(__DIR__,2).'/public/assets/js/phase10.js');foreach(['dashboardDate','dashboardClient','dashboardLocation','dashboardShift']as$id)self::assertStringContainsString('id="'.$id.'"',$page);self::assertStringContainsString('60000',$client);self::assertStringContainsString('/notifications/read',$client);}
 public function testPortalVigilanteIntegraSoloGloboYMetricasPropias():void{$client=file_get_contents(dirname(__DIR__,2).'/public/assets/js/guard-operation.js');self::assertStringNotContainsString("dataset.guardView='notificaciones'",$client);self::assertStringContainsString('id="guardNotificationButton"',$client);self::assertStringContainsString('id="guardTopNotificationBadge"',$client);self::assertStringContainsString('/guard/notifications',$client);self::assertStringContainsString('/guard/dashboard',$client);}
}
