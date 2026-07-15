<?php
declare(strict_types=1);
namespace Vigilancia\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Vigilancia\Auth\BackoffPolicy;

final class BackoffPolicyTest extends TestCase
{
    public function testNoBloqueaAntesDelQuintoFallo(): void
    {
        self::assertSame(0, BackoffPolicy::seconds(4));
    }

    public function testAplicaEsperaProgresivaConTope(): void
    {
        self::assertSame(60, BackoffPolicy::seconds(5));
        self::assertSame(120, BackoffPolicy::seconds(6));
        self::assertSame(3600, BackoffPolicy::seconds(20));
    }
}
