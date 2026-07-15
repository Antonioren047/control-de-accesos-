<?php
declare(strict_types=1);

namespace Vigilancia\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Vigilancia\Database\Connection;
use Vigilancia\Repositories\OfflineRepository;
use Vigilancia\Repositories\OperationalRepository;
use Vigilancia\Repositories\SecurityLogRepository;
use Vigilancia\Services\OfflineEvidenceService;
use Vigilancia\Services\OfflineService;
use Vigilancia\Support\Config;

final class OfflineSchemaTest extends TestCase
{
    private function connection(): PDO
    {
        try {
            return Connection::make(Config::database());
        } catch (\Throwable $error) {
            self::markTestSkipped('Base no disponible: ' . $error->getMessage());
        }
    }

    public function testMigracionOfflineEstaInstalada(): void
    {
        $pdo = $this->connection();
        self::assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM migrations WHERE version='008_offline_sync'")->fetchColumn());
        $tables = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('offline_devices','offline_operations')")->fetchAll(PDO::FETCH_COLUMN);
        self::assertCount(2, $tables);
    }

    public function testVigilanteConservaPermisoOfflineEnInstalacionExistente(): void
    {
        $pdo = $this->connection();
        $count = $pdo->query("SELECT COUNT(*) FROM role_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id WHERE r.code='guard' AND p.code='offline_operations.capture'")->fetchColumn();
        self::assertSame(1, (int) $count);
    }

    public function testUuidDeOperacionEsIdempotente(): void
    {
        $pdo = $this->connection();
        $unique = $pdo->query("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='offline_operations' AND column_name='client_uuid' AND non_unique=0")->fetchColumn();
        self::assertGreaterThanOrEqual(1, (int) $unique);
    }

    public function testSincronizacionRealEsIdempotente(): void
    {
        $pdo = $this->connection();
        $guardId = $pdo->query("SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id WHERE r.code='guard' LIMIT 1")->fetchColumn();
        if (!$guardId) self::markTestSkipped('No hay un vigilante para la prueba offline.');

        $pdo->beginTransaction();
        try {
            $repository = new OfflineRepository($pdo);
            $token = bin2hex(random_bytes(32));
            $repository->authorize((int) $guardId, hash('sha256', 'phpunit-device'), hash('sha256', $token));
            $service = new OfflineService(
                $pdo,
                $repository,
                new OfflineEvidenceService(dirname(__DIR__, 2)),
                new SecurityLogRepository($pdo),
                new OperationalRepository($pdo)
            );
            $uuid = '9c55bb50-782e-4f80-ae25-' . substr(bin2hex(random_bytes(6)), 0, 12);
            $operation = ['uuid' => $uuid, 'type' => 'comment', 'payload' => ['text' => 'Prueba offline'], 'occurred_at' => gmdate(DATE_ATOM)];
            $first = $service->sync($token, ['operations' => [$operation]]);
            $second = $service->sync($token, ['operations' => [$operation]]);
            self::assertSame('synchronized', $first['results'][0]['status']);
            self::assertTrue($second['results'][0]['duplicate']);
            self::assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM offline_operations WHERE client_uuid=" . $pdo->quote($uuid))->fetchColumn());
        } finally {
            if ($pdo->inTransaction()) $pdo->rollBack();
        }
    }
}
