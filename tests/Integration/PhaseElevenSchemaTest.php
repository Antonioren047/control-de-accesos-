<?php
declare(strict_types=1);
namespace Vigilancia\Tests\Integration;
use PDO;use PHPUnit\Framework\TestCase;use Vigilancia\Database\Connection;use Vigilancia\Support\Config;
final class PhaseElevenSchemaTest extends TestCase
{
 private function db():PDO{try{return Connection::make(Config::database());}catch(\Throwable$e){self::markTestSkipped('Base no disponible: '.$e->getMessage());}}
 public function testTablasYColumnasDeFaseOnce():void{$pdo=$this->db();self::assertSame(1,(int)$pdo->query("SELECT COUNT(*) FROM migrations WHERE version='014_reports_audit_storage_cron'")->fetchColumn());$tables=$pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('report_generations','attendance_absences','cron_runs','storage_alerts','retention_actions')")->fetchAll(PDO::FETCH_COLUMN);self::assertCount(5,$tables);$columns=$pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='security_logs' AND column_name IN ('module_name','record_type','record_id','old_values_json','new_values_json')")->fetchAll(PDO::FETCH_COLUMN);self::assertCount(5,$columns);}
 public function testAuditoriaParaAdministrador():void{$pdo=$this->db();$count=(int)$pdo->query("SELECT COUNT(*) FROM role_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id WHERE r.code='admin' AND p.code='audit.view'")->fetchColumn();self::assertSame(1,$count);}
}
