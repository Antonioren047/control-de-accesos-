<?php
declare(strict_types=1);
namespace Vigilancia\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Vigilancia\Database\Connection;
use Vigilancia\Support\Config;

final class AuthSchemaTest extends TestCase
{
    private function connection(): PDO
    {
        try {
            return Connection::make(Config::database());
        } catch (\Throwable $exception) {
            self::markTestSkipped('Base de integración no disponible: ' . $exception->getMessage());
        }
    }

    public function testEsquemaDeAutenticacionEstaInstalado(): void
    {
        $pdo = $this->connection();
        $migration = $pdo->query("SELECT COUNT(*) FROM migrations WHERE version='002_authentication'")->fetchColumn();
        $table = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='auth_attempts'")->fetchColumn();
        self::assertSame(1, (int) $migration);
        self::assertSame(1, (int) $table);
    }

    public function testSuperadministradorTienePermisosDeFaseDos(): void
    {
        $pdo = $this->connection();
        $statement = $pdo->query(
            "SELECT p.code FROM role_permissions rp
             JOIN roles r ON r.id=rp.role_id
             JOIN permissions p ON p.id=rp.permission_id
             WHERE r.code='superadmin' AND p.code IN ('auth.password.change','users.password_reset')"
        );
        self::assertCount(2, $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    public function testSoloRolesAdministrativosPuedenConsultarYRevocarSusSesiones(): void
    {
        $pdo = $this->connection();
        $statement = $pdo->query(
            "SELECT r.code,COUNT(DISTINCT p.code) AS permission_count
             FROM roles r
             JOIN role_permissions rp ON rp.role_id=r.id
             JOIN permissions p ON p.id=rp.permission_id
             WHERE r.code IN ('superadmin','admin','supervisor','resident')
               AND p.code IN ('auth.sessions.view','auth.sessions.revoke')
             GROUP BY r.code ORDER BY r.code"
        );
        $result = [];
        foreach ($statement->fetchAll() as $row) $result[$row['code']] = (int) $row['permission_count'];

        self::assertSame([
            'admin' => 2,
            'superadmin' => 2,
            'supervisor' => 2,
        ], $result);
    }

    public function testResidenteNoTienePermisosDeSeguridadDeCuenta():void
    {
        $pdo=$this->connection();
        $count=$pdo->query("SELECT COUNT(*) FROM role_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id WHERE r.code='resident' AND p.code IN ('auth.password.change','auth.sessions.view','auth.sessions.revoke')")->fetchColumn();
        self::assertSame(0,(int)$count);
    }

    public function testPermisosOperativosBaseEstanSeparadosPorRol(): void
    {
        $pdo = $this->connection();
        $statement = $pdo->query(
            "SELECT r.code,p.code AS permission_code FROM role_permissions rp
             JOIN roles r ON r.id=rp.role_id
             JOIN permissions p ON p.id=rp.permission_id
             WHERE r.code IN ('admin','supervisor','guard','resident')
               AND p.code IN ('users.manage','operations.view','visits.manage','permissions.manage')"
        );
        $result = [];
        foreach ($statement->fetchAll() as $row) $result[$row['code']][] = $row['permission_code'];
        foreach ($result as &$permissions) sort($permissions);

        self::assertSame(['operations.view', 'users.manage'], $result['admin'] ?? []);
        self::assertSame(['operations.view'], $result['supervisor'] ?? []);
        self::assertSame(['operations.view'], $result['guard'] ?? []);
        self::assertSame(['visits.manage'], $result['resident'] ?? []);
    }

    public function testSoloSuperadministradorAdministraLaMatrizPorDefecto(): void
    {
        $pdo = $this->connection();
        $roles = $pdo->query(
            "SELECT r.code FROM role_permissions rp
             JOIN roles r ON r.id=rp.role_id
             JOIN permissions p ON p.id=rp.permission_id
             WHERE p.code='permissions.manage' ORDER BY r.code"
        )->fetchAll(PDO::FETCH_COLUMN);
        self::assertSame(['superadmin'], $roles);
        self::assertSame('0', (string) $pdo->query(
            "SELECT setting_value FROM system_settings WHERE setting_key='security.guard_web_login_enabled'"
        )->fetchColumn());
    }

    public function testVigilanteNoPuedeUsarLoginDeCorreoAunqueSeActiveElInterruptor():void
    {
        $pdo=$this->connection();
        $email=(string)$pdo->query("SELECT u.email FROM users u JOIN roles r ON r.id=u.role_id WHERE r.code='guard' AND u.email='vigilante@demo.local' LIMIT 1")->fetchColumn();
        if($email==='')self::markTestSkipped('Vigilante demo no disponible.');
        $pdo->beginTransaction();
        try{
            $pdo->exec("UPDATE system_settings SET setting_value='1' WHERE setting_key='security.guard_web_login_enabled'");
            $this->expectException(\Vigilancia\Exceptions\HttpException::class);
            (new \Vigilancia\Services\AuthService($pdo))->login($email,'Ccserv-10.02!');
        }finally{if($pdo->inTransaction())$pdo->rollBack();}
    }

    public function testPermisosDeModuloDistinguenAdministradorYResidente(): void
    {
        $pdo = $this->connection();
        $statement = $pdo->query(
            "SELECT r.code,p.code AS permission_code FROM role_permissions rp
             JOIN roles r ON r.id=rp.role_id
             JOIN permissions p ON p.id=rp.permission_id
             WHERE r.code IN ('admin','resident')
               AND p.code IN ('clients.manage','locations.manage','visits.manage','providers.create','reports.own_history')
             ORDER BY r.code,p.code"
        );
        $permissions = ['admin' => [], 'resident' => []];
        foreach ($statement->fetchAll() as $row) $permissions[$row['code']][] = $row['permission_code'];

        self::assertSame(['clients.manage', 'locations.manage'], $permissions['admin']);
        self::assertSame(['providers.create', 'reports.own_history', 'visits.manage'], $permissions['resident']);
    }
}
