<?php
declare(strict_types=1);
namespace Vigilancia\Tests\Integration;
use PDO;use PHPUnit\Framework\TestCase;use Vigilancia\Database\Connection;use Vigilancia\Support\Config;
final class SupervisionSchemaTest extends TestCase
{
 private function db():PDO{try{return Connection::make(Config::database());}catch(\Throwable$e){self::markTestSkipped('Base no disponible: '.$e->getMessage());}}
 public function testMigracionYTablasDeFaseNueve():void{$pdo=$this->db();self::assertSame(1,(int)$pdo->query("SELECT COUNT(*) FROM migrations WHERE version='012_supervisions'")->fetchColumn());$tables=$pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('supervision_schedules','supervision_schedule_points','supervisions','supervision_points','supervision_evidence','supervision_comments')")->fetchAll(PDO::FETCH_COLUMN);self::assertCount(6,$tables);}
 public function testPermisosDiferencianAdministradorYSupervisor():void{$pdo=$this->db();$admin=(int)$pdo->query("SELECT COUNT(*) FROM role_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id WHERE r.code='admin' AND p.code IN ('supervisions.manage','supervisions.schedule')")->fetchColumn();$supervisor=(int)$pdo->query("SELECT COUNT(*) FROM role_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id WHERE r.code='supervisor' AND p.code='supervisions.manage'")->fetchColumn();self::assertSame(2,$admin);self::assertSame(1,$supervisor);}
 public function testPinDeConfirmacionNuncaEsTextoPlano():void{$pdo=$this->db();$hash=$pdo->query("SELECT confirmation_pin_hash FROM users WHERE confirmation_pin_hash IS NOT NULL LIMIT 1")->fetchColumn();if(!$hash)self::markTestSkipped('No hay datos demo con PIN de confirmación.');self::assertStringStartsWith('$',(string)$hash);self::assertNotSame('102938',$hash);}
}
