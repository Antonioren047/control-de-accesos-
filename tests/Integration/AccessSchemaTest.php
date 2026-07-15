<?php
declare(strict_types=1);
namespace Vigilancia\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Vigilancia\Database\Connection;
use Vigilancia\Repositories\AccessRepository;
use Vigilancia\Support\Config;

final class AccessSchemaTest extends TestCase
{
    private function connection():PDO{try{return Connection::make(Config::database());}catch(\Throwable$e){self::markTestSkipped('Base no disponible: '.$e->getMessage());}}

    public function testMigracionDeFaseSieteEstaInstalada():void
    {
        $pdo=$this->connection();self::assertSame(1,(int)$pdo->query("SELECT COUNT(*) FROM migrations WHERE version='009_visitors_providers'")->fetchColumn());
        $tables=$pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('access_policies','visitor_passes','visitor_accesses','visitor_share_logs','provider_accesses')")->fetchAll(PDO::FETCH_COLUMN);self::assertCount(5,$tables);
    }

    public function testPoliticaPredeterminadaEsDosHorasTreintaDiasYDiezQr():void
    {
        $pdo=$this->connection();$policy=$pdo->query('SELECT visit_duration_minutes,max_advance_days,max_active_visits_per_resident,identification_retention_days FROM access_policies LIMIT 1')->fetch();if(!$policy)self::markTestSkipped('No hay clientes con política de acceso.');self::assertSame(120,(int)$policy['visit_duration_minutes']);self::assertSame(30,(int)$policy['max_advance_days']);self::assertSame(10,(int)$policy['max_active_visits_per_resident']);self::assertSame(90,(int)$policy['identification_retention_days']);
    }

    public function testRepositorioDetectaDuplicadoDeVisita():void
    {
        $pdo=$this->connection();$row=$pdo->query("SELECT ru.resident_user_id,u.id unit_id,u.location_id FROM resident_units ru JOIN units u ON u.id=ru.unit_id WHERE ru.is_active=1 LIMIT 1")->fetch();if(!$row)self::markTestSkipped('No hay residente con unidad.');$pdo->beginTransaction();try{$repo=new AccessRepository($pdo);$token=bin2hex(random_bytes(32));$scheduled=gmdate('Y-m-d H:i:s',time()+3600);$repo->createVisit(['resident_id'=>(int)$row['resident_user_id'],'unit_id'=>(int)$row['unit_id'],'location_id'=>(int)$row['location_id'],'visitor_name'=>'Visitante PHPUnit','phone'=>'','identification_type'=>'','identification_number'=>'','company'=>'','host_name'=>'Residente','reason'=>'Prueba automatizada','license_plate'=>'','vehicle'=>'','scheduled_at'=>$scheduled,'valid_from'=>$scheduled,'valid_until'=>gmdate('Y-m-d H:i:s',time()+10800),'token_hash'=>hash('sha256',$token),'reference'=>substr(hash('sha256',$token),0,12)]);self::assertTrue($repo->duplicateVisit((int)$row['resident_user_id'],(int)$row['location_id'],'Visitante PHPUnit',$scheduled));}finally{if($pdo->inTransaction())$pdo->rollBack();}
    }

    public function testVigilanteNoPuedeConsultarIdentificaciones():void
    {
        $pdo=$this->connection();$count=$pdo->query("SELECT COUNT(*) FROM role_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id WHERE r.code='guard' AND p.code='access_identifications.view'")->fetchColumn();self::assertSame(0,(int)$count);
    }

    public function testProveedorPrevioSeCreaConQrPendiente():void
    {
        $pdo=$this->connection();$row=$pdo->query("SELECT ru.resident_user_id,u.id unit_id,u.location_id FROM resident_units ru JOIN units u ON u.id=ru.unit_id WHERE ru.is_active=1 LIMIT 1")->fetch();if(!$row)self::markTestSkipped('No hay residente con unidad.');$pdo->beginTransaction();try{$repo=new AccessRepository($pdo);$token=bin2hex(random_bytes(32));$id=$repo->createProvider(['resident_id'=>(int)$row['resident_user_id'],'creator'=>(int)$row['resident_user_id'],'unit_id'=>(int)$row['unit_id'],'location_id'=>(int)$row['location_id'],'company'=>'Proveedor PHPUnit','service'=>'Mantenimiento','responsible'=>'Responsable de prueba','materials'=>'Refacciones','tools'=>'Herramientas','scheduled_at'=>gmdate('Y-m-d H:i:s',time()+3600),'token_hash'=>hash('sha256',$token),'reference'=>substr(hash('sha256',$token),0,12)]);$provider=$repo->providerById($id);self::assertSame('pending',$provider['status']);self::assertSame('Proveedor PHPUnit',$provider['provider_company']);self::assertSame((int)$row['location_id'],(int)$provider['location_id']);}finally{if($pdo->inTransaction())$pdo->rollBack();}
    }
}
