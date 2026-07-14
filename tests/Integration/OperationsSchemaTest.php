<?php
declare(strict_types=1);
namespace Vigilancia\Tests\Integration;
use PDO;use PHPUnit\Framework\TestCase;use Vigilancia\Database\Connection;use Vigilancia\Support\Config;
final class OperationsSchemaTest extends TestCase
{
 private function connection():PDO{try{return Connection::make(Config::database());}catch(\Throwable$e){self::markTestSkipped('Base no disponible: '.$e->getMessage());}}
 public function testMigracionDeOperacionesEstaInstalada():void{$pdo=$this->connection();self::assertSame(1,(int)$pdo->query("SELECT COUNT(*) FROM migrations WHERE version='005_operations'")->fetchColumn());$tables=$pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('operational_sessions','attendance_records')")->fetchAll(PDO::FETCH_COLUMN);self::assertCount(2,$tables);}
 public function testUnaAsistenciaPerteneceAUnaSesion():void{$pdo=$this->connection();$indexes=$pdo->query("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='attendance_records' AND index_name='operational_session_id' AND non_unique=0")->fetchColumn();self::assertGreaterThanOrEqual(1,(int)$indexes);}
}
