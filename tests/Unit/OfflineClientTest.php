<?php
declare(strict_types=1);

namespace Vigilancia\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class OfflineClientTest extends TestCase
{
    public function testClienteUsaIndexedDbYLotesLimitados(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/offline-queue.js');
        self::assertStringContainsString('indexedDB.open', $source);
        self::assertStringContainsString('BATCH_SIZE = 50', $source);
        self::assertStringContainsString('vigilancia-offline-sync', $source);
    }

    public function testServiceWorkerNoInterceptaApis(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/public/service-worker.js');
        self::assertStringContainsString("url.pathname.includes('/api/')", $source);
        self::assertStringContainsString("event.tag==='vigilancia-offline-sync'", $source);
    }

    public function testVisitasNoFormanParteDeOperacionesOfflinePermitidas(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/app/Services/OfflineService.php');
        self::assertStringContainsString("'round_start','round_end','event','evidence','comment'", $source);
        self::assertStringNotContainsString("'visitor'", $source);
        self::assertStringContainsString('$age>43200?\'expired\':\'synchronized\'', $source);
    }
}
