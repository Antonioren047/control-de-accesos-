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
}
