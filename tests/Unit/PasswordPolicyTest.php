<?php
declare(strict_types=1);
namespace Vigilancia\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Vigilancia\Auth\PasswordPolicy;

final class PasswordPolicyTest extends TestCase
{
    public function testAceptaContrasenaRobusta(): void
    {
        self::assertTrue(PasswordPolicy::passes('AccesoSeguro#2026'));
        self::assertSame([], PasswordPolicy::errors('AccesoSeguro#2026'));
    }

    public function testRechazaCadaReglaFaltante(): void
    {
        self::assertFalse(PasswordPolicy::passes('debil'));
        self::assertGreaterThanOrEqual(4, count(PasswordPolicy::errors('debil')));
    }
}
