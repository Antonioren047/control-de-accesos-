<?php
declare(strict_types=1);
namespace Vigilancia\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ModuleConfigTest extends TestCase
{
    public function testModulosReferencianPermisosDelCatalogo(): void
    {
        $config = require dirname(__DIR__, 2) . '/config/modules.php';
        $catalog = array_column($config['permissions'], 0);
        foreach ($config['modules'] as $module) {
            self::assertNotEmpty($module['permissions']);
            self::assertSame([], array_values(array_diff($module['permissions'], $catalog)));
        }
    }

    public function testAdministradorYResidenteTienenAlcancesDistintos(): void
    {
        $roles = (require dirname(__DIR__, 2) . '/config/modules.php')['roles'];
        self::assertContains('clients.manage', $roles['admin']);
        self::assertNotContains('clients.manage', $roles['resident']);
        self::assertContains('visits.manage', $roles['resident']);
        self::assertNotContains('visits.manage', $roles['admin']);
        self::assertNotSame($roles['admin'], $roles['resident']);
    }

    public function testVigilanteNoRecibeModuloAdministrativoDeTurnos(): void
    {
        $turnos = (require dirname(__DIR__, 2) . '/config/modules.php')['modules']['turnos'];
        self::assertNotContains('guard', $turnos['roles']);
        self::assertContains('admin', $turnos['roles']);
        self::assertContains('supervisor', $turnos['roles']);
    }

    public function testVigilantePuedeCapturarOfflinePeroNoResolverConflictos(): void
    {
        $guard = (require dirname(__DIR__, 2) . '/config/modules.php')['roles']['guard'];
        self::assertContains('offline_operations.capture', $guard);
        self::assertNotContains('offline_conflicts.manage', $guard);
    }

    public function testPermisosDeAccesosRespetanResponsabilidades(): void
    {
        $roles = (require dirname(__DIR__, 2) . '/config/modules.php')['roles'];
        self::assertContains('visits.manage', $roles['resident']);
        self::assertContains('providers.create', $roles['resident']);
        self::assertContains('visits.check_in', $roles['guard']);
        self::assertContains('providers.check_in', $roles['guard']);
        self::assertNotContains('access_identifications.view', $roles['guard']);
        self::assertContains('access_identifications.view', $roles['supervisor']);
    }

    public function testSupervisionesSeparanProgramacionYEjecucion(): void
    {
        $roles=(require dirname(__DIR__,2).'/config/modules.php')['roles'];
        self::assertContains('supervisions.schedule',$roles['admin']);
        self::assertContains('supervisions.manage',$roles['admin']);
        self::assertContains('supervisions.manage',$roles['supervisor']);
        self::assertNotContains('supervisions.schedule',$roles['supervisor']);
        self::assertNotContains('supervisions.manage',$roles['guard']);
    }
}
