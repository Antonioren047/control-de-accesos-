<?php
declare(strict_types=1);
namespace Vigilancia\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Vigilancia\Database\Connection;
use Vigilancia\Repositories\OrganizationRepository;
use Vigilancia\Repositories\UserRepository;
use Vigilancia\Support\Config;

final class OrganizationSchemaTest extends TestCase
{
    private function connection():PDO{try{return Connection::make(Config::database());}catch(\Throwable $e){self::markTestSkipped('Base no disponible: '.$e->getMessage());}}

    public function testMigracionDeOrganizacionEstaInstalada():void
    {
        $pdo=$this->connection();
        self::assertSame(1,(int)$pdo->query("SELECT COUNT(*) FROM migrations WHERE version='003_organization'")->fetchColumn());
        $tables=$pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('clients','locations','access_points','units','resident_units','user_client_scopes','user_location_scopes','user_access_point_scopes')")->fetchAll(PDO::FETCH_COLUMN);
        self::assertCount(8,$tables);
    }

    public function testDatosDemoYAlcancesSeparanAdministradorSupervisorYResidente():void
    {
        $pdo=$this->connection();$users=new UserRepository($pdo);$repository=new OrganizationRepository($pdo);
        $admin=$users->findByEmail('admin@demo.local');$supervisor=$users->findByEmail('supervisor@demo.local');$resident=$users->findByEmail('residente@demo.local');
        if(!$admin||!$supervisor||!$resident)self::markTestSkipped('Usuarios demo no disponibles.');
        self::assertCount(1,$repository->clients($admin));
        self::assertCount(2,$repository->locations($supervisor));
        $residentUnits=$repository->units($resident);
        self::assertCount(1,$residentUnits);
        self::assertSame('CASA-101',$residentUnits[0]['code']);
    }
}
