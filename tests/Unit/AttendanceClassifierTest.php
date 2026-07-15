<?php
declare(strict_types=1);
namespace Vigilancia\Tests\Unit;
use DateTimeImmutable;use PHPUnit\Framework\TestCase;use Vigilancia\Operations\AttendanceClassifier;
final class AttendanceClassifierTest extends TestCase
{
 public function testClasificaEntradaPuntualRetardoYFueraDeHorario():void{$s=new DateTimeImmutable('2026-07-14 07:00:00');$e=new DateTimeImmutable('2026-07-14 19:00:00');self::assertSame('on_time',AttendanceClassifier::entry(new DateTimeImmutable('2026-07-14 07:08:00'),$s,$e,10)['classification']);self::assertSame('late',AttendanceClassifier::entry(new DateTimeImmutable('2026-07-14 07:11:00'),$s,$e,10)['classification']);self::assertSame('outside_schedule',AttendanceClassifier::entry(new DateTimeImmutable('2026-07-14 19:01:00'),$s,$e,10)['classification']);}
 public function testClasificaSalidaAnticipadaCompletaYTiempoExtra():void{$e=new DateTimeImmutable('2026-07-14 19:00:00');self::assertSame('early_departure',AttendanceClassifier::exit(new DateTimeImmutable('2026-07-14 18:40:00'),$e,5,15)['classification']);self::assertSame('completed',AttendanceClassifier::exit(new DateTimeImmutable('2026-07-14 19:10:00'),$e,5,15)['classification']);self::assertSame('overtime',AttendanceClassifier::exit(new DateTimeImmutable('2026-07-14 19:20:00'),$e,5,15)['classification']);}
}
