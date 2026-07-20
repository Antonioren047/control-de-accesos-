<?php
declare(strict_types=1);
namespace Vigilancia\Tests\Unit;
use PHPUnit\Framework\TestCase;
final class PhaseTenClientTest extends TestCase
{
 public function testPanelIncluyeBadgeDropdownYPanelAmpliado():void{$page=file_get_contents(dirname(__DIR__,2).'/public/index.php');self::assertStringContainsString('id="notificationBadge"',$page);self::assertStringContainsString('id="notificationDropdown"',$page);self::assertStringContainsString('data-notification-panel',$page);self::assertStringContainsString('data-notifications-read-all',$page);}
 public function testDashboardTieneFiltrosYActualizacionCadaMinuto():void{$page=file_get_contents(dirname(__DIR__,2).'/public/index.php');$client=file_get_contents(dirname(__DIR__,2).'/public/assets/js/phase10.js');foreach(['dashboardDate','dashboardClient','dashboardLocation','dashboardShift']as$id)self::assertStringContainsString('id="'.$id.'"',$page);self::assertStringContainsString('60000',$client);self::assertStringContainsString('/notifications/read',$client);}
 public function testPortalVigilanteIntegraGloboNotificacionesYMetricasPropias():void{$client=file_get_contents(dirname(__DIR__,2).'/public/assets/js/guard-operation.js');self::assertStringContainsString("dataset.guardView='notificaciones'",$client);self::assertStringContainsString('id="guardNotificationButton"',$client);self::assertStringContainsString('id="guardTopNotificationBadge"',$client);self::assertStringContainsString('/guard/notifications',$client);self::assertStringContainsString('/guard/dashboard',$client);}
}
