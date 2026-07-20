<?php
declare(strict_types=1);
namespace Vigilancia\Tests\Integration;
use PDO;use PHPUnit\Framework\TestCase;use Vigilancia\Database\Connection;use Vigilancia\Support\Config;
final class PhaseTwelveGlobalTest extends TestCase
{
 private function db():PDO{try{return Connection::make(Config::database());}catch(\Throwable$e){self::markTestSkipped('Base no disponible: '.$e->getMessage());}}
 public function testTodasLasMigracionesEstanAplicadas():void{$pdo=$this->db();$count=(int)$pdo->query('SELECT COUNT(*) FROM migrations')->fetchColumn();self::assertGreaterThanOrEqual(14,$count);self::assertSame(0,(int)$pdo->query("SELECT COUNT(*) FROM migrations WHERE version IS NULL OR version=''")->fetchColumn());}
 public function testExistenUsuariosActivosDeLosCincoRoles():void{$pdo=$this->db();$roles=$pdo->query("SELECT r.code,COUNT(u.id) users_count FROM roles r LEFT JOIN users u ON u.role_id=r.id AND u.is_active=1 WHERE r.code IN ('superadmin','admin','supervisor','guard','resident') GROUP BY r.code")->fetchAll(PDO::FETCH_KEY_PAIR);self::assertCount(5,$roles);foreach($roles as$count)self::assertGreaterThan(0,(int)$count);}
 public function testPermisosSensiblesNoSeFiltranAResidenteOVigilante():void{$pdo=$this->db();$count=(int)$pdo->query("SELECT COUNT(*) FROM role_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id WHERE r.code IN ('guard','resident') AND p.code IN ('audit.view','maintenance.manage','reports.view','system.configure','auth.sessions.view')")->fetchColumn();self::assertSame(0,$count);}
 public function testEvidenciasPersistidasPermanecenFueraDePublic():void{$pdo=$this->db();foreach(["SELECT file_path FROM event_evidence","SELECT file_path FROM supervision_evidence"]as$sql)foreach($pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN)as$path)self::assertStringStartsWith('storage/',str_replace('\\','/',(string)$path));}
}
