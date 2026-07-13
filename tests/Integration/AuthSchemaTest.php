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

    public function testRolesWebPuedenConsultarYRevocarSusPropiasSesiones(): void
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
            'resident' => 2,
            'superadmin' => 2,
            'supervisor' => 2,
        ], $result);
    }
}
