<?php
declare(strict_types=1);

namespace Vigilancia\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class RootRewriteTest extends TestCase
{
    public function testLaRaizDetieneElReprocesamientoAntesDeDelegarAPublic(): void
    {
        $rules = file_get_contents(dirname(__DIR__, 2) . '/.htaccess');

        self::assertIsString($rules);
        self::assertStringContainsString('RewriteRule ^$ index.php [END]', $rules);
        self::assertLessThan(
            strpos($rules, 'RewriteRule ^(.*)$ public/$1 [L]'),
            strpos($rules, 'RewriteRule ^$ index.php [END]')
        );
    }
}
