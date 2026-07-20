<?php
declare(strict_types=1);
namespace Vigilancia\Tests\Unit;
use PHPUnit\Framework\TestCase;
final class PhaseElevenClientTest extends TestCase
{
 public function testPanelIncluyeTresEspaciosDeFaseOnce():void{$page=file_get_contents(dirname(__DIR__,2).'/public/index.php');foreach(['reportes','auditoria','mantenimiento']as$m)self::assertStringContainsString("data-phase11-module=\"<?= htmlspecialchars(\$moduleId) ?>\"",$page);self::assertStringContainsString('data-report-form',$page);self::assertStringContainsString('data-audit-form',$page);self::assertStringContainsString('data-cron-run',$page);}
 public function testReportesSonPdfYLimitanNoventaDias():void{$service=file_get_contents(dirname(__DIR__,2).'/app/Services/ReportService.php');$pdf=file_get_contents(dirname(__DIR__,2).'/public/report.php');self::assertStringContainsString('>90',$service);self::assertStringContainsString("setPaper('letter','landscape')",$pdf);self::assertStringContainsString('reports.downloaded',$pdf);}
 public function testCronTieneBloqueoYTareasRequeridas():void{$service=file_get_contents(dirname(__DIR__,2).'/app/Services/CronService.php');$repo=file_get_contents(dirname(__DIR__,2).'/app/Repositories/MaintenanceRepository.php');foreach(['calculate_absences','close_incomplete_rounds','pending_supervisions','expire_qr','storage_alerts','retention','temporary_cleanup']as$t)self::assertStringContainsString($t,$service);self::assertStringContainsString('GET_LOCK',$repo);}
}
