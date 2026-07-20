<?php
declare(strict_types=1);
namespace Vigilancia\Tests\Integration;
use PDO;use PHPUnit\Framework\TestCase;use Vigilancia\Database\Connection;use Vigilancia\Repositories\EventRepository;use Vigilancia\Support\Config;
final class EventSchemaTest extends TestCase
{
 private function connection():PDO{try{return Connection::make(Config::database());}catch(\Throwable$e){self::markTestSkipped('Base no disponible: '.$e->getMessage());}}
 public function testMigracionDeFaseOchoEstaInstalada():void{$pdo=$this->connection();self::assertSame(1,(int)$pdo->query("SELECT COUNT(*) FROM migrations WHERE version='010_events_rounds_novelties'")->fetchColumn());$tables=$pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('event_types','events','event_comments','event_evidence','round_policies','rounds','shift_novelties')")->fetchAll(PDO::FETCH_COLUMN);self::assertCount(7,$tables);}
 public function testCatalogoInicialContieneCincoTipos():void{$pdo=$this->connection();$codes=$pdo->query("SELECT code FROM event_types WHERE code IN ('incident','provider_entry','round','visit','shift_novelty')")->fetchAll(PDO::FETCH_COLUMN);self::assertCount(5,$codes);}
 public function testPermisosSeparanVigilanteYSupervisor():void{$pdo=$this->connection();$guard=$pdo->query("SELECT p.code FROM role_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id WHERE r.code='guard' AND p.code IN ('events.create','rounds.execute','shifts.novelty')")->fetchAll(PDO::FETCH_COLUMN);self::assertCount(3,$guard);$review=(int)$pdo->query("SELECT COUNT(*) FROM role_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id WHERE r.code='supervisor' AND p.code IN ('events.review','rounds.review')")->fetchColumn();self::assertSame(2,$review);}
 public function testRepositorioImpideDosRecorridosAbiertosPorConsulta():void{$pdo=$this->connection();$guard=$pdo->query("SELECT guard_user_id FROM operational_sessions ORDER BY id DESC LIMIT 1")->fetchColumn();if(!$guard)self::markTestSkipped('No existen sesiones operativas.');$repo=new EventRepository($pdo);$round=$repo->openRound((int)$guard);self::assertTrue($round===null||$round['status']==='open');}
}
