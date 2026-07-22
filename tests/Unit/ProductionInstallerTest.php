<?php
declare(strict_types=1);

namespace Vigilancia\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ProductionInstallerTest extends TestCase
{
    public function testInstallerNoExponeNiEjecutaDatosDemo(): void
    {
        $root = dirname(__DIR__, 2);
        $page = file_get_contents($root . '/public/install/index.php');
        $service = file_get_contents($root . '/app/Services/InstallerService.php');

        self::assertIsString($page);
        self::assertIsString($service);
        self::assertStringNotContainsString('name="demo"', $page);
        self::assertStringContainsString('run(false)', $service);
        self::assertStringContainsString('único usuario global', $page);
    }

    public function testInstallerExigeBaseVaciaYNoIntentaCrearla(): void
    {
        $service = file_get_contents(dirname(__DIR__, 2) . '/app/Services/InstallerService.php');

        self::assertIsString($service);
        self::assertStringContainsString('assertEmptyDatabase', $service);
        self::assertStringContainsString('information_schema.tables', $service);
        self::assertStringNotContainsString('Connection::make($cfg, false)', $service);
        self::assertStringNotContainsString("exec('CREATE DATABASE", $service);
        self::assertStringContainsString("SELECT COUNT(*) FROM users", $service);
    }

    public function testManualYConstructorDelPaqueteEstanDisponibles(): void
    {
        $root = dirname(__DIR__, 2);

        self::assertFileExists($root . '/docs/INSTALACION-CPANEL.md');
        self::assertFileExists($root . '/scripts/build_cpanel_package.ps1');
        self::assertFileExists($root . '/storage/.htaccess');
    }
}
