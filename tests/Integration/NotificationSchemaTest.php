<?php
declare(strict_types=1);
namespace Vigilancia\Tests\Integration;
use PDO;use PHPUnit\Framework\TestCase;use Vigilancia\Database\Connection;use Vigilancia\Support\Config;
final class NotificationSchemaTest extends TestCase
{
 private function db():PDO{try{return Connection::make(Config::database());}catch(\Throwable$e){self::markTestSkipped('Base no disponible: '.$e->getMessage());}}
 public function testMigracionYTablaDeFaseDiez():void{$pdo=$this->db();self::assertSame(1,(int)$pdo->query("SELECT COUNT(*) FROM migrations WHERE version='013_notifications_dashboards'")->fetchColumn());self::assertSame(1,(int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='notifications'")->fetchColumn());}
 public function testNotificacionTieneDeduplicacionYLectura():void{$pdo=$this->db();$columns=$pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='notifications' AND column_name IN ('deduplication_key','read_at','expires_at','related_view')")->fetchAll(PDO::FETCH_COLUMN);self::assertCount(4,$columns);$index=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='notifications' AND index_name='uq_notification_dedup'")->fetchColumn();self::assertSame(2,$index);}
 public function testPermisosLleganATodosLosRoles():void{$pdo=$this->db();$count=(int)$pdo->query("SELECT COUNT(DISTINCT r.code) FROM role_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id WHERE p.code='notifications.view' AND r.code IN ('superadmin','admin','supervisor','guard','resident')")->fetchColumn();self::assertSame(5,$count);}
}
