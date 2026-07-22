<?php
declare(strict_types=1);

namespace Vigilancia\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class UserAdminTest extends TestCase
{
    public function testGlobalPuedeAdministrarTodosLosRolesDesdeUsuarios(): void
    {
        $root = dirname(__DIR__, 2);
        $page = file_get_contents($root . '/public/index.php');
        $client = file_get_contents($root . '/public/assets/js/phase4.js');

        self::assertStringContainsString('data-can-manage-users=', $page);
        self::assertStringContainsString('Nuevo usuario administrativo', $page);
        self::assertStringContainsString('Nuevo residente', $page);
        self::assertStringContainsString('Nuevo vigilante', $page);
        self::assertStringContainsString('Usuarios administrativos', $client);
        foreach (['superadmin', 'admin', 'supervisor'] as $role) {
            self::assertStringContainsString($role, $client);
        }
        foreach (['/users/create', '/users/update', '/users/status'] as $endpoint) {
            self::assertStringContainsString($endpoint, $client);
        }
    }

    public function testBackendRestringeGestionTotalAlGlobalYProtegeLaUltimaCuenta(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = file_get_contents($root . '/routes/api.php');
        $service = file_get_contents($root . '/app/Services/UserAdminService.php');

        foreach (["get('/users'", "post('/users/create'", "post('/users/update'", "post('/users/status'"] as $route) {
            self::assertStringContainsString($route, $routes);
        }
        self::assertStringContainsString("\$actor['role_code'] !== 'superadmin'", $service);
        self::assertStringNotContainsString("require(\$actor, 'users.manage')", $service);
        self::assertStringContainsString("['superadmin', 'admin', 'supervisor']", $service);
        self::assertStringContainsString('Debe permanecer al menos un usuario global activo.', $service);
        self::assertStringContainsString('users.administrative_created', $service);
        self::assertStringContainsString('users.administrative_updated', $service);
        self::assertStringContainsString('users.administrative_status_changed', $service);
    }
}
