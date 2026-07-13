<?php
declare(strict_types=1);
namespace Vigilancia\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Vigilancia\Http\Request;

final class RequestTest extends TestCase
{
    public function testConsultaEncabezadosSinImportarMayusculas(): void
    {
        $request = new Request('POST', '/auth/login', [], [], ['x-csrf-token' => 'abc']);
        self::assertSame('abc', $request->header('X-CSRF-Token'));
    }
}
