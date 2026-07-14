<?php
declare(strict_types=1);
namespace Vigilancia\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class MigrationTest extends TestCase
{
    public function testMigracionesSonEjecutables(): void
    {
        $root = dirname(__DIR__, 2) . '/database/migrations/';
        self::assertIsCallable(require $root . '001_foundation.php');
        self::assertIsCallable(require $root . '002_authentication.php');
        self::assertIsCallable(require $root . '003_organization.php');
        self::assertIsCallable(require $root . '004_workforce.php');
        self::assertIsCallable(require $root . '005_operations.php');
        self::assertIsCallable(require $root . '006_resident_security_restriction.php');
        self::assertIsCallable(require $root . '007_guard_operational_login_only.php');
    }
}
