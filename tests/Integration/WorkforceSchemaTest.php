<?php
declare(strict_types=1);
namespace Vigilancia\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Vigilancia\Database\Connection;
use Vigilancia\Repositories\UserRepository;
use Vigilancia\Repositories\WorkforceRepository;
use Vigilancia\Support\Config;

final class WorkforceSchemaTest extends TestCase
{
    private function connection():PDO{try{return Connection::make(Config::database());}catch(\Throwable$e){self::markTestSkipped('Base no disponible: '.$e->getMessage());}}
    public function testMigracionDePersonalEstaInstalada():void{$pdo=$this->connection();self::assertSame(1,(int)$pdo->query("SELECT COUNT(*) FROM migrations WHERE version='004_workforce'")->fetchColumn());$tables=$pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('guard_profiles','guard_credentials','shifts','shift_locations','guard_assignments','assignment_history','assignment_change_requests')")->fetchAll(PDO::FETCH_COLUMN);self::assertCount(7,$tables);}
    public function testDemoIncluyeDosVigilantesTurnosYAsignacionesSinDatosEnQr():void{$pdo=$this->connection();$users=new UserRepository($pdo);$admin=$users->findByEmail('admin@demo.local');if(!$admin)self::markTestSkipped('Administrador demo no disponible.');$repository=new WorkforceRepository($pdo);self::assertGreaterThanOrEqual(2,count($repository->guards($admin)));self::assertGreaterThanOrEqual(2,count($repository->shifts($admin)));self::assertGreaterThanOrEqual(2,count($repository->assignments($admin)));$credential=$pdo->query("SELECT gc.token_hash,gc.token_reference,u.full_name FROM guard_credentials gc JOIN users u ON u.id=gc.guard_user_id ORDER BY gc.id LIMIT 1")->fetch();self::assertSame(64,strlen($credential['token_hash']));self::assertStringNotContainsString($credential['full_name'],$credential['token_hash']);self::assertSame(12,strlen($credential['token_reference']));}
    public function testDetectaTraslapePorFechasYDias():void{$pdo=$this->connection();$row=$pdo->query("SELECT guard_user_id,start_date,applicable_days FROM guard_assignments WHERE status='active' ORDER BY id LIMIT 1")->fetch();if(!$row)self::markTestSkipped('Asignación demo no disponible.');$days=json_decode($row['applicable_days'],true);self::assertNotEmpty((new WorkforceRepository($pdo))->overlappingAssignments((int)$row['guard_user_id'],$row['start_date'],null,$days));}
}
